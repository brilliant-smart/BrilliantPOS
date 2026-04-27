import { Product } from '@/types/ims';

// Cart Types
export interface CartItem {
  product_id: number;
  product_name: string;
  sku?: string;
  quantity: number;
  unit_price: number;
  unit_type: string;
  cost_price: number;
  discount: number;
  stock_available: number;
}

export interface Customer {
  id: number;
  name: string;
  phone?: string;
  email?: string;
}

// Payment Types
export type PaymentMethod = 'cash' | 'card' | 'pos' | 'credit' | 'bank_transfer';

export interface Payment {
  method: PaymentMethod;
  amount: number;
  reference?: string;
}

// Discount Types
export type DiscountType = 'percentage' | 'amount';

export interface DiscountConfig {
  type: DiscountType;
  value: number;
  requiresApproval?: boolean;
  maxPercentage?: number;
  maxAmount?: number;
}

// Scanner Types
export interface ScannerConfig {
  debounceMs?: number;
  minBarcodeLength?: number;
  autoFocus?: boolean;
}

export interface SearchResult {
  products: Product[];
  query: string;
  timestamp: number;
}

// POS State Types
export interface POSState {
  cartItems: CartItem[];
  customer: Customer | null;
  discount: {
    percentage: number;
    amount: number;
  };
  payments: Payment[];
  isProcessing: boolean;
  lastScannedProductId: number | null;
}

// Export all types
export type {
  Product,
};
