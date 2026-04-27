/**
 * POS Cart Component
 * 
 * Main cart display with header, items, and footer
 */

import { Button } from '@/components/ui/button';
import { X } from 'lucide-react';
import POSCartItems from './POSCartItems';
import POSFooter from './POSFooter';
import { UsePosCartReturn } from '../composables';

interface POSCartProps {
  cart: UsePosCartReturn;
  onNewSale: () => void;
}

export default function POSCart({ cart, onNewSale }: POSCartProps) {
  return (
    <div className="w-[60%] flex flex-col border-r bg-card">
      {/* Cart Header */}
      <div className="px-6 py-4 border-b bg-background">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold text-foreground">
            Cart Items ({cart.itemCount})
          </h2>
          <Button
            variant="ghost"
            size="sm"
            onClick={onNewSale}
            disabled={cart.items.length === 0}
          >
            <X className="h-4 w-4 mr-2" />
            Clear Cart (F4)
          </Button>
        </div>
      </div>

      {/* Cart Items */}
      <div className="flex-1 overflow-auto">
        <POSCartItems cart={cart} />
      </div>

      {/* Cart Summary */}
      <POSFooter cart={cart} />
    </div>
  );
}
