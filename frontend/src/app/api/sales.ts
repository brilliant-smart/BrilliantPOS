import { api } from "@/app/lib/api";

export interface SaleItem {
  id?: number;
  product_id: number;
  product_name?: string;
  quantity: number;
  unit_price: number;
  cost_price?: number;
  total_price?: number;
  profit?: number;
}

export interface Sale {
  id: number;
  sale_number: string;
  sale_date: string;
  customer_name: string | null;
  customer_phone: string | null;
  total_amount: number;
  total_profit: number;
  sale_type: 'cash' | 'credit' | 'online' | 'pos';
  payment_status: 'unpaid' | 'partially_paid' | 'paid';
  notes: string | null;
  sold_by: number;
  sold_by_name?: string;
  items?: SaleItem[];
  created_at: string;
  updated_at: string;
}

export interface CreateSaleData {
  sale_date: string;
  customer_name?: string;
  customer_phone?: string;
  sale_type: 'cash' | 'credit' | 'online' | 'pos';
  payment_status: 'unpaid' | 'partially_paid' | 'paid';
  notes?: string;
  items: Array<{
    product_id: number | string;
    quantity: number;
    unit_price: number;
  }>;
}

export interface UpdateSaleData {
  sale_date?: string;
  customer_name?: string;
  customer_phone?: string;
  sale_type?: 'cash' | 'credit' | 'online' | 'pos';
  payment_status?: 'unpaid' | 'partially_paid' | 'paid';
  notes?: string;
}

export const salesApi = {
  // Get all sales
  getAll: async (params?: { 
    start_date?: string; 
    end_date?: string; 
    sale_type?: string;
    payment_status?: string;
    customer_name?: string;
  }) => {
    const queryParams = new URLSearchParams();
    if (params?.start_date) queryParams.append('start_date', params.start_date);
    if (params?.end_date) queryParams.append('end_date', params.end_date);
    if (params?.sale_type) queryParams.append('sale_type', params.sale_type);
    if (params?.payment_status) queryParams.append('payment_status', params.payment_status);
    if (params?.customer_name) queryParams.append('customer_name', params.customer_name);
    
    const query = queryParams.toString();
    const response = await api.get(`/sales${query ? '?' + query : ''}`);
    return response.data.data || response.data;
  },

  // Get single sale
  getById: async (id: number) => {
    const response = await api.get(`/sales/${id}`);
    return response.data.data || response.data;
  },

  // Create sale
  create: async (data: CreateSaleData) => {
    const response = await api.post('/sales', data);
    return response.data.data || response.data;
  },

  // Update sale
  update: async (id: number, data: UpdateSaleData) => {
    const response = await api.put(`/sales/${id}`, data);
    return response.data.data || response.data;
  },

  // Delete sale
  delete: async (id: number) => {
    const response = await api.delete(`/sales/${id}`);
    return response.data;
  },

  // Get sales summary
  getSummary: async (startDate?: string, endDate?: string) => {
    const params = new URLSearchParams();
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);
    
    const query = params.toString();
    const response = await api.get(`/sales/summary${query ? '?' + query : ''}`);
    return response.data.data || response.data;
  },

  // Get sales analytics
  getAnalytics: async (
    period: 'today' | 'yesterday' | 'this_week' | 'this_month' | 'custom' = 'this_month',
    startDate?: string,
    endDate?: string
  ) => {
    const params = new URLSearchParams();
    params.append('period', period);
    
    if (period === 'custom' && startDate && endDate) {
      params.append('start_date', startDate);
      params.append('end_date', endDate);
    }
    
    const response = await api.get(`/sales/analytics?${params.toString()}`);
    return response.data;
  },

  // Export sales
  export: async (startDate?: string, endDate?: string, format: 'pdf' | 'csv' = 'csv') => {
    const params = new URLSearchParams();
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);
    params.append('format', format);
    
    const query = params.toString();
    const response = await api.get(`/sales/export${query ? '?' + query : ''}`, {
      responseType: 'blob'
    });
    return response.data;
  },
};