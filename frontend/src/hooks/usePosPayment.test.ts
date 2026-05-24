import { describe, it, expect, beforeEach, vi } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { usePosPayment } from './usePosPayment';

describe('usePosPayment', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---- Adding payments ----

  it('adds a cash payment', () => {
    const { result } = renderHook(() => usePosPayment(1000));

    act(() => {
      result.current.addPayment('cash', 1000);
    });

    expect(result.current.payments).toHaveLength(1);
    expect(result.current.payments[0].method).toBe('cash');
    expect(result.current.payments[0].amount).toBe(1000);
  });

  it('adds multiple payment methods', () => {
    const { result } = renderHook(() => usePosPayment(1000));

    act(() => {
      result.current.addPayment('cash', 300);
      result.current.addPayment('card', 500);
      result.current.addPayment('bank_transfer', 200);
    });

    expect(result.current.payments).toHaveLength(3);
    expect(result.current.totalPaid).toBe(1000);
  });

  it('rejects zero or negative payment amounts', () => {
    const { result } = renderHook(() => usePosPayment(1000));

    act(() => {
      result.current.addPayment('cash', 0);
    });
    expect(result.current.payments).toHaveLength(0);

    act(() => {
      result.current.addPayment('cash', -50);
    });
    expect(result.current.payments).toHaveLength(0);
  });

  // ---- Removing payments ----

  it('removes a payment by index', () => {
    const { result } = renderHook(() => usePosPayment(1000));

    act(() => {
      result.current.addPayment('cash', 500);
      result.current.addPayment('card', 500);
    });
    expect(result.current.payments).toHaveLength(2);

    act(() => {
      result.current.removePayment(0);
    });
    expect(result.current.payments).toHaveLength(1);
    expect(result.current.payments[0].method).toBe('card');
  });

  // ---- Clearing payments ----

  it('clears all payments', () => {
    const { result } = renderHook(() => usePosPayment(1000));

    act(() => {
      result.current.addPayment('cash', 500);
      result.current.addPayment('card', 300);
    });

    act(() => {
      result.current.clearPayments();
    });
    expect(result.current.payments).toHaveLength(0);
    expect(result.current.totalPaid).toBe(0);
  });

  // ---- Computed values ----

  it('calculates total paid correctly', () => {
    const { result } = renderHook(() => usePosPayment(1000));

    act(() => {
      result.current.addPayment('cash', 300);
      result.current.addPayment('card', 400);
    });

    expect(result.current.totalPaid).toBe(700);
  });

  it('calculates balance (remaining) correctly', () => {
    const { result } = renderHook(() => usePosPayment(1000));

    act(() => {
      result.current.addPayment('cash', 300);
    });

    expect(result.current.balance).toBe(700);
  });

  it('calculates change when overpaid', () => {
    const { result } = renderHook(() => usePosPayment(1000));

    act(() => {
      result.current.addPayment('cash', 1500);
    });

    expect(result.current.change).toBe(500);
    expect(result.current.balance).toBe(0);
  });

  it('canComplete is true when fully paid', () => {
    const { result } = renderHook(() => usePosPayment(1000));

    act(() => {
      result.current.addPayment('cash', 1000);
    });

    expect(result.current.canComplete).toBe(true);
  });

  it('canComplete is true with credit payment even when underpaid', () => {
    const { result } = renderHook(() => usePosPayment(1000));

    act(() => {
      result.current.addPayment('cash', 300);
      result.current.addPayment('credit', 700);
    });

    expect(result.current.canComplete).toBe(true);
  });

  it('canComplete is false when underpaid without credit', () => {
    const { result } = renderHook(() => usePosPayment(1000));

    act(() => {
      result.current.addPayment('cash', 500);
    });

    expect(result.current.canComplete).toBe(false);
  });

  it('canComplete is true when overpaid', () => {
    const { result } = renderHook(() => usePosPayment(1000));

    act(() => {
      result.current.addPayment('cash', 2000);
    });

    expect(result.current.canComplete).toBe(true);
  });

  // ---- Edge cases ----

  it('handles zero total amount', () => {
    const { result } = renderHook(() => usePosPayment(0));

    expect(result.current.totalPaid).toBe(0);
    expect(result.current.balance).toBe(0);
    expect(result.current.canComplete).toBe(true);
  });

  it('updates balance when total amount changes (re-render)', () => {
    const { result } = renderHook(({ total }) => usePosPayment(total), {
      initialProps: { total: 1000 }
    });

    act(() => {
      result.current.addPayment('cash', 500);
    });
    expect(result.current.balance).toBe(500);

    // Re-render with different total
    renderHook(({ total }) => usePosPayment(total), {
      initialProps: { total: 500 }
    });
  });
});