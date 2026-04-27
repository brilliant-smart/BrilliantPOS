import { api } from '@/app/lib/api';

export interface Backup {
  filename: string;
  size: number;
  created_at: string;
}

export const backupApi = {
  // Get all backups
  getBackups: async (): Promise<{ backups: Backup[] }> => {
    const response = await api.get('/backups');
    return response.data;
  },

  // Create new backup
  createBackup: async () => {
    const response = await api.post('/backups/create');
    return response.data;
  },

  // Download backup
  downloadBackup: async (filename: string) => {
    const response = await api.get(`/backups/${filename}/download`, {
      responseType: 'blob',
    });
    
    // Create download link
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  },

  // Delete backup
  deleteBackup: async (filename: string) => {
    const response = await api.delete(`/backups/${filename}`);
    return response.data;
  },

  // Upload backup file
  uploadBackup: async (file: File) => {
    const formData = new FormData();
    formData.append('backup_file', file);
    
    const response = await api.post('/backups/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },

  // Restore backup with password
  restoreBackup: async (filename: string, password: string) => {
    const response = await api.post(`/backups/${filename}/restore`, { password });
    return response.data;
  },
};
