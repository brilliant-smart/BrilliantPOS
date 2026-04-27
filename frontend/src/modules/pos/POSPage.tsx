import { useState } from 'react';
import { useAuth } from '@/app/auth/AuthContext';
import { usePosKeyboard } from '@/hooks/usePosKeyboard';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { 
  ShoppingCart, 
  CreditCard, 
  Archive, 
  RotateCcw, 
  Ban,
  User,
  Percent,
  X,
} from 'lucide-react';
import { toast } from 'sonner';

// Import POS module composables and components
import { 
  usePosCart, 
  useScanner, 
  usePayment, 
  useDiscount 
} from './composables';

import { 
  POSSearch, 
  POSCart, 
  PaymentModal 
} from './components';

import POSHoldRecallModal from '@/pages/admin/pos/POSHoldRecallModal';
import POSVoidModal from '@/pages/admin/pos/POSVoidModal';

export default function POSPage() {
  const { user } = useAuth();

  // Initialize composables
  const cart = usePosCart();
  const scanner = useScanner({
    onProductScanned: (product) => {
      cart.addItem(product);
      toast.success(`Added: ${product.name}`);
    },
    onError: (message) => {
      toast.error(message);
    },
  });
  const payment = usePayment({ totalAmount: cart.grandTotal });
  const discount = useDiscount({
    canApplyDiscount: true, // Can be role-based: user?.role === 'owner' || user?.role === 'manager'
  });

  // Modal states
  const [paymentDrawerOpen, setPaymentDrawerOpen] = useState(false);
  const [holdRecallOpen, setHoldRecallOpen] = useState(false);
  const [voidModalOpen, setVoidModalOpen] = useState(false);

  // Customer state
  const [showCustomerInput, setShowCustomerInput] = useState(false);
  const [customerName, setCustomerName] = useState('');

  // Keyboard shortcuts
  usePosKeyboard({
    onPayment: () => {
      if (cart.items.length > 0) {
        setPaymentDrawerOpen(true);
      } else {
        toast.error('Cart is empty');
      }
    },
    onHold: () => {
      if (cart.items.length > 0) {
        handleHoldCart();
      } else {
        toast.error('Cart is empty');
      }
    },
    onRecall: () => {
      setHoldRecallOpen(true);
    },
    onVoid: () => {
      setVoidModalOpen(true);
    },
    onNewSale: () => {
      handleNewSale();
    },
    enabled: !paymentDrawerOpen && !holdRecallOpen && !voidModalOpen,
  });

  const handleHoldCart = async () => {
    try {
      setHoldRecallOpen(true);
    } catch (error: any) {
      toast.error(error.message || 'Failed to hold cart');
    }
  };

  const handleNewSale = () => {
    if (cart.items.length > 0) {
      if (confirm('Clear current cart and start new sale?')) {
        cart.clearCart();
        payment.clearPayments();
        setCustomerName('');
        scanner.focusScanner();
        toast.success('New sale started');
      }
    }
  };

  const handleApplyDiscount = () => {
    const value = parseFloat(discount.discountValue);
    
    const validation = discount.validateDiscount(value, cart.subtotal);
    if (!validation.valid) {
      toast.error(validation.error || 'Invalid discount');
      return;
    }

    const success = discount.applyDiscount(value, cart.subtotal, (percentage, amount) => {
      cart.applyGlobalDiscount(percentage, amount);
      
      if (percentage > 0) {
        toast.success(`${percentage}% discount applied`);
      } else {
        toast.success(`₦${amount.toLocaleString()} discount applied`);
      }
    });

    if (success) {
      discount.closeModal();
    }
  };

  return (
    <div className="h-screen flex flex-col bg-background">
      {/* Header */}
      <div className="bg-green-600 dark:bg-green-700 text-white px-6 py-4 flex items-center justify-between shadow-md">
        <div className="flex items-center gap-4">
          <ShoppingCart className="h-6 w-6" />
          <div>
            <h1 className="text-xl font-bold">POS Terminal</h1>
            <p className="text-green-100 dark:text-green-200 text-sm">Brilliant POS</p>
          </div>
        </div>
        <div className="flex items-center gap-6">
          <div className="text-right">
            <p className="text-green-100 dark:text-green-200 text-sm">Cashier</p>
            <p className="font-semibold">{user?.name}</p>
          </div>
          <div className="h-10 w-10 bg-green-500 dark:bg-green-600 rounded-full flex items-center justify-center">
            <User className="h-6 w-6" />
          </div>
        </div>
      </div>

      {/* Scanner Input */}
      <POSSearch scanner={scanner} cart={cart} />

      {/* Main Content */}
      <div className="flex-1 flex overflow-hidden">
        {/* Left: Cart */}
        <POSCart cart={cart} onNewSale={handleNewSale} />

        {/* Right: Actions & Summary */}
        <div className="w-[40%] flex flex-col bg-background">
          {/* Quick Actions */}
          <Card className="m-4">
            <CardHeader className="pb-3">
              <CardTitle className="text-base">Quick Actions</CardTitle>
            </CardHeader>
            <CardContent className="grid grid-cols-2 gap-2">
              <Button
                variant="outline"
                onClick={handleNewSale}
                className="h-14 flex-col"
              >
                <X className="h-5 w-5 mb-1" />
                <span className="text-xs">New Sale (F4)</span>
              </Button>
              <Button
                variant="outline"
                onClick={() => setHoldRecallOpen(true)}
                className="h-14 flex-col"
                disabled={cart.items.length === 0}
              >
                <Archive className="h-5 w-5 mb-1" />
                <span className="text-xs">Hold (F8)</span>
              </Button>
              <Button
                variant="outline"
                onClick={() => setHoldRecallOpen(true)}
                className="h-14 flex-col"
              >
                <RotateCcw className="h-5 w-5 mb-1" />
                <span className="text-xs">Recall (F9)</span>
              </Button>
              <Button
                variant="outline"
                onClick={() => setVoidModalOpen(true)}
                className="h-14 flex-col"
              >
                <Ban className="h-5 w-5 mb-1" />
                <span className="text-xs">Void (F10)</span>
              </Button>
            </CardContent>
          </Card>

          {/* Customer Info */}
          <Card className="mx-4 mb-4">
            <CardHeader className="pb-3">
              <CardTitle className="text-base">Customer</CardTitle>
            </CardHeader>
            <CardContent>
              {!showCustomerInput ? (
                <Button
                  variant="outline"
                  onClick={() => setShowCustomerInput(true)}
                  className="w-full"
                >
                  <User className="h-4 w-4 mr-2" />
                  Add Customer (Optional)
                </Button>
              ) : (
                <div className="flex gap-2">
                  <Input
                    value={customerName}
                    onChange={(e) => setCustomerName(e.target.value)}
                    placeholder="Customer name"
                    className="flex-1"
                  />
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => {
                      setShowCustomerInput(false);
                      setCustomerName('');
                    }}
                  >
                    <X className="h-4 w-4" />
                  </Button>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Discount */}
          <Card className="mx-4 mb-4">
            <CardHeader className="pb-3">
              <CardTitle className="text-base">Discount</CardTitle>
            </CardHeader>
            <CardContent>
              {!discount.isModalOpen ? (
                <Button
                  variant="outline"
                  onClick={discount.openModal}
                  className="w-full"
                  disabled={cart.items.length === 0}
                >
                  <Percent className="h-4 w-4 mr-2" />
                  Apply Discount
                </Button>
              ) : (
                <div className="space-y-2">
                  <div className="flex gap-2">
                    <Button
                      variant={discount.discountType === 'percentage' ? 'default' : 'outline'}
                      size="sm"
                      onClick={() => discount.setDiscountType('percentage')}
                    >
                      %
                    </Button>
                    <Button
                      variant={discount.discountType === 'amount' ? 'default' : 'outline'}
                      size="sm"
                      onClick={() => discount.setDiscountType('amount')}
                    >
                      ₦
                    </Button>
                  </div>
                  <div className="flex gap-2">
                    <Input
                      type="number"
                      value={discount.discountValue}
                      onChange={(e) => discount.setDiscountValue(e.target.value)}
                      placeholder={discount.discountType === 'percentage' ? 'Percentage' : 'Amount'}
                      className="flex-1"
                      min="0"
                      max={discount.discountType === 'percentage' ? '100' : cart.subtotal.toString()}
                    />
                    <Button onClick={handleApplyDiscount} size="sm">
                      Apply
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={discount.closeModal}
                    >
                      <X className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Spacer */}
          <div className="flex-1"></div>

          {/* Complete Payment Button */}
          <div className="p-4 bg-card border-t">
            <Button
              onClick={() => setPaymentDrawerOpen(true)}
              disabled={cart.items.length === 0}
              className="w-full h-16 text-xl font-bold bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-600"
              size="lg"
            >
              <CreditCard className="h-6 w-6 mr-3" />
              Complete Payment (F6)
            </Button>
          </div>
        </div>
      </div>

      {/* Modals */}
      <PaymentModal
        open={paymentDrawerOpen}
        onClose={() => setPaymentDrawerOpen(false)}
        cart={cart}
        payment={payment}
        scanner={scanner}
        customerName={customerName}
      />

      <POSHoldRecallModal
        open={holdRecallOpen}
        onClose={() => {
          setHoldRecallOpen(false);
          scanner.focusScanner();
        }}
        cart={cart}
      />

      <POSVoidModal
        open={voidModalOpen}
        onClose={() => {
          setVoidModalOpen(false);
          scanner.focusScanner();
        }}
      />
    </div>
  );
}
