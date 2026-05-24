export interface CartItem {
  quantity: number;
  unit_price: number;
  conversion_factor: number;
  cost_price: number;
  discount: number;
}

export function calcLineTotal(item: CartItem): number {
  return item.quantity * item.unit_price - item.discount;
}

export function calcLineCost(item: CartItem): number {
  return item.quantity * item.conversion_factor * item.cost_price;
}

export function calcLineProfit(item: CartItem): number {
  return calcLineTotal(item) - calcLineCost(item);
}

export function calcSubtotal(items: CartItem[]): number {
  return items.reduce((sum, item) => sum + calcLineTotal(item), 0);
}

export function calcGlobalDiscount(
  subtotal: number,
  discountAmount: number,
  discountPercentage: number
): number {
  if (discountAmount > 0) return discountAmount;
  if (discountPercentage > 0) return (subtotal * discountPercentage) / 100;
  return 0;
}

export function calcGrandTotal(subtotal: number, globalDiscount: number): number {
  return Math.max(0, subtotal - globalDiscount);
}

export function calcTotalCost(items: CartItem[]): number {
  return items.reduce((sum, item) => sum + calcLineCost(item), 0);
}

export function calcTotalProfit(items: CartItem[], globalDiscount: number): number {
  const itemsProfit = items.reduce((sum, item) => sum + calcLineProfit(item), 0);
  return itemsProfit - globalDiscount;
}

export function calcProfitMargin(grandTotal: number, totalProfit: number): number {
  if (grandTotal === 0) return 0;
  return (totalProfit / grandTotal) * 100;
}

export function calcItemCount(items: CartItem[]): number {
  return items.reduce((sum, item) => sum + item.quantity, 0);
}

export function calcMaxUnits(stockAvailable: number, conversionFactor: number): number {
  return Math.floor(stockAvailable / conversionFactor);
}