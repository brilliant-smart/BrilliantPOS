import { useState, useCallback, useMemo } from 'react';
import {
  calcTotalPaid,
  calcBalance,
  calcChange,
  canCompleteSale,
} from '@/modules/pos/utils/posCalculationsPayment';

export type PaymentMethod = 'cash' | 'card' | 'pos' | 'credit' | 'bank_transfer';

export interface Payment {
  method: PaymentMethod;
  amount: number;
  reference?: string;
}

export interface UsePosPaymentReturn {
  payments: Payment[];
  totalPaid: number;
  balance: number;
  change: number;
  canComplete: boolean;
  
  addPayment: (method: PaymentMethod, amount: number, reference?: string) => void;
  removePayment: (index: number) => void;
  clearPayments: () => void;
}

export const usePosPayment = (totalAmount: number): UsePosPaymentReturn => {
  const [payments, setPayments] = useState<Payment[]>([]);

  // Add payment
  const addPayment = useCallback((method: PaymentMethod, amount: number, reference?: string) => {
    if (amount <= 0) return;
    
    setPayments(prev => [...prev, { method, amount, reference }]);
  }, []);

  // Remove payment
  const removePayment = useCallback((index: number) => {
    setPayments(prev => prev.filter((_, i) => i !== index));
  }, []);

  // Clear all payments
  const clearPayments = useCallback(() => {
    setPayments([]);
  }, []);

  // Calculate total paid
  const totalPaid = useMemo(() => calcTotalPaid(payments), [payments]);

  // Calculate balance (remaining to pay)
  const balance = useMemo(() => calcBalance(totalAmount, totalPaid), [totalAmount, totalPaid]);

  // Calculate change (overpayment)
  const change = useMemo(() => calcChange(totalAmount, totalPaid), [totalPaid, totalAmount]);

  // Can complete sale?
  const canComplete = useMemo(() => {
    const hasCreditPayment = payments.some(p => p.method === 'credit');
    return canCompleteSale(totalAmount, totalPaid, hasCreditPayment);
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
