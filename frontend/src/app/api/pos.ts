import { api } from '@/app/lib/api';
import { CartItem } from '@/hooks/usePosCart';
import { Payment } from '@/hooks/usePosPayment';

export interface CompleteSaleRequest {
  items: Array<{
    product_id: number;
    quantity: number;
    unit_price: number;
    unit_type?: string;
    discount?: number;
  }>;
  payments: Array<{
    method: string;
    amount: number;
    reference?: string;
  }>;
  customer_id?: number;
  customer_name?: string;
  discount_percentage?: number;
  discount_amount?: number;
  notes?: string;
}

export interface HoldCartRequest {
  items: CartItem[];
  customer_id?: number;
  discount_percentage?: number;
  discount_amount?: number;
  notes?: string;
}

export interface VoidSaleRequest {
  reason: string;
}

export const posApi = {
  // Validate stock before completing sale
  validateStock: async (items: Array<{ product_id: number; quantity: number }>) => {
    const response = await api.post('/pos/validate-stock', { items });
    return response.data;
  },

  // Complete sale with split payments
  completeSale: async (data: CompleteSaleRequest) => {
    const response = await api.post('/pos/complete-sale', data);
    return response.data;
  },

  // Hold current cart
  holdCart: async (data: HoldCartRequest) => {
    const response = await api.post('/pos/hold-cart', data);
    return response.data;
  },

  // Get all held carts
  getHeldCarts: async () => {
    const response = await api.get('/pos/held-carts');
    return response.data;
  },

  // Recall a held cart
  recallCart: async (id: number) => {
    const response = await api.get(`/pos/held-carts/${id}`);
    return response.data;
  },

  // Delete a held cart
  deleteHeldCart: async (id: number) => {
    const response = await api.delete(`/pos/held-carts/${id}`);
    return response.data;
  },

  // Void a sale
  voidSale: async (saleId: number, data: VoidSaleRequest) => {
    const response = await api.post(`/pos/void-sale/${saleId}`, data);
    return response.data;
  },

  // Get sale for reprint
  getSaleForReprint: async (saleId: number) => {
    const response = await api.get(`/pos/reprint/${saleId}`);
    return response.data;
  },

  // Generate short-lived receipt token
  generateReceiptToken: async (saleId: number) => {
    const response = await api.post(`/pos/sales/${saleId}/receipt-token`);
    return response.data;
  },
};
