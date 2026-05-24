import { useState, useEffect, useCallback } from 'react';
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
import { useAuth } from '@/app/auth/AuthContext';
import { isOwner, isManager } from '@/app/auth/guards';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';

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
  const { user } = useAuth();
  const canUseCredit = isOwner(user) || isManager(user);
  const receiptPromptEnabled = localStorage.getItem('brilliant_pos_receipt_prompt') !== '0';
  const visiblePaymentMethods = paymentMethods.filter(
    (m) => m.value !== 'credit' || canUseCredit
  );

  // Auto-select POS Terminal as default (most common in Nigeria)
  const [selectedMethod, setSelectedMethod] = useState<PaymentMethod>('pos');
  const [amount, setAmount] = useState('');
  const [reference, setReference] = useState('');
  const [completing, setCompleting] = useState(false);
  const [showCompleteConfirm, setShowCompleteConfirm] = useState(false);
  const [confirmData, setConfirmData] = useState<{
    totalPaid: number;
    change: number;
    paymentAmount: number;
    autoAddedPayment: boolean;
    selectedMethod: PaymentMethod;
    reference: string;
  } | null>(null);
  const [showSaleResult, setShowSaleResult] = useState(false);
  const [saleResult, setSaleResult] = useState<{
    saleNumber: string;
    total: number;
    change: number;
    saleId: number;
  } | null>(null);

  // Discount state
  const [discountType, setDiscountType] = useState<'percentage' | 'amount'>('percentage');
  const [discountValue, setDiscountValue] = useState('');

  // Reset state when drawer opens
  useEffect(() => {
    if (open) {
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
      } else {
        setDiscountType('percentage');
      }
    }
  }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

  // Sync discount changes back to cart so displayed totals stay consistent
  useEffect(() => {
    if (!open) return;
    const value = parseFloat(discountValue);
    if (discountType === 'percentage' && !isNaN(value) && value > 0) {
      cart.applyGlobalDiscount(value, 0);
    } else if (discountType === 'amount' && !isNaN(value) && value > 0) {
      cart.applyGlobalDiscount(0, value);
    } else if (discountValue === '' || discountValue === '0') {
      cart.applyGlobalDiscount(0, 0);
    }
  }, [discountType, discountValue, open]); // eslint-disable-line react-hooks/exhaustive-deps

  const handleCompleteSale = useCallback(() => {
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

      autoAddedPayment = true;
    } else {
      paymentAmount = 0;
    }

    // Calculate totals for confirmation
    let totalPaid: number;
    if (autoAddedPayment) {
      // Calculate manually including the would-be-added payment
      const existingTotal = payment.payments.reduce((sum, p) => sum + p.amount, 0);
      totalPaid = existingTotal + paymentAmount;
    } else {
      // Use the hook's calculated value
      totalPaid = payment.totalPaid;
    }

    const change = Math.max(0, totalPaid - cart.grandTotal);

    // Store confirmation data and show dialog
    setConfirmData({
      totalPaid,
      change,
      paymentAmount,
      autoAddedPayment,
      selectedMethod,
      reference,
    });
    setShowCompleteConfirm(true);
  }, [amount, selectedMethod, reference, payment.payments, payment.totalPaid, cart.grandTotal]);

  // Keyboard shortcuts — only when drawer is open
  useEffect(() => {
    if (!open) return;

    const handleKeyPress = (e: KeyboardEvent) => {
      // Don't interfere with input fields
      if (e.target instanceof HTMLInputElement) return;

      // Method selection hotkeys
      if (e.key.toLowerCase() === 'p') setSelectedMethod('pos');
      if (e.key.toLowerCase() === 't') setSelectedMethod('bank_transfer');
      if (e.key.toLowerCase() === 'c') setSelectedMethod('cash');
      if (e.key.toLowerCase() === 'r' && canUseCredit) setSelectedMethod('credit');

      // Enter to complete sale (exact payment)
      if (e.key === 'Enter' && payment.payments.length === 0) {
        e.preventDefault();
        handleCompleteSale();
      }
    };

    window.addEventListener('keydown', handleKeyPress);
    return () => window.removeEventListener('keydown', handleKeyPress);
  }, [open, canUseCredit, payment.payments.length, handleCompleteSale]);

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

  const handleConfirmCompleteSale = async () => {
    if (!confirmData || completing) return;
    setShowCompleteConfirm(false);

    // Add payment if auto-added
    if (confirmData.autoAddedPayment) {
      payment.addPayment(confirmData.selectedMethod, confirmData.paymentAmount, confirmData.reference);
    }

    setCompleting(true);

    try {
      // Build payments array
      // If we auto-added a payment, include it manually (state might not have updated yet)
      let paymentsToSend;
      if (confirmData.autoAddedPayment) {
        paymentsToSend = [
          ...payment.payments,
          {
            method: confirmData.selectedMethod,
            amount: confirmData.paymentAmount,
            reference: confirmData.reference || undefined,
          }
        ];
      } else {
        paymentsToSend = payment.payments;
      }

      // Use the cart's discount values (single source of truth)
      const saleData = {
        items: cart.items.map(item => ({
          product_id: item.product_id,
          quantity: item.quantity,
          unit_price: item.unit_price,
          unit_type: item.unit_type,
          product_unit_type_id: item.unit_type_id,
          conversion_factor: item.conversion_factor,
          discount: item.discount,
        })),
        payments: paymentsToSend.map(p => ({
          method: p.method,
          amount: p.amount,
          reference: p.reference,
        })),
        customer_name: customerName || undefined,
        discount_percentage: cart.discountPercentage || undefined,
        discount_amount: cart.discountAmount || undefined,
      };

      const response = await posApi.completeSale(saleData);

      // Show toast first — guaranteed visible even if AlertDialog has z-index issues with Sheet
      toast.success(`Sale ${response.sale.sale_number} completed — ₦${cart.grandTotal.toLocaleString()}`);

      // Close the payment drawer BEFORE showing the result dialog
      // This avoids nested-modal z-index conflicts between Sheet and AlertDialog
      onClose();

      setSaleResult({
        saleNumber: response.sale.sale_number,
        total: cart.grandTotal,
        change: response.change,
        saleId: response.sale.id,
      });
      setShowSaleResult(true);

    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to complete sale');
      // Remove auto-added payment on error
      if (confirmData.autoAddedPayment) {
        payment.clearPayments();
      }
    } finally {
      setCompleting(false);
    }
  };

  const handlePrintReceipt = async () => {
    if (!saleResult) return;
    try {
      const receiptData = await posApi.generateReceiptToken(saleResult.saleId);
      // Receipt routes are on the web routes, not under /api
      // Vite dev server proxies /api to backend, but receipt URLs need the backend directly
      const backendOrigin = window.location.port === '5173' || window.location.port === '8080'
        ? 'http://localhost:8000'
        : window.location.origin;
      window.open(`${backendOrigin}/receipt/${receiptData.token}`, '_blank');
    } catch {
      const backendOrigin = window.location.port === '5173' || window.location.port === '8080'
        ? 'http://localhost:8000'
        : window.location.origin;
      window.open(`${backendOrigin}/admin/sales/${saleResult?.saleId}/receipt`, '_blank');
    }
  };

  const handleFinishSale = () => {
    setShowSaleResult(false);
    cart.clearCart();
    payment.clearPayments();
    // Drawer is already closed by onClose() called after API success
  };

  const handleClose = () => {
    // Don't clear state if a sale was just completed — handleFinishSale handles that
    if (showSaleResult) return;
    payment.clearPayments();
    // Reset discount in cart when closing without completing sale
    cart.applyGlobalDiscount(0, 0);
    onClose();
  };

  return (
  <>
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
              {visiblePaymentMethods.map((method) => {
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
            <p className="text-sm text-muted-foreground dark:text-muted-foreground/80 mb-2">
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
                      {p.reference && <p className="text-sm text-muted-foreground dark:text-muted-foreground/80">Ref: {p.reference}</p>}
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

    {/* Complete Sale Confirmation */}
    <AlertDialog open={showCompleteConfirm} onOpenChange={setShowCompleteConfirm}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Complete Sale?</AlertDialogTitle>
          <AlertDialogDescription asChild>
            <div className="text-left">
              <p className="mb-2"><strong>Total:</strong> ₦{cart.grandTotal.toLocaleString()}</p>
              <p className="mb-2"><strong>Paid:</strong> ₦{(confirmData?.totalPaid ?? 0).toLocaleString()}</p>
              {(confirmData?.change ?? 0) > 0 && (
                <p className="mb-2 text-green-600 dark:text-green-400"><strong>Change:</strong> ₦{(confirmData?.change ?? 0).toLocaleString()}</p>
              )}
            </div>
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancel</AlertDialogCancel>
          <AlertDialogAction onClick={handleConfirmCompleteSale} className="bg-green-600 hover:bg-green-700">
            Complete Sale
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    {/* Sale Result */}
    <AlertDialog open={showSaleResult} onOpenChange={(open) => { if (!open) handleFinishSale(); }}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Sale Completed!</AlertDialogTitle>
          <AlertDialogDescription asChild>
            <div className="text-left">
              <p className="mb-2"><strong>Sale #:</strong> {saleResult?.saleNumber}</p>
              <p className="mb-2"><strong>Total:</strong> ₦{(saleResult?.total ?? 0).toLocaleString()}</p>
              {(saleResult?.change ?? 0) > 0 && (
                <p className="text-green-600 dark:text-green-400 text-lg font-bold">Change: ₦{(saleResult?.change ?? 0).toLocaleString()}</p>
              )}
            </div>
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel onClick={handleFinishSale}>Close</AlertDialogCancel>
          {receiptPromptEnabled && (
            <AlertDialogAction onClick={handlePrintReceipt} className="bg-blue-600 hover:bg-blue-700">
              Print Receipt
            </AlertDialogAction>
          )}
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </>
  );
}
