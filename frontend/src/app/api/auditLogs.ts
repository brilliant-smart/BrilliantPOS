import { api } from '@/app/lib/api';

export interface AuditLog {
  id: number;
  user_id: number;
  action: string;
  action_label: string;
  action_category: string;
  model_type: string;
  model_label: string;
  model_id: number | null;
  old_values: any;
  new_values: any;
  ip_address: string;
  user_agent: string;
  description: string | null;
  created_at: string;
  user?: {
    id: number;
    name: string;
    email: string;
    role: string;
  };
}

export interface AuditLogFilters {
  user_id?: number;
  action?: string;
  action_category?: string;
  model_type?: string;
  start_date?: string;
  end_date?: string;
  page?: number;
  per_page?: number;
}

export const auditLogApi = {
  // Get audit logs with filters
  getAuditLogs: async (filters: AuditLogFilters = {}) => {
    const response = await api.get('/audit-logs', { params: filters });
    return response.data;
  },

  // Get audit log statistics
  getStatistics: async () => {
    const response = await api.get('/audit-logs/statistics');
    return response.data;
  },

  // Get specific audit log
  getAuditLog: async (id: number) => {
    const response = await api.get(`/audit-logs/${id}`);
    return response.data;
  },
};
