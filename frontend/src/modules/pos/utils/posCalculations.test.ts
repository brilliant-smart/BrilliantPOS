import { describe, it, expect } from 'vitest';
import {
  type CartItem,
  calcSubtotal,
  calcGlobalDiscount,
  calcGrandTotal,
  calcTotalCost,
  calcTotalProfit,
  calcProfitMargin,
  calcItemCount,
  calcMaxUnits,
  calcLineTotal,
  calcLineCost,
  calcLineProfit,
} from './posCalculations';

const makeItem = (overrides: Partial<CartItem> = {}): CartItem => ({
  quantity: 1,
  unit_price: 100,
  conversion_factor: 1,
  cost_price: 60,
  discount: 0,
  ...overrides,
});

describe('calcLineTotal', () => {
  it('calculates qty * price - discount', () => {
    expect(calcLineTotal(makeItem({ quantity: 2, unit_price: 500, discount: 200 }))).toBe(800);
  });

  it('with zero discount', () => {
    expect(calcLineTotal(makeItem({ quantity: 2, unit_price: 500 }))).toBe(1000);
  });
});

describe('calcLineCost', () => {
  it('base unit: qty * cf * cost', () => {
    expect(calcLineCost(makeItem({ quantity: 2, conversion_factor: 1, cost_price: 100 }))).toBe(200);
  });

  it('bulk unit: qty * cf * cost', () => {
    expect(calcLineCost(makeItem({ quantity: 1, conversion_factor: 12, cost_price: 100 }))).toBe(1200);
  });
});

describe('calcLineProfit', () => {
  it('revenue minus cost', () => {
    // qty=1, price=500, discount=0, cost=300 => profit=200
    expect(calcLineProfit(makeItem({ quantity: 1, unit_price: 500, cost_price: 300 }))).toBe(200);
  });
});

describe('calcSubtotal', () => {
  it('empty cart returns 0', () => {
    expect(calcSubtotal([])).toBe(0);
  });

  it('single item', () => {
    expect(calcSubtotal([makeItem({ quantity: 2, unit_price: 500 })])).toBe(1000);
  });

  it('with line discount', () => {
    expect(calcSubtotal([makeItem({ quantity: 2, unit_price: 500, discount: 200 })])).toBe(800);
  });

  it('multiple items sum correctly', () => {
    const items = [
      makeItem({ quantity: 2, unit_price: 500 }),
      makeItem({ quantity: 1, unit_price: 2000 }),
    ];
    expect(calcSubtotal(items)).toBe(3000);
  });

  it('decimal prices', () => {
    expect(calcSubtotal([makeItem({ quantity: 1, unit_price: 999.99 })])).toBeCloseTo(999.99, 2);
  });
});

describe('calcGlobalDiscount', () => {
  it('both zero returns 0', () => {
    expect(calcGlobalDiscount(1000, 0, 0)).toBe(0);
  });

  it('percentage only', () => {
    expect(calcGlobalDiscount(1000, 0, 10)).toBe(100);
  });

  it('flat amount takes priority over percentage', () => {
    expect(calcGlobalDiscount(1000, 200, 5)).toBe(200);
  });

  it('100% discount', () => {
    expect(calcGlobalDiscount(1000, 0, 100)).toBe(1000);
  });

  it('decimal subtotal with percentage', () => {
    expect(calcGlobalDiscount(333.33, 0, 10)).toBeCloseTo(33.333, 2);
  });
});

describe('calcGrandTotal', () => {
  it('normal: subtotal - discount', () => {
    expect(calcGrandTotal(1000, 200)).toBe(800);
  });

  it('discount exceeds subtotal clamps to 0', () => {
    expect(calcGrandTotal(500, 600)).toBe(0);
  });

  it('exact match', () => {
    expect(calcGrandTotal(500, 500)).toBe(0);
  });
});

describe('calcTotalCost', () => {
  it('base units', () => {
    expect(calcTotalCost([makeItem({ quantity: 2, conversion_factor: 1, cost_price: 100 })])).toBe(200);
  });

  it('bulk unit', () => {
    expect(calcTotalCost([makeItem({ quantity: 1, conversion_factor: 12, cost_price: 100 })])).toBe(1200);
  });

  it('mixed items', () => {
    const items = [
      makeItem({ quantity: 2, conversion_factor: 1, cost_price: 100 }),
      makeItem({ quantity: 1, conversion_factor: 12, cost_price: 50 }),
    ];
    expect(calcTotalCost(items)).toBe(200 + 600);
  });
});

describe('calcTotalProfit', () => {
  it('simple: no discounts', () => {
    // revenue=500, cost=300, profit=200
    expect(calcTotalProfit([makeItem({ quantity: 1, unit_price: 500, cost_price: 300 })], 0)).toBe(200);
  });

  it('with line discount', () => {
    // revenue=500-50=450, cost=300, profit=150
    expect(calcTotalProfit([makeItem({ quantity: 1, unit_price: 500, discount: 50, cost_price: 300 })], 0)).toBe(150);
  });

  it('with global discount', () => {
    // item profit=200, global=50, total=150
    expect(calcTotalProfit([makeItem({ quantity: 1, unit_price: 500, cost_price: 300 })], 50)).toBe(150);
  });

  it('negative profit (loss)', () => {
    // revenue=200, cost=300, profit=-100
    expect(calcTotalProfit([makeItem({ quantity: 1, unit_price: 200, cost_price: 300 })], 0)).toBe(-100);
  });

  it('bulk unit', () => {
    // qty=1 carton(cf=12), price=5000, cost=300/pc => revenue=5000, cost=3600, profit=1400
    expect(calcTotalProfit([makeItem({ quantity: 1, conversion_factor: 12, unit_price: 5000, cost_price: 300 })], 0)).toBe(1400);
  });

  it('global discount exceeds item profit', () => {
    // item profit=50, global=100, total=-50
    expect(calcTotalProfit([makeItem({ quantity: 1, unit_price: 500, discount: 0, cost_price: 450 })], 100)).toBe(-50);
  });
});

describe('calcProfitMargin', () => {
  it('20%', () => {
    expect(calcProfitMargin(1000, 200)).toBeCloseTo(20.0, 1);
  });

  it('zero grandTotal returns 0', () => {
    expect(calcProfitMargin(0, 100)).toBe(0);
  });

  it('negative margin', () => {
    expect(calcProfitMargin(1000, -100)).toBeCloseTo(-10.0, 1);
  });
});

describe('calcItemCount', () => {
  it('empty cart', () => {
    expect(calcItemCount([])).toBe(0);
  });

  it('sums quantities', () => {
    expect(calcItemCount([makeItem({ quantity: 3 }), makeItem({ quantity: 2 })])).toBe(5);
  });
});

describe('calcMaxUnits', () => {
  it('exact division', () => {
    expect(calcMaxUnits(100, 1)).toBe(100);
  });

  it('bulk unit floors', () => {
    expect(calcMaxUnits(100, 12)).toBe(8);
  });

  it('insufficient stock for bulk', () => {
    expect(calcMaxUnits(3, 12)).toBe(0);
  });

  it('zero stock', () => {
    expect(calcMaxUnits(0, 1)).toBe(0);
  });
});