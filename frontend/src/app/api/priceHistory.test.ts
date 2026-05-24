import { describe, it, expect, beforeEach, vi } from 'vitest';
import { priceHistoryApi } from './priceHistory';
import { api } from '@/app/lib/api';

vi.mock('@/app/lib/api', () => ({
  api: {
    post: vi.fn(),
  },
}));

describe('priceHistoryApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('gets price comparison for products', async () => {
    vi.mocked(api.post).mockResolvedValue({
      data: { comparisons: [{ product_id: 1, cheapest_supplier: 'Acme' }] },
    });

    await priceHistoryApi.getPriceComparison([1, 2, 3]);

    expect(api.post).toHaveBeenCalledWith('/purchase-orders/price-comparison', {
      product_ids: [1, 2, 3],
    });
  });

  it('gets price comparison with empty array', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { comparisons: [] } });

    await priceHistoryApi.getPriceComparison([]);

    expect(api.post).toHaveBeenCalledWith('/purchase-orders/price-comparison', {
      product_ids: [],
    });
  });
});