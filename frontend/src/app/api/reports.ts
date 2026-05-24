import { api } from "@/app/lib/api";

export interface ProfitLossReport {
  period: {
    start_date: string;
    end_date: string;
  };
  revenue: {
    total_sales_count: number;
    total_revenue: number;
    average_sale_value: number;
  };
  costs: {
    cost_of_goods_sold: number;
    total_purchases: number;
  };
  profit: {
    gross_profit: number;
    profit_margin_percent: number;
  };
  cash_flow: {
    cash_in: number;
    cash_out: number;
    net_cash_flow: number;
  };
  outstanding: {
    receivables: number;
    payables: number;
  };
  inventory: {
    current_stock_value: number;
    total_products: number;
    low_stock_items: number;
    out_of_stock_items: number;
  };
}

export interface StockVarianceReport {
  variances: Array<{
    product_id: number;
    product_name: string;
    sku: string;
    expected_stock: number;
    actual_stock: number;
    variance: number;
    variance_percent: number;
    variance_value: number;
    severity: string;
  }>;
}

export interface ExpiringProductsReport {
  products: Array<{
    product_id: number;
    product_name: string;
    sku: string;
    batch_number: string;
    expiry_date: string;
    days_until_expiry: number;
    stock_quantity: number;
    stock_value: number;
    urgency: string;
  }>;
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
