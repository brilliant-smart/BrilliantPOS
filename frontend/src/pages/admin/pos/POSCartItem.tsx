import React, { useState } from 'react';
import { CartItem } from '@/hooks/usePosCart';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Plus, Minus, X } from 'lucide-react';

interface POSCartItemProps {
  item: CartItem;
  isLastScanned: boolean;
  onIncrement: () => void;
  onDecrement: () => void;
  onRemove: () => void;
  onUpdateQuantity: (qty: number) => void;
  onUpdatePrice: (price: number) => void;
}

const POSCartItem: React.FC<POSCartItemProps> = React.memo(({
  item,
  isLastScanned,
  onIncrement,
  onDecrement,
  onRemove,
  onUpdateQuantity,
  onUpdatePrice,
}) => {
  const [editingQty, setEditingQty] = useState(false);
  const [editingPrice, setEditingPrice] = useState(false);
  const [tempQty, setTempQty] = useState(item.quantity.toString());
  const [tempPrice, setTempPrice] = useState(item.unit_price.toString());

  const lineTotal = item.quantity * item.unit_price - item.discount;

  const handleQtySubmit = () => {
    const qty = parseInt(tempQty);
    if (!isNaN(qty) && qty > 0) {
      onUpdateQuantity(qty);
    } else {
      setTempQty(item.quantity.toString());
    }
    setEditingQty(false);
  };

  const handlePriceSubmit = () => {
    const price = parseFloat(tempPrice);
    if (!isNaN(price) && price >= 0) {
      onUpdatePrice(price);
    } else {
      setTempPrice(item.unit_price.toString());
    }
    setEditingPrice(false);
  };

  return (
    <tr 
      className={`
        hover:bg-muted transition-colors
        ${isLastScanned ? 'bg-green-50 dark:bg-green-950/30 border-l-4 border-green-500 dark:border-green-700 animate-pulse-once' : ''}
      `}
    >
      {/* Product Name */}
      <td className="px-3 py-2">
        <div>
          <p className="font-medium text-sm text-foreground leading-tight">{item.product_name}</p>
          <div className="flex items-center gap-1.5 mt-0.5">
            {item.sku && (
              <span className="text-[11px] text-muted-foreground dark:text-muted-foreground/80">SKU: {item.sku}</span>
            )}
            {item.quantity >= item.stock_available ? (
              <span className="text-[11px] font-medium text-amber-600 dark:text-amber-400">
                Max stock ({item.stock_available} {item.unit_type})
              </span>
            ) : (
              <span className="text-[11px] text-muted-foreground dark:text-muted-foreground/80">
                Stock: {item.stock_available} {item.unit_type}
              </span>
            )}
          </div>
        </div>
      </td>

      {/* Quantity Controls */}
      <td className="px-2 py-2">
        <div className="flex items-center gap-0.5">
          <Button
            variant="outline"
            size="icon"
            className="h-7 w-7"
            onClick={onDecrement}
            disabled={item.quantity <= 1}
          >
            <Minus className="h-3 w-3" />
          </Button>

          {editingQty ? (
            <Input
              type="number"
              value={tempQty}
              onChange={(e) => setTempQty(e.target.value)}
              onBlur={handleQtySubmit}
              onKeyPress={(e) => e.key === 'Enter' && handleQtySubmit()}
              className="w-12 h-7 text-center text-xs px-1"
              autoFocus
              min="1"
            />
          ) : (
            <button
              onClick={() => {
                setEditingQty(true);
                setTempQty(item.quantity.toString());
              }}
              className="w-12 h-7 text-center text-xs font-semibold hover:bg-muted rounded border"
            >
              {item.quantity}
            </button>
          )}

          <Button
            variant="outline"
            size="icon"
            className="h-7 w-7"
            onClick={onIncrement}
            disabled={item.quantity >= item.stock_available}
          >
            <Plus className="h-3 w-3" />
          </Button>
        </div>
      </td>

      {/* Unit Price */}
      <td className="px-2 py-2 text-right">
        {editingPrice ? (
          <Input
            type="number"
            value={tempPrice}
            onChange={(e) => setTempPrice(e.target.value)}
            onBlur={handlePriceSubmit}
            onKeyPress={(e) => e.key === 'Enter' && handlePriceSubmit()}
            className="w-20 h-7 text-right text-xs"
            autoFocus
            min="0"
            step="0.01"
          />
        ) : (
          <button
            onClick={() => {
              setEditingPrice(true);
              setTempPrice(item.unit_price.toString());
            }}
            className="text-xs font-semibold hover:bg-muted px-1 py-0.5 rounded"
            title="Click to edit price"
          >
            ₦{item.unit_price.toLocaleString()}
          </button>
        )}
      </td>

      {/* Line Total */}
      <td className="px-2 py-2 text-right">
        <div>
          <p className="text-sm font-bold text-foreground">
            ₦{lineTotal.toLocaleString()}
          </p>
          {item.discount > 0 && (
            <p className="text-[11px] text-red-600 dark:text-red-400">
              -₦{item.discount.toLocaleString()} disc
            </p>
          )}
        </div>
      </td>

      {/* Remove Button */}
      <td className="px-2 py-2">
        <Button
          variant="ghost"
          size="icon"
          className="h-7 w-7 text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30"
          onClick={onRemove}
        >
          <X className="h-3 w-3" />
        </Button>
      </td>
    </tr>
  );
}, (prevProps, nextProps) => {
  // Custom comparison for optimal re-rendering
  return (
    prevProps.item.product_id === nextProps.item.product_id &&
    prevProps.item.quantity === nextProps.item.quantity &&
    prevProps.item.unit_price === nextProps.item.unit_price &&
    prevProps.item.discount === nextProps.item.discount &&
    prevProps.isLastScanned === nextProps.isLastScanned
  );
});

POSCartItem.displayName = 'POSCartItem';

export default POSCartItem;
