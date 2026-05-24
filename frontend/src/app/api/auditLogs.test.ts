import { describe, it, expect, beforeEach, vi } from 'vitest';
import { auditLogApi } from './auditLogs';
import { api } from '@/app/lib/api';

vi.mock('@/app/lib/api', () => ({
  api: {
    get: vi.fn(),
  },
}));

describe('auditLogApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('fetches audit logs without filters', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: [] } });

    await auditLogApi.getAuditLogs();

    expect(api.get).toHaveBeenCalledWith('/audit-logs', { params: {} });
  });

  it('fetches audit logs with all filters', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: [] } });

    await auditLogApi.getAuditLogs({
      user_id: 1,
      action: 'product.created',
      action_category: 'products',
      model_type: 'Product',
      start_date: '2026-01-01',
      end_date: '2026-01-31',
      page: 2,
      per_page: 50,
    });

    expect(api.get).toHaveBeenCalledWith('/audit-logs', {
      params: {
        user_id: 1,
        action: 'product.created',
        action_category: 'products',
        model_type: 'Product',
        start_date: '2026-01-01',
        end_date: '2026-01-31',
        page: 2,
        per_page: 50,
      },
    });
  });

  it('fetches audit log statistics', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { total_actions: 500 } });

    await auditLogApi.getStatistics();

    expect(api.get).toHaveBeenCalledWith('/audit-logs/statistics');
  });

  it('fetches a single audit log by ID', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { id: 42, action: 'sale.completed' } });

    await auditLogApi.getAuditLog(42);

    expect(api.get).toHaveBeenCalledWith('/audit-logs/42');
  });
});