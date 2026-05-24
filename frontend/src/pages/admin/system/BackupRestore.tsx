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
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { useAuth } from '@/app/auth/AuthContext';

export default function BackupRestore() {
  const { user } = useAuth();
  const [backups, setBackups] = useState<Backup[]>([]);
  const [loading, setLoading] = useState(false);
  const [creating, setCreating] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [restoring, setRestoring] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const isOwner = user?.role === 'owner';

  // AlertDialog states
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState('');
  const [showRestoreConfirm, setShowRestoreConfirm] = useState(false);
  const [restoreFilename, setRestoreFilename] = useState('');
  const [restoreAgreed, setRestoreAgreed] = useState(false);
  const [restorePassword, setRestorePassword] = useState('');
  const [showRestoreSuccess, setShowRestoreSuccess] = useState(false);

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

  const handleDelete = (filename: string) => {
    setDeleteTarget(filename);
    setShowDeleteConfirm(true);
  };

  const confirmDelete = async () => {
    setShowDeleteConfirm(false);
    try {
      await backupApi.deleteBackup(deleteTarget);
      toast.success('Backup deleted successfully');
      loadBackups();
    } catch (error: any) {
      toast.error('Failed to delete backup');
      console.error('Delete error:', error);
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

  const handleRestore = (filename: string) => {
    setRestoreFilename(filename);
    setRestoreAgreed(false);
    setRestorePassword('');
    setShowRestoreConfirm(true);
  };

  const confirmRestore = async () => {
    if (!restoreAgreed) {
      toast.error('You must confirm the risks to proceed');
      return;
    }
    if (!restorePassword) {
      toast.error('Password is required');
      return;
    }

    setShowRestoreConfirm(false);
    setRestoring(true);
    try {
      const result = await backupApi.restoreBackup(restoreFilename, restorePassword);
      if (result.statements_failed > 0) {
        toast.warning(`Restore completed with ${result.statements_failed} warnings. ${result.statements_executed} statements executed successfully.`);
      }
      setShowRestoreSuccess(true);
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to restore backup');
      console.error('Restore error:', error);
    } finally {
      setRestoring(false);
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
    <div className="p-4 md:p-6 space-y-6">
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
                            disabled={restoring}
                          >
                            <RefreshCw className={`h-4 w-4 ${restoring ? 'animate-spin' : ''}`} />
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

      {/* Delete Confirmation */}
      <AlertDialog open={showDeleteConfirm} onOpenChange={setShowDeleteConfirm}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Backup?</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete <strong>{deleteTarget}</strong>? This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={confirmDelete} className="bg-red-600 hover:bg-red-700">
              Yes, Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Restore Confirmation (combined checkbox + password) */}
      <AlertDialog open={showRestoreConfirm} onOpenChange={setShowRestoreConfirm}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Restore Database?</AlertDialogTitle>
            <AlertDialogDescription asChild>
              <div className="text-left space-y-3">
                <p className="text-red-600 dark:text-red-400 font-bold">CRITICAL WARNING</p>
                <p>This will <strong>REPLACE</strong> all current data with data from:</p>
                <p className="font-mono text-sm">{restoreFilename}</p>
                <p className="text-sm text-muted-foreground">All changes made after this backup will be LOST.</p>
                <p className="text-sm text-red-600 dark:text-red-400 font-semibold">This action is IRREVERSIBLE!</p>
              </div>
            </AlertDialogDescription>
          </AlertDialogHeader>
          <div className="space-y-4 px-6">
            <div className="flex items-center space-x-2">
              <Checkbox
                id="restore-agree"
                checked={restoreAgreed}
                onCheckedChange={(checked) => setRestoreAgreed(checked === true)}
              />
              <label htmlFor="restore-agree" className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                I understand the risks and want to proceed
              </label>
            </div>
            <div className="space-y-2">
              <Label htmlFor="restore-password">Password Verification</Label>
              <Input
                id="restore-password"
                type="password"
                placeholder="Enter your password"
                value={restorePassword}
                onChange={(e) => setRestorePassword(e.target.value)}
              />
              <p className="text-xs text-muted-foreground">For security, please enter your password to confirm this restore operation.</p>
            </div>
          </div>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmRestore}
              className="bg-red-600 hover:bg-red-700"
              disabled={!restoreAgreed || !restorePassword}
            >
              Restore Database
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Restore Success */}
      <AlertDialog open={showRestoreSuccess} onOpenChange={(open) => { if (!open) window.location.reload(); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Database Restored!</AlertDialogTitle>
            <AlertDialogDescription>
              The application will refresh now.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogAction onClick={() => window.location.reload()}>
              OK
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
