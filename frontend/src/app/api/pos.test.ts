import { describe, it, expect, beforeEach, vi } from 'vitest';
import { posApi } from './pos';
import { api } from '@/app/lib/api';

vi.mock('@/app/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
  },
}));

describe('posApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---- validateStock ----

  it('validates stock for items', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { valid: true, items: [] } });

    const items = [{ product_id: 1, quantity: 5 }];
    await posApi.validateStock(items);

    expect(api.post).toHaveBeenCalledWith('/pos/validate-stock', { items });
  });

  // ---- completeSale ----

  it('completes a sale with items and payments', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { sale_id: 1, receipt_token: 'abc' } });

    const data = {
      items: [{ product_id: 1, quantity: 2, unit_price: 100 }],
      payments: [{ method: 'cash', amount: 200 }],
    };
    await posApi.completeSale(data);

    expect(api.post).toHaveBeenCalledWith('/pos/complete-sale', data);
  });

  // ---- holdCart ----

  it('holds current cart', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { id: 1 } });

    const data = {
      items: [{ product_id: 1, name: 'Test', price: 100, quantity: 2, unit_price: 100, unit_type: 'piece' }],
      notes: 'Customer coming back',
    };
    await posApi.holdCart(data);

    expect(api.post).toHaveBeenCalledWith('/pos/hold-cart', data);
  });

  // ---- getHeldCarts ----

  it('fetches all held carts', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: [] });

    await posApi.getHeldCarts();

    expect(api.get).toHaveBeenCalledWith('/pos/held-carts');
  });

  // ---- recallCart ----

  it('recalls a held cart by ID', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { id: 1, items: [] } });

    await posApi.recallCart(1);

    expect(api.get).toHaveBeenCalledWith('/pos/held-carts/1');
  });

  // ---- deleteHeldCart ----

  it('deletes a held cart', async () => {
    vi.mocked(api.delete).mockResolvedValue({ data: { message: 'Deleted' } });

    await posApi.deleteHeldCart(1);

    expect(api.delete).toHaveBeenCalledWith('/pos/held-carts/1');
  });

  // ---- voidSale ----

  it('voids a sale with reason', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { message: 'Voided' } });

    await posApi.voidSale(5, { reason: 'Customer cancelled' });

    expect(api.post).toHaveBeenCalledWith('/pos/void-sale/5', { reason: 'Customer cancelled' });
  });

  // ---- getSaleForReprint ----

  it('gets sale for reprint', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { id: 1, items: [] } });

    await posApi.getSaleForReprint(1);

    expect(api.get).toHaveBeenCalledWith('/pos/reprint/1');
  });

  // ---- generateReceiptToken ----

  it('generates receipt token for a sale', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { token: 'abc123' } });

    await posApi.generateReceiptToken(10);

    expect(api.post).toHaveBeenCalledWith('/pos/sales/10/receipt-token');
  });
});