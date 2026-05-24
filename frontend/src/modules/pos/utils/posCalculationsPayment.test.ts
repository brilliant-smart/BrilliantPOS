import { describe, it, expect } from 'vitest';
import {
  type Payment,
  calcTotalPaid,
  calcBalance,
  calcChange,
  canCompleteSale,
} from './posCalculationsPayment';

const makePayment = (overrides: Partial<Payment> = {}): Payment => ({
  method: 'cash',
  amount: 0,
  ...overrides,
});

describe('calcTotalPaid', () => {
  it('empty payments', () => {
    expect(calcTotalPaid([])).toBe(0);
  });

  it('sums all payment amounts', () => {
    const payments = [
      makePayment({ method: 'cash', amount: 3000 }),
      makePayment({ method: 'bank_transfer', amount: 2000 }),
    ];
    expect(calcTotalPaid(payments)).toBe(5000);
  });
});

describe('calcBalance', () => {
  it('fully paid', () => {
    expect(calcBalance(5000, 5000)).toBe(0);
  });

  it('underpaid', () => {
    expect(calcBalance(5000, 3000)).toBe(2000);
  });

  it('overpaid clamps to 0', () => {
    expect(calcBalance(5000, 7000)).toBe(0);
  });
});

describe('calcChange', () => {
  it('exact payment', () => {
    expect(calcChange(5000, 5000)).toBe(0);
  });

  it('overpayment', () => {
    expect(calcChange(5000, 10000)).toBe(5000);
  });

  it('underpaid returns 0', () => {
    expect(calcChange(5000, 3000)).toBe(0);
  });
});

describe('canCompleteSale', () => {
  it('fully paid', () => {
    expect(canCompleteSale(5000, 5000, false)).toBe(true);
  });

  it('overpaid', () => {
    expect(canCompleteSale(5000, 7000, false)).toBe(true);
  });

  it('underpaid cash only', () => {
    expect(canCompleteSale(5000, 3000, false)).toBe(false);
  });

  it('credit payment allows completion', () => {
    expect(canCompleteSale(5000, 0, true)).toBe(true);
  });

  it('mixed cash + credit, partially paid', () => {
    expect(canCompleteSale(5000, 2000, true)).toBe(true);
  });
});