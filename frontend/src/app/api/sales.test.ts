import { describe, it, expect, beforeEach, vi } from 'vitest';
import { salesApi } from './sales';
import { api } from '@/app/lib/api';

vi.mock('@/app/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

describe('salesApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---- getAll ----

  it('fetches all sales without params', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: [], current_page: 1 } });

    await salesApi.getAll();

    expect(api.get).toHaveBeenCalledWith('/sales');
  });

  it('fetches sales with filter params', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: [], current_page: 1 } });

    await salesApi.getAll({
      start_date: '2026-01-01',
      end_date: '2026-01-31',
      sale_type: 'cash',
      payment_status: 'paid',
      search: 'john',
      page: 2,
      per_page: 50,
    });

    const calledUrl = vi.mocked(api.get).mock.calls[0][0] as string;
    expect(calledUrl).toContain('start_date=2026-01-01');
    expect(calledUrl).toContain('end_date=2026-01-31');
    expect(calledUrl).toContain('sale_type=cash');
    expect(calledUrl).toContain('payment_status=paid');
    expect(calledUrl).toContain('search=john');
    expect(calledUrl).toContain('page=2');
    expect(calledUrl).toContain('per_page=50');
  });

  // ---- getById ----

  it('fetches a single sale by ID', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: { id: 1, sale_number: 'SALE-001' } } });

    const result = await salesApi.getById(1);

    expect(api.get).toHaveBeenCalledWith('/sales/1');
    expect(result.id).toBe(1);
  });

  // ---- create ----

  it('creates a sale', async () => {
    const data = {
      sale_date: '2026-01-15',
      sale_type: 'cash' as const,
      payment_status: 'paid' as const,
      items: [{ product_id: 1, quantity: 2, unit_price: 100 }],
    };
    vi.mocked(api.post).mockResolvedValue({ data: { data: { id: 5 } } });

    await salesApi.create(data);

    expect(api.post).toHaveBeenCalledWith('/sales', data);
  });

  // ---- update ----

  it('updates a sale', async () => {
    const data = { notes: 'Updated note' };
    vi.mocked(api.put).mockResolvedValue({ data: { data: { id: 1 } } });

    await salesApi.update(1, data);

    expect(api.put).toHaveBeenCalledWith('/sales/1', data);
  });

  // ---- delete ----

  it('deletes a sale', async () => {
    vi.mocked(api.delete).mockResolvedValue({ data: { message: 'Deleted' } });

    await salesApi.delete(1);

    expect(api.delete).toHaveBeenCalledWith('/sales/1');
  });

  // ---- getSummary ----

  it('gets sales summary with date range', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: { total_revenue: 5000 } } });

    await salesApi.getSummary('2026-01-01', '2026-01-31');

    const calledUrl = vi.mocked(api.get).mock.calls[0][0] as string;
    expect(calledUrl).toContain('start_date=2026-01-01');
  });

  // ---- getAnalytics ----

  it('gets analytics for this month by default', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { total_sales: 100 } });

    await salesApi.getAnalytics();

    const calledUrl = vi.mocked(api.get).mock.calls[0][0] as string;
    expect(calledUrl).toContain('period=this_month');
  });

  // ---- export ----

  it('exports sales as CSV blob', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: new Blob(['csv'], { type: 'text/csv' }) });

    await salesApi.export('2026-01-01', '2026-01-31', 'csv');

    expect(api.get).toHaveBeenCalledWith(
      expect.stringContaining('format=csv'),
      expect.objectContaining({ responseType: 'blob' }),
    );
  });

  // ---- generateReceiptToken ----

  it('generates a receipt token', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { token: 'abc123' } });

    await salesApi.generateReceiptToken(5);

    expect(api.post).toHaveBeenCalledWith('/pos/sales/5/receipt-token');
  });

  // ---- getCreditSummary ----

  it('gets credit summary', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { total_outstanding: 5000 } });

    await salesApi.getCreditSummary();

    expect(api.get).toHaveBeenCalledWith('/sales/credit-summary');
  });

  // ---- getOverdueCount ----

  it('gets overdue count', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { count: 3 } });

    const result = await salesApi.getOverdueCount();

    expect(api.get).toHaveBeenCalledWith('/sales/overdue-count');
    expect(result.count).toBe(3);
  });

  // ---- recordPaymentExtended ----

  it('records a payment with extended fields', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { message: 'Payment recorded' } });

    await salesApi.recordPaymentExtended(1, {
      amount: 500,
      method: 'cash',
      reference: 'REF-001',
      notes: 'Partial payment',
    });

    expect(api.post).toHaveBeenCalledWith('/sales/1/payment', {
      amount: 500,
      method: 'cash',
      reference: 'REF-001',
      notes: 'Partial payment',
    });
  });

  // ---- updateContact ----

  it('updates contact info for credit sale', async () => {
    vi.mocked(api.patch).mockResolvedValue({ data: { message: 'Updated' } });

    await salesApi.updateContact(1, 'John Doe', '08012345678');

    expect(api.patch).toHaveBeenCalledWith('/sales/1/contact', {
      contact_name: 'John Doe',
      customer_phone: '08012345678',
    });
  });

  it('updates contact with only name', async () => {
    vi.mocked(api.patch).mockResolvedValue({ data: { message: 'Updated' } });

    await salesApi.updateContact(1, 'Jane', undefined);

    expect(api.patch).toHaveBeenCalledWith('/sales/1/contact', {
      contact_name: 'Jane',
    });
  });
});