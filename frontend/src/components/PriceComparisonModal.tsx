import { useState, useEffect } from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { TrendingUp, TrendingDown, Minus, AlertTriangle } from 'lucide-react';
import { format } from 'date-fns';

interface PriceHistory {
  price: number;
  date: string;
  supplier: string;
  reference: string;
}

interface ProductComparison {
  product_id: number;
  product_name: string;
  sku: string;
  current_cost_price: number;
  last_purchase_price: number;
  price_history: PriceHistory[];
}

interface PriceComparisonModalProps {
  open: boolean;
  onClose: () => void;
  comparisons: ProductComparison[];
  currentPrices: { [key: number]: number }; // product_id => current input price
}

export default function PriceComparisonModal({
  open,
  onClose,
  comparisons,
  currentPrices,
}: PriceComparisonModalProps) {
  const [alerts, setAlerts] = useState<{ productId: number; message: string; type: 'increase' | 'decrease' }[]>([]);

  useEffect(() => {
    // Calculate price change alerts
    const newAlerts: typeof alerts = [];
    
    comparisons.forEach((product) => {
      const currentPrice = currentPrices[product.product_id];
      const lastPrice = product.last_purchase_price || product.current_cost_price;
      
      if (currentPrice && lastPrice) {
        const change = currentPrice - lastPrice;
        const percentChange = ((change / lastPrice) * 100).toFixed(2);
        
        if (Math.abs(change) > 0.01) {
          newAlerts.push({
            productId: product.product_id,
            message: `${product.product_name}: ${change > 0 ? 'Increased' : 'Decreased'} by ₦${Math.abs(change).toFixed(2)} (${Math.abs(parseFloat(percentChange))}%)`,
            type: change > 0 ? 'increase' : 'decrease',
          });
        }
      }
    });
    
    setAlerts(newAlerts);
  }, [comparisons, currentPrices]);

  const getPriceChangeIndicator = (oldPrice: number, newPrice: number) => {
    if (newPrice > oldPrice) {
      return <TrendingUp className="h-4 w-4 text-red-500 dark:text-red-400" />;
    } else if (newPrice < oldPrice) {
      return <TrendingDown className="h-4 w-4 text-green-500 dark:text-green-400" />;
    }
    return <Minus className="h-4 w-4 text-muted-foreground dark:text-muted-foreground/80" />;
  };

  const getPriceChangeBadge = (oldPrice: number, newPrice: number) => {
    const change = newPrice - oldPrice;
    const percentChange = ((change / oldPrice) * 100).toFixed(2);
    
    if (Math.abs(change) < 0.01) {
      return <Badge variant="outline">No Change</Badge>;
    }
    
    if (change > 0) {
      return (
        <Badge variant="destructive" className="flex items-center gap-1">
          <TrendingUp className="h-3 w-3" />
          +₦{change.toFixed(2)} (+{percentChange}%)
        </Badge>
      );
    }
    
    return (
      <Badge variant="default" className="bg-green-600 dark:bg-green-700 flex items-center gap-1">
        <TrendingDown className="h-3 w-3" />
        -₦{Math.abs(change).toFixed(2)} ({percentChange}%)
      </Badge>
    );
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-5xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Price Comparison Analysis</DialogTitle>
          <DialogDescription>
            Review historical prices and detect price changes before creating the purchase order
          </DialogDescription>
        </DialogHeader>

        {/* Price Alerts */}
        {alerts.length > 0 && (
          <div className="space-y-2">
            {alerts.map((alert, index) => (
              <Alert key={index} variant={alert.type === 'increase' ? 'destructive' : 'default'}>
                <AlertTriangle className="h-4 w-4" />
                <AlertDescription>{alert.message}</AlertDescription>
              </Alert>
            ))}
          </div>
        )}

        {/* Price Comparison Table */}
        <div className="space-y-6">
          {comparisons.map((product) => {
            const currentPrice = currentPrices[product.product_id];
            const lastPrice = product.last_purchase_price || product.current_cost_price;

            return (
              <div key={product.product_id} className="border rounded-lg p-4">
                <div className="flex items-start justify-between mb-4">
                  <div>
                    <h3 className="font-semibold text-lg">{product.product_name}</h3>
                    <p className="text-sm text-muted-foreground dark:text-muted-foreground/80">SKU: {product.sku}</p>
                  </div>
                  
                  {currentPrice && lastPrice && (
                    <div className="flex items-center gap-2">
                      {getPriceChangeIndicator(lastPrice, currentPrice)}
                      {getPriceChangeBadge(lastPrice, currentPrice)}
                    </div>
                  )}
                </div>

                {/* Current vs Last Price */}
                <div className="grid grid-cols-3 gap-4 mb-4 bg-muted p-3 rounded">
                  <div>
                    <p className="text-xs text-muted-foreground dark:text-muted-foreground/80">Last Purchase Price</p>
                    <p className="text-lg font-semibold">₦{lastPrice?.toFixed(2) || 'N/A'}</p>
                  </div>
                  <div>
                    <p className="text-xs text-muted-foreground dark:text-muted-foreground/80">Current Input Price</p>
                    <p className="text-lg font-semibold text-blue-600 dark:text-blue-400">
                      ₦{currentPrice?.toFixed(2) || 'Not Set'}
                    </p>
                  </div>
                  <div>
                    <p className="text-xs text-muted-foreground dark:text-muted-foreground/80">Average Cost Price</p>
                    <p className="text-lg font-semibold">₦{product.current_cost_price?.toFixed(2) || 'N/A'}</p>
                  </div>
                </div>

                {/* Price History */}
                {product.price_history && product.price_history.length > 0 && (
                  <div>
                    <h4 className="text-sm font-semibold mb-2">Recent Purchase History</h4>
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Date</TableHead>
                          <TableHead>Price</TableHead>
                          <TableHead>Supplier</TableHead>
                          <TableHead>PO Reference</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {product.price_history.map((history, index) => (
                          <TableRow key={index}>
                            <TableCell>
                              {format(new Date(history.date), 'MMM dd, yyyy')}
                            </TableCell>
                            <TableCell className="font-semibold">
                              ₦{history.price.toFixed(2)}
                            </TableCell>
                            <TableCell>{history.supplier || 'N/A'}</TableCell>
                            <TableCell className="text-muted-foreground dark:text-muted-foreground/80">{history.reference || 'N/A'}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>
                )}

                {(!product.price_history || product.price_history.length === 0) && (
                  <p className="text-sm text-muted-foreground dark:text-muted-foreground/80 italic">No purchase history available</p>
                )}
              </div>
            );
          })}
        </div>
      </DialogContent>
    </Dialog>
  );
}
