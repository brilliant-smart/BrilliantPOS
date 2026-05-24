import { describe, it, expect, beforeEach, vi } from 'vitest';
import {
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
import {
  calcTotalPaid,
  calcBalance,
  calcChange,
  canCompleteSale,
  type Payment,
} from './posCalculationsPayment';

// ============================================================
// EDGE CASE & REGRESSION TESTS FOR POS CALCULATIONS
// ============================================================

describe('POS Calculations - Edge Cases & Regression Tests', () => {
  // ---- Floating Point Precision Edge Cases ----

  describe('floating point precision', () => {
    it('handles 0.1 + 0.2 precision issue in subtotal', () => {
      const items = [
        { quantity: 1, unit_price: 0.1, conversion_factor: 1, cost_price: 0, discount: 0 },
        { quantity: 1, unit_price: 0.2, conversion_factor: 1, cost_price: 0, discount: 0 },
      ];
      const result = calcSubtotal(items);
      expect(result).toBeCloseTo(0.3, 10);
    });

    it('handles 999.99 * 3 precision', () => {
      const items = [
        { quantity: 3, unit_price: 999.99, conversion_factor: 1, cost_price: 0, discount: 0 },
      ];
      const result = calcSubtotal(items);
      expect(result).toBeCloseTo(2999.97, 2);
    });

    it('handles percentage discount on decimal subtotal', () => {
      // 333.33 * 10% = 33.333, should round properly
      const result = calcGlobalDiscount(333.33, 0, 10);
      expect(result).toBeCloseTo(33.333, 2);
    });

    it('handles large quantities without overflow', () => {
      const items = [
        { quantity: 999999, unit_price: 9999.99, conversion_factor: 1, cost_price: 0, discount: 0 },
      ];
      const result = calcSubtotal(items);
      expect(result).toBeCloseTo(999999 * 9999.99, 0);
    });
  });

  // ---- Discount Edge Cases ----

  describe('discount edge cases', () => {
    it('handles 100% global discount', () => {
      expect(calcGlobalDiscount(1000, 0, 100)).toBe(1000);
      expect(calcGrandTotal(1000, 1000)).toBe(0);
    });

    it('handles global discount amount equal to subtotal', () => {
      expect(calcGlobalDiscount(500, 500, 0)).toBe(500);
      expect(calcGrandTotal(500, 500)).toBe(0);
    });

    it('handles global discount exceeding subtotal (clamped)', () => {
      expect(calcGlobalDiscount(300, 500, 0)).toBe(500);
      expect(calcGrandTotal(300, 500)).toBe(0);
    });

    it('handles zero subtotal with discount', () => {
      expect(calcGlobalDiscount(0, 0, 10)).toBe(0);
      expect(calcGrandTotal(0, 0)).toBe(0);
    });

    it('flat amount takes priority over percentage when both set', () => {
      const result = calcGlobalDiscount(1000, 50, 10);
      // 50 flat takes priority, not 100 (10%)
      expect(result).toBe(50);
    });
  });

  // ---- Unit Conversion Edge Cases ----

  describe('unit conversion edge cases', () => {
    it('calcMaxUnits returns 0 when stock is 0', () => {
      expect(calcMaxUnits(0, 1)).toBe(0);
      expect(calcMaxUnits(0, 12)).toBe(0);
    });

    it('calcMaxUnits floors correctly for fractional results', () => {
      // 10 stock, 3 per unit = 3 units (9 used, 1 leftover)
      expect(calcMaxUnits(10, 3)).toBe(3);
      // 11 stock, 3 per unit = 3 units (9 used, 2 leftover)
      expect(calcMaxUnits(11, 3)).toBe(3);
      // 12 stock, 3 per unit = 4 units (exact)
      expect(calcMaxUnits(12, 3)).toBe(4);
    });

    it('calcMaxUnits with conversion factor 1 returns stock directly', () => {
      expect(calcMaxUnits(50, 1)).toBe(50);
      expect(calcMaxUnits(1, 1)).toBe(1);
    });

    it('calcLineCost correctly multiplies by conversion factor', () => {
      // 2 cartons at cost 50/piece, conversion factor 12
      // cost = 2 * 12 * 50 = 1200
      expect(calcLineCost({ quantity: 2, conversion_factor: 12, cost_price: 50 })).toBe(1200);
    });

    it('calcLineTotal with discount on bulk unit', () => {
      // 1 carton at 1200/piece, discount 100 (flat)
      // total = 1 * 1200 - 100 = 1100
      expect(calcLineTotal({ quantity: 1, unit_price: 1200, discount: 100 })).toBe(1100);
    });

    it('calcTotalCost with mixed unit types', () => {
      const items = [
        { quantity: 2, conversion_factor: 1, cost_price: 50 },   // 2 * 1 * 50 = 100
        { quantity: 3, conversion_factor: 12, cost_price: 30 },    // 3 * 12 * 30 = 1080
        { quantity: 5, conversion_factor: 1, cost_price: 10 },    // 5 * 1 * 10 = 50
      ];
      expect(calcTotalCost(items)).toBe(1230);
    });
  });

  // ---- Profit Calculation Edge Cases ----

  describe('profit calculation edge cases', () => {
    it('handles negative profit (loss)', () => {
      // Selling below cost
      const items = [
        { quantity: 1, unit_price: 50, conversion_factor: 1, cost_price: 100, discount: 0 },
      ];
      expect(calcTotalProfit(items, 0)).toBe(-50);
    });

    it('handles zero revenue with global discount equaling subtotal', () => {
      const items = [
        { quantity: 1, unit_price: 100, conversion_factor: 1, cost_price: 60, discount: 0 },
      ];
      // revenue = 100, global discount = 100, profit = 100 - 100 - 60 = -60
      expect(calcTotalProfit(items, 100)).toBe(-60);
    });

    it('calcProfitMargin returns 0 when grandTotal is 0', () => {
      expect(calcProfitMargin(0, 100)).toBe(0);
      expect(calcProfitMargin(0, -50)).toBe(0);
    });

    it('calcProfitMargin handles negative margin', () => {
      // total = 100, profit = -20, margin = -20%
      expect(calcProfitMargin(100, -20)).toBeCloseTo(-20, 1);
    });
  });

  // ---- Payment Edge Cases ----

  describe('payment edge cases', () => {
    it('canCompleteSale with credit and partial cash', () => {
      // Total 1000, paid 300 cash + 700 credit
      expect(canCompleteSale(1000, 300, true)).toBe(true);
    });

    it('canCompleteSale with credit and zero cash', () => {
      expect(canCompleteSale(1000, 0, true)).toBe(true);
    });

    it('canCompleteSale fails without credit when underpaid', () => {
      expect(canCompleteSale(1000, 500, false)).toBe(false);
    });

    it('canCompleteSale with exact payment', () => {
      expect(canCompleteSale(1000, 1000, false)).toBe(true);
    });

    it('canCompleteSale with overpayment', () => {
      expect(canCompleteSale(1000, 1500, false)).toBe(true);
    });

    it('calcBalance clamps to 0 on overpayment', () => {
      expect(calcBalance(1000, 1500)).toBe(0);
    });

    it('calcChange returns overpayment amount', () => {
      expect(calcChange(1000, 1500)).toBe(500);
    });

    it('calcChange returns 0 when underpaid', () => {
      expect(calcChange(1000, 500)).toBe(0);
    });

    it('handles empty payments array', () => {
      expect(calcTotalPaid([])).toBe(0);
      expect(calcBalance(1000, 0)).toBe(1000);
    });

    it('handles multiple payment methods summing correctly', () => {
      const payments: Payment[] = [
        { method: 'cash', amount: 300 },
        { method: 'card', amount: 500 },
        { method: 'bank_transfer', amount: 200 },
      ];
      expect(calcTotalPaid(payments)).toBe(1000);
    });
  });

  // ---- Cart Quantity Edge Cases ----

  describe('cart quantity edge cases', () => {
    it('calcItemCount with empty cart returns 0', () => {
      expect(calcItemCount([])).toBe(0);
    });

    it('calcItemCount sums all quantities', () => {
      const items = [
        { quantity: 3, unit_price: 100, conversion_factor: 1, cost_price: 50, discount: 0 },
        { quantity: 2, unit_price: 200, conversion_factor: 1, cost_price: 100, discount: 0 },
      ];
      expect(calcItemCount(items)).toBe(5);
    });

    it('handles single item with quantity 1', () => {
      const items = [
        { quantity: 1, unit_price: 999.99, conversion_factor: 1, cost_price: 500, discount: 0 },
      ];
      expect(calcSubtotal(items)).toBeCloseTo(999.99, 2);
      expect(calcItemCount(items)).toBe(1);
    });
  });

  // ---- Zero and Negative Edge Cases ----

  describe('zero and boundary values', () => {
    it('handles zero quantity item', () => {
      const items = [
        { quantity: 0, unit_price: 100, conversion_factor: 1, cost_price: 60, discount: 0 },
      ];
      expect(calcSubtotal(items)).toBe(0);
      expect(calcItemCount(items)).toBe(0);
    });

    it('handles zero price item', () => {
      const items = [
        { quantity: 5, unit_price: 0, conversion_factor: 1, cost_price: 0, discount: 0 },
      ];
      expect(calcSubtotal(items)).toBe(0);
      expect(calcTotalCost(items)).toBe(0);
    });

    it('handles very small quantities with large conversion factor', () => {
      // 1 carton of 24 pieces
      const items = [
        { quantity: 1, unit_price: 2400, conversion_factor: 24, cost_price: 50, discount: 0 },
      ];
      expect(calcLineCost(items[0])).toBe(24 * 50); // 1200
      expect(calcLineTotal(items[0])).toBe(2400);
    });
  });
});