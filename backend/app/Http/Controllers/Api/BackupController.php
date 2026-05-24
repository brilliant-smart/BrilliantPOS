<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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
     * Split SQL text into individual statements, respecting quoted strings,
     * backtick identifiers, and comments so that semicolons inside data values
     * (e.g., serialized PHP objects) do not break the parsing.
     */
    public function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $len = strlen($sql);
        $i = 0;

        while ($i < $len) {
            $ch = $sql[$i];

            // Single-line comment (-- ...) — discard entirely
            if ($ch === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                // Skip until end of line
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                // Skip the newline too
                if ($i < $len) {
                    $i++;
                }
                continue;
            }

            // Single-quoted string
            if ($ch === "'") {
                $current .= $sql[$i];
                $i++;
                while ($i < $len) {
                    if ($sql[$i] === '\\' && $i + 1 < $len) {
                        // Escaped character — consume both
                        $current .= $sql[$i];
                        $i++;
                        $current .= $sql[$i];
                        $i++;
                    } elseif ($sql[$i] === "'") {
                        // Could be '' (escaped quote in SQL) or end of string
                        $current .= $sql[$i];
                        $i++;
                        if ($i < $len && $sql[$i] === "'") {
                            // Doubled quote — still inside string
                            $current .= $sql[$i];
                            $i++;
                        } else {
                            break;
                        }
                    } else {
                        $current .= $sql[$i];
                        $i++;
                    }
                }
                continue;
            }

            // Double-quoted string (used for JSON-like values in some dialects)
            if ($ch === '"') {
                $current .= $sql[$i];
                $i++;
                while ($i < $len) {
                    if ($sql[$i] === '\\' && $i + 1 < $len) {
                        $current .= $sql[$i];
                        $i++;
                        $current .= $sql[$i];
                        $i++;
                    } elseif ($sql[$i] === '"') {
                        $current .= $sql[$i];
                        $i++;
                        if ($i < $len && $sql[$i] === '"') {
                            $current .= $sql[$i];
                            $i++;
                        } else {
                            break;
                        }
                    } else {
                        $current .= $sql[$i];
                        $i++;
                    }
                }
                continue;
            }

            // Backtick-quoted identifier
            if ($ch === '`') {
                $current .= $sql[$i];
                $i++;
                while ($i < $len && $sql[$i] !== '`') {
                    $current .= $sql[$i];
                    $i++;
                }
                if ($i < $len) {
                    $current .= $sql[$i];
                    $i++;
                }
                continue;
            }

            // Semicolon outside any string = statement boundary
            if ($ch === ';') {
                $stmt = trim($current);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $current = '';
                $i++;
                continue;
            }

            $current .= $ch;
            $i++;
        }

        // Handle last statement (file may not end with ;)
        $stmt = trim($current);
        if ($stmt !== '') {
            $statements[] = $stmt;
        }

        return $statements;
    }

    /**
     * Validate and resolve a backup filename to a safe path.
     * Blocks path traversal, null bytes, and non-SQL files.
     */
    private function resolveBackupPath(string $filename): string
    {
        $basename = basename($filename);

        if (!preg_match('/^[\w.\-]+\.sql$/', $basename)) {
            abort(400, 'Invalid backup filename');
        }

        $filepath = storage_path('app/backups/' . $basename);
        $realpath = realpath($filepath);
        $backupDir = realpath(storage_path('app/backups'));

        if ($realpath && !str_starts_with($realpath, $backupDir . DIRECTORY_SEPARATOR)) {
            abort(400, 'Invalid backup path');
        }

        return $filepath;
    }

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
     * Tables that contain ephemeral data and should be skipped during backup.
     */
    private const EPHEMERAL_TABLES = ['cache', 'sessions', 'jobs', 'failed_jobs', 'password_reset_tokens'];

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
            $dbName = config('database.connections.mysql.database');
            $tableKey = 'Tables_in_' . $dbName;

            $sql = "-- Database Backup\n";
            $sql .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- Database: {$dbName}\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            foreach ($tables as $table) {
                $tableName = $table->$tableKey;

                // Get CREATE TABLE statement (always include, even for ephemeral tables,
                // so the table structure is restored)
                $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
                $sql .= "-- Table: {$tableName}\n";
                $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $sql .= $createTable[0]->{'Create Table'} . ";\n\n";

                // Skip data for ephemeral tables — cache, sessions, jobs, etc.
                // are repopulated automatically and often contain serialized data
                // with characters that break naive SQL parsing.
                if (in_array($tableName, self::EPHEMERAL_TABLES)) {
                    $sql .= "-- (data skipped for ephemeral table)\n\n";
                    continue;
                }

                // Get table data
                $rows = DB::table($tableName)->get();

                if ($rows->count() > 0) {
                    // Get column names for explicit INSERT (more robust than positional)
                    $columns = array_keys((array) $rows->first());
                    $columnList = implode(', ', array_map(fn($col) => "`{$col}`", $columns));

                    foreach ($rows as $row) {
                        $values = [];
                        foreach ((array)$row as $value) {
                            if (is_null($value)) {
                                $values[] = 'NULL';
                            } else {
                                $values[] = DB::getPdo()->quote((string) $value);
                            }
                        }
                        $sql .= "INSERT INTO `{$tableName}` ({$columnList}) VALUES (" . implode(', ', $values) . ");\n";
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

            AuditLog::logAction('backup.create', 'Backup', null, null, null, "Backup created: {$filename}");

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
        $filepath = $this->resolveBackupPath($filename);

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
        $filepath = $this->resolveBackupPath($filename);

        if (!file_exists($filepath)) {
            return response()->json([
                'message' => 'Backup file not found',
            ], 404);
        }

        unlink($filepath);

        Log::info('Backup deleted', [
            'filename' => $filename,
        ]);

        AuditLog::logAction('backup.delete', 'Backup', null, null, null, "Backup deleted: {$filename}");

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
            'backup_file' => 'required|file|extensions:sql|max:102400', // Max 100MB
        ]);

        try {
            $file = $request->file('backup_file');
            $originalName = $file->getClientOriginalName();
            
            // Generate safe filename to avoid conflicts and path traversal
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '', pathinfo($originalName, PATHINFO_FILENAME));
            $filename = 'uploaded_' . date('Y-m-d_His') . '_' . $safeName . '.sql';
            
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

            AuditLog::logAction('backup.upload', 'Backup', null, null, null, "Backup uploaded: {$filename}");

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
            $filepath = $this->resolveBackupPath($filename);

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

            // Execute SQL statements with safety checks
            $dangerousPatterns = [
                '/\bDROP\s+DATABASE\b/i',
                '/\bDROP\s+USER\b/i',
                '/\bGRANT\s+/i',
                '/\bREVOKE\s+/i',
                '/\bALTER\s+USER\b/i',
                '/\bSET\s+PASSWORD\b/i',
                '/\bLOAD\s+DATA\b/i',
                '/\bINTO\s+OUTFILE\b/i',
                '/\bINTO\s+DUMPFILE\b/i',
            ];

            foreach ($dangerousPatterns as $pattern) {
                if (preg_match($pattern, $sql)) {
                    return response()->json([
                        'message' => 'Backup file contains prohibited SQL statements',
                    ], 422);
                }
            }

            // Only allow DROP TABLE for tables that actually exist in this database
            $existingTables = DB::select('SHOW TABLES');
            $dbName = config('database.connections.mysql.database');
            $tableKey = 'Tables_in_' . $dbName;
            $allowedTables = collect($existingTables)->map(fn($t) => $t->$tableKey)->toArray();

            preg_match_all('/DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`?(\w+)`?/i', $sql, $dropMatches);
            foreach ($dropMatches[1] as $tableToDrop) {
                if (!in_array($tableToDrop, $allowedTables)) {
                    return response()->json([
                        'message' => "Backup references unknown table: {$tableToDrop}",
                    ], 422);
                }
            }

            // Split into individual statements using a SQL-aware parser
            // that respects quoted strings, backtick identifiers, and comments.
            // A naive explode(';', $sql) breaks on semicolons inside data values
            // (e.g., serialized PHP objects in cache/sessions tables).
            $statements = $this->splitSqlStatements($sql);

            // Execute statements, tracking successes and failures.
            // We don't wrap in a transaction because DDL statements (DROP TABLE,
            // CREATE TABLE) implicitly commit in MySQL, so a transaction rollback
            // would not undo them anyway. Instead, we log failures and continue.
            $successCount = 0;
            $failureCount = 0;
            $errors = [];

            foreach ($statements as $statement) {
                if (empty(trim($statement))) {
                    continue;
                }
                try {
                    DB::unprepared($statement);
                    $successCount++;
                } catch (\Exception $stmtEx) {
                    $failureCount++;
                    $errors[] = $stmtEx->getMessage();
                    Log::warning('Restore statement failed', [
                        'statement' => mb_substr($statement, 0, 200),
                        'error' => $stmtEx->getMessage(),
                    ]);
                }
            }

            Log::warning('Database restored from backup', [
                'filename' => $filename,
                'user_id' => $request->user()->id,
                'user_name' => $request->user()->name,
                'timestamp' => now(),
                'statements_executed' => $successCount,
                'statements_failed' => $failureCount,
            ]);

            AuditLog::logAction('backup.restore', 'Backup', null, null, null, "Database restored from backup: {$filename}");

            if ($failureCount > 0) {
                return response()->json([
                    'message' => 'Database restored with some warnings.',
                    'statements_executed' => $successCount,
                    'statements_failed' => $failureCount,
                    'errors' => array_slice($errors, 0, 10),
                ]);
            }

            return response()->json([
                'message' => 'Database restored successfully. Please refresh the application.',
                'statements_executed' => $successCount,
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
