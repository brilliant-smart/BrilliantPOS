import { api } from "@/app/lib/api";

export interface StockMovement {
  id: number;
  product_id: number;
  user_id: number;
  type: "purchase" | "sale" | "adjustment" | "damage" | "return" | "initial";
  quantity: number;
  previous_stock: number;
  new_stock: number;
  notes: string | null;
  unit_cost: number | null;
  created_at: string;
  updated_at: string;
  user?: {
    id: number;
    name: string;
  };
}

export interface InventorySummary {
  total_products: number;
  in_stock: number;
  low_stock: number;
  out_of_stock: number;
  total_stock_value: number;
}

export interface StockAdjustmentRequest {
  quantity: number;
  type?: "purchase" | "sale" | "adjustment" | "damage" | "return" | "initial";
  notes?: string;
  unit_cost?: number;
}

/**
 * Add stock to a product
 */
export const addStock = async (
  productId: number,
  data: StockAdjustmentRequest,
): Promise<any> => {
  const response = await api.post(
    `/inventory/products/${productId}/add-stock`,
    data,
  );
  return response.data;
};

/**
 * Reduce stock from a product
 */
export const reduceStock = async (
  productId: number,
  data: Omit<StockAdjustmentRequest, "unit_cost">,
): Promise<any> => {
  const response = await api.post(
    `/inventory/products/${productId}/reduce-stock`,
    data,
  );
  return response.data;
};

/**
 * Adjust stock to a specific quantity
 */
export const adjustStock = async (
  productId: number,
  quantity: number,
  notes?: string,
): Promise<any> => {
  const response = await api.post(
    `/inventory/products/${productId}/adjust-stock`,
    {
      quantity,
      notes,
    },
  );
  return response.data;
};

/**
 * Get stock movement history for a product
 */
export const getStockHistory = async (
  productId: number,
): Promise<StockMovement[]> => {
  const response = await api.get(
    `/inventory/products/${productId}/stock-history`,
  );
  return response.data;
};

/**
 * Get low stock products
 */
export const getLowStockProducts = async (): Promise<any[]> => {
  const response = await api.get("/inventory/low-stock");
  return response.data;
};

/**
 * Get out of stock products
 */
export const getOutOfStockProducts = async (): Promise<any[]> => {
  const response = await api.get("/inventory/out-of-stock");
  return response.data;
};

/**
 * Get inventory summary statistics
 */
export const getInventorySummary = async (): Promise<InventorySummary> => {
  const response = await api.get("/inventory/summary");
  return response.data;
};

/**
 * Bulk update stock quantities
 */
export const bulkUpdateStock = async (
  updates: Array<{ product_id: number; quantity: number }>,
  notes?: string,
): Promise<any> => {
  const response = await api.post("/inventory/bulk-update", {
    updates,
    notes,
  });
  return response.data;
};
