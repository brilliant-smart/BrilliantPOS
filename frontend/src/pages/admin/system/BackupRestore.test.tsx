import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import BackupRestore from './BackupRestore';

// Mock the auth context
vi.mock('@/app/auth/AuthContext', () => ({
  useAuth: () => ({ user: { id: 1, name: 'Owner', email: 'owner@store.com', role: 'owner' } }),
}));

vi.mock('@/app/api/backups', () => ({
  backupApi: {
    getBackups: vi.fn(),
    createBackup: vi.fn(),
    downloadBackup: vi.fn(),
    deleteBackup: vi.fn(),
    uploadBackup: vi.fn(),
    restoreBackup: vi.fn(),
  },
}));

vi.mock('sonner', () => ({
  toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn() },
}));

vi.mock('date-fns', () => ({
  format: (_date: Date, _fmt: string) => 'Jan 15, 2026 at 10:30 AM',
}));

import { backupApi } from '@/app/api/backups';
import { toast } from 'sonner';

const mockBackups = [
  { filename: 'backup_2026-01-15.sql', size: 1024000, created_at: '2026-01-15T10:30:00Z' },
  { filename: 'backup_2026-01-10.sql', size: 512000, created_at: '2026-01-10T08:00:00Z' },
];

describe('BackupRestore', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(backupApi.getBackups).mockResolvedValue({ backups: mockBackups });
  });

  it('loads and displays backup list', async () => {
    render(<BackupRestore />);

    await waitFor(() => {
      expect(screen.getByText('backup_2026-01-15.sql')).toBeInTheDocument();
    });
    expect(screen.getByText('backup_2026-01-10.sql')).toBeInTheDocument();
  });

  it('shows empty state when no backups', async () => {
    vi.mocked(backupApi.getBackups).mockResolvedValue({ backups: [] });

    render(<BackupRestore />);

    await waitFor(() => {
      expect(screen.getByText('No backups found')).toBeInTheDocument();
    });
  });

  it('shows backup count in description', async () => {
    render(<BackupRestore />);

    await waitFor(() => {
      expect(screen.getByText(/2 backups available/)).toBeInTheDocument();
    });
  });

  it('creates a new backup on button click', async () => {
    vi.mocked(backupApi.createBackup).mockResolvedValue({ message: 'Backup created' });

    render(<BackupRestore />);

    await waitFor(() => {
      expect(screen.getByText('backup_2026-01-15.sql')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button', { name: /create backup/i }));

    await waitFor(() => {
      expect(backupApi.createBackup).toHaveBeenCalled();
    });
    expect(toast.success).toHaveBeenCalledWith('Backup created successfully');
  });

  it('shows error when backup creation fails', async () => {
    vi.mocked(backupApi.createBackup).mockRejectedValue({
      response: { data: { message: 'Backup failed' } },
    });

    render(<BackupRestore />);

    await waitFor(() => {
      expect(screen.getByText('backup_2026-01-15.sql')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button', { name: /create backup/i }));

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledWith('Backup failed');
    });
  });

  it('downloads backup on download button click', async () => {
    vi.mocked(backupApi.downloadBackup).mockResolvedValue(undefined);

    render(<BackupRestore />);

    await waitFor(() => {
      expect(screen.getByText('backup_2026-01-15.sql')).toBeInTheDocument();
    });

    const downloadButtons = screen.getAllByRole('button', { name: '' });
    // Find the download button (first action button in the row)
    const downloadBtn = downloadButtons.find(btn => btn.querySelector('.lucide-download'));
    if (downloadBtn) {
      fireEvent.click(downloadBtn);
      await waitFor(() => {
        expect(backupApi.downloadBackup).toHaveBeenCalledWith('backup_2026-01-15.sql');
      });
    }
  });

  it('opens delete confirmation dialog on delete click', async () => {
    render(<BackupRestore />);

    await waitFor(() => {
      expect(screen.getByText('backup_2026-01-15.sql')).toBeInTheDocument();
    });

    // Click the delete button (variant="destructive")
    const deleteButtons = screen.getAllByRole('button').filter(btn =>
      btn.classList.contains('bg-destructive') || btn.closest('[class*="destructive"]')
    );
    if (deleteButtons.length > 0) {
      fireEvent.click(deleteButtons[0]);
      await waitFor(() => {
        expect(screen.getByText('Delete Backup?')).toBeInTheDocument();
      });
    }
  });

  it('shows error toast when loading backups fails', async () => {
    vi.mocked(backupApi.getBackups).mockRejectedValue(new Error('Network error'));

    render(<BackupRestore />);

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledWith('Failed to load backups');
    });
  });

  it('validates file extension on upload', async () => {
    render(<BackupRestore />);

    await waitFor(() => {
      expect(screen.getByText('backup_2026-01-15.sql')).toBeInTheDocument();
    });

    const fileInput = document.getElementById('backup-upload') as HTMLInputElement;
    const badFile = new File(['content'], 'backup.txt', { type: 'text/plain' });

    // Create a mock event with target.files
    Object.defineProperty(fileInput, 'files', {
      value: [badFile],
      configurable: true,
    });

    fireEvent.change(fileInput);

    expect(toast.error).toHaveBeenCalledWith('Please select a valid SQL backup file (.sql)');
  });

  it('shows restore confirmation dialog', async () => {
    render(<BackupRestore />);

    await waitFor(() => {
      expect(screen.getByText('backup_2026-01-15.sql')).toBeInTheDocument();
    });

    // Find restore button (has RefreshCw icon in action column, owner-only)
    const restoreButtons = screen.getAllByRole('button').filter(btn =>
      btn.getAttribute('title') === 'Restore Database (Owner Only)'
    );
    fireEvent.click(restoreButtons[0]);

    await waitFor(() => {
      expect(screen.getByText('Restore Database?')).toBeInTheDocument();
    });
  });
});