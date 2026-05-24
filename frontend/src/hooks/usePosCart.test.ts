import { describe, it, expect, beforeEach, vi } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { usePosCart } from './usePosCart';

// Mock localStorage
const store: Record<string, string> = {};
const localStorageMock = {
  getItem: vi.fn((key: string) => store[key] ?? null),
  setItem: vi.fn((key: string, value: string) => { store[key] = value; }),
  removeItem: vi.fn((key: string) => { delete store[key]; }),
  clear: vi.fn(() => { Object.keys(store).forEach(k => delete store[k]); }),
  get length() { return Object.keys(store).length; },
  key: vi.fn((index: number) => Object.keys(store)[index] ?? null),
};
Object.defineProperty(window, 'localStorage', { value: localStorageMock });

const mockProduct = {
  id: 1,
  name: 'Test Product',
  sku: 'SKU-001',
  price: 100,
  cost_price: 60,
  stock_quantity: 50,
  unit_type: 'piece',
};

const mockCartonProduct = {
  id: 2,
  name: 'Carton Product',
  sku: 'SKU-002',
  price: 1200,
  cost_price: 50,
  stock_quantity: 24,
  unit_type: 'carton',
};

// Cart key format: productId_unitTypeId (null becomes 'base')
function cartKey(productId: number, unitTypeId: string | null = 'base') {
  return `${productId}_${unitTypeId}`;
}

describe('usePosCart', () => {
  beforeEach(() => {
    localStorageMock.clear();
    vi.clearAllMocks();
  });

  // ---- Adding items ----

  it('adds a product to an empty cart', () => {
    const { result } = renderHook(() => usePosCart());

    act(() => {
      result.current.addItem(mockProduct);
    });

    expect(result.current.items).toHaveLength(1);
    expect(result.current.items[0].product_id).toBe(1);
    expect(result.current.items[0].quantity).toBe(1);
    expect(result.current.items[0].unit_price).toBe(100);
  });

  it('increments quantity when adding the same product again', () => {
    const { result } = renderHook(() => usePosCart());

    act(() => {
      result.current.addItem(mockProduct);
      result.current.addItem(mockProduct);
    });

    expect(result.current.items).toHaveLength(1);
    expect(result.current.items[0].quantity).toBe(2);
  });

  it('respects stock limit when adding items', () => {
    const { result } = renderHook(() => usePosCart());

    act(() => {
      const lowStock = { ...mockProduct, stock_quantity: 2, id: 99 };
      result.current.addItem(lowStock);
      result.current.addItem(lowStock);
      result.current.addItem(lowStock);
    });

    // Should be capped at max units (2 stock / 1 conversion = 2 max)
    const item = result.current.items.find(i => i.product_id === 99);
    expect(item?.quantity).toBeLessThanOrEqual(2);
  });

  it('blocks adding out-of-stock product', () => {
    const { result } = renderHook(() => usePosCart());

    act(() => {
      result.current.addItem({ ...mockProduct, stock_quantity: 0 });
    });

    expect(result.current.items).toHaveLength(0);
  });

  // ---- Removing items ----

  it('removes an item from the cart', () => {
    const { result } = renderHook(() => usePosCart());

    act(() => {
      result.current.addItem(mockProduct);
    });
    expect(result.current.items).toHaveLength(1);

    act(() => {
      result.current.removeItem(cartKey(1));
    });
    expect(result.current.items).toHaveLength(0);
  });

  // ---- Quantity updates ----

  it('increments quantity', () => {
    const { result } = renderHook(() => usePosCart());

    act(() => {
      result.current.addItem(mockProduct);
      result.current.incrementQty(cartKey(1));
    });

    expect(result.current.items[0].quantity).toBe(2);
  });

  it('decrements quantity but keeps item at min 1', () => {
    const { result } = renderHook(() => usePosCart());

    act(() => {
      result.current.addItem(mockProduct);
      result.current.decrementQty(cartKey(1));
    });

    // decrementQty keeps item at 1, doesn't remove
    expect(result.current.items).toHaveLength(1);
    expect(result.current.items[0].quantity).toBe(1);
  });

  it('decrements from 2 to 1', () => {
    const { result } = renderHook(() => usePosCart());

    act(() => {
      result.current.addItem(mockProduct);
      result.current.addItem(mockProduct);
      result.current.decrementQty(cartKey(1));
    });

    expect(result.current.items).toHaveLength(1);
    expect(result.current.items[0].quantity).toBe(1);
  });

  it('updateQuantity sets exact quantity', () => {
    const { result } = renderHook(() => usePosCart());

    act(() => {
      result.current.addItem(mockProduct);
      result.current.updateQuantity(cartKey(1), 5);
    });

    expect(result.current.items[0].quantity).toBe(5);
  });

  it('updateQuantity rejects values less than 1', () => {
    const { result } = renderHook(() => usePosCart());

    act(() => {
      result.current.addItem(mockProduct);
      result.current.updateQuantity(cartKey(1), 0);
    });

    expect(result.current.items[0].quantity).toBe(1);
  });

  // ---- Discounts ----

  it('applies percentage global discount', () => {
    const { result } = renderHook(() => usePosCart());

    act(() => {
      result.current.addItem(mockProduct);
      result.current.applyGlobalDiscount(10, 0); // (percentage, amount)
    });

    expect(result.current.discountPercentage).toBe(10);
    expect(result.current.discountAmount).toBe(0);
  });

  it('applies flat amount global discount', () => {
    const { result } = renderHook(() => usePosCart());

    act(() => {
      result.current.addItem(mockProduct);
      result.current.applyGlobalDiscount(0, 20); // (percentage, amount)
    });

    expect(result.current.discountPercentage).toBe(0);
    expect(result.current.discountAmount).toBe(20);
  });

  it('applies line discount to a specific item', () => {
    const { result } = renderHook(() => usePosCart());

    act(() => {
      result.current.addItem(mockProduct);
    });

    act(() => {
      result.current.applyLineDiscount(cartKey(1), 15);
    });

    const item = result.current.items.find(i => i.product_id === 1);
    expect(item?.discount).toBe(15);
  });

  // ---- Price updates ----

  it('allows price update when role is owner', () => {
    // Set user role in localStorage
    store['brilliant_pos_user'] = JSON.stringify({ role: 'owner' });

    const { result } = renderHook(() => usePosCart());

    act(() => {
      result.current.addItem(mockProduct);
    });

    act(() => {
      result.current.updatePrice(cartKey(1), 80);
    });

    const item = result.current.items.find(i => i.product_id === 1);
    expect(item?.unit_price).toBe(80);
  });

  it('blocks price update for cashier role', () => {
    // Set user role in localStorage
    store['brilliant_pos_user'] = JSON.stringify({ role: 'cashier' });

    const { result } = renderHook(() => usePosCart());

    act(() => {
      result.current.addItem(mockProduct);
    });

    act(() => {
      result.current.updatePrice(cartKey(1), 80);
    });

    const item = result.current.items.find(i => i.product_id === 1);
    expect(item?.unit_price).toBe(100); // Price unchanged
  });

  it('allows price update via explicit role parameter', () => {
    const { result } = renderHook(() => usePosCart());

    act(() => {
      result.current.addItem(mockProduct);
    });

    act(() => {
      result.current.updatePrice(cartKey(1), 80, 'manager');
    });

    const item = result.current.items.find(i => i.product_id === 1);
    expect(item?.unit_price).toBe(80);
  });

  // ---- Clear cart ----

  it('clears all items from cart', () => {
    const { result } = renderHook(() => usePosCart());

    act(() => {
      result.current.addItem(mockProduct);
      result.current.addItem(mockCartonProduct);
    });
    expect(result.current.items).toHaveLength(2);

    act(() => {
      result.current.clearCart();
    });
    expect(result.current.items).toHaveLength(0);
    expect(result.current.discountPercentage).toBe(0);
    expect(result.current.discountAmount).toBe(0);
  });

  // ---- Computed values ----

  it('calculates correct subtotal', () => {
    const { result } = renderHook(() => usePosCart());

    act(() => {
      result.current.addItem(mockProduct); // 1 * 100 = 100
    });

    expect(result.current.subtotal).toBe(100);
  });

  it('calculates item count correctly', () => {
    const { result } = renderHook(() => usePosCart());

    act(() => {
      result.current.addItem(mockProduct);
      result.current.addItem(mockProduct); // qty = 2
    });

    expect(result.current.itemCount).toBe(2);
  });

  // ---- localStorage persistence ----

  it('persists cart to localStorage', () => {
    const { result } = renderHook(() => usePosCart());

    act(() => {
      result.current.addItem(mockProduct);
    });

    expect(localStorageMock.setItem).toHaveBeenCalledWith(
      'brilliant_pos_cart',
      expect.any(String)
    );
  });
});