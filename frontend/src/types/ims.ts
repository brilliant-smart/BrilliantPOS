// IMS Type Definitions

export interface ProductBarcode {
  id: number;
  product_id: number;
  product_unit_type_id?: number | null;
  barcode: string;
  created_at: string;
  updated_at: string;
}

export interface ProductUnitType {
  id: number;
  product_id: number;
  name: string;
  short_name: string;
  conversion_factor: number;
  selling_price: number;
  is_base: boolean;
  sort_order: number;
  barcodes?: ProductBarcode[];
  created_at: string;
  updated_at: string;
}

export interface Product {
  id: number;
  name: string;
  slug: string;
  sku?: string | null;
  description?: string | null;
  price: number;
  cost_price?: number;
  last_purchase_price?: number;
  stock_quantity: number;
  unit_type?: string;
  low_stock_threshold: number;
  image_url?: string | null;
  image_full_url?: string | null;
  is_active: boolean;
  is_featured: boolean;
  track_batch?: boolean;
  track_expiry?: boolean;
  batch_number?: string | null;
  expiry_date?: string | null;
  manufacturing_date?: string | null;
  reorder_point?: number;
  max_stock_level?: number;
  stock_status: 'in_stock' | 'low_stock' | 'out_of_stock';
  barcodes?: ProductBarcode[];
  unit_types?: ProductUnitType[];
  created_at: string;
  updated_at: string;
  deleted_at?: string | null;
}

export interface Supplier {
  id: number;
  code: string;
  name: string;
  contact_person: string | null;
  email: string | null;
  phone: string | null;
  address: string | null;
  payment_terms: 'cash' | 'net_15' | 'net_30' | 'net_60' | 'net_90';
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface PurchaseOrder {
  id: number;
  po_number: string;
  supplier_id: number;
  supplier?: Supplier;
  order_date: string;
  expected_delivery_date: string | null;
  status: 'draft' | 'pending' | 'approved' | 'received' | 'cancelled' | 'completed';
  subtotal: string;
  tax_amount: string;
  total_amount: string;
  payment_status: 'unpaid' | 'partial' | 'paid';
  payment_due_date: string | null;
  notes: string | null;
  approved_by: number | null;
  approved_at: string | null;
  received_by: number | null;
  received_at: string | null;
  created_by: number;
  created_at: string;
  updated_at: string;
  items?: PurchaseOrderItem[];
}

export interface PurchaseOrderItem {
  id: number;
  purchase_order_id: number;
  product_id: number;
  product?: any;
  product_unit_type_id?: number | null;
  quantity_ordered: number;
  quantity_received: number;
  unit_type: 'piece' | 'carton' | 'box' | 'pack' | 'dozen' | 'kg' | 'liter' | 'meter';
  conversion_factor?: number;
  unit_price: string;
  unit_cost: string;
  tax_rate: string;
  total_price: string;
  total_cost: string;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface Sale {
  id: number;
  sale_number: string;
  sale_date: string;
  customer_name: string | null;
  customer_phone: string | null;
  subtotal: string;
  vat_amount: string;
  discount_percentage: string;
  discount_amount: string;
  total_amount: string;
  sale_type: 'cash' | 'credit' | 'online' | 'pos';
  payment_status: 'paid' | 'partially_paid' | 'unpaid';
  amount_paid: string;
  amount_due: string;
  cost_of_goods_sold: string;
  gross_profit: string;
  profit_margin: string;
  notes: string | null;
  cashier_id: number;
  cashier?: { id: number; name: string };
  status?: 'completed' | 'voided';
  voided_by?: number | null;
  void_reason?: string | null;
  voided_at?: string | null;
  created_at: string;
  updated_at: string;
  items?: SaleItem[];
  payments?: Payment[];
}

export interface Payment {
  id: number;
  sale_id: number;
  method: string;
  amount: number;
  reference: string | null;
  notes: string | null;
  created_at: string;
}

export interface SaleItem {
  id: number;
  sale_id: number;
  product_id: number;
  product_unit_type_id?: number | null;
  product?: any;
  unitType?: any;
  quantity: number;
  unit_type: string;
  conversion_factor?: number;
  unit_price: string;
  unit_cost: string;
  discount_percent: string;
  line_total: string;
  line_cost: string;
  line_profit: string;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

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

export interface StockVariance {
  product_id: number;
  product_name: string;
  sku: string;
  expected_stock: number;
  actual_stock: number;
  variance: number;
  variance_percent: number;
  variance_value: number;
  severity: string;
}

export interface ExpiringProduct {
  id: number;
  name: string;
  sku: string;
  batch_number: string | null;
  expiry_date: string;
  days_until_expiry: number;
  stock_quantity: number;
  stock_value: number;
  urgency: string;
}
