import { api } from "@/app/lib/api";

export interface InventoryOverview {
  total_products: number;
  total_stock_units: number;
  total_stock_value: number;
  average_stock_per_product: number;
}

export interface StockStatusBreakdown {
  in_stock: number;
  low_stock: number;
  out_of_stock: number;
  total: number;
}

export interface MovementByType {
  count: number;
  total_quantity: number;
}

export interface MovementSummary {
  total_movements: number;
  by_type: {
    purchase: MovementByType;
    sale: MovementByType;
    adjustment: MovementByType;
    damage: MovementByType;
    return: MovementByType;
  };
  net_change: number;
}

export interface TopProductItem {
  product_id: number;
  product_name: string;
  quantity_sold?: number;
  quantity_purchased?: number;
  current_stock: number;
}

export interface TopProducts {
  top_sold: TopProductItem[];
  top_purchased: TopProductItem[];
}

export interface InventoryValue {
  grand_total: number;
}

export interface AlertItem {
  id: number;
  name: string;
  sku?: string | null;
  current_stock?: number;
  threshold?: number;
}

export interface Alerts {
  low_stock_items: AlertItem[];
  out_of_stock_items: AlertItem[];
}

export interface DashboardStats {
  overview: InventoryOverview;
  stock_status: StockStatusBreakdown;
  movement_summary: MovementSummary;
  top_products: TopProducts;
  inventory_value: InventoryValue;
  alerts: Alerts;
}

export interface MovementReportItem {
  id: number;
  product_id: number;
  product_name: string;
  sku: string;
  type: string;
  quantity: number;
  previous_quantity: number;
  new_quantity: number;
  notes: string | null;
  user_name: string;
  created_at: string;
}

export interface MovementReport {
  movements: MovementReportItem[];
  total_count: number;
}

export interface TurnoverProduct {
  product_id: number;
  product_name: string;
  sku?: string | null;
  units_sold: number;
  current_stock: number;
  turnover_rate: number;
  days_of_stock: number;
}

export interface TurnoverRate {
  period_days: number;
  products: TurnoverProduct[];
  average_turnover: number;
}

// API Functions

/**
 * Get dashboard statistics
 */
export const getDashboardStats = async (
  startDate?: string,
  endDate?: string
): Promise<DashboardStats> => {
  const params = new URLSearchParams();
  if (startDate) params.append("start_date", startDate);
  if (endDate) params.append("end_date", endDate);

  const response = await api.get(
    `/inventory/analytics/dashboard?${params.toString()}`
  );
  return response.data;
};

/**
 * Get movement report with filters
 */
export const getMovementReport = async (filters?: {
  start_date?: string;
  end_date?: string;
  type?: string;
  product_id?: number;
  user_id?: number;
  limit?: number;
}): Promise<MovementReport> => {
  const params = new URLSearchParams();
  if (filters?.start_date) params.append("start_date", filters.start_date);
  if (filters?.end_date) params.append("end_date", filters.end_date);
  if (filters?.type) params.append("type", filters.type);
  if (filters?.product_id) params.append("product_id", filters.product_id.toString());
  if (filters?.user_id) params.append("user_id", filters.user_id.toString());
  if (filters?.limit) params.append("limit", filters.limit.toString());

  const response = await api.get(
    `/inventory/analytics/movements?${params.toString()}`
  );
  return response.data;
};

/**
 * Get turnover rate analysis
 */
export const getTurnoverRate = async (days: number = 30): Promise<TurnoverRate> => {
  const response = await api.get(`/inventory/analytics/turnover?days=${days}`);
  return response.data;
};

/**
 * Export report
 */
export const exportReport = async (
  type: "dashboard" | "movements" | "turnover",
  format: "csv" | "pdf",
  startDate?: string,
  endDate?: string
): Promise<Blob> => {
  const params = new URLSearchParams();
  params.append("type", type);
  params.append("format", format);
  if (startDate) params.append("start_date", startDate);
  if (endDate) params.append("end_date", endDate);

  const response = await api.get(
    `/inventory/analytics/export?${params.toString()}`,
    { responseType: 'blob' }
  );
  return response.data;
};
