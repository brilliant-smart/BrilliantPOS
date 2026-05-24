import { api } from "@/app/lib/api";

export interface PurchaseOrderItem {
  id?: number;
  product_id: number;
  product_name?: string;
  quantity: number;
  unit_cost: number;
  total_cost?: number;
}

export interface PurchaseOrder {
  id: number;
  po_number: string;
  supplier_id: number;
  supplier_name?: string;
  order_date: string;
  expected_delivery_date: string | null;
  status: 'pending' | 'approved' | 'received' | 'cancelled';
  total_amount: number;
  notes: string | null;
  ordered_by: number;
  ordered_by_name?: string;
  approved_by: number | null;
  approved_by_name?: string | null;
  approved_at: string | null;
  received_by: number | null;
  received_by_name?: string | null;
  received_at: string | null;
  items?: PurchaseOrderItem[];
  created_at: string;
  updated_at: string;
}

export interface CreatePurchaseOrderData {
  supplier_id: number;
  order_date: string;
  expected_delivery_date?: string;
  payment_method?: string;
  payment_due_date?: string;
  notes?: string;
  items: Array<{
    product_id: number;
    quantity_ordered: number;
    unit_cost: number;
    unit_type?: string;
    product_unit_type_id?: number | null;
    conversion_factor?: number;
    batch_number?: string;
    expiry_date?: string;
    manufacturing_date?: string;
  }>;
}

export interface UpdatePurchaseOrderData {
  supplier_id?: number;
  order_date?: string;
  expected_delivery_date?: string;
  notes?: string;
  items?: Array<{
    product_id: number;
    quantity_ordered: number;
    unit_cost: number;
    unit_type?: string;
    product_unit_type_id?: number | null;
    conversion_factor?: number;
    batch_number?: string;
    expiry_date?: string;
    manufacturing_date?: string;
  }>;
}

export const purchaseOrderApi = {
  // Get all purchase orders (paginated)
  getAll: async (params?: { status?: string; supplier_id?: number; page?: number; per_page?: number }) => {
    const queryParams = new URLSearchParams();
    if (params?.status) queryParams.append('status', params.status);
    if (params?.supplier_id) queryParams.append('supplier_id', params.supplier_id.toString());
    if (params?.page) queryParams.append('page', String(params.page));
    if (params?.per_page) queryParams.append('per_page', String(params.per_page));

    const query = queryParams.toString();
    const response = await api.get(`/purchase-orders${query ? '?' + query : ''}`);
    return response.data as import('./sales').PaginatedResponse<PurchaseOrder>;
  },

  // Get single purchase order
  getById: async (id: number) => {
    const response = await api.get(`/purchase-orders/${id}`);
    return response.data.data || response.data;
  },

  // Create purchase order
  create: async (data: CreatePurchaseOrderData) => {
    const response = await api.post('/purchase-orders', data);
    return response.data.data || response.data;
  },

  // Update purchase order
  update: async (id: number, data: UpdatePurchaseOrderData) => {
    const response = await api.put(`/purchase-orders/${id}`, data);
    return response.data.data || response.data;
  },

  // Delete purchase order
  delete: async (id: number) => {
    const response = await api.delete(`/purchase-orders/${id}`);
    return response.data;
  },

  // Approve purchase order
  approve: async (id: number) => {
    const response = await api.post(`/purchase-orders/${id}/approve`);
    return response.data.data || response.data;
  },

  // Receive purchase order (update inventory)
  receive: async (id: number, data: { items: Array<{ product_id: number; quantity_received: number }> }) => {
    const response = await api.post(`/purchase-orders/${id}/receive`, data);
    return response.data.data || response.data;
  },

  // Record payment
  recordPayment: async (id: number, data: { amount: number; payment_method: string; payment_date: string; reference?: string }) => {
    const response = await api.post(`/purchase-orders/${id}/record-payment`, data);
    return response.data.data || response.data;
  },

  // Cancel purchase order
  cancel: async (id: number, data: { cancellation_reason: string }) => {
    const response = await api.post(`/purchase-orders/${id}/cancel`, data);
    return response.data.data || response.data;
  },

  // Export purchase order
  export: async (id: number, format: 'pdf' | 'csv' = 'pdf') => {
    const response = await api.get(`/purchase-orders/${id}/export?format=${format}`, {
      responseType: 'blob'
    });
    return response.data;
  },

  // Export all POs to CSV
  exportAll: async () => {
    const response = await api.get('/purchase-orders-export', {
      responseType: 'blob',
    });
    return response.data;
  },

  // Generate short-lived PDF token for download
  generatePdfToken: async (id: number) => {
    const response = await api.post(`/purchase-orders/${id}/pdf-token`);
    return response.data;
  },
};