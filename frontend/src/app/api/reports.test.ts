import { describe, it, expect, beforeEach, vi } from 'vitest';
import { reportsApi } from './reports';
import { api } from '@/app/lib/api';

vi.mock('@/app/lib/api', () => ({
  api: {
    get: vi.fn(),
  },
}));

describe('reportsApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---- getProfitLoss ----

  it('fetches profit/loss report with date range', async () => {
    vi.mocked(api.get).mockResolvedValue({
      data: { data: { period: { start_date: '2026-01-01', end_date: '2026-01-31' }, revenue: {} } },
    });

    await reportsApi.getProfitLoss('2026-01-01', '2026-01-31');

    expect(api.get).toHaveBeenCalledWith(
      expect.stringContaining('/reports/financial-overview?start_date=2026-01-01&end_date=2026-01-31'),
    );
  });

  it('fetches profit/loss report without dates', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: { period: {} } } });

    await reportsApi.getProfitLoss('', '');

    expect(api.get).toHaveBeenCalledWith(expect.stringContaining('/reports/financial-overview?'));
  });

  it('unwraps nested data property from profit/loss response', async () => {
    const innerData = { period: { start_date: '2026-01-01' }, revenue: { total_revenue: 5000 } };
    vi.mocked(api.get).mockResolvedValue({ data: { data: innerData } });

    const result = await reportsApi.getProfitLoss('2026-01-01', '2026-01-31');

    expect(result.revenue.total_revenue).toBe(5000);
  });

  // ---- getStockVariance ----

  it('fetches stock variance report', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: { variances: [] } } });

    await reportsApi.getStockVariance();

    expect(api.get).toHaveBeenCalledWith('/reports/stock-variances');
  });

  // ---- getExpiringProducts ----

  it('fetches expiring products with default 30 days', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: { products: [] } } });

    await reportsApi.getExpiringProducts(30);

    expect(api.get).toHaveBeenCalledWith(expect.stringContaining('days_ahead=30'));
  });

  it('fetches expiring products with custom days', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: { products: [] } } });

    await reportsApi.getExpiringProducts(60);

    expect(api.get).toHaveBeenCalledWith(expect.stringContaining('days_ahead=60'));
  });

  // ---- getSalesSummary ----

  it('fetches sales summary with date range', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: { total: 100 } } });

    await reportsApi.getSalesSummary('2026-01-01', '2026-01-31');

    expect(api.get).toHaveBeenCalledWith(expect.stringContaining('start_date=2026-01-01'));
  });

  // ---- getPurchaseSummary ----

  it('fetches purchase summary with date range', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: { total: 50 } } });

    await reportsApi.getPurchaseSummary('2026-01-01', '2026-01-31');

    expect(api.get).toHaveBeenCalledWith(expect.stringContaining('start_date=2026-01-01'));
  });

  // ---- getLowStockAlert ----

  it('fetches low stock alert', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: [] } });

    await reportsApi.getLowStockAlert();

    expect(api.get).toHaveBeenCalledWith('/reports/low-stock-alert');
  });

  // ---- exportProfitLoss ----

  it('exports profit/loss as CSV blob', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: new Blob(['csv'], { type: 'text/csv' }) });

    await reportsApi.exportProfitLoss('2026-01-01', '2026-01-31');

    expect(api.get).toHaveBeenCalledWith(
      expect.stringContaining('format=csv'),
      expect.objectContaining({ responseType: 'blob' }),
    );
  });
});