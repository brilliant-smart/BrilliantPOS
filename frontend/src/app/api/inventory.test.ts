import { describe, it, expect, beforeEach, vi } from 'vitest';
import {
  addStock,
  reduceStock,
  adjustStock,
  getStockHistory,
  getLowStockProducts,
  getOutOfStockProducts,
  getInventorySummary,
  bulkUpdateStock,
} from './inventory';
import { api } from '@/app/lib/api';

vi.mock('@/app/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

describe('inventory API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---- addStock ----

  it('adds stock to a product', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { stock_quantity: 55 } });

    await addStock(1, { quantity: 5, type: 'purchase' });

    expect(api.post).toHaveBeenCalledWith('/inventory/products/1/add-stock', {
      quantity: 5,
      type: 'purchase',
    });
  });

  it('adds stock with unit cost', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { stock_quantity: 55 } });

    await addStock(1, { quantity: 5, type: 'purchase', unit_cost: 100 });

    expect(api.post).toHaveBeenCalledWith('/inventory/products/1/add-stock', {
      quantity: 5,
      type: 'purchase',
      unit_cost: 100,
    });
  });

  // ---- reduceStock ----

  it('reduces stock from a product', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { stock_quantity: 40 } });

    await reduceStock(1, { quantity: 10, type: 'damage' });

    expect(api.post).toHaveBeenCalledWith('/inventory/products/1/reduce-stock', {
      quantity: 10,
      type: 'damage',
    });
  });

  // ---- adjustStock ----

  it('adjusts stock to exact quantity', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { stock_quantity: 25 } });

    await adjustStock(1, 25, 'Manual count');

    expect(api.post).toHaveBeenCalledWith('/inventory/products/1/adjust-stock', {
      quantity: 25,
      notes: 'Manual count',
    });
  });

  it('adjusts stock without notes', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { stock_quantity: 0 } });

    await adjustStock(1, 0);

    expect(api.post).toHaveBeenCalledWith('/inventory/products/1/adjust-stock', {
      quantity: 0,
      notes: undefined,
    });
  });

  // ---- getStockHistory ----

  it('fetches stock history for a product', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: [{ id: 1, type: 'purchase' }] });

    await getStockHistory(5);

    expect(api.get).toHaveBeenCalledWith('/inventory/products/5/stock-history');
  });

  // ---- getLowStockProducts ----

  it('fetches low stock products', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: [{ id: 1, name: 'Low Item' }] });

    await getLowStockProducts();

    expect(api.get).toHaveBeenCalledWith('/inventory/low-stock');
  });

  // ---- getOutOfStockProducts ----

  it('fetches out of stock products', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: [{ id: 2, name: 'Out Item' }] });

    await getOutOfStockProducts();

    expect(api.get).toHaveBeenCalledWith('/inventory/out-of-stock');
  });

  // ---- getInventorySummary ----

  it('fetches inventory summary', async () => {
    vi.mocked(api.get).mockResolvedValue({
      data: { total_products: 10, in_stock: 8, low_stock: 1, out_of_stock: 1, total_stock_value: 50000 },
    });

    const result = await getInventorySummary();

    expect(api.get).toHaveBeenCalledWith('/inventory/summary');
    expect(result.total_products).toBe(10);
  });

  // ---- bulkUpdateStock ----

  it('bulk updates stock for multiple products', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { message: 'Updated' } });

    const updates = [
      { product_id: 1, quantity: 75 },
      { product_id: 2, quantity: 10 },
    ];

    await bulkUpdateStock(updates, 'Bulk adjustment');

    expect(api.post).toHaveBeenCalledWith('/inventory/bulk-update', {
      updates,
      notes: 'Bulk adjustment',
    });
  });

  it('bulk updates stock without notes', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { message: 'Updated' } });

    await bulkUpdateStock([{ product_id: 1, quantity: 50 }]);

    expect(api.post).toHaveBeenCalledWith('/inventory/bulk-update', {
      updates: [{ product_id: 1, quantity: 50 }],
      notes: undefined,
    });
  });
});