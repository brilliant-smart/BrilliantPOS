import { useState, useCallback, useMemo } from 'react';
import { PaymentMethod, Payment } from '../types';

export interface UsePaymentReturn {
  payments: Payment[];
  totalPaid: number;
  balance: number;
  change: number;
  canComplete: boolean;
  
  addPayment: (method: PaymentMethod, amount: number, reference?: string) => void;
  removePayment: (index: number) => void;
  clearPayments: () => void;
}

interface UsePaymentProps {
  totalAmount: number;
}

/**
 * Payment Hook for POS
 * Manages payment collection and validation
 */
export const usePayment = ({ totalAmount }: UsePaymentProps): UsePaymentReturn => {
  const [payments, setPayments] = useState<Payment[]>([]);

  /**
   * Add a payment
   * Supports multiple payment methods (split payments)
   */
  const addPayment = useCallback((method: PaymentMethod, amount: number, reference?: string) => {
    if (amount <= 0) return;
    
    setPayments(prev => [...prev, { method, amount, reference }]);
  }, []);

  /**
   * Remove a payment by index
   */
  const removePayment = useCallback((index: number) => {
    setPayments(prev => prev.filter((_, i) => i !== index));
  }, []);

  /**
   * Clear all payments
   */
  const clearPayments = useCallback(() => {
    setPayments([]);
  }, []);

  /**
   * Calculate total paid amount
   */
  const totalPaid = useMemo(() => {
    return payments.reduce((sum, p) => sum + p.amount, 0);
  }, [payments]);

  /**
   * Calculate balance (remaining to pay)
   */
  const balance = useMemo(() => {
    return Math.max(0, totalAmount - totalPaid);
  }, [totalAmount, totalPaid]);

  /**
   * Calculate change (overpayment)
   */
  const change = useMemo(() => {
    return Math.max(0, totalPaid - totalAmount);
  }, [totalPaid, totalAmount]);

  /**
   * Can complete sale?
   * If credit payment exists, allow completion even if unpaid
   * Otherwise, must be fully paid
   */
  const canComplete = useMemo(() => {
    const hasCreditPayment = payments.some(p => p.method === 'credit');
    return hasCreditPayment || totalPaid >= totalAmount;
  }, [payments, totalPaid, totalAmount]);

  return {
    payments,
    totalPaid,
    balance,
    change,
    canComplete,
    addPayment,
    removePayment,
    clearPayments,
  };
};
