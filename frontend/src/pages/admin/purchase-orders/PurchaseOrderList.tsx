import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Download, Clock, CheckCircle2, DollarSign, Package, XCircle, FileText, AlertTriangle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { purchaseOrderApi } from '@/app/api/purchaseOrders';
import { PurchaseOrder } from '@/types/ims';
import { toast } from 'sonner';

export default function PurchaseOrderList() {
  const [orders, setOrders] = useState<PurchaseOrder[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadOrders();
  }, []);

  const loadOrders = async () => {
    try {
      const data = await purchaseOrderApi.getAll();
      // Sort by created_at or order_date descending (latest first)
      const sortedData = data.sort((a, b) => {
        const dateA = new Date(a.created_at || a.order_date).getTime();
        const dateB = new Date(b.created_at || b.order_date).getTime();
        return dateB - dateA; // Latest first
      });
      setOrders(sortedData);
    } catch (error: any) {
      toast.error(error.message || 'Failed to load purchase orders');
    } finally {
      setLoading(false);
    }
  };

  const handleExportAll = async () => {
    try {
      const blob = await purchaseOrderApi.exportAll();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `purchase-orders-${new Date().toISOString().split('T')[0]}.csv`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
      toast.success('Purchase orders exported successfully');
    } catch (error: any) {
      toast.error(error.message || 'Failed to export purchase orders');
    }
  };

  // Enhanced status badge function with workflow awareness
  const getEnhancedStatusInfo = (order: PurchaseOrder) => {
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
    } else if (isApproved && !isPaid) {
      progress = 50;
      statusBadge = (
        <Badge variant="default" className="flex items-center gap-1 bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-400 border-blue-300 dark:border-blue-800">
          <DollarSign className="h-3 w-3" />
          APPROVED - PAYMENT PENDING
        </Badge>
      );
      nextAction = 'Record payment';
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

  // Calculate unpaid/overdue POs for reminder
  const unpaidPOs = orders.filter(o => 
    o.payment_status !== 'paid' && 
    !['cancelled', 'draft'].includes(o.status)
  );
  
  const overduePOs = unpaidPOs.filter(o => 
    o.payment_due_date && 
    new Date(o.payment_due_date) < new Date()
  );
  
  const dueSoonPOs = unpaidPOs.filter(o => 
    o.payment_due_date && 
    new Date(o.payment_due_date) >= new Date() &&
    new Date(o.payment_due_date).getTime() - new Date().getTime() < 3 * 24 * 60 * 60 * 1000
  );

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Purchase Orders</h1>
          <p className="text-muted-foreground">Manage purchase orders and stock receiving</p>
        </div>
        <div className="flex items-center space-x-2">
          <Button onClick={handleExportAll} variant="outline">
            <Download className="mr-2 h-4 w-4" />
            Export All to CSV
          </Button>
          <Link to="/admin/purchase-orders/create">
            <Button>
              <Plus className="mr-2 h-4 w-4" />
              Create PO
            </Button>
          </Link>
        </div>
      </div>

      {/* Payment Reminder Alert */}
      {(overduePOs.length > 0 || dueSoonPOs.length > 0) && (
        <Alert variant={overduePOs.length > 0 ? "destructive" : "default"} className={overduePOs.length > 0 ? "border-red-500 dark:border-red-700 bg-red-50 dark:bg-red-950/30" : "border-orange-500 dark:border-orange-700 bg-orange-50 dark:bg-orange-950/30"}>
          <AlertTriangle className="h-4 w-4" />
          <AlertTitle className="font-bold">
            {overduePOs.length > 0 ? "⚠️ Overdue Payments" : "⏰ Payments Due Soon"}
          </AlertTitle>
          <AlertDescription>
            {overduePOs.length > 0 && (
              <p className="text-sm">
                <strong>{overduePOs.length}</strong> purchase order{overduePOs.length > 1 ? 's have' : ' has'} overdue payment{overduePOs.length > 1 ? 's' : ''}.
                Total overdue: <strong className="text-red-700 dark:text-red-400">₦{overduePOs.reduce((sum, o) => sum + (o.total_amount - (o.amount_paid || 0)), 0).toLocaleString()}</strong>
              </p>
            )}
            {dueSoonPOs.length > 0 && (
              <p className="text-sm mt-1">
                <strong>{dueSoonPOs.length}</strong> payment{dueSoonPOs.length > 1 ? 's are' : ' is'} due within 3 days.
                Total: <strong className="text-orange-700 dark:text-orange-400">₦{dueSoonPOs.reduce((sum, o) => sum + (o.total_amount - (o.amount_paid || 0)), 0).toLocaleString()}</strong>
              </p>
            )}
            <p className="text-xs mt-2 text-muted-foreground">Click on the PO to record payment.</p>
          </AlertDescription>
        </Alert>
      )}

      {loading ? (
        <Card>
          <CardContent className="p-4 md:p-6">
            <p className="text-center text-muted-foreground">Loading purchase orders...</p>
          </CardContent>
        </Card>
      ) : orders.length === 0 ? (
        <Card>
          <CardContent className="p-4 md:p-6">
            <p className="text-center text-muted-foreground">No purchase orders yet. Create your first one!</p>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-4">
          {orders.map((order) => {
            const statusInfo = getEnhancedStatusInfo(order);
            
            return (
              <Link key={order.id} to={`/admin/purchase-orders/${order.id}`}>
                <Card className="hover:shadow-md transition-shadow cursor-pointer">
                  <CardContent className="p-4 md:p-6">
                    <div className="space-y-4">
                      {/* Header Row */}
                      <div className="flex items-start justify-between">
                        <div className="space-y-1">
                          <div className="flex items-center space-x-3">
                            <h3 className="text-lg font-semibold">{order.po_number}</h3>
                            {statusInfo.badge}
                          </div>
                          <p className="text-sm text-muted-foreground">
                            Supplier: {order.supplier?.name || 'N/A'}
                          </p>
                          <p className="text-sm text-muted-foreground">
                            Order Date: {new Date(order.order_date).toLocaleDateString()}
                          </p>
                        </div>
                        <div className="text-right">
                          <p className="text-2xl font-bold">₦{parseFloat(order.total_amount).toLocaleString()}</p>
                          <p className="text-sm text-muted-foreground mt-1">
                            Payment: {order.payment_status?.toUpperCase() || 'UNPAID'}
                          </p>
                        </div>
                      </div>

                      {/* Progress Bar */}
                      <div className="space-y-2">
                        <div className="flex justify-between items-center text-sm">
                          <span className="text-muted-foreground font-medium">{statusInfo.nextAction}</span>
                          <span className="text-muted-foreground">{statusInfo.progress}%</span>
                        </div>
                        <Progress value={statusInfo.progress} className="h-2" />
                        <div className="flex justify-between text-xs text-muted-foreground">
                          <span>📝 Draft</span>
                          <span>✅ Approved</span>
                          <span>💰 Paid</span>
                          <span>📦 Received</span>
                        </div>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </Link>
            );
          })}
        </div>
      )}
    </div>
  );
}
