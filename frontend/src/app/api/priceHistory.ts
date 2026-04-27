import { api } from "@/app/lib/api";

export const priceHistoryApi = {
  // Get price comparison for products
  getPriceComparison: async (productIds: number[]) => {
    const response = await api.post('/purchase-orders/price-comparison', {
      product_ids: productIds,
    });
    return response.data;
  },
};
