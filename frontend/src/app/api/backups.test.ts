import { describe, it, expect, beforeEach, vi } from 'vitest';
import { backupApi } from './backups';
import { api } from '@/app/lib/api';

vi.mock('@/app/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
  },
}));

describe('backupApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---- getBackups ----

  it('fetches backup list', async () => {
    const mockBackups = [
      { filename: 'backup_2026-01-01.sql', size: 1024, created_at: '2026-01-01T00:00:00Z' },
    ];
    vi.mocked(api.get).mockResolvedValue({ data: { backups: mockBackups } });

    const result = await backupApi.getBackups();

    expect(api.get).toHaveBeenCalledWith('/backups');
    expect(result.backups).toHaveLength(1);
    expect(result.backups[0].filename).toBe('backup_2026-01-01.sql');
  });

  // ---- createBackup ----

  it('creates a new backup', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { message: 'Backup created' } });

    const result = await backupApi.createBackup();

    expect(api.post).toHaveBeenCalledWith('/backups/create');
    expect(result.message).toBe('Backup created');
  });

  // ---- deleteBackup ----

  it('deletes a backup by filename', async () => {
    vi.mocked(api.delete).mockResolvedValue({ data: { message: 'Deleted' } });

    await backupApi.deleteBackup('backup_2026-01-01.sql');

    expect(api.delete).toHaveBeenCalledWith('/backups/backup_2026-01-01.sql');
  });

  // ---- uploadBackup ----

  it('uploads a backup file with FormData', async () => {
    const file = new File(['content'], 'backup.sql', { type: 'application/octet-stream' });
    vi.mocked(api.post).mockResolvedValue({ data: { message: 'Uploaded' } });

    const result = await backupApi.uploadBackup(file);

    expect(api.post).toHaveBeenCalledWith('/backups/upload', expect.any(FormData), {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    expect(result.message).toBe('Uploaded');
  });

  // ---- restoreBackup ----

  it('restores a backup with password', async () => {
    vi.mocked(api.post).mockResolvedValue({
      data: { message: 'Restored', statements_executed: 42, statements_failed: 0, errors: [] },
    });

    const result = await backupApi.restoreBackup('backup.sql', 'secret123');

    expect(api.post).toHaveBeenCalledWith('/backups/backup.sql/restore', { password: 'secret123' });
    expect(result.statements_executed).toBe(42);
    expect(result.statements_failed).toBe(0);
  });

  it('restore reports partial failures', async () => {
    vi.mocked(api.post).mockResolvedValue({
      data: {
        message: 'Restored with errors',
        statements_executed: 38,
        statements_failed: 4,
        errors: ['Error on line 10', 'Error on line 22'],
      },
    });

    const result = await backupApi.restoreBackup('backup.sql', 'pass');

    expect(result.statements_failed).toBe(4);
    expect(result.errors).toHaveLength(2);
  });

  // ---- downloadBackup ----

  it('downloads a backup file', async () => {
    const blob = new Blob(['SQL content'], { type: 'application/sql' });
    vi.mocked(api.get).mockResolvedValue({ data: blob });

    const linkClickSpy = vi.fn();
    const createElementSpy = vi.spyOn(document, 'createElement').mockReturnValue({
      href: '',
      setAttribute: vi.fn(),
      click: linkClickSpy,
      remove: vi.fn(),
    } as any);
    vi.spyOn(document.body, 'appendChild').mockImplementation(() => document.createElement('a'));
    vi.spyOn(window.URL, 'createObjectURL').mockReturnValue('blob:mock');
    vi.spyOn(window.URL, 'revokeObjectURL').mockImplementation(() => {});

    await backupApi.downloadBackup('backup_2026-01-01.sql');

    expect(api.get).toHaveBeenCalledWith('/backups/backup_2026-01-01.sql/download', {
      responseType: 'blob',
    });
    expect(linkClickSpy).toHaveBeenCalled();

    createElementSpy.mockRestore();
  });
});