import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Printer, TrendingUp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { salesApi } from '@/app/api/sales';
import { Sale } from '@/types/ims';
import { toast } from 'sonner';

export default function SalesList() {
  const [sales, setSales] = useState<Sale[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadSales();
  }, []);

  const loadSales = async () => {
    try {
      const data = await salesApi.getAll();
      setSales(data);
    } catch (error: any) {
      toast.error(error.message || 'Failed to load sales');
    } finally {
      setLoading(false);
    }
  };

  const handlePrintReceipt = async (saleId: number) => {
    try {
      // Get the auth token (consistent key with POS)
      const token = localStorage.getItem('brilliant_auth_token') || localStorage.getItem('token');
      const backendUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000';
      
      // Open thermal receipt in new window (80mm POS printer optimized)
      window.open(`${backendUrl}/admin/receipts/${saleId}?token=${token}`, '_blank');
    } catch (error: any) {
      toast.error('Failed to open receipt');
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Sales</h1>
          <p className="text-muted-foreground">Record and manage sales transactions</p>
        </div>
        <div className="flex gap-2">
          <Link to="/admin/sales/analytics">
            <Button variant="outline">
              <TrendingUp className="mr-2 h-4 w-4" />
              View Analytics
            </Button>
          </Link>
          <Link to="/admin/sales/create">
            <Button>
              <Plus className="mr-2 h-4 w-4" />
              Record Sale
            </Button>
          </Link>
        </div>
      </div>

      {loading ? (
        <Card>
          <CardContent className="p-4 md:p-6">
            <p className="text-center text-muted-foreground">Loading sales...</p>
          </CardContent>
        </Card>
      ) : sales.length === 0 ? (
        <Card>
          <CardContent className="p-4 md:p-6">
            <p className="text-center text-muted-foreground">No sales yet. Record your first sale!</p>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-4">
          {sales.map((sale) => (
            <Card key={sale.id} className="hover:shadow-md transition-shadow">
              <CardContent className="p-4 md:p-6">
                <div className="flex items-center justify-between">
                  <div className="space-y-1">
                    <div className="flex items-center space-x-2">
                      <h3 className="font-semibold">{sale.sale_number}</h3>
                      <Badge>{sale.payment_status.toUpperCase()}</Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                      Date: {new Date(sale.sale_date).toLocaleDateString()}
                    </p>
                    {sale.customer_name && (
                      <p className="text-sm text-muted-foreground">Customer: {sale.customer_name}</p>
                    )}
                  </div>
                  <div className="flex items-start gap-4">
                    <div className="text-right space-y-1">
                      <p className="text-2xl font-bold">₦{parseFloat(sale.total_amount || 0).toLocaleString()}</p>
                      <p className="text-sm text-green-600 dark:text-green-400">
                        Profit: ₦{parseFloat(sale.gross_profit || sale.total_profit || 0).toLocaleString()} ({(sale.profit_margin || 0).toFixed(1)}%)
                      </p>
                      <p className="text-sm text-muted-foreground">
                        COGS: ₦{parseFloat(sale.cost_of_goods_sold || sale.cost_of_goods || 0).toLocaleString()}
                      </p>
                    </div>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handlePrintReceipt(sale.id)}
                    >
                      <Printer className="h-4 w-4 mr-2" />
                      Print
                    </Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
