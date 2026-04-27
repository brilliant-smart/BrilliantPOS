/**
 * POS Cart Items Component
 * 
 * Displays cart items table with quantity controls
 */

import { ShoppingCart } from 'lucide-react';
import POSCartItem from '@/pages/admin/pos/POSCartItem';
import { UsePosCartReturn } from '../composables';

interface POSCartItemsProps {
  cart: UsePosCartReturn;
}

export default function POSCartItems({ cart }: POSCartItemsProps) {
  if (cart.items.length === 0) {
    return (
      <div className="flex items-center justify-center h-full">
        <div className="text-center text-muted-foreground">
          <ShoppingCart className="h-16 w-16 mx-auto mb-4 opacity-20" />
          <p className="text-lg font-medium">Cart is empty</p>
          <p className="text-sm">Scan a product to get started</p>
        </div>
      </div>
    );
  }

  return (
    <table className="w-full">
      <thead className="bg-muted sticky top-0">
        <tr className="text-left text-sm text-muted-foreground">
          <th className="px-6 py-3 font-medium">Product</th>
          <th className="px-4 py-3 font-medium w-32">Quantity</th>
          <th className="px-4 py-3 font-medium w-32 text-right">Price</th>
          <th className="px-4 py-3 font-medium w-32 text-right">Total</th>
          <th className="px-4 py-3 font-medium w-16"></th>
        </tr>
      </thead>
      <tbody className="divide-y">
        {cart.items.map((item) => (
          <POSCartItem
            key={item.product_id}
            item={item}
            isLastScanned={item.product_id === cart.lastScannedProductId}
            onIncrement={() => cart.incrementQty(item.product_id)}
            onDecrement={() => cart.decrementQty(item.product_id)}
            onRemove={() => cart.removeItem(item.product_id)}
            onUpdateQuantity={(qty) => cart.updateQuantity(item.product_id, qty)}
            onUpdatePrice={(price) => cart.updatePrice(item.product_id, price)}
          />
        ))}
      </tbody>
    </table>
  );
}
