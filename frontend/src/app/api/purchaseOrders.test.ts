import { describe, it, expect, beforeEach, vi } from 'vitest';
import { purchaseOrderApi } from './purchaseOrders';
import { api } from '@/app/lib/api';

vi.mock('@/app/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

describe('purchaseOrderApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---- getAll ----

  it('fetches all purchase orders without params', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: [], current_page: 1 } });

    await purchaseOrderApi.getAll();

    expect(api.get).toHaveBeenCalledWith('/purchase-orders');
  });

  it('fetches purchase orders with status filter', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: [] } });

    await purchaseOrderApi.getAll({ status: 'pending', page: 1 });

    const calledUrl = vi.mocked(api.get).mock.calls[0][0] as string;
    expect(calledUrl).toContain('status=pending');
  });

  // ---- getById ----

  it('fetches a single purchase order', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: { id: 1, po_number: 'PO-001' } } });

    await purchaseOrderApi.getById(1);

    expect(api.get).toHaveBeenCalledWith('/purchase-orders/1');
  });

  // ---- create ----

  it('creates a purchase order', async () => {
    const data = {
      supplier_id: 1,
      order_date: '2026-01-15',
      items: [{ product_id: 1, quantity_ordered: 10, unit_cost: 50 }],
    };
    vi.mocked(api.post).mockResolvedValue({ data: { data: { id: 1 } } });

    await purchaseOrderApi.create(data);

    expect(api.post).toHaveBeenCalledWith('/purchase-orders', data);
  });

  // ---- approve ----

  it('approves a purchase order', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { data: { id: 1, status: 'approved' } } });

    await purchaseOrderApi.approve(1);

    expect(api.post).toHaveBeenCalledWith('/purchase-orders/1/approve');
  });

  // ---- receive ----

  it('receives a purchase order with quantities', async () => {
    const data = {
      items: [
        { product_id: 1, quantity_received: 10 },
        { product_id: 2, quantity_received: 5 },
      ],
    };
    vi.mocked(api.post).mockResolvedValue({ data: { data: { id: 1, status: 'received' } } });

    await purchaseOrderApi.receive(1, data);

    expect(api.post).toHaveBeenCalledWith('/purchase-orders/1/receive', data);
  });

  // ---- recordPayment ----

  it('records a payment on a purchase order', async () => {
    const data = { amount: 5000, payment_method: 'bank_transfer', payment_date: '2026-01-20' };
    vi.mocked(api.post).mockResolvedValue({ data: { data: { id: 1 } } });

    await purchaseOrderApi.recordPayment(1, data);

    expect(api.post).toHaveBeenCalledWith('/purchase-orders/1/record-payment', data);
  });

  // ---- cancel ----

  it('cancels a purchase order with reason', async () => {
    const data = { cancellation_reason: 'Supplier discontinued' };
    vi.mocked(api.post).mockResolvedValue({ data: { data: { id: 1, status: 'cancelled' } } });

    await purchaseOrderApi.cancel(1, data);

    expect(api.post).toHaveBeenCalledWith('/purchase-orders/1/cancel', data);
  });

  // ---- export ----

  it('exports a single purchase order', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: new Blob(['pdf'], { type: 'application/pdf' }) });

    await purchaseOrderApi.export(1, 'pdf');

    expect(api.get).toHaveBeenCalledWith(
      '/purchase-orders/1/export?format=pdf',
      expect.objectContaining({ responseType: 'blob' }),
    );
  });

  // ---- exportAll ----

  it('exports all purchase orders to CSV', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: new Blob(['csv'], { type: 'text/csv' }) });

    await purchaseOrderApi.exportAll();

    expect(api.get).toHaveBeenCalledWith('/purchase-orders-export', { responseType: 'blob' });
  });

  // ---- delete ----

  it('deletes a purchase order', async () => {
    vi.mocked(api.delete).mockResolvedValue({ data: { message: 'Deleted' } });

    await purchaseOrderApi.delete(1);

    expect(api.delete).toHaveBeenCalledWith('/purchase-orders/1');
  });

  // ---- generatePdfToken ----

  it('generates a PDF token for download', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { token: 'abc123' } });

    const result = await purchaseOrderApi.generatePdfToken(5);

    expect(api.post).toHaveBeenCalledWith('/purchase-orders/5/pdf-token');
    expect(result).toEqual({ token: 'abc123' });
  });
});