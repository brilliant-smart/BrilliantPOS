<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

use function scandir;
use function file_exists;
use function mkdir;
use function pathinfo;
use function filesize;
use function filemtime;

class BackupController extends Controller
{
    /**
     * Get list of all backups
     */
    public function index()
    {
        try {
            $backupPath = storage_path('app/backups');
            
            if (!file_exists($backupPath)) {
                mkdir($backupPath, 0755, true);
            }

            $backups = [];
            $files = @scandir($backupPath);

            if ($files === false) {
                return response()->json([
                    'backups' => [],
                    'count' => 0,
                ]);
            }

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                
                if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                    $backups[] = [
                        'filename' => $file,
                        'size' => filesize($backupPath . '/' . $file),
                        'created_at' => Carbon::createFromTimestamp(filemtime($backupPath . '/' . $file))->toISOString(),
                    ];
                }
            }

            // Sort by created_at desc
            usort($backups, function ($a, $b) {
                return strcmp($b['created_at'], $a['created_at']);
            });

            return response()->json([
                'backups' => $backups,
                'count' => count($backups),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to list backups', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to load backups',
                'error' => $e->getMessage(),
                'backups' => [],
            ], 500);
        }
    }

    /**
     * Create a new database backup using PHP (works on all platforms)
     */
    public function create(Request $request)
    {
        try {
            $filename = 'backup_' . date('Y-m-d_His') . '.sql';
            $backupPath = storage_path('app/backups');
            
            if (!file_exists($backupPath)) {
                mkdir($backupPath, 0755, true);
            }

            $filepath = $backupPath . '/' . $filename;

            // Get all table names
            $tables = DB::select('SHOW TABLES');
            $dbName = env('DB_DATABASE');
            $tableKey = 'Tables_in_' . $dbName;
            
            $sql = "-- Database Backup\n";
            $sql .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- Database: {$dbName}\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            foreach ($tables as $table) {
                $tableName = $table->$tableKey;
                
                // Get CREATE TABLE statement
                $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
                $sql .= "-- Table: {$tableName}\n";
                $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $sql .= $createTable[0]->{'Create Table'} . ";\n\n";
                
                // Get table data
                $rows = DB::table($tableName)->get();
                
                if ($rows->count() > 0) {
                    foreach ($rows as $row) {
                        $values = [];
                        foreach ((array)$row as $value) {
                            if (is_null($value)) {
                                $values[] = 'NULL';
                            } else {
                                $values[] = "'" . addslashes($value) . "'";
                            }
                        }
                        $sql .= "INSERT INTO `{$tableName}` VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $sql .= "\n";
                }
            }

            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

            // Write to file
            file_put_contents($filepath, $sql);

            // Verify backup file was created
            if (!file_exists($filepath) || filesize($filepath) === 0) {
                return response()->json([
                    'message' => 'Backup file was not created or is empty',
                ], 500);
            }

            Log::info('Database backup created', [
                'filename' => $filename,
                'size' => filesize($filepath),
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Backup created successfully',
                'filename' => $filename,
                'size' => filesize($filepath),
            ]);

        } catch (\Exception $e) {
            Log::error('Backup creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to create backup',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download a backup file
     */
    public function download($filename)
    {
        $filepath = storage_path('app/backups/' . $filename);

        if (!file_exists($filepath)) {
            return response()->json([
                'message' => 'Backup file not found',
            ], 404);
        }

        Log::info('Backup downloaded', [
            'filename' => $filename,
        ]);

        return response()->download($filepath);
    }

    /**
     * Delete a backup file
     */
    public function destroy($filename)
    {
        $filepath = storage_path('app/backups/' . $filename);

        if (!file_exists($filepath)) {
            return response()->json([
                'message' => 'Backup file not found',
            ], 404);
        }

        unlink($filepath);

        Log::info('Backup deleted', [
            'filename' => $filename,
        ]);

        return response()->json([
            'message' => 'Backup deleted successfully',
        ]);
    }

    /**
     * Upload backup file
     * POST /api/backups/upload
     * Only Master Admin can upload
     */
    public function upload(Request $request)
    {
        // Check if user is Owner
        if ($request->user()->role !== 'owner') {
            return response()->json([
                'message' => 'Only Owner can upload backups',
            ], 403);
        }

        $validated = $request->validate([
            'backup_file' => 'required|file|mimes:sql|max:102400', // Max 100MB
        ]);

        try {
            $file = $request->file('backup_file');
            $originalName = $file->getClientOriginalName();
            
            // Generate unique filename to avoid conflicts
            $filename = 'uploaded_' . date('Y-m-d_His') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $originalName);
            
            $backupPath = storage_path('app/backups');
            
            if (!file_exists($backupPath)) {
                mkdir($backupPath, 0755, true);
            }

            // Move uploaded file
            $file->move($backupPath, $filename);

            Log::info('Backup file uploaded', [
                'filename' => $filename,
                'original_name' => $originalName,
                'size' => filesize($backupPath . '/' . $filename),
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Backup uploaded successfully',
                'filename' => $filename,
                'size' => filesize($backupPath . '/' . $filename),
            ]);

        } catch (\Exception $e) {
            Log::error('Backup upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to upload backup',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore database from backup using PHP (works on all platforms)
     * Only Owner can restore
     */
    public function restore(Request $request, $filename)
    {
        // Check if user is Owner
        if ($request->user()->role !== 'owner') {
            return response()->json([
                'message' => 'Only Owner can restore backups',
            ], 403);
        }

        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        // Verify the password matches the current user's password
        if (!Hash::check($validated['password'], $request->user()->password)) {
            Log::warning('Failed restore attempt - incorrect password', [
                'filename' => $filename,
                'user_id' => $request->user()->id,
            ]);
            
            return response()->json([
                'message' => 'Incorrect password. Restore cancelled for security.',
            ], 401);
        }

        try {
            $filepath = storage_path('app/backups/' . $filename);

            if (!file_exists($filepath)) {
                return response()->json([
                    'message' => 'Backup file not found',
                ], 404);
            }

            // Validate SQL file content
            $sql = file_get_contents($filepath);

            if (empty($sql)) {
                return response()->json([
                    'message' => 'Backup file is empty',
                ], 500);
            }

            // Basic validation - check if it looks like a SQL file
            if (stripos($sql, 'CREATE TABLE') === false && stripos($sql, 'INSERT INTO') === false) {
                return response()->json([
                    'message' => 'Invalid backup file format',
                ], 422);
            }

            // Execute SQL statements
            DB::unprepared($sql);

            Log::warning('Database restored from backup', [
                'filename' => $filename,
                'user_id' => $request->user()->id,
                'user_name' => $request->user()->name,
                'timestamp' => now(),
            ]);

            return response()->json([
                'message' => 'Database restored successfully. Please refresh the application.',
            ]);

        } catch (\Exception $e) {
            Log::error('Restore failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'filename' => $filename,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Failed to restore backup',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
