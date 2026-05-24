import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Printer, Trash2, CreditCard, ShoppingCart } from 'lucide-react';
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from '@/components/ui/sheet';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog';
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
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { salesApi } from '@/app/api/sales';
import { posApi } from '@/app/api/pos';
import { useAuth } from '@/app/auth/AuthContext';
import { isOwner, isManager } from '@/app/auth/guards';
import { toast } from 'sonner';

const PAYMENT_METHOD_LABELS: Record<string, string> = {
  cash: 'Cash',
  pos: 'POS Terminal',
  bank_transfer: 'Bank Transfer',
  credit: 'Credit',
  card: 'Card',
  online: 'Online Transfer',
};

interface SaleDetailDrawerProps {
  open: boolean;
  onClose: () => void;
  saleId: number | null;
}

export default function SaleDetailDrawer({ open, onClose, saleId }: SaleDetailDrawerProps) {
  const navigate = useNavigate();
  const { user } = useAuth();
  const canViewProfit = isOwner(user) || isManager(user);
  const canVoid = isOwner(user) || isManager(user);

  const [sale, setSale] = useState<any>(null);
  const [loading, setLoading] = useState(false);

  // Void state
  const [voidOpen, setVoidOpen] = useState(false);
  const [voidReason, setVoidReason] = useState('');
  const [voiding, setVoiding] = useState(false);

  // Record payment state
  const [paymentOpen, setPaymentOpen] = useState(false);
  const [paymentAmount, setPaymentAmount] = useState('');
  const [paymentMethod, setPaymentMethod] = useState('cash');
  const [paymentReference, setPaymentReference] = useState('');
  const [paymentNotes, setPaymentNotes] = useState('');
  const [recording, setRecording] = useState(false);

  useEffect(() => {
    if (open && saleId) {
      loadSale();
    }
  }, [open, saleId]);

  const loadSale = async () => {
    if (!saleId) return;
    try {
      setLoading(true);
      const data = await salesApi.getById(saleId);
      setSale(data);
    } catch {
      toast.error('Failed to load sale details');
    } finally {
      setLoading(false);
    }
  };

  const handlePrintReceipt = async () => {
    if (!saleId) return;
    try {
      const receiptData = await salesApi.generateReceiptToken(saleId);
      // Receipt routes are on web routes, not under /api
      const backendOrigin = window.location.port === '5173' || window.location.port === '8080'
        ? 'http://localhost:8000'
        : window.location.origin;
      window.open(`${backendOrigin}/receipt/${receiptData.token}`, '_blank');
    } catch {
      toast.error('Failed to open receipt');
    }
  };

  const handleVoidSale = async () => {
    if (!saleId || !voidReason.trim()) return;
    try {
      setVoiding(true);
      await posApi.voidSale(saleId, { reason: voidReason });
      toast.success('Sale voided successfully');
      setVoidOpen(false);
      setVoidReason('');
      loadSale();
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to void sale');
    } finally {
      setVoiding(false);
    }
  };

  const handleRecordPayment = async () => {
    if (!saleId || !paymentAmount) return;
    try {
      setRecording(true);
      await salesApi.recordPaymentExtended(saleId, {
        amount: parseFloat(paymentAmount),
        method: paymentMethod,
        reference: paymentReference || undefined,
        notes: paymentNotes || undefined,
      });
      toast.success('Payment recorded successfully');
      setPaymentOpen(false);
      setPaymentAmount('');
      setPaymentReference('');
      setPaymentNotes('');
      loadSale();
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to record payment');
    } finally {
      setRecording(false);
    }
  };

  const handleRecreateSale = () => {
    if (!sale) return;

    const cartItems = (sale.items || []).map((item: any) => ({
      product_id: item.product_id,
      product_name: item.product?.name || 'Unknown',
      sku: item.product?.sku || '',
      quantity: item.quantity,
      unit_price: parseFloat(item.unit_price) || 0,
      unit_type: item.unit_type || 'piece',
      unit_type_id: item.product_unit_type_id || null,
      conversion_factor: item.conversion_factor || 1,
      cost_price: parseFloat(item.unit_cost) || 0,
      discount: 0,
      stock_available: item.product?.stock_quantity ?? 999,
    }));

    localStorage.setItem('brilliant_pos_cart', JSON.stringify(cartItems));
    if (sale.customer_name) {
      localStorage.setItem('brilliant_pos_customer', JSON.stringify({ name: sale.customer_name, phone: sale.customer_phone }));
    }

    onClose();
    navigate('/admin/pos');
    toast.success('Items loaded into POS cart');
  };

  const formatCurrency = (amount: number | string | null | undefined) => {
    if (amount == null) return '₦0.00';
    return `₦${Number(amount).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  };

  const isUnpaid = sale && (sale.payment_status === 'unpaid' || sale.payment_status === 'partially_paid');

  return (
    <>
      <Sheet open={open} onOpenChange={onClose}>
        <SheetContent side="right" className="w-full sm:max-w-2xl overflow-y-auto">
          <SheetHeader>
            <SheetTitle>Sale Details</SheetTitle>
            <SheetDescription>
              {sale ? sale.sale_number : 'Loading...'}
            </SheetDescription>
          </SheetHeader>

          {loading ? (
            <div className="py-12 text-center text-muted-foreground">Loading...</div>
          ) : !sale ? (
            <div className="py-12 text-center text-muted-foreground">Sale not found</div>
          ) : (
            <div className="mt-6 space-y-6">
              {/* Header Info */}
              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-muted-foreground">
                      {new Date(sale.sale_date).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short' })}
                    </p>
                    <p className="text-sm text-muted-foreground">Cashier: {sale.cashier?.name || 'Unknown'}</p>
                  </div>
                  <div className="flex gap-2">
                    {getSaleTypeBadge(sale.sale_type, sale.payment_status)}
                    {getPaymentStatusBadge(sale.payment_status)}
                  </div>
                </div>
                {sale.customer_name && (
                  <p className="text-sm">Customer: <span className="font-medium">{sale.customer_name}</span>
                    {sale.customer_phone && <span className="text-muted-foreground"> ({sale.customer_phone})</span>}
                  </p>
                )}
                {sale.status === 'voided' && (
                  <div className="p-3 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 rounded-lg">
                    <p className="text-sm font-medium text-red-800 dark:text-red-400">VOIDED</p>
                    {sale.void_reason && <p className="text-sm text-red-600 dark:text-red-400">Reason: {sale.void_reason}</p>}
                  </div>
                )}
              </div>

              {/* Items Table */}
              <div className="border rounded-lg">
                <table className="w-full text-sm">
                  <thead className="bg-muted/50">
                    <tr>
                      <th className="text-left p-2">Item</th>
                      <th className="text-center p-2">Qty</th>
                      <th className="text-right p-2">Price</th>
                      <th className="text-right p-2">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(sale.items || []).map((item: any) => (
                      <tr key={item.id} className="border-t">
                        <td className="p-2">
                          <div className="font-medium">{item.product?.name || 'Unknown'}</div>
                          {item.unit_type && item.unit_type !== 'piece' && (
                            <div className="text-xs text-muted-foreground">{item.unit_type}</div>
                          )}
                        </td>
                        <td className="text-center p-2">{item.quantity}</td>
                        <td className="text-right p-2">{formatCurrency(item.unit_price)}</td>
                        <td className="text-right p-2 font-medium">{formatCurrency(item.line_total || (item.quantity * parseFloat(item.unit_price)))}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Totals */}
              <div className="space-y-1 text-sm">
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Subtotal</span>
                  <span>{formatCurrency(sale.subtotal)}</span>
                </div>
                {parseFloat(sale.discount_amount || 0) > 0 && (
                  <div className="flex justify-between text-red-600 dark:text-red-400">
                    <span>Discount</span>
                    <span>-{formatCurrency(sale.discount_amount)}</span>
                  </div>
                )}
                <div className="flex justify-between font-bold text-base border-t pt-2">
                  <span>Grand Total</span>
                  <span>{formatCurrency(sale.total_amount)}</span>
                </div>
              </div>

              {/* Payment Breakdown */}
              {(sale.payments && sale.payments.length > 0) && (
                <div className="border rounded-lg p-3 space-y-2">
                  <p className="font-medium text-sm">Payments</p>
                  {sale.payments.map((payment: any) => (
                    <div key={payment.id} className="flex justify-between text-sm">
                      <span>{PAYMENT_METHOD_LABELS[payment.method] || payment.method}</span>
                      <span className="font-medium">{formatCurrency(payment.amount)}</span>
                    </div>
                  ))}
                  {parseFloat(sale.amount_due || 0) > 0 && (
                    <div className="flex justify-between text-sm text-red-600 dark:text-red-400 border-t pt-1">
                      <span>Amount Due</span>
                      <span className="font-medium">{formatCurrency(sale.amount_due)}</span>
                    </div>
                  )}
                </div>
              )}

              {/* Profit Section (owner/manager only) */}
              {canViewProfit && (
                <div className="border rounded-lg p-3 space-y-1 text-sm">
                  <p className="font-medium text-sm mb-2">Profit Details</p>
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Cost of Goods</span>
                    <span>{formatCurrency(sale.cost_of_goods_sold || sale.cost_of_goods)}</span>
                  </div>
                  <div className="flex justify-between text-green-600 dark:text-green-400">
                    <span>Gross Profit</span>
                    <span className="font-medium">{formatCurrency(sale.gross_profit || sale.total_profit)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Margin</span>
                    <span>{(parseFloat(sale.profit_margin) || 0).toFixed(1)}%</span>
                  </div>
                </div>
              )}

              {/* Actions */}
              <div className="flex flex-wrap gap-2 pt-4 border-t">
                <Button variant="outline" onClick={handlePrintReceipt}>
                  <Printer className="h-4 w-4 mr-2" />
                  Print Receipt
                </Button>
                {canVoid && sale.status !== 'voided' && (
                  <Button variant="outline" className="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-400" onClick={() => setVoidOpen(true)}>
                    <Trash2 className="h-4 w-4 mr-2" />
                    Void Sale
                  </Button>
                )}
                {isUnpaid && canViewProfit && (
                  <Button variant="outline" onClick={() => setPaymentOpen(true)}>
                    <CreditCard className="h-4 w-4 mr-2" />
                    Record Payment
                  </Button>
                )}
                {sale.status !== 'voided' && (
                  <Button variant="outline" onClick={handleRecreateSale}>
                    <ShoppingCart className="h-4 w-4 mr-2" />
                    Recreate Sale
                  </Button>
                )}
              </div>
            </div>
          )}
        </SheetContent>
      </Sheet>

      {/* Void Confirmation */}
      <AlertDialog open={voidOpen} onOpenChange={setVoidOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Void This Sale?</AlertDialogTitle>
            <AlertDialogDescription>
              This action cannot be undone. Stock will be restored and the sale will be marked as voided.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <div className="py-2">
            <Label>Reason for voiding</Label>
            <Input
              value={voidReason}
              onChange={(e) => setVoidReason(e.target.value)}
              placeholder="Enter reason..."
              className="mt-1"
            />
          </div>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleVoidSale}
              disabled={voiding || !voidReason.trim()}
              className="bg-red-600 hover:bg-red-700"
            >
              {voiding ? 'Voiding...' : 'Void Sale'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Record Payment Dialog */}
      <Dialog open={paymentOpen} onOpenChange={setPaymentOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Record Payment</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="p-3 bg-muted rounded-lg text-sm">
              <div className="flex justify-between">
                <span>Total Amount</span>
                <span className="font-medium">{formatCurrency(sale?.total_amount)}</span>
              </div>
              <div className="flex justify-between">
                <span>Already Paid</span>
                <span className="font-medium">{formatCurrency(sale?.amount_paid)}</span>
              </div>
              <div className="flex justify-between text-red-600 dark:text-red-400 font-medium">
                <span>Balance Due</span>
                <span>{formatCurrency(sale?.amount_due)}</span>
              </div>
            </div>
            <div>
              <Label>Amount</Label>
              <Input
                type="number"
                min="0.01"
                step="0.01"
                value={paymentAmount}
                onChange={(e) => setPaymentAmount(e.target.value)}
                placeholder={String(sale?.amount_due || '0')}
              />
            </div>
            <div>
              <Label>Payment Method</Label>
              <Select value={paymentMethod} onValueChange={setPaymentMethod}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="cash">Cash</SelectItem>
                  <SelectItem value="pos">POS Terminal</SelectItem>
                  <SelectItem value="bank_transfer">Bank Transfer</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label>Reference (Optional)</Label>
              <Input
                value={paymentReference}
                onChange={(e) => setPaymentReference(e.target.value)}
                placeholder="Transaction reference..."
              />
            </div>
            <div>
              <Label>Notes (Optional)</Label>
              <Input
                value={paymentNotes}
                onChange={(e) => setPaymentNotes(e.target.value)}
                placeholder="Payment notes..."
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setPaymentOpen(false)}>Cancel</Button>
            <Button onClick={handleRecordPayment} disabled={recording || !paymentAmount}>
              {recording ? 'Recording...' : 'Record Payment'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

function getSaleTypeBadge(type: string, paymentStatus?: string) {
  const label = PAYMENT_METHOD_LABELS[type] || type;
  if (type === 'credit') {
    if (paymentStatus === 'paid') return <Badge variant="secondary">{label}</Badge>;
    return <Badge variant="destructive">{label}</Badge>;
  }
  return <Badge variant="outline">{label}</Badge>;
}

function getPaymentStatusBadge(status: string) {
  const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    paid: 'default',
    partially_paid: 'secondary',
    unpaid: 'destructive',
  };
  const variant = variants[status] || 'outline';
  const label = status === 'partially_paid' ? 'Partial' : status.charAt(0).toUpperCase() + status.slice(1);
  return <Badge variant={variant}>{label}</Badge>;
}