import { describe, it, expect, beforeEach, vi } from 'vitest';
import { getDashboardStats, getMovementReport, getTurnoverRate, exportReport } from './inventoryAnalytics';
import { api } from '@/app/lib/api';

vi.mock('@/app/lib/api', () => ({
  api: {
    get: vi.fn(),
  },
}));

describe('inventoryAnalytics API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---- getDashboardStats ----

  it('fetches dashboard stats without date range', async () => {
    vi.mocked(api.get).mockResolvedValue({
      data: { overview: { total_products: 10 }, stock_status: { in_stock: 8 } },
    });

    await getDashboardStats();

    expect(api.get).toHaveBeenCalledWith(expect.stringContaining('/inventory/analytics/dashboard?'));
  });

  it('fetches dashboard stats with date range', async () => {
    vi.mocked(api.get).mockResolvedValue({
      data: { overview: { total_products: 10 } },
    });

    await getDashboardStats('2026-01-01', '2026-01-31');

    const calledUrl = vi.mocked(api.get).mock.calls[0][0] as string;
    expect(calledUrl).toContain('start_date=2026-01-01');
    expect(calledUrl).toContain('end_date=2026-01-31');
  });

  // ---- getMovementReport ----

  it('fetches movement report without filters', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { movements: [], total_count: 0 } });

    await getMovementReport();

    expect(api.get).toHaveBeenCalledWith(expect.stringContaining('/inventory/analytics/movements?'));
  });

  it('fetches movement report with all filters', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { movements: [], total_count: 0 } });

    await getMovementReport({
      start_date: '2026-01-01',
      end_date: '2026-01-31',
      type: 'purchase',
      product_id: 5,
      user_id: 2,
      limit: 100,
    });

    const calledUrl = vi.mocked(api.get).mock.calls[0][0] as string;
    expect(calledUrl).toContain('start_date=2026-01-01');
    expect(calledUrl).toContain('type=purchase');
    expect(calledUrl).toContain('product_id=5');
    expect(calledUrl).toContain('limit=100');
  });

  // ---- getTurnoverRate ----

  it('fetches turnover rate with default 30 days', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { period_days: 30, products: [] } });

    await getTurnoverRate();

    expect(api.get).toHaveBeenCalledWith('/inventory/analytics/turnover?days=30');
  });

  it('fetches turnover rate with custom days', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { period_days: 90, products: [] } });

    await getTurnoverRate(90);

    expect(api.get).toHaveBeenCalledWith('/inventory/analytics/turnover?days=90');
  });

  // ---- exportReport ----

  it('exports dashboard report as CSV', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: new Blob(['csv'], { type: 'text/csv' }) });

    await exportReport('dashboard', 'csv', '2026-01-01', '2026-01-31');

    const calledUrl = vi.mocked(api.get).mock.calls[0][0] as string;
    expect(calledUrl).toContain('type=dashboard');
    expect(calledUrl).toContain('format=csv');
    expect(calledUrl).toContain('start_date=2026-01-01');
  });

  it('exports movements report as PDF', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: new Blob(['pdf'], { type: 'application/pdf' }) });

    await exportReport('movements', 'pdf');

    const calledUrl = vi.mocked(api.get).mock.calls[0][0] as string;
    expect(calledUrl).toContain('type=movements');
    expect(calledUrl).toContain('format=pdf');
  });
});