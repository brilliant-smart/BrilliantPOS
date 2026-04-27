import { useState, useCallback, useMemo } from 'react';
import { DiscountType, DiscountConfig } from '../types';

export interface UseDiscountReturn {
  discountType: DiscountType;
  discountValue: string;
  isModalOpen: boolean;
  
  setDiscountType: (type: DiscountType) => void;
  setDiscountValue: (value: string) => void;
  openModal: () => void;
  closeModal: () => void;
  
  validateDiscount: (value: number, subtotal: number) => { valid: boolean; error?: string };
  applyDiscount: (value: number, subtotal: number, onApply: (percentage: number, amount: number) => void) => boolean;
}

interface UseDiscountProps {
  config?: Partial<DiscountConfig>;
  canApplyDiscount?: boolean;
}

const DEFAULT_CONFIG: DiscountConfig = {
  type: 'percentage',
  value: 0,
  requiresApproval: false,
  maxPercentage: 100,
  maxAmount: undefined,
};

/**
 * Discount Hook for POS
 * Manages discount state and validation
 */
export const useDiscount = ({
  config = {},
  canApplyDiscount = true,
}: UseDiscountProps = {}): UseDiscountReturn => {
  const mergedConfig = { ...DEFAULT_CONFIG, ...config };
  
  const [discountType, setDiscountType] = useState<DiscountType>(mergedConfig.type);
  const [discountValue, setDiscountValue] = useState<string>('');
  const [isModalOpen, setIsModalOpen] = useState(false);

  /**
   * Open discount modal
   */
  const openModal = useCallback(() => {
    if (!canApplyDiscount) {
      return;
    }
    setIsModalOpen(true);
  }, [canApplyDiscount]);

  /**
   * Close discount modal and reset
   */
  const closeModal = useCallback(() => {
    setIsModalOpen(false);
    setDiscountValue('');
  }, []);

  /**
   * Validate discount value
   * Returns validation result with error message if invalid
   */
  const validateDiscount = useCallback((value: number, subtotal: number): { valid: boolean; error?: string } => {
    // Check if discount can be applied
    if (!canApplyDiscount) {
      return { valid: false, error: 'You do not have permission to apply discounts' };
    }

    // Basic validation
    if (isNaN(value) || value < 0) {
      return { valid: false, error: 'Invalid discount value' };
    }

    // Percentage validation
    if (discountType === 'percentage') {
      const maxPercentage = mergedConfig.maxPercentage || 100;
      if (value > maxPercentage) {
        return { valid: false, error: `Discount percentage cannot exceed ${maxPercentage}%` };
      }
      
      // Check if percentage discount exceeds subtotal
      const discountAmount = (subtotal * value) / 100;
      if (discountAmount > subtotal) {
        return { valid: false, error: 'Discount cannot exceed subtotal' };
      }
    }

    // Fixed amount validation
    if (discountType === 'amount') {
      if (value > subtotal) {
        return { valid: false, error: 'Discount amount cannot exceed subtotal' };
      }
      
      // Check max amount if configured
      if (mergedConfig.maxAmount && value > mergedConfig.maxAmount) {
        return { valid: false, error: `Discount amount cannot exceed ₦${mergedConfig.maxAmount.toLocaleString()}` };
      }
    }

    return { valid: true };
  }, [discountType, canApplyDiscount, mergedConfig.maxPercentage, mergedConfig.maxAmount]);

  /**
   * Apply discount
   * Validates and calls the onApply callback with calculated values
   */
  const applyDiscount = useCallback((
    value: number,
    subtotal: number,
    onApply: (percentage: number, amount: number) => void
  ): boolean => {
    // Validate
    const validation = validateDiscount(value, subtotal);
    if (!validation.valid) {
      return false;
    }

    // Apply based on type
    if (discountType === 'percentage') {
      onApply(value, 0);
    } else {
      onApply(0, value);
    }

    // Close modal
    closeModal();
    return true;
  }, [discountType, validateDiscount, closeModal]);

  return {
    discountType,
    discountValue,
    isModalOpen,
    
    setDiscountType,
    setDiscountValue,
    openModal,
    closeModal,
    
    validateDiscount,
    applyDiscount,
  };
};
