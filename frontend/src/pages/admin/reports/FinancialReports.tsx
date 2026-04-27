import { useState, useEffect } from 'react';
import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { reportsApi } from '@/app/api/reports';
import { toast } from 'sonner';
import { DatePicker } from '@/components/DatePicker';

export default function FinancialReports() {
  const [loading, setLoading] = useState(false);
  const [dateRange, setDateRange] = useState({
    start_date: new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0],
    end_date: new Date().toISOString().split('T')[0],
  });
  const [profitLoss, setProfitLoss] = useState<any>(null);
  const [variance, setVariance] = useState<any[]>([]);
  const [expiring, setExpiring] = useState<any[]>([]);

  useEffect(() => {
    loadReports();
  }, []);

  const loadReports = async () => {
    try {
      setLoading(true);
      const [plData, varianceData, expiringData] = await Promise.all([
        reportsApi.getProfitLoss(dateRange.start_date, dateRange.end_date),
        reportsApi.getStockVariance(),
        reportsApi.getExpiringProducts(30),
      ]);
      
      // Handle the response structure from backend
      setProfitLoss(plData);
      setVariance(varianceData?.variances || []);
      setExpiring(expiringData?.products || []);
    } catch (error: any) {
      console.error('Report loading error:', error);
      toast.error(error.response?.data?.message || error.message || 'Failed to load reports');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Financial Reports</h1>
          <p className="text-muted-foreground">Complete financial overview and analytics</p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Date Range</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex items-end gap-4">
            <div className="flex-1 space-y-2">
              <Label>Start Date</Label>
              <DatePicker
                value={dateRange.start_date}
                onChange={(v) => setDateRange({ ...dateRange, start_date: v })}
              />
            </div>
            <div className="flex-1 space-y-2">
              <Label>End Date</Label>
              <DatePicker
                value={dateRange.end_date}
                onChange={(v) => setDateRange({ ...dateRange, end_date: v })}
              />
            </div>
            <Button onClick={loadReports} disabled={loading}>
              {loading ? 'Loading...' : 'Generate Report'}
            </Button>
          </div>
        </CardContent>
      </Card>

      {profitLoss && (
        <Card>
          <CardHeader>
            <CardTitle>Profit & Loss Summary</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-3">
              <div className="space-y-2">
                <p className="text-sm text-muted-foreground">Total Revenue</p>
                <p className="text-2xl font-bold text-green-600 dark:text-green-400">
                  ₦{parseFloat(profitLoss.sales?.total_revenue || 0).toLocaleString()}
                </p>
                <p className="text-sm text-muted-foreground">
                  {profitLoss.sales?.total_sales || 0} sales
                </p>
              </div>
              <div className="space-y-2">
                <p className="text-sm text-muted-foreground">Total COGS</p>
                <p className="text-2xl font-bold text-red-600 dark:text-red-400">
                  ₦{parseFloat(profitLoss.sales?.total_cogs || 0).toLocaleString()}
                </p>
              </div>
              <div className="space-y-2">
                <p className="text-sm text-muted-foreground">Gross Profit</p>
                <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                  ₦{parseFloat(profitLoss.sales?.total_profit || 0).toLocaleString()}
                </p>
                <p className="text-sm text-muted-foreground">
                  Margin: {profitLoss.sales?.profit_margin || 0}%
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {variance.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>Stock Variance (Theft Detection)</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              {variance.length > 0 ? (
                variance.slice(0, 5).map((item: any, index: number) => (
                  <div key={index} className="flex justify-between items-center border-b pb-2">
                    <div>
                      <p className="font-medium">{item.product_name}</p>
                      <p className="text-sm text-muted-foreground">SKU: {item.sku || 'N/A'}</p>
                      <span className={`text-xs px-2 py-1 rounded ${
                        item.severity === 'high' ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400' :
                        item.severity === 'medium' ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400' :
                        'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400'
                      }`}>
                        {item.severity}
                      </span>
                    </div>
                    <div className="text-right">
                      <p className="font-bold text-red-600 dark:text-red-400">
                        {item.variance_quantity} units
                      </p>
                      <p className="text-sm text-muted-foreground">
                        ₦{parseFloat(item.variance_value || 0).toLocaleString()}
                      </p>
                    </div>
                  </div>
                ))
              ) : (
                <p className="text-center text-muted-foreground py-4">
                  No stock variances detected. All inventory matches records!
                </p>
              )}
            </div>
          </CardContent>
        </Card>
      )}

      {expiring.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>Expiring Products (Next 30 Days)</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              {expiring.length > 0 ? (
                expiring.map((item: any, index: number) => (
                  <div key={index} className="flex justify-between items-center border-b pb-2">
                    <div>
                      <p className="font-medium">{item.product_name}</p>
                      <p className="text-sm text-muted-foreground">
                        Batch: {item.batch_number || 'N/A'} | Expires: {item.expiry_date}
                      </p>
                      <span className={`text-xs px-2 py-1 rounded ${
                        item.urgency === 'critical' ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400' :
                        item.urgency === 'high' ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400' :
                        'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400'
                      }`}>
                        {item.urgency}
                      </span>
                    </div>
                    <div className="text-right">
                      <p className="font-medium text-orange-600 dark:text-orange-400">
                        {item.days_to_expiry} days left
                      </p>
                      <p className="text-sm text-muted-foreground">
                        {item.stock_quantity} units | ₦{parseFloat(item.stock_value || 0).toLocaleString()}
                      </p>
                    </div>
                  </div>
                ))
              ) : (
                <p className="text-center text-muted-foreground py-4">
                  No products expiring in the next 30 days
                </p>
              )}
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
