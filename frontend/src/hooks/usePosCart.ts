import { useState, useCallback, useMemo, useEffect } from 'react';
import { Product, ProductUnitType } from '@/types/ims';
import { toast } from 'sonner';
import {
  calcSubtotal,
  calcGlobalDiscount,
  calcGrandTotal,
  calcTotalCost,
  calcTotalProfit,
  calcProfitMargin,
  calcItemCount,
  calcMaxUnits,
} from '@/modules/pos/utils/posCalculations';

const POS_CART_KEY = 'brilliant_pos_cart';
const POS_CUSTOMER_KEY = 'brilliant_pos_customer';
const POS_DISCOUNT_KEY = 'brilliant_pos_discount';

export interface CartItem {
  product_id: number;
  product_name: string;
  sku?: string;
  quantity: number;
  unit_price: number;
  unit_type: string;
  unit_type_id?: number | null;
  conversion_factor: number;
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

export interface UsePosCartReturn {
  // State
  items: CartItem[];
  customer: Customer | null;
  discountPercentage: number;
  discountAmount: number;
  lastScannedProductId: number | null;

  // Actions
  addItem: (product: Product, unitType?: ProductUnitType | null) => void;
  incrementQty: (cartKey: string) => void;
  decrementQty: (cartKey: string) => void;
  removeItem: (cartKey: string) => void;
  updateQuantity: (cartKey: string, qty: number) => void;
  updatePrice: (cartKey: string, price: number, userRole?: string) => void;
  applyLineDiscount: (cartKey: string, discount: number) => void;
  applyGlobalDiscount: (percentage: number, amount: number) => void;
  setCustomer: (customer: Customer | null) => void;
  clearCart: () => void;

  // Computed (memoized)
  subtotal: number;
  globalDiscountAmount: number;
  grandTotal: number;
  totalProfit: number;
  totalCost: number;
  itemCount: number;
  profitMargin: number;
}

// Generate a unique cart key for each product + unit type combination
function getCartKey(productId: number, unitTypeId: number | null | undefined): string {
  return `${productId}_${unitTypeId ?? 'base'}`;
}

export const usePosCart = (): UsePosCartReturn => {
  const [items, setItems] = useState<CartItem[]>(() => {
    try {
      const saved = localStorage.getItem(POS_CART_KEY);
      return saved ? JSON.parse(saved) : [];
    } catch { return []; }
  });
  const [customer, setCustomer] = useState<Customer | null>(() => {
    try {
      const saved = localStorage.getItem(POS_CUSTOMER_KEY);
      return saved ? JSON.parse(saved) : null;
    } catch { return null; }
  });
  const [discountPercentage, setDiscountPercentage] = useState<number>(() => {
    try {
      const saved = localStorage.getItem(POS_DISCOUNT_KEY);
      return saved ? JSON.parse(saved).percentage : 0;
    } catch { return 0; }
  });
  const [discountAmount, setDiscountAmount] = useState<number>(() => {
    try {
      const saved = localStorage.getItem(POS_DISCOUNT_KEY);
      return saved ? JSON.parse(saved).amount : 0;
    } catch { return 0; }
  });
  const [lastScannedProductId, setLastScannedProductId] = useState<number | null>(null);

  // Persist cart to localStorage on changes
  useEffect(() => {
    localStorage.setItem(POS_CART_KEY, JSON.stringify(items));
  }, [items]);

  useEffect(() => {
    localStorage.setItem(POS_CUSTOMER_KEY, JSON.stringify(customer));
  }, [customer]);

  useEffect(() => {
    localStorage.setItem(POS_DISCOUNT_KEY, JSON.stringify({ percentage: discountPercentage, amount: discountAmount }));
  }, [discountPercentage, discountAmount]);

  // Add item or increment quantity if already exists (CRITICAL for scanner)
  const addItem = useCallback((product: Product, unitType?: ProductUnitType | null) => {
    const stockAvailable = product.stock_quantity || 0;
    const unitTypeId = unitType?.id ?? null;
    const conversionFactor = unitType?.conversion_factor ?? 1;
    const unitPrice = unitType?.selling_price ?? product.price ?? 0;
    const unitTypeName = unitType?.name ?? product.unit_type ?? 'piece';
    const cartKey = getCartKey(product.id, unitTypeId);

    // Calculate max units available for this unit type
    const maxUnits = calcMaxUnits(stockAvailable, conversionFactor);

    // Block out-of-stock products entirely
    if (maxUnits <= 0) {
      toast.error(`${product.name} is out of stock${conversionFactor > 1 ? ` (need ${conversionFactor} pcs per ${unitTypeName})` : ''}`);
      return;
    }

    setItems(prev => {
      const existingIndex = prev.findIndex(item => getCartKey(item.product_id, item.unit_type_id) === cartKey);

      if (existingIndex !== -1) {
        const existing = prev[existingIndex];
        const newQuantity = existing.quantity + 1;

        // Cap at available stock in this unit type
        if (newQuantity > maxUnits) {
          toast.error(`Maximum available stock reached (${maxUnits} ${unitTypeName})`);
          const updated = [...prev];
          updated[existingIndex] = {
            ...existing,
            quantity: maxUnits,
          };
          setLastScannedProductId(product.id);
          return updated;
        }

        const updated = [...prev];
        updated[existingIndex] = {
          ...updated[existingIndex],
          quantity: newQuantity,
        };
        setLastScannedProductId(product.id);
        return updated;
      }

      // Add new item
      const newItem: CartItem = {
        product_id: product.id,
        product_name: product.name,
        sku: product.sku,
        quantity: 1,
        unit_price: unitPrice,
        unit_type: unitTypeName,
        unit_type_id: unitTypeId,
        conversion_factor: conversionFactor,
        cost_price: product.cost_price || 0,
        discount: 0,
        stock_available: stockAvailable,
      };

      setLastScannedProductId(product.id);
      return [newItem, ...prev]; // Add to top for visibility
    });
  }, []);

  // Increment quantity
  const incrementQty = useCallback((cartKey: string) => {
    setItems(prev => {
      const item = prev.find(i => getCartKey(i.product_id, i.unit_type_id) === cartKey);
      if (!item) return prev;

      const maxUnits = calcMaxUnits(item.stock_available, item.conversion_factor);
      if (item.quantity + 1 > maxUnits) {
        toast.error(`Maximum available stock reached (${maxUnits} ${item.unit_type})`);
        return prev;
      }

      return prev.map(i =>
        getCartKey(i.product_id, i.unit_type_id) === cartKey
          ? { ...i, quantity: i.quantity + 1 }
          : i
      );
    });
  }, []);

  // Decrement quantity
  const decrementQty = useCallback((cartKey: string) => {
    setItems(prev =>
      prev.map(item =>
        getCartKey(item.product_id, item.unit_type_id) === cartKey && item.quantity > 1
          ? { ...item, quantity: item.quantity - 1 }
          : item
      )
    );
  }, []);

  // Remove item
  const removeItem = useCallback((cartKey: string) => {
    setItems(prev => prev.filter(item => getCartKey(item.product_id, item.unit_type_id) !== cartKey));
  }, []);

  // Update quantity directly
  const updateQuantity = useCallback((cartKey: string, qty: number) => {
    if (qty < 1) return;

    setItems(prev =>
      prev.map(item => {
        if (getCartKey(item.product_id, item.unit_type_id) !== cartKey) return item;

        const maxUnits = calcMaxUnits(item.stock_available, item.conversion_factor);
        if (qty > maxUnits) {
          toast.error(`Quantity capped at available stock (${maxUnits} ${item.unit_type})`);
          return { ...item, quantity: maxUnits };
        }

        return { ...item, quantity: qty };
      })
    );
  }, []);

  // Update price (role-restricted — only owner/manager can modify prices)
  // Note: backend enforces this server-side too; this is a UX guard only.
  const updatePrice = useCallback((cartKey: string, price: number, userRole?: string) => {
    if (price < 0) return;

    const role = userRole ?? (() => {
      try {
        const u = JSON.parse(localStorage.getItem('brilliant_pos_user') || '{}');
        return u?.role;
      } catch { return undefined; }
    })();

    if (role && !['owner', 'manager'].includes(role)) {
      toast.error('Only managers can modify prices');
      return;
    }

    setItems(prev =>
      prev.map(item =>
        getCartKey(item.product_id, item.unit_type_id) === cartKey
          ? { ...item, unit_price: price }
          : item
      )
    );
  }, []);

  // Apply line-level discount
  const applyLineDiscount = useCallback((cartKey: string, discount: number) => {
    if (discount < 0) return;

    setItems(prev =>
      prev.map(item =>
        getCartKey(item.product_id, item.unit_type_id) === cartKey
          ? { ...item, discount }
          : item
      )
    );
  }, []);

  // Apply global discount
  const applyGlobalDiscount = useCallback((percentage: number, amount: number) => {
    setDiscountPercentage(percentage);
    setDiscountAmount(amount);
  }, []);

  // Clear cart
  const clearCart = useCallback(() => {
    setItems([]);
    setCustomer(null);
    setDiscountPercentage(0);
    setDiscountAmount(0);
    setLastScannedProductId(null);
    localStorage.removeItem(POS_CART_KEY);
    localStorage.removeItem(POS_CUSTOMER_KEY);
    localStorage.removeItem(POS_DISCOUNT_KEY);
  }, []);

  // Memoized calculations
  const subtotal = useMemo(() => calcSubtotal(items), [items]);

  const globalDiscountAmount = useMemo(() => {
    return calcGlobalDiscount(subtotal, discountAmount, discountPercentage);
  }, [subtotal, discountPercentage, discountAmount]);

  const grandTotal = useMemo(() => {
    return calcGrandTotal(subtotal, globalDiscountAmount);
  }, [subtotal, globalDiscountAmount]);

  // Cost is always per-piece, so multiply by conversion factor for unit type cost
  const totalCost = useMemo(() => calcTotalCost(items), [items]);

  const totalProfit = useMemo(() => calcTotalProfit(items, globalDiscountAmount), [items, globalDiscountAmount]);

  const profitMargin = useMemo(() => calcProfitMargin(grandTotal, totalProfit), [totalProfit, grandTotal]);

  const itemCount = useMemo(() => calcItemCount(items), [items]);

  return {
    items,
    customer,
    discountPercentage,
    discountAmount,
    lastScannedProductId,

    addItem,
    incrementQty,
    decrementQty,
    removeItem,
    updateQuantity,
    updatePrice,
    applyLineDiscount,
    applyGlobalDiscount,
    setCustomer,
    clearCart,

    subtotal,
    globalDiscountAmount,
    grandTotal,
    totalProfit,
    totalCost,
    itemCount,
    profitMargin,
  };
};
