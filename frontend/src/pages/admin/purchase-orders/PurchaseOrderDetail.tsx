import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { ArrowLeft, Download, Package, CheckCircle2, Edit, XCircle, Clock, FileText, Calendar } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { purchaseOrderApi } from '@/app/api/purchaseOrders';
import { PurchaseOrder } from '@/types/ims';
import { toast } from 'sonner';
import Swal from 'sweetalert2';
import { MonthYearPicker } from '@/components/MonthYearPicker';
import { DatePicker } from '@/components/DatePicker';
import { useAuth } from '@/app/auth/AuthContext';

export default function PurchaseOrderDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user } = useAuth();
  const [order, setOrder] = useState<PurchaseOrder | null>(null);
  const [loading, setLoading] = useState(true);
  const [showPaymentDialog, setShowPaymentDialog] = useState(false);
  const [paymentData, setPaymentData] = useState({
    amount: '',
    payment_method: 'bank_transfer' as 'cash' | 'bank_transfer' | 'cheque' | 'card' | 'credit',
    payment_date: new Date().toISOString().split('T')[0],
    reference: '',
  });
  const [formattedAmount, setFormattedAmount] = useState('');

  useEffect(() => {
    if (id) loadOrder();
  }, [id]);

  const loadOrder = async () => {
    try {
      const data = await purchaseOrderApi.getById(parseInt(id!));
      setOrder(data);
    } catch (error: any) {
      toast.error(error.message || 'Failed to load purchase order');
    } finally {
      setLoading(false);
    }
  };

  const handleApprove = async () => {
    // Small delay to ensure any previous dialogs are closed
    await new Promise(resolve => setTimeout(resolve, 100));

    const result = await Swal.fire({
      title: 'Approve Purchase Order?',
      text: `PO: ${order?.po_number} - Total: ₦${order?.total_amount?.toLocaleString()}`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#22c55e',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'Yes, Approve',
      cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) return;

    try {
      await purchaseOrderApi.approve(parseInt(id!));
      toast.success('Purchase order approved');
      loadOrder();
    } catch (error: any) {
      toast.error(error.message || 'Failed to approve');
    }
  };

  const handleAmountChange = (value: string) => {
    const numericValue = value.replace(/[^\d.]/g, '');
    setPaymentData({ ...paymentData, amount: numericValue });
    if (numericValue) {
      const parts = numericValue.split('.');
      parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
      setFormattedAmount(parts.join('.'));
    } else {
      setFormattedAmount('');
    }
  };

  const handlePaymentDialogOpen = () => {
    if (!order) return;
    const balance = (order.total_amount || 0) - (order.amount_paid || 0);
    const defaultPaymentMethod = order.payment_method || 'credit';
    
    setPaymentData({
      amount: balance.toString(),
      payment_method: defaultPaymentMethod as any,
      payment_date: new Date().toISOString().split('T')[0],
      reference: '',
    });
    setFormattedAmount(balance.toLocaleString());
    setShowPaymentDialog(true);
  };

  const balance = order ? (order.total_amount || 0) - (order.amount_paid || 0) : 0;
  const isAmountMatching = order && paymentData.amount && 
    parseFloat(paymentData.amount) === balance;

  const [showReceiveDialog, setShowReceiveDialog] = useState(false);
  const [receivingItems, setReceivingItems] = useState<any[]>([]);

  const handleReceiveClick = async () => {
    const isPaid = order?.payment_status === 'paid' || order?.payment_status === 'partially_paid';
    const isCreditPurchase = ['credit', 'credit_7', 'credit_14', 'credit_30', 'credit_60'].includes(order?.payment_method || '');
    
    // Only enforce payment for non-credit purchases
    if (!isCreditPurchase && !isPaid) {
      // Small delay for any previous dialogs
      await new Promise(resolve => setTimeout(resolve, 100));
      
      const result = await Swal.fire({
        title: 'Payment Required',
        html: `
          <p><strong>Payment must be recorded before receiving goods for cash/immediate purchases.</strong></p>
          <p class="text-sm text-muted-foreground mt-2">This ensures proper financial tracking.</p>
          <p class="text-sm text-muted-foreground mt-2">Total Amount: <strong>₦${order?.total_amount?.toLocaleString()}</strong></p>
          <p class="text-sm mt-3">Would you like to record payment now?</p>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#22c55e',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '₦ Record Payment',
        cancelButtonText: 'Cancel'
      });
      
      if (result.isConfirmed) {
        handlePaymentDialogOpen();
      }
      return;
    }
    
    // Initialize receiving quantities with batch/expiry tracking
    const items = order?.items?.map(item => ({
      id: item.id,
      product_id: item.product_id,
      product_name: item.product?.name || `Product #${item.product_id}`,
      quantity_ordered: item.quantity_ordered,
      quantity_already_received: item.quantity_received || 0,
      quantity_to_receive: item.quantity_ordered - (item.quantity_received || 0),
      unit_cost: item.unit_cost,
      batch_number: item.batch_number || '',
      manufacturing_date: item.manufacturing_date || '',
      expiry_date: item.expiry_date || '',
      track_batch: item.product?.track_batch || false,
      track_expiry: item.product?.track_expiry || false,
    })) || [];
    setReceivingItems(items);
    setShowReceiveDialog(true);
  };

  const handleConfirmReceive = async () => {
    // Validate quantities
    const hasInvalidQuantity = receivingItems.some(
      item => item.quantity_to_receive < 0 || 
      item.quantity_to_receive > (item.quantity_ordered - item.quantity_already_received)
    );

    if (hasInvalidQuantity) {
      toast.error('Invalid receive quantities. Cannot exceed ordered amount.');
      return;
    }

    const itemsToReceive = receivingItems.filter(item => item.quantity_to_receive > 0);
    
    if (itemsToReceive.length === 0) {
      toast.error('Please specify quantities to receive');
      return;
    }

    // Validate batch/expiry requirements
    for (let i = 0; i < itemsToReceive.length; i++) {
      const item = itemsToReceive[i];
      
      // Check if batch number is required
      if (item.track_batch && !item.batch_number) {
        toast.error(`Batch number is required for "${item.product_name}"`);
        return;
      }

      // Check if expiry date is required
      if (item.track_expiry && !item.expiry_date) {
        toast.error(`Expiry date is required for "${item.product_name}"`);
        return;
      }

      // Validate expiry date is in the future
      if (item.expiry_date) {
        const expiryDate = new Date(item.expiry_date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (expiryDate < today) {
          toast.error(`Expiry date must be in the future for "${item.product_name}"`);
          return;
        }
      }

      // Validate manufacturing date is before expiry date
      if (item.manufacturing_date && item.expiry_date) {
        const mfgDate = new Date(item.manufacturing_date);
        const expDate = new Date(item.expiry_date);
        
        if (mfgDate >= expDate) {
          toast.error(`Manufacturing date must be before expiry date for "${item.product_name}"`);
          return;
        }
      }
    }

    // CRITICAL FIX: Close the dialog FIRST to prevent z-index conflicts
    setShowReceiveDialog(false);

    // Small delay to ensure dialog is fully closed before showing SweetAlert
    await new Promise(resolve => setTimeout(resolve, 100));

    const result = await Swal.fire({
      title: 'Confirm Goods Receipt',
      html: `
        <p>Receive <strong>${itemsToReceive.length} item(s)</strong> and update inventory?</p>
        <p class="text-sm text-muted-foreground mt-2">This action will increase your stock levels.</p>
      `,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3b82f6',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'Yes, Update Inventory',
      cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) {
      // Re-open dialog if user cancels
      setShowReceiveDialog(true);
      return;
    }

    try {
      const payload = {
        items: itemsToReceive.map(item => ({
          product_id: item.product_id,
          quantity_received: item.quantity_to_receive,
          batch_number: item.batch_number || null,
          manufacturing_date: item.manufacturing_date || null,
          expiry_date: item.expiry_date || null,
        }))
      };
      
      await purchaseOrderApi.receive(parseInt(id!), payload);
      toast.success('Goods received and inventory updated successfully!');
      loadOrder();
    } catch (error: any) {
      toast.error(error.message || 'Failed to receive stock');
      // Re-open dialog on error so user can try again
      setShowReceiveDialog(true);
    }
  };

  const updateReceivingQuantity = (index: number, value: number) => {
    const updated = [...receivingItems];
    updated[index].quantity_to_receive = value;
    setReceivingItems(updated);
  };

  const updateReceivingField = (index: number, field: string, value: any) => {
    const updated = [...receivingItems];
    updated[index] = { ...updated[index], [field]: value };
    setReceivingItems(updated);
  };

  const handleExportPDF = async () => {
    try {
      // Get the auth token
      const token = localStorage.getItem('brilliant_auth_token');
      
      // Open PDF in new window
      const backendUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000';
      const url = `${backendUrl}/admin/purchase-orders/${id}/pdf?token=${token}`;
      
      window.open(url, '_blank');
    } catch (error: any) {
      toast.error('Failed to open PDF');
    }
  };

  const handleRecordPayment = async () => {
    if (!paymentData.amount || parseFloat(paymentData.amount) <= 0) {
      toast.error('Please enter a valid payment amount');
      return;
    }

    // Close the payment dialog first to avoid conflicts with SweetAlert
    setShowPaymentDialog(false);

    // Small delay to ensure dialog is fully closed
    await new Promise(resolve => setTimeout(resolve, 100));

    const result = await Swal.fire({
      title: 'Confirm Payment',
      html: `
        <p>Record payment of <strong>₦${parseFloat(paymentData.amount).toLocaleString()}</strong>?</p>
        <p class="text-sm text-muted-foreground mt-2">Method: ${paymentData.payment_method.replace('_', ' ').toUpperCase()}</p>
      `,
      icon: 'info',
      showCancelButton: true,
      confirmButtonColor: '#22c55e',
      cancelButtonColor: '#6b7280',
      confirmButtonText: '₦ Confirm Payment',
      cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) {
      // Re-open dialog if user cancels
      setShowPaymentDialog(true);
      return;
    }

    try {
      await purchaseOrderApi.recordPayment(parseInt(id!), {
        amount: parseFloat(paymentData.amount),
        payment_method: paymentData.payment_method,
        payment_date: paymentData.payment_date,
        reference: paymentData.reference,
      });
      toast.success('Payment recorded successfully');
      setPaymentData({
        amount: '',
        payment_method: 'bank_transfer',
        payment_date: new Date().toISOString().split('T')[0],
        reference: '',
      });
      loadOrder();
    } catch (error: any) {
      toast.error(error.message || 'Failed to record payment');
      // Re-open dialog on error
      setShowPaymentDialog(true);
    }
  };

  const handleCancelPO = async () => {
    // Small delay to ensure any previous dialogs are closed
    await new Promise(resolve => setTimeout(resolve, 100));

    const result = await Swal.fire({
      title: 'Cancel Purchase Order?',
      html: `
        <p>Enter cancellation reason:</p>
        <textarea id="swal-input-reason" class="swal2-input" style="height: 80px; width: 90%;" placeholder="Why are you cancelling this PO?"></textarea>
      `,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'Yes, Cancel PO',
      cancelButtonText: 'Keep PO',
      preConfirm: () => {
        const reason = (document.getElementById('swal-input-reason') as HTMLTextAreaElement)?.value;
        if (!reason || reason.trim().length < 5) {
          Swal.showValidationMessage('Please provide a reason (at least 5 characters)');
          return false;
        }
        return reason;
      }
    });

    if (!result.isConfirmed || !result.value) return;

    try {
      await purchaseOrderApi.cancel(parseInt(id!), { cancellation_reason: result.value });
      toast.success('Purchase order cancelled successfully');
      loadOrder();
    } catch (error: any) {
      toast.error(error.message || 'Failed to cancel purchase order');
    }
  };

  if (loading) {
    return <div className="p-4 md:p-6">Loading...</div>;
  }

  if (!order) {
    return <div className="p-4 md:p-6">Purchase order not found</div>;
  }

  // Enhanced status badge with workflow awareness
  const getEnhancedStatusInfo = () => {
    const isApproved = order.status === 'approved' || order.status === 'received' || order.status === 'completed';
    const isPaid = order.payment_status === 'paid' || order.payment_status === 'partial';
    const isReceived = order.status === 'received' || order.status === 'completed';
    const isCancelled = order.status === 'cancelled';

    let progress = 0;
    let nextAction = '';
    let statusBadge = null;

    if (isCancelled) {
      progress = 0;
      statusBadge = (
        <Badge variant="destructive" className="flex items-center gap-1">
          <XCircle className="h-3 w-3" />
          CANCELLED
        </Badge>
      );
      nextAction = 'Order Cancelled';
    } else if (!isApproved) {
      progress = 25;
      statusBadge = (
        <Badge variant="secondary" className="flex items-center gap-1 bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-400 border-yellow-300 dark:border-yellow-800">
          <Clock className="h-3 w-3" />
          PENDING APPROVAL
        </Badge>
      );
      nextAction = 'Waiting for approval';
    } else if (isApproved && !isPaid && !isReceived) {
      // Check if credit purchase (can receive without payment)
      const isCreditPurchase = ['credit', 'credit_7', 'credit_14', 'credit_30', 'credit_60'].includes(order.payment_method || '');
      
      if (isCreditPurchase) {
        progress = 60;
        statusBadge = (
          <Badge variant="default" className="flex items-center gap-1 bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-400 border-purple-300 dark:border-purple-800">
            <Package className="h-3 w-3" />
            APPROVED - READY TO RECEIVE (CREDIT)
          </Badge>
        );
        nextAction = 'Receive goods (payment due later)';
      } else {
        progress = 50;
        statusBadge = (
          <Badge variant="default" className="flex items-center gap-1 bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-400 border-blue-300 dark:border-blue-800">
            <span className="font-bold">₦</span>
            APPROVED - PAYMENT PENDING
          </Badge>
        );
        nextAction = 'Record payment';
      }
    } else if (isApproved && isPaid && !isReceived) {
      progress = 75;
      statusBadge = (
        <Badge variant="default" className="flex items-center gap-1 bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-400 border-purple-300 dark:border-purple-800">
          <Package className="h-3 w-3" />
          PAID - AWAITING DELIVERY
        </Badge>
      );
      nextAction = 'Receive goods';
    } else if (isReceived) {
      progress = 100;
      statusBadge = (
        <Badge variant="default" className="flex items-center gap-1 bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-400 border-green-300 dark:border-green-800">
          <CheckCircle2 className="h-3 w-3" />
          COMPLETED
        </Badge>
      );
      nextAction = 'All done!';
    }

    return { badge: statusBadge, progress, nextAction };
  };

  const statusInfo = getEnhancedStatusInfo();

  const isOwnerOrManager = user?.role === 'owner' || user?.role === 'manager';

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-4">
          <Button variant="ghost" size="icon" onClick={() => navigate('/admin/purchase-orders')}>
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div>
            <h1 className="text-3xl font-bold">{order.po_number}</h1>
            <p className="text-muted-foreground">Purchase Order Details</p>
          </div>
        </div>
        <div className="flex items-center space-x-2">
          {/* APPROVAL: Owner/Manager Only */}
          {isOwnerOrManager && order.status === 'pending' && (
            <Button onClick={handleApprove}>Approve</Button>
          )}
          {/* EDIT: Owner/Manager Only */}
          {isOwnerOrManager && (order.status === 'draft' || order.status === 'pending') && (
            <Button onClick={() => navigate(`/admin/purchase-orders/${id}/edit`)} variant="outline">
              <Edit className="mr-2 h-4 w-4" />
              Edit PO
            </Button>
          )}
          {/* APPROVE: Owner/Manager Only */}
          {isOwnerOrManager && order.status === 'draft' && (
            <Button onClick={handleApprove}>
              <CheckCircle2 className="mr-2 h-4 w-4" />
              Approve PO
            </Button>
          )}
          {/* Show payment button first if needed for non-credit purchases */}
          {order.payment_status !== 'paid' && order.status !== 'draft' && order.status !== 'cancelled' && (
            <Button 
              onClick={handlePaymentDialogOpen} 
              variant={order.status === 'approved' && !['credit', 'credit_7', 'credit_14', 'credit_30', 'credit_60'].includes(order.payment_method || '') ? 'default' : 'outline'}
            >
              ₦ Record Payment
            </Button>
          )}
          {order.status === 'approved' && (
            <Button onClick={handleReceiveClick}>
              <Package className="mr-2 h-4 w-4" />
              Receive Goods
            </Button>
          )}
          {/* CANCEL: Owner/Manager Only */}
          {isOwnerOrManager && !['received', 'completed', 'cancelled'].includes(order.status) && (
            <Button onClick={handleCancelPO} variant="destructive" size="sm">
              <XCircle className="mr-2 h-4 w-4" />
              Cancel PO
            </Button>
          )}
          <Button variant="outline" onClick={handleExportPDF}>
            <Download className="mr-2 h-4 w-4" />
            Export PDF
          </Button>
        </div>
      </div>

      <div className="grid gap-6 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Order Information</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            <div className="flex justify-between">
              <span className="text-muted-foreground">Status:</span>
              {statusInfo.badge}
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">Supplier:</span>
              <span className="font-medium">{order.supplier?.name}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">Order Date:</span>
              <span>{new Date(order.order_date).toLocaleDateString()}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">Payment Status:</span>
              <Badge>{order.payment_status?.toUpperCase() || 'UNPAID'}</Badge>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">Payment Method:</span>
              <span className="font-medium">{order.payment_method?.replace('_', ' ').toUpperCase() || 'CREDIT'}</span>
            </div>
            {order.payment_due_date && (
              <div className="flex justify-between">
                <span className="text-muted-foreground">Payment Due:</span>
                <span className={`font-medium ${
                  new Date(order.payment_due_date) < new Date() && order.payment_status !== 'paid'
                    ? 'text-red-600 dark:text-red-400 font-bold'
                    : new Date(order.payment_due_date).getTime() - new Date().getTime() < 3 * 24 * 60 * 60 * 1000
                    ? 'text-orange-600 dark:text-orange-400 font-bold'
                    : ''
                }`}>
                  {new Date(order.payment_due_date).toLocaleDateString()}
                  {new Date(order.payment_due_date) < new Date() && order.payment_status !== 'paid' && ' ⚠️ OVERDUE'}
                  {new Date(order.payment_due_date).getTime() - new Date().getTime() < 3 * 24 * 60 * 60 * 1000 && 
                   new Date(order.payment_due_date) >= new Date() && 
                   order.payment_status !== 'paid' && ' ⏰ DUE SOON'}
                </span>
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Financial Summary</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            <div className="flex justify-between text-lg font-bold">
              <span>Total:</span>
              <span>₦{parseFloat(order.total_amount).toLocaleString()}</span>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Items</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {order.items?.map((item, index) => (
              <div key={index} className="flex justify-between items-center border-b pb-2">
                <div>
                  <p className="font-medium">{item.product?.name || `Product #${item.product_id}`}</p>
                  <p className="text-sm text-muted-foreground">
                    Qty: {item.quantity_received || 0}/{item.quantity_ordered} @ ₦{parseFloat(item.unit_cost || 0).toLocaleString()}
                  </p>
                </div>
                <div className="text-right">
                  <p className="font-medium">₦{parseFloat(item.total_cost || (item.quantity_ordered * item.unit_cost)).toLocaleString()}</p>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Receive Goods Dialog */}
      <Dialog open={showReceiveDialog} onOpenChange={setShowReceiveDialog}>
      <DialogContent className="max-w-3xl max-h-[80vh] overflow-y-auto" aria-describedby="receive-goods-description">
        <DialogHeader>
          <DialogTitle>Receive Goods - {order?.po_number}</DialogTitle>
        </DialogHeader>
        <p id="receive-goods-description" className="sr-only">
          Review and confirm the quantities received for each item. Inventory will be updated automatically.
        </p>
        
        <div className="space-y-4 py-4">
          <p className="text-sm text-muted-foreground">
            Verify and confirm the quantities received. This will update your inventory.
          </p>
          
          <div className="space-y-3">
            {receivingItems.map((item, index) => (
              <Card key={index}>
                <CardContent className="pt-4">
                  <div className="space-y-4">
                    {/* Product Info & Quantity */}
                    <div className="grid gap-4 md:grid-cols-4">
                      <div className="md:col-span-2">
                        <Label className="text-sm font-medium">{item.product_name}</Label>
                        <p className="text-xs text-muted-foreground mt-1">
                          Ordered: {item.quantity_ordered} | Already Received: {item.quantity_already_received}
                        </p>
                        {(item.track_batch || item.track_expiry) && (
                          <div className="flex gap-2 mt-2">
                            {item.track_batch && <Badge variant="outline" className="text-xs">📦 Batch Tracking</Badge>}
                            {item.track_expiry && <Badge variant="outline" className="text-xs">📅 Expiry Tracking</Badge>}
                          </div>
                        )}
                      </div>
                      
                      <div className="space-y-2">
                        <Label htmlFor={`qty-${index}`}>Receive Qty</Label>
                        <Input
                          id={`qty-${index}`}
                          type="number"
                          min="0"
                          max={item.quantity_ordered - item.quantity_already_received}
                          value={item.quantity_to_receive}
                          onChange={(e) => updateReceivingQuantity(index, parseInt(e.target.value) || 0)}
                          className={item.quantity_to_receive > (item.quantity_ordered - item.quantity_already_received) ? 'border-red-500 dark:border-red-700' : ''}
                        />
                      </div>
                      
                      <div className="space-y-2">
                        <Label>Unit Cost</Label>
                        <p className="text-sm font-medium pt-2">₦{parseFloat(item.unit_cost || 0).toLocaleString()}</p>
                      </div>
                    </div>

                    {/* Batch & Expiry Fields */}
                    <div className="grid grid-cols-3 gap-4 pt-3 border-t">
                      <div className="space-y-2">
                        <Label className="flex items-center gap-2">
                          <Package className="h-3.5 w-3.5 text-muted-foreground" />
                          Batch Number {item.track_batch && <span className="text-red-500 dark:text-red-400">*</span>}
                        </Label>
                        <Input
                          type="text"
                          placeholder={item.track_batch ? "Required" : "Optional"}
                          value={item.batch_number || ''}
                          onChange={(e) => updateReceivingField(index, 'batch_number', e.target.value)}
                          className={item.track_batch ? 'border-blue-300 dark:border-blue-800' : ''}
                        />
                        {item.track_batch && (
                          <p className="text-xs text-muted-foreground">
                            ⚠️ Required for this product
                          </p>
                        )}
                      </div>

                      <div className="space-y-2">
                        <Label className="flex items-center gap-2">
                          <Calendar className="h-3.5 w-3.5 text-muted-foreground" />
                          Manufacturing Date (Optional)
                        </Label>
                        <MonthYearPicker
                          value={item.manufacturing_date || ''}
                          onChange={(v) => updateReceivingField(index, 'manufacturing_date', v)}
                          placeholder="Select month & year"
                          maxDate={new Date()}
                        />
                      </div>

                      <div className="space-y-2">
                        <Label className="flex items-center gap-2">
                          <Calendar className="h-3.5 w-3.5 text-orange-500 dark:text-orange-400" />
                          Expiry Date {item.track_expiry && <span className="text-red-500 dark:text-red-400">*</span>}
                        </Label>
                        <MonthYearPicker
                          value={item.expiry_date || ''}
                          onChange={(v) => updateReceivingField(index, 'expiry_date', v)}
                          placeholder={item.track_expiry ? "Required" : "Optional"}
                          minDate={new Date()}
                          className={item.track_expiry ? 'border-orange-300 dark:border-orange-800' : ''}
                        />
                        {item.track_expiry && (
                          <p className="text-xs text-muted-foreground">
                            ⚠️ Required for this product
                          </p>
                        )}
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
          
          <div className="bg-muted p-4 rounded-lg">
            <div className="flex justify-between items-center">
              <span className="font-semibold">Total Items to Receive:</span>
              <span className="text-lg font-bold">
                {receivingItems.reduce((sum, item) => sum + item.quantity_to_receive, 0)} units
              </span>
            </div>
          </div>
        </div>
        
        <DialogFooter>
          <Button variant="outline" onClick={() => setShowReceiveDialog(false)}>
            Cancel
          </Button>
          <Button onClick={handleConfirmReceive}>
            <Package className="mr-2 h-4 w-4" />
            Confirm & Update Inventory
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

      {/* Payment Recording Dialog */}
      <Dialog open={showPaymentDialog} onOpenChange={setShowPaymentDialog}>
        <DialogContent aria-describedby="payment-description">
          <DialogHeader>
            <DialogTitle>Record Payment - {order?.po_number}</DialogTitle>
          </DialogHeader>
          <p id="payment-description" className="sr-only">
            Record a payment made for this purchase order. You can record partial or full payments.
          </p>
          
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label htmlFor="payment-amount">Amount (₦)</Label>
              <Input
                id="payment-amount"
                type="text"
                placeholder="Enter payment amount"
                value={formattedAmount}
                onChange={(e) => handleAmountChange(e.target.value)}
                className={isAmountMatching ? 'border-green-500 dark:border-green-700 bg-green-50 dark:bg-green-950/30 text-green-700 dark:text-green-400 font-bold' : ''}
              />
              <p className="text-xs text-muted-foreground">
                Total: ₦{order?.total_amount?.toLocaleString()} | 
                Paid: ₦{(order?.amount_paid || 0).toLocaleString()} | 
                Balance: ₦{((order?.total_amount || 0) - (order?.amount_paid || 0)).toLocaleString()}
              </p>
              {isAmountMatching && (
                <p className="text-xs text-green-600 dark:text-green-400 font-semibold">✓ Amount matches total</p>
              )}
            </div>

            <div className="space-y-2">
              <Label>Payment Method</Label>
              <Select 
                value={paymentData.payment_method} 
                onValueChange={(value: any) => setPaymentData({ ...paymentData, payment_method: value })}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="cash">💵 Cash</SelectItem>
                  <SelectItem value="bank_transfer">🏦 Bank Transfer</SelectItem>
                  <SelectItem value="cheque">📝 Cheque</SelectItem>
                  <SelectItem value="card">💳 Card</SelectItem>
                  <SelectItem value="credit">📋 Credit (Pay Later)</SelectItem>
                </SelectContent>
              </Select>
              {order?.payment_method && (
                <p className="text-xs text-muted-foreground">
                  PO Payment Method: {order.payment_method.replace('_', ' ').toUpperCase()}
                </p>
              )}
            </div>

            <div className="space-y-2">
              <Label htmlFor="payment-date">Payment Date</Label>
              <DatePicker
                value={paymentData.payment_date}
                onChange={(v) => setPaymentData({ ...paymentData, payment_date: v })}
                placeholder="Select payment date"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="payment-reference">Reference/Note (Optional)</Label>
              <Input
                id="payment-reference"
                placeholder="Transaction ID, check number, etc."
                value={paymentData.reference}
                onChange={(e) => setPaymentData({ ...paymentData, reference: e.target.value })}
              />
            </div>
          </div>
          
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowPaymentDialog(false)}>
              Cancel
            </Button>
            <Button onClick={handleRecordPayment} className={isAmountMatching ? 'bg-green-600 dark:bg-green-700 hover:bg-green-700 dark:hover:bg-green-600' : ''}>
              ₦ Confirm Payment
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
