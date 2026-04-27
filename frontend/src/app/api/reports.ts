import { api } from "@/app/lib/api";

export interface ProfitLossReport {
  period: {
    start_date: string;
    end_date: string;
  };
  revenue: {
    total_sales: number;
    total_revenue: number;
  };
  costs: {
    total_purchases: number;
    cogs: number;
  };
  profit: {
    gross_profit: number;
    gross_margin: number;
  };
}

export interface StockVarianceReport {
  period: {
    start_date: string;
    end_date: string;
  };
  variances: Array<{
    product_id: number;
    product_name: string;
    sku: string;
    expected_stock: number;
    actual_stock: number;
    variance: number;
    variance_value: number;
  }>;
  summary: {
    total_products_checked: number;
    products_with_variance: number;
    total_variance_value: number;
  };
}

export interface ExpiringProductsReport {
  days_ahead: number;
  products: Array<{
    product_id: number;
    product_name: string;
    sku: string;
    batch_number: string;
    expiry_date: string;
    days_until_expiry: number;
    stock_quantity: number;
    value_at_risk: number;
  }>;
  summary: {
    total_products: number;
    total_units: number;
    total_value_at_risk: number;
  };
}

export const reportsApi = {
  // Get financial overview (includes profit/loss data)
  getProfitLoss: async (startDate: string, endDate: string) => {
    const params = new URLSearchParams();
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);

    const response = await api.get(`/reports/financial-overview?${params.toString()}`);
    return response.data.data || response.data;
  },

  // Get stock variances (fraud detection)
  getStockVariance: async () => {
    const response = await api.get(`/reports/stock-variances`);
    return response.data.data || response.data;
  },

  // Get expiring products (pharmacy)
  getExpiringProducts: async (daysAhead: number = 30) => {
    const params = new URLSearchParams();
    params.append('days_ahead', daysAhead.toString());

    const response = await api.get(`/reports/expiring-products?${params.toString()}`);
    return response.data.data || response.data;
  },

  getSalesSummary: async (startDate: string, endDate: string) => {
    const response = await api.get(`/reports/sales-summary?start_date=${startDate}&end_date=${endDate}`);
    return response.data.data || response.data;
  },

  getPurchaseSummary: async (startDate: string, endDate: string) => {
    const response = await api.get(`/reports/purchase-summary?start_date=${startDate}&end_date=${endDate}`);
    return response.data.data || response.data;
  },

  getLowStockAlert: async () => {
    const response = await api.get(`/reports/low-stock-alert`);
    return response.data.data || response.data;
  },

  exportProfitLoss: async (startDate: string, endDate: string) => {
    const response = await api.get(`/reports/profit-loss/export?start_date=${startDate}&end_date=${endDate}&format=csv`, {
      responseType: 'blob'
    });
    return response.data;
  },
};
