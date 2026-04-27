import { useState, useCallback, useMemo } from 'react';
import { Product } from '@/types/ims';
import { CartItem, Customer } from '../types';

export interface UsePosCartReturn {
  // State
  items: CartItem[];
  customer: Customer | null;
  discountPercentage: number;
  discountAmount: number;
  lastScannedProductId: number | null;
  
  // Actions
  addItem: (product: Product) => void;
  incrementQty: (productId: number) => void;
  decrementQty: (productId: number) => void;
  removeItem: (productId: number) => void;
  updateQuantity: (productId: number, qty: number) => void;
  updatePrice: (productId: number, price: number) => void;
  applyLineDiscount: (productId: number, discount: number) => void;
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

export const usePosCart = (): UsePosCartReturn => {
  const [items, setItems] = useState<CartItem[]>([]);
  const [customer, setCustomer] = useState<Customer | null>(null);
  const [discountPercentage, setDiscountPercentage] = useState(0);
  const [discountAmount, setDiscountAmount] = useState(0);
  const [lastScannedProductId, setLastScannedProductId] = useState<number | null>(null);

  /**
   * Add item to cart or increment quantity if already exists
   * This is critical for barcode scanner functionality
   */
  const addItem = useCallback((product: Product) => {
    setItems(prev => {
      const existingIndex = prev.findIndex(item => item.product_id === product.id);
      
      if (existingIndex !== -1) {
        // Increment quantity instead of adding duplicate
        const updated = [...prev];
        updated[existingIndex] = {
          ...updated[existingIndex],
          quantity: updated[existingIndex].quantity + 1,
        };
        setLastScannedProductId(product.id);
        return updated;
      }
      
      // Add new item at top for visibility
      const newItem: CartItem = {
        product_id: product.id,
        product_name: product.name,
        sku: product.sku,
        quantity: 1,
        unit_price: product.price || 0,
        unit_type: product.unit_type || 'piece',
        cost_price: product.cost_price || 0,
        discount: 0,
        stock_available: product.stock_quantity || 0,
      };
      
      setLastScannedProductId(product.id);
      return [newItem, ...prev];
    });
  }, []);

  /**
   * Increment item quantity by 1
   */
  const incrementQty = useCallback((productId: number) => {
    setItems(prev =>
      prev.map(item =>
        item.product_id === productId
          ? { ...item, quantity: item.quantity + 1 }
          : item
      )
    );
  }, []);

  /**
   * Decrement item quantity by 1 (minimum 1)
   */
  const decrementQty = useCallback((productId: number) => {
    setItems(prev =>
      prev.map(item =>
        item.product_id === productId && item.quantity > 1
          ? { ...item, quantity: item.quantity - 1 }
          : item
      )
    );
  }, []);

  /**
   * Remove item from cart completely
   */
  const removeItem = useCallback((productId: number) => {
    setItems(prev => prev.filter(item => item.product_id !== productId));
  }, []);

  /**
   * Update item quantity directly
   */
  const updateQuantity = useCallback((productId: number, qty: number) => {
    if (qty < 1) return;
    
    setItems(prev =>
      prev.map(item =>
        item.product_id === productId
          ? { ...item, quantity: qty }
          : item
      )
    );
  }, []);

  /**
   * Update item price (role-restricted in UI)
   * SaaS Note: Can add permission check here
   */
  const updatePrice = useCallback((productId: number, price: number) => {
    if (price < 0) return;
    
    setItems(prev =>
      prev.map(item =>
        item.product_id === productId
          ? { ...item, unit_price: price }
          : item
      )
    );
  }, []);

  /**
   * Apply line-level discount to specific item
   */
  const applyLineDiscount = useCallback((productId: number, discount: number) => {
    if (discount < 0) return;
    
    setItems(prev =>
      prev.map(item =>
        item.product_id === productId
          ? { ...item, discount }
          : item
      )
    );
  }, []);

  /**
   * Apply global discount to entire cart
   * Supports both percentage and fixed amount
   */
  const applyGlobalDiscount = useCallback((percentage: number, amount: number) => {
    setDiscountPercentage(percentage);
    setDiscountAmount(amount);
  }, []);

  /**
   * Clear cart and reset all state
   */
  const clearCart = useCallback(() => {
    setItems([]);
    setCustomer(null);
    setDiscountPercentage(0);
    setDiscountAmount(0);
    setLastScannedProductId(null);
  }, []);

  /**
   * Calculate subtotal (before global discount)
   * Includes line-level discounts
   */
  const subtotal = useMemo(() => {
    return items.reduce((sum, item) => {
      const lineTotal = item.quantity * item.unit_price;
      const lineNet = lineTotal - item.discount;
      return sum + lineNet;
    }, 0);
  }, [items]);

  /**
   * Calculate global discount amount
   * Priority: fixed amount > percentage
   */
  const globalDiscountAmount = useMemo(() => {
    if (discountAmount > 0) return discountAmount;
    if (discountPercentage > 0) return (subtotal * discountPercentage) / 100;
    return 0;
  }, [subtotal, discountPercentage, discountAmount]);

  /**
   * Calculate grand total (after all discounts)
   */
  const grandTotal = useMemo(() => {
    return Math.max(0, subtotal - globalDiscountAmount);
  }, [subtotal, globalDiscountAmount]);

  /**
   * Calculate total cost of goods
   */
  const totalCost = useMemo(() => {
    return items.reduce((sum, item) => {
      return sum + (item.quantity * item.cost_price);
    }, 0);
  }, [items]);

  /**
   * Calculate total profit (after all discounts)
   */
  const totalProfit = useMemo(() => {
    const itemsProfit = items.reduce((sum, item) => {
      const lineTotal = item.quantity * item.unit_price;
      const lineNet = lineTotal - item.discount;
      const lineCost = item.quantity * item.cost_price;
      return sum + (lineNet - lineCost);
    }, 0);
    
    return itemsProfit - globalDiscountAmount;
  }, [items, globalDiscountAmount]);

  /**
   * Calculate profit margin percentage
   */
  const profitMargin = useMemo(() => {
    if (grandTotal === 0) return 0;
    return (totalProfit / grandTotal) * 100;
  }, [totalProfit, grandTotal]);

  /**
   * Calculate total item count (sum of quantities)
   */
  const itemCount = useMemo(() => {
    return items.reduce((sum, item) => sum + item.quantity, 0);
  }, [items]);

  return {
    // State
    items,
    customer,
    discountPercentage,
    discountAmount,
    lastScannedProductId,
    
    // Actions
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
    
    // Computed
    subtotal,
    globalDiscountAmount,
    grandTotal,
    totalProfit,
    totalCost,
    itemCount,
    profitMargin,
  };
};
