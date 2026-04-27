import { useState, useEffect } from 'react';
import { UsePosCartReturn } from '@/hooks/usePosCart';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import { Archive, RotateCcw, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { posApi } from '@/app/api/pos';
import { format } from 'date-fns';

interface POSHoldRecallModalProps {
  open: boolean;
  onClose: () => void;
  cart: UsePosCartReturn;
}

interface HeldCart {
  id: number;
  reference: string;
  items: any[];
  discount_percentage: number;
  discount_amount: number;
  notes?: string;
  held_at: string;
  customer?: { name: string };
}

export default function POSHoldRecallModal({ open, onClose, cart }: POSHoldRecallModalProps) {
  const [heldCarts, setHeldCarts] = useState<HeldCart[]>([]);
  const [loading, setLoading] = useState(false);
  const [notes, setNotes] = useState('');

  useEffect(() => {
    if (open) {
      loadHeldCarts();
    }
  }, [open]);

  const loadHeldCarts = async () => {
    try {
      setLoading(true);
      const data = await posApi.getHeldCarts();
      setHeldCarts(data);
    } catch (error: any) {
      toast.error('Failed to load held carts');
    } finally {
      setLoading(false);
    }
  };

  const handleHoldCart = async () => {
    if (cart.items.length === 0) {
      toast.error('Cart is empty');
      return;
    }

    try {
      await posApi.holdCart({
        items: cart.items,
        discount_percentage: cart.discountPercentage,
        discount_amount: cart.discountAmount,
        notes: notes || undefined,
      });

      toast.success('Cart held successfully');
      cart.clearCart();
      setNotes('');
      onClose();
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to hold cart');
    }
  };

  const handleRecallCart = async (heldCartId: number) => {
    try {
      const response = await posApi.recallCart(heldCartId);
      const heldCart = response.cart;

      // Load items into current cart
      cart.clearCart();
      
      heldCart.items.forEach((item: any) => {
        // We need to reconstruct the product object
        const product = {
          id: item.product_id,
          name: item.product_name,
          sku: item.sku,
          price: item.unit_price,
          unit_type: item.unit_type,
          cost_price: item.cost_price,
          stock_quantity: item.stock_available,
        };
        
        // Add item with correct quantity
        for (let i = 0; i < item.quantity; i++) {
          cart.addItem(product as any);
        }
        
        // Apply line discount if exists
        if (item.discount > 0) {
          cart.applyLineDiscount(item.product_id, item.discount);
        }
      });

      // Apply global discount
      if (heldCart.discount_percentage > 0 || heldCart.discount_amount > 0) {
        cart.applyGlobalDiscount(heldCart.discount_percentage, heldCart.discount_amount);
      }

      toast.success('Cart recalled successfully');
      onClose();
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to recall cart');
    }
  };

  const handleDeleteHeldCart = async (heldCartId: number) => {
    if (!confirm('Delete this held cart?')) return;

    try {
      await posApi.deleteHeldCart(heldCartId);
      toast.success('Held cart deleted');
      loadHeldCarts();
    } catch (error: any) {
      toast.error('Failed to delete held cart');
    }
  };

  const calculateHeldCartTotal = (heldCart: HeldCart) => {
    const subtotal = heldCart.items.reduce((sum, item) => {
      return sum + (item.quantity * item.unit_price - (item.discount || 0));
    }, 0);

    const discount = heldCart.discount_amount || (subtotal * heldCart.discount_percentage / 100);
    return subtotal - discount;
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-3xl max-h-[80vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Hold & Recall</DialogTitle>
          <DialogDescription>
            Hold current cart for later or recall a previously held cart
          </DialogDescription>
        </DialogHeader>

        <Tabs defaultValue="hold" className="mt-4">
          <TabsList className="grid w-full grid-cols-2">
            <TabsTrigger value="hold">
              <Archive className="h-4 w-4 mr-2" />
              Hold Cart
            </TabsTrigger>
            <TabsTrigger value="recall">
              <RotateCcw className="h-4 w-4 mr-2" />
              Recall Cart ({heldCarts.length})
            </TabsTrigger>
          </TabsList>

          {/* Hold Tab */}
          <TabsContent value="hold" className="space-y-4">
            {cart.items.length === 0 ? (
              <div className="text-center py-12 text-muted-foreground">
                <Archive className="h-12 w-12 mx-auto mb-3 opacity-20" />
                <p>Cart is empty</p>
              </div>
            ) : (
              <>
                <div className="bg-background p-4 rounded-lg">
                  <p className="text-sm text-muted-foreground mb-2">Current Cart Summary:</p>
                  <div className="space-y-1">
                    <p><strong>Items:</strong> {cart.itemCount}</p>
                    <p><strong>Total:</strong> ₦{cart.grandTotal.toLocaleString()}</p>
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium mb-2">Notes (Optional)</label>
                  <Textarea
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    placeholder="Add notes about this cart..."
                    rows={3}
                  />
                </div>

                <Button onClick={handleHoldCart} className="w-full" size="lg">
                  <Archive className="h-4 w-4 mr-2" />
                  Hold This Cart
                </Button>
              </>
            )}
          </TabsContent>

          {/* Recall Tab */}
          <TabsContent value="recall">
            {loading ? (
              <div className="text-center py-12">
                <p className="text-muted-foreground">Loading held carts...</p>
              </div>
            ) : heldCarts.length === 0 ? (
              <div className="text-center py-12 text-muted-foreground">
                <RotateCcw className="h-12 w-12 mx-auto mb-3 opacity-20" />
                <p>No held carts</p>
              </div>
            ) : (
              <div className="space-y-3 max-h-96 overflow-y-auto">
                {heldCarts.map((heldCart) => (
                  <div
                    key={heldCart.id}
                    className="border rounded-lg p-4 hover:bg-muted transition-colors"
                  >
                    <div className="flex items-start justify-between mb-3">
                      <div>
                        <p className="font-semibold text-lg">{heldCart.reference}</p>
                        <p className="text-sm text-muted-foreground">
                          {format(new Date(heldCart.held_at), 'PPp')}
                        </p>
                        {heldCart.customer && (
                          <p className="text-sm text-muted-foreground mt-1">
                            Customer: {heldCart.customer.name}
                          </p>
                        )}
                      </div>
                      <Badge variant="secondary">
                        {heldCart.items.length} items
                      </Badge>
                    </div>

                    {heldCart.notes && (
                      <p className="text-sm text-muted-foreground mb-3 italic">
                        "{heldCart.notes}"
                      </p>
                    )}

                    <div className="flex items-center justify-between pt-3 border-t">
                      <p className="text-xl font-bold text-green-600 dark:text-green-400">
                        ₦{calculateHeldCartTotal(heldCart).toLocaleString()}
                      </p>
                      <div className="flex gap-2">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleRecallCart(heldCart.id)}
                        >
                          <RotateCcw className="h-4 w-4 mr-2" />
                          Recall
                        </Button>
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleDeleteHeldCart(heldCart.id)}
                          className="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-400"
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </TabsContent>
        </Tabs>
      </DialogContent>
    </Dialog>
  );
}
