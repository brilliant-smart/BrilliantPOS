import { useState, useEffect } from 'react';
import { UsePosCartReturn } from '@/hooks/usePosCart';
import { UsePosPaymentReturn, PaymentMethod } from '@/hooks/usePosPayment';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import { Smartphone, Building2, UserCheck, X } from 'lucide-react';
import { Banknote } from 'lucide-react';
import { toast } from 'sonner';
import { posApi } from '@/app/api/pos';
import Swal from 'sweetalert2';

interface POSPaymentDrawerProps {
  open: boolean;
  onClose: () => void;
  cart: UsePosCartReturn;
  payment: UsePosPaymentReturn;
  customerName?: string;
}

const paymentMethods: Array<{ value: PaymentMethod; label: string; icon: any; hotkey: string }> = [
  { value: 'pos', label: 'POS Terminal', icon: Smartphone, hotkey: 'P' },
  { value: 'bank_transfer', label: 'Transfer', icon: Building2, hotkey: 'T' },
  { value: 'cash', label: 'Cash', icon: Banknote, hotkey: 'C' },
  { value: 'credit', label: 'Credit', icon: UserCheck, hotkey: 'R' },
];

export default function POSPaymentDrawer({
  open,
  onClose,
  cart,
  payment,
  customerName,
}: POSPaymentDrawerProps) {
  // Auto-select POS Terminal as default (most common in Nigeria)
  const [selectedMethod, setSelectedMethod] = useState<PaymentMethod>('pos');
  const [amount, setAmount] = useState('');
  const [reference, setReference] = useState('');
  const [completing, setCompleting] = useState(false);
  
  // Discount state
  const [discountType, setDiscountType] = useState<'percentage' | 'amount'>('percentage');
  const [discountValue, setDiscountValue] = useState('');

  // Auto-focus and keyboard shortcuts
  useEffect(() => {
    if (open) {
      // Auto-select POS Terminal
      setSelectedMethod('pos');
      setAmount('');
      setReference('');
      setDiscountValue('');
      
      // Load existing discount from cart if any
      if (cart.discountPercentage > 0) {
        setDiscountType('percentage');
        setDiscountValue(cart.discountPercentage.toString());
      } else if (cart.discountAmount > 0) {
        setDiscountType('amount');
        setDiscountValue(cart.discountAmount.toString());
      }
      
      const handleKeyPress = (e: KeyboardEvent) => {
        // Don't interfere with input fields
        if (e.target instanceof HTMLInputElement) return;
        
        // Method selection hotkeys
        if (e.key.toLowerCase() === 'p') setSelectedMethod('pos');
        if (e.key.toLowerCase() === 't') setSelectedMethod('bank_transfer');
        if (e.key.toLowerCase() === 'c') setSelectedMethod('cash');
        if (e.key.toLowerCase() === 'r') setSelectedMethod('credit');
        
        // Enter to complete sale (exact payment)
        if (e.key === 'Enter' && payment.payments.length === 0) {
          e.preventDefault();
          handleCompleteSale();
        }
      };

      window.addEventListener('keydown', handleKeyPress);
      return () => window.removeEventListener('keydown', handleKeyPress);
    }
  }, [open, payment.payments.length]);

  const handleAddPayment = () => {
    const amountNum = parseFloat(amount);
    
    if (isNaN(amountNum) || amountNum <= 0) {
      toast.error('Please enter a valid amount');
      return;
    }

    payment.addPayment(selectedMethod, amountNum, reference);
    setAmount('');
    setReference('');
    toast.success(`₦${amountNum.toLocaleString()} ${selectedMethod} payment added`);
  };

  const handleCompleteSale = async () => {
    // Calculate the payment amount to use
    let paymentAmount: number;
    let autoAddedPayment = false;
    
    // If no payments added, determine amount to use
    if (payment.payments.length === 0) {
      const amountNum = parseFloat(amount);
      
      // If amount field is empty or invalid, default to exact total
      // Otherwise use the entered amount
      if (!amount || amount.trim() === '' || isNaN(amountNum) || amountNum <= 0) {
        paymentAmount = cart.grandTotal;
      } else {
        paymentAmount = amountNum;
      }
      
      // Validate payment amount (except for credit)
      if (selectedMethod !== 'credit') {
        // For cash, allow overpayment (change will be calculated)
        if (selectedMethod === 'cash') {
          // Cash can be any amount >= total
          if (paymentAmount < cart.grandTotal) {
            toast.error(`Payment amount (₦${paymentAmount.toLocaleString()}) is less than total (₦${cart.grandTotal.toLocaleString()})`);
            return;
          }
        } else {
          // For other methods (POS, Transfer), must be exact or more
          if (paymentAmount < cart.grandTotal) {
            toast.error(`Payment amount (₦${paymentAmount.toLocaleString()}) is less than total (₦${cart.grandTotal.toLocaleString()})`);
            return;
          }
        }
      }
      
      // Auto-add payment
      payment.addPayment(selectedMethod, paymentAmount, reference);
      autoAddedPayment = true;
    }

    // Calculate totals for confirmation
    // If we just auto-added a payment, calculate manually to avoid React state batching issue
    let totalPaid: number;
    if (autoAddedPayment) {
      // Calculate manually including the just-added payment
      const existingTotal = payment.payments.reduce((sum, p) => sum + p.amount, 0);
      totalPaid = existingTotal + paymentAmount;
    } else {
      // Use the hook's calculated value
      totalPaid = payment.totalPaid;
    }
    
    const change = Math.max(0, totalPaid - cart.grandTotal);

    // Close the payment drawer modal before showing Swal to prevent blocking
    onClose();

    // Confirm
    const result = await Swal.fire({
      title: 'Complete Sale?',
      html: `
        <div class="text-left">
          <p class="mb-2"><strong>Total:</strong> ₦${cart.grandTotal.toLocaleString()}</p>
          <p class="mb-2"><strong>Paid:</strong> ₦${totalPaid.toLocaleString()}</p>
          ${change > 0 ? `<p class="mb-2 text-green-600"><strong>Change:</strong> ₦${change.toLocaleString()}</p>` : ''}
        </div>
      `,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Complete Sale',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#16a34a',
    });

    if (!result.isConfirmed) {
      // If cancelled and auto-added payment, remove it
      if (autoAddedPayment) {
        payment.clearPayments();
      }
      // Don't need to reopen modal - user cancelled
      return;
    }

    setCompleting(true);

    try {
      // Build payments array
      // If we auto-added a payment, include it manually (state might not have updated yet)
      let paymentsToSend;
      if (autoAddedPayment) {
        paymentsToSend = [
          ...payment.payments,
          {
            method: selectedMethod,
            amount: paymentAmount,
            reference: reference || undefined,
          }
        ];
      } else {
        paymentsToSend = payment.payments;
      }
      
      // Calculate discount from the discount inputs
      const calculatedDiscountPercentage = discountType === 'percentage' && discountValue 
        ? parseFloat(discountValue) 
        : undefined;
      const calculatedDiscountAmount = discountType === 'amount' && discountValue 
        ? parseFloat(discountValue) 
        : undefined;
      
      const saleData = {
        items: cart.items.map(item => ({
          product_id: item.product_id,
          quantity: item.quantity,
          unit_price: item.unit_price,
          unit_type: item.unit_type,
          discount: item.discount,
        })),
        payments: paymentsToSend.map(p => ({
          method: p.method,
          amount: p.amount,
          reference: p.reference,
        })),
        customer_name: customerName || undefined,
        discount_percentage: calculatedDiscountPercentage,
        discount_amount: calculatedDiscountAmount,
      };

      const response = await posApi.completeSale(saleData);

      // Success
      await Swal.fire({
        title: 'Sale Completed!',
        html: `
          <div class="text-left">
            <p class="mb-2"><strong>Sale #:</strong> ${response.sale.sale_number}</p>
            <p class="mb-2"><strong>Total:</strong> ₦${cart.grandTotal.toLocaleString()}</p>
            ${response.change > 0 ? `<p class="text-green-600 text-lg font-bold">Change: ₦${response.change.toLocaleString()}</p>` : ''}
          </div>
        `,
        icon: 'success',
        confirmButtonText: 'Print Receipt',
        showCancelButton: true,
        cancelButtonText: 'Close',
      }).then((result) => {
        if (result.isConfirmed) {
          // Open thermal receipt in new window (80mm POS printer optimized)
          const token = localStorage.getItem('brilliant_auth_token');
          const backendUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000';
          window.open(`${backendUrl}/admin/receipts/${response.sale.id}?token=${token}`, '_blank');
        }
      });

      // Clear cart and close
      cart.clearCart();
      payment.clearPayments();
      onClose();

    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to complete sale');
      // Remove auto-added payment on error
      if (autoAddedPayment) {
        payment.clearPayments();
      }
    } finally {
      setCompleting(false);
    }
  };

  const handleClose = () => {
    payment.clearPayments();
    onClose();
  };

  return (
    <Sheet open={open} onOpenChange={handleClose}>
      <SheetContent side="right" className="w-full sm:max-w-2xl overflow-y-auto">
        <SheetHeader>
          <SheetTitle className="text-2xl">Payment — ₦{cart.grandTotal.toLocaleString()}</SheetTitle>
          <SheetDescription>
            Select payment method and press Enter for exact payment, or enter amount for cash with change
          </SheetDescription>
        </SheetHeader>

        <div className="mt-6 space-y-6">
          {/* Payment Method Selection */}
          <div>
            <Label className="text-base mb-3 block">Payment Method</Label>
            <div className="grid grid-cols-3 gap-2">
              {paymentMethods.map((method) => {
                const Icon = method.icon;
                return (
                  <Button
                    key={method.value}
                    variant={selectedMethod === method.value ? 'default' : 'outline'}
                    className="h-20 flex flex-col gap-2"
                    onClick={() => setSelectedMethod(method.value)}
                  >
                    <Icon className="h-5 w-5" />
                    <span className="text-xs">{method.label}</span>
                    <span className="text-[10px] opacity-70">({method.hotkey})</span>
                  </Button>
                );
              })}
            </div>
          </div>

          {/* Discount Input (Percentage or Amount Toggle) */}
          <div>
            <Label className="text-base mb-2 block">Discount (optional)</Label>
            <div className="flex gap-2 mb-2">
              <Button
                type="button"
                variant={discountType === 'percentage' ? 'default' : 'outline'}
                size="sm"
                onClick={() => setDiscountType('percentage')}
                className="flex-1"
              >
                Percentage (%)
              </Button>
              <Button
                type="button"
                variant={discountType === 'amount' ? 'default' : 'outline'}
                size="sm"
                onClick={() => setDiscountType('amount')}
                className="flex-1"
              >
                Amount (₦)
              </Button>
            </div>
            <div className="relative">
              {discountType === 'amount' && (
                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-lg font-semibold">₦</span>
              )}
              <Input
                type="number"
                value={discountValue}
                onChange={(e) => setDiscountValue(e.target.value)}
                placeholder={discountType === 'percentage' ? 'Enter percentage (e.g., 10)' : 'Enter amount (e.g., 500)'}
                className={`h-12 text-lg ${discountType === 'amount' ? 'pl-8' : ''}`}
                min="0"
                max={discountType === 'percentage' ? '100' : undefined}
                step="0.01"
              />
              {discountType === 'percentage' && discountValue && (
                <span className="absolute right-3 top-1/2 -translate-y-1/2 text-lg font-semibold">%</span>
              )}
            </div>
            {discountValue && parseFloat(discountValue) > 0 && (
              <p className="text-sm text-blue-600 dark:text-blue-400 font-medium mt-2">
                Discount: {discountType === 'percentage' 
                  ? `${discountValue}% (₦${((cart.subtotal * parseFloat(discountValue)) / 100).toLocaleString()})`
                  : `₦${parseFloat(discountValue).toLocaleString()}`
                }
              </p>
            )}
          </div>

          {/* Amount Input (Optional for exact payment) */}
          <div>
            <Label htmlFor="amount" className="text-base">Amount (optional)</Label>
            <p className="text-sm text-muted-foreground mb-2">
              Use this only if the customer gives you more money than the total so you can give them change.
            </p>
            <div className="flex gap-2">
              <div className="flex-1 relative">
                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-lg font-semibold">₦</span>
                <Input
                  id="amount"
                  type="number"
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                  placeholder="Leave empty for exact amount"
                  className="pl-8 h-12 text-lg"
                  min="0"
                  step="0.01"
                />
              </div>
            </div>
            {amount && parseFloat(amount) > cart.grandTotal && (
              <p className="text-sm text-green-600 dark:text-green-400 font-medium mt-2">
                Change: ₦{(parseFloat(amount) - cart.grandTotal).toLocaleString()}
              </p>
            )}
          </div>

          {/* Reference (for pos/transfer) */}
          {['pos', 'bank_transfer'].includes(selectedMethod) && (
            <div>
              <Label htmlFor="reference" className="text-base">Reference / Transaction ID (optional)</Label>
              <Input
                id="reference"
                type="text"
                value={reference}
                onChange={(e) => setReference(e.target.value)}
                placeholder="Transaction reference"
                className="mt-2"
              />
            </div>
          )}

          {/* Add Payment Button (for split payments) */}
          {payment.payments.length > 0 && (
            <Button onClick={handleAddPayment} variant="outline" className="w-full h-12 text-lg">
              Add Another Payment (Split Payment)
            </Button>
          )}

          {payment.payments.length > 0 && <Separator />}

          {/* Payments List */}
          {payment.payments.length > 0 && (
            <div>
              <Label className="text-base mb-3 block">Payments Added</Label>
              <div className="space-y-2">
                {payment.payments.map((p, index) => (
                  <div
                    key={index}
                    className="flex items-center justify-between p-4 border rounded-lg bg-background"
                  >
                    <div className="flex-1">
                      <p className="font-semibold capitalize">{p.method}</p>
                      {p.reference && <p className="text-sm text-muted-foreground">Ref: {p.reference}</p>}
                    </div>
                    <p className="font-bold text-lg mr-4">₦{p.amount.toLocaleString()}</p>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => payment.removePayment(index)}
                      className="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30"
                    >
                      <X className="h-4 w-4" />
                    </Button>
                  </div>
                ))}
              </div>
            </div>
          )}

          {payment.payments.length > 0 && <Separator />}

          {/* Summary */}
          <div className="space-y-3 bg-green-50 dark:bg-green-950/30 p-4 rounded-lg">
            <div className="flex justify-between text-lg">
              <span>Total:</span>
              <span className="font-bold">₦{cart.grandTotal.toLocaleString()}</span>
            </div>
            {payment.payments.length > 0 && (
              <>
                <div className="flex justify-between text-lg">
                  <span>Paid:</span>
                  <span className="font-bold text-green-600 dark:text-green-400">₦{payment.totalPaid.toLocaleString()}</span>
                </div>
                {payment.balance > 0 && (
                  <div className="flex justify-between text-lg">
                    <span>Balance:</span>
                    <span className="font-bold text-red-600 dark:text-red-400">₦{payment.balance.toLocaleString()}</span>
                  </div>
                )}
                {payment.change > 0 && (
                  <div className="flex justify-between text-xl">
                    <span>Change:</span>
                    <span className="font-bold text-green-600 dark:text-green-400">₦{payment.change.toLocaleString()}</span>
                  </div>
                )}
              </>
            )}
          </div>

          {/* Complete Button */}
          <Button
            onClick={handleCompleteSale}
            disabled={completing}
            className="w-full h-14 text-xl font-bold bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-600"
            size="lg"
          >
            {completing ? 'Processing...' : payment.payments.length === 0 ? 'Complete Sale' : `Complete Sale${payment.change > 0 ? ` (Change: ₦${payment.change.toLocaleString()})` : ''}`}
          </Button>
        </div>
      </SheetContent>
    </Sheet>
  );
}
