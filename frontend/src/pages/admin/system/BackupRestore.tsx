import { useState, useEffect, useRef } from 'react';
import { Database, Download, Trash2, RefreshCw, AlertTriangle, Upload } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { backupApi, Backup } from '@/app/api/backups';
import { toast } from 'sonner';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { format } from 'date-fns';
import Swal from 'sweetalert2';
import { useAuth } from '@/app/auth/AuthContext';

export default function BackupRestore() {
  const { user } = useAuth();
  const [backups, setBackups] = useState<Backup[]>([]);
  const [loading, setLoading] = useState(false);
  const [creating, setCreating] = useState(false);
  const [uploading, setUploading] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const isOwner = user?.role === 'owner';

  useEffect(() => {
    loadBackups();
  }, []);

  const loadBackups = async () => {
    try {
      setLoading(true);
      const data = await backupApi.getBackups();
      setBackups(data.backups);
    } catch (error: any) {
      toast.error('Failed to load backups');
      console.error('Backup load error:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleCreateBackup = async () => {
    try {
      setCreating(true);
      await backupApi.createBackup();
      toast.success('Backup created successfully');
      loadBackups();
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to create backup');
      console.error('Backup creation error:', error);
    } finally {
      setCreating(false);
    }
  };

  const handleDownload = async (filename: string) => {
    try {
      await backupApi.downloadBackup(filename);
      toast.success('Backup downloaded successfully');
    } catch (error: any) {
      toast.error('Failed to download backup');
      console.error('Download error:', error);
    }
  };

  const handleDelete = async (filename: string) => {
    const result = await Swal.fire({
      title: 'Delete Backup?',
      text: `Are you sure you want to delete ${filename}? This action cannot be undone.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'Yes, Delete',
      cancelButtonText: 'Cancel',
    });

    if (result.isConfirmed) {
      try {
        await backupApi.deleteBackup(filename);
        toast.success('Backup deleted successfully');
        loadBackups();
      } catch (error: any) {
        toast.error('Failed to delete backup');
        console.error('Delete error:', error);
      }
    }
  };

  const handleUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    
    if (!file) {
      return;
    }
    
    // Validate file extension
    if (!file.name.endsWith('.sql')) {
      toast.error('Please select a valid SQL backup file (.sql)');
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
      return;
    }

    try {
      setUploading(true);
      await backupApi.uploadBackup(file);
      toast.success('Backup file uploaded successfully');
      
      // Clear file input
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
      
      loadBackups();
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to upload backup');
    } finally {
      setUploading(false);
    }
  };

  const handleRestore = async (filename: string) => {
    // First confirmation - warn about data loss
    const confirmResult = await Swal.fire({
      title: 'Restore Database?',
      html: `
        <p class="text-red-600 dark:text-red-400 font-bold mb-2">⚠️ CRITICAL WARNING ⚠️</p>
        <p>This will <strong>REPLACE</strong> all current data with data from:</p>
        <p class="font-mono text-sm mt-2 mb-2">${filename}</p>
        <p class="text-sm text-muted-foreground">All changes made after this backup will be LOST.</p>
        <p class="text-sm text-red-600 dark:text-red-400 mt-3">This action is IRREVERSIBLE!</p>
      `,
      icon: 'error',
      showCancelButton: true,
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'Yes, Continue',
      cancelButtonText: 'Cancel',
      input: 'checkbox',
      inputValue: 0,
      inputPlaceholder: 'I understand the risks and want to proceed',
      inputValidator: (result) => {
        return !result && 'You must confirm to proceed';
      },
    });

    if (!confirmResult.isConfirmed) return;

    // Second step - password verification
    const passwordResult = await Swal.fire({
      title: 'Password Verification Required',
      html: `
        <div class="text-left mb-4">
          <p class="text-sm text-muted-foreground mb-3">
            For security, please enter your password to confirm this restore operation.
          </p>
          <input
            type="password"
            id="swal-password"
            class="swal2-input"
            placeholder="Enter your password"
            style="width: 90%; margin: 0;"
          />
        </div>
      `,
      icon: 'info',
      showCancelButton: true,
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'Restore Database',
      cancelButtonText: 'Cancel',
      preConfirm: () => {
        const password = (document.getElementById('swal-password') as HTMLInputElement)?.value;
        if (!password) {
          Swal.showValidationMessage('Password is required');
          return false;
        }
        return password;
      }
    });

    if (!passwordResult.isConfirmed || !passwordResult.value) return;

    try {
      await backupApi.restoreBackup(filename, passwordResult.value);
      
      Swal.fire({
        title: 'Database Restored!',
        text: 'The application will refresh now.',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false,
      }).then(() => {
        window.location.reload();
      });
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to restore backup');
      console.error('Restore error:', error);
    }
  };

  const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold">Backup & Restore</h1>
          <p className="text-muted-foreground mt-1">
            Protect your data with regular backups
          </p>
        </div>
        <div className="flex gap-2">
          {isOwner && (
            <>
              <Input
                ref={fileInputRef}
                type="file"
                accept=".sql"
                className="hidden"
                id="backup-upload"
                onChange={handleUpload}
              />
              <Label htmlFor="backup-upload" className="m-0 cursor-pointer">
                <Button
                  type="button"
                  variant="outline"
                  disabled={uploading}
                  asChild
                >
                  <span>
                    <Upload className="h-4 w-4 mr-2" />
                    {uploading ? 'Uploading...' : 'Upload Backup'}
                  </span>
                </Button>
              </Label>
            </>
          )}
          <Button variant="outline" onClick={loadBackups} disabled={loading}>
            <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
            Refresh
          </Button>
          <Button onClick={handleCreateBackup} disabled={creating}>
            <Database className="h-4 w-4 mr-2" />
            {creating ? 'Creating...' : 'Create Backup'}
          </Button>
        </div>
      </div>


      <Card>
        <CardHeader>
          <CardTitle>Available Backups</CardTitle>
          <CardDescription>
            {backups.length} backup{backups.length !== 1 ? 's' : ''} available
          </CardDescription>
        </CardHeader>
        <CardContent>
          {backups.length === 0 ? (
            <div className="text-center py-12">
              <Database className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
              <p className="text-muted-foreground">No backups found</p>
              <p className="text-sm text-muted-foreground mt-1">
                Create your first backup to get started
              </p>
            </div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Filename</TableHead>
                  <TableHead>Created</TableHead>
                  <TableHead>Size</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {backups.map((backup) => (
                  <TableRow key={backup.filename}>
                    <TableCell className="font-mono text-sm">
                      {backup.filename}
                    </TableCell>
                    <TableCell>
                      {format(new Date(backup.created_at), 'PPpp')}
                    </TableCell>
                    <TableCell>{formatFileSize(backup.size)}</TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-2">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleDownload(backup.filename)}
                        >
                          <Download className="h-4 w-4" />
                        </Button>
                        {isOwner && (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleRestore(backup.filename)}
                            title="Restore Database (Owner Only)"
                          >
                            <RefreshCw className="h-4 w-4" />
                          </Button>
                        )}
                        <Button
                          variant="destructive"
                          size="sm"
                          onClick={() => handleDelete(backup.filename)}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
