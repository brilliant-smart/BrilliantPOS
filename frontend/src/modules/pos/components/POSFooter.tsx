/**
 * POS Footer Component
 * 
 * Displays cart summary and totals
 */

import { Separator } from '@/components/ui/separator';
import { UsePosCartReturn } from '../composables';

interface POSFooterProps {
  cart: UsePosCartReturn;
}

export default function POSFooter({ cart }: POSFooterProps) {
  return (
    <div className="border-t bg-background px-6 py-4">
      <div className="space-y-2">
        <div className="flex justify-between text-lg">
          <span className="text-muted-foreground">Subtotal:</span>
          <span className="font-semibold">₦{cart.subtotal.toLocaleString()}</span>
        </div>

        {cart.globalDiscountAmount > 0 && (
          <div className="flex justify-between text-lg text-red-600 dark:text-red-400">
            <span>Discount:</span>
            <span className="font-semibold">-₦{cart.globalDiscountAmount.toLocaleString()}</span>
          </div>
        )}

        <Separator />

        <div className="flex justify-between text-2xl font-bold text-green-600 dark:text-green-400">
          <span>TOTAL:</span>
          <span>₦{cart.grandTotal.toLocaleString()}</span>
        </div>

        <div className="flex justify-between text-sm text-muted-foreground">
          <span>Profit:</span>
          <span className="text-green-600 dark:text-green-400 font-semibold">
            ₦{cart.totalProfit.toLocaleString()} ({cart.profitMargin.toFixed(1)}%)
          </span>
        </div>
      </div>
    </div>
  );
}
