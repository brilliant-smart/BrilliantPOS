import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { ArrowLeft, TrendingUp, ShoppingCart, Download, Calendar } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { salesApi } from '@/app/api/sales';
import { toast } from 'sonner';

export default function SalesAnalytics() {
  const [period, setPeriod] = useState<'today' | 'yesterday' | 'this_week' | 'this_month' | 'custom'>('this_month');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [analytics, setAnalytics] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadAnalytics();
  }, [period, startDate, endDate]);

  const loadAnalytics = async () => {
    try {
      setLoading(true);
      const data = await salesApi.getAnalytics(period, startDate, endDate);
      setAnalytics(data);
    } catch (error: any) {
      console.error('Failed to load analytics:', error);
      toast.error('Failed to load sales analytics');
    } finally {
      setLoading(false);
    }
  };

  const handleExport = async (format: 'csv' | 'pdf') => {
    try {
      const blob = await salesApi.export(startDate, endDate, format);
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `sales-report-${new Date().toISOString().split('T')[0]}.${format}`;
      link.click();
      window.URL.revokeObjectURL(url);
      toast.success(`Sales report exported as ${format.toUpperCase()}`);
    } catch (error) {
      toast.error('Failed to export report');
    }
  };

  const formatCurrency = (amount: number | undefined | null) => {
    if (amount == null) return '₦0.00';
    return `₦${Number(amount).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  };

  if (loading) {
    return (
      <div className="p-4 md:p-6">
        <div className="text-center py-12">
          <p className="text-muted-foreground">Loading analytics...</p>
        </div>
      </div>
    );
  }

  if (!analytics) {
    return null;
  }

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Link to="/admin/sales">
            <Button variant="ghost" size="icon">
              <ArrowLeft className="h-5 w-5" />
            </Button>
          </Link>
          <div>
            <h1 className="text-3xl font-bold">Sales Analytics & Reports</h1>
            <p className="text-muted-foreground">Comprehensive sales performance and profit analysis</p>
          </div>
        </div>

        <div className="flex gap-2">
          <Button variant="outline" onClick={() => handleExport('csv')} className="gap-2">
            <Download className="h-4 w-4" />
            Export CSV
          </Button>
          <Button variant="outline" onClick={() => handleExport('pdf')} className="gap-2">
            <Download className="h-4 w-4" />
            Export PDF
          </Button>
        </div>
      </div>

      {/* Filters */}
      <Card>
        <CardHeader>
          <CardTitle className="text-lg flex items-center gap-2">
            <Calendar className="h-5 w-5" />
            Date Filters
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 md:grid-cols-4">
            <div className="space-y-2">
              <Label>Period</Label>
              <Select value={period} onValueChange={(value: any) => setPeriod(value)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="today">Today</SelectItem>
                  <SelectItem value="yesterday">Yesterday</SelectItem>
                  <SelectItem value="this_week">This Week</SelectItem>
                  <SelectItem value="this_month">This Month</SelectItem>
                  <SelectItem value="custom">Custom Range</SelectItem>
                </SelectContent>
              </Select>
            </div>

            {period === 'custom' && (
              <>
                <div className="space-y-2">
                  <Label>Start Date</Label>
                  <Input
                    type="date"
                    value={startDate}
                    onChange={(e) => setStartDate(e.target.value)}
                  />
                </div>
                <div className="space-y-2">
                  <Label>End Date</Label>
                  <Input
                    type="date"
                    value={endDate}
                    onChange={(e) => setEndDate(e.target.value)}
                  />
                </div>
              </>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Summary Metrics */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Total Sales</CardTitle>
            <ShoppingCart className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{analytics.summary.total_sales}</div>
            <p className="text-xs text-muted-foreground">Total Orders: {analytics.summary.total_orders}</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Total Revenue</CardTitle>
            <span className="text-2xl font-bold text-muted-foreground">₦</span>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{formatCurrency(analytics.summary.total_revenue)}</div>
            <p className="text-xs text-muted-foreground">Avg: {formatCurrency(analytics.summary.average_sale_value)}</p>
          </CardContent>
        </Card>

        <Card className="border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950/30">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-green-900 dark:text-green-400">Total Profit</CardTitle>
            <TrendingUp className="h-4 w-4 text-green-600 dark:text-green-400" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-green-900 dark:text-green-400">{formatCurrency(analytics.summary.total_profit)}</div>
            <p className="text-xs text-green-700 dark:text-green-400">Margin: {(analytics.summary.profit_margin ?? 0).toFixed(2)}%</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Cost of Goods</CardTitle>
            <span className="text-2xl font-bold text-muted-foreground">₦</span>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{formatCurrency(analytics.summary.total_cogs)}</div>
            <p className="text-xs text-muted-foreground">Direct Costs</p>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-6 md:grid-cols-2">
        {/* Payment Method Breakdown */}
        <Card>
          <CardHeader>
            <CardTitle>Sales by Payment Method</CardTitle>
            <CardDescription>Revenue and profit breakdown</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {analytics.payment_method_breakdown.map((payment: any, index: number) => {
                const percentage = analytics.summary.total_revenue ? ((payment.total / analytics.summary.total_revenue) * 100).toFixed(1) : '0.0';
                return (
                  <div key={index} className="space-y-2">
                    <div className="flex items-center justify-between text-sm">
                      <span className="font-medium capitalize">{payment.method}</span>
                      <div className="text-right">
                        <div className="font-bold">{formatCurrency(payment.total)}</div>
                        <div className="text-xs text-green-600 dark:text-green-400">Profit: {formatCurrency(payment.profit)}</div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <div className="flex-1 h-2 bg-muted rounded-full overflow-hidden">
                        <div
                          className="h-full bg-primary transition-all"
                          style={{ width: `${percentage}%` }}
                        />
                      </div>
                      <span className="text-xs text-muted-foreground w-12 text-right">{percentage}%</span>
                    </div>
                    <p className="text-xs text-muted-foreground">{payment.count} transactions</p>
                  </div>
                );
              })}
            </div>
          </CardContent>
        </Card>

        {/* Top Products */}
        <Card>
          <CardHeader>
            <CardTitle>Top Selling Products</CardTitle>
            <CardDescription>Best performers by quantity</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {analytics.top_products.slice(0, 10).map((product: any, index: number) => (
                <div key={index} className="flex items-center justify-between p-3 rounded-lg bg-muted/50">
                  <div className="flex items-center gap-3">
                    <div className="flex items-center justify-center w-8 h-8 rounded-full bg-primary text-primary-foreground font-bold text-sm">
                      {index + 1}
                    </div>
                    <div>
                      <div className="font-medium">{product.product_name}</div>
                      <div className="text-sm text-muted-foreground">{product.quantity_sold} units sold</div>
                    </div>
                  </div>
                  <div className="text-right">
                    <div className="font-bold">{formatCurrency(product.revenue)}</div>
                    <div className="text-sm text-green-600 dark:text-green-400">+{formatCurrency(product.profit)}</div>
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Financial Summary - Always Show */}
      <Card>
        <CardHeader>
          <CardTitle>Financial Summary</CardTitle>
          <CardDescription>Complete breakdown for the selected period</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-2">
            {/* Show daily breakdown if multi-day period */}
            {analytics.daily_trend.length > 0 && analytics.daily_trend.map((day: any, index: number) => (
              <div key={index} className="flex items-center justify-between text-sm p-2 hover:bg-muted/50 rounded">
                <span className="font-medium">{new Date(day.date).toLocaleDateString('en-NG', { month: 'short', day: 'numeric', weekday: 'short' })}</span>
                <div className="flex items-center gap-6">
                  <div className="text-right">
                    <div className="text-muted-foreground text-xs">Sales</div>
                    <div className="font-medium">{day.count}</div>
                  </div>
                  <div className="text-right">
                    <div className="text-muted-foreground text-xs">Revenue</div>
                    <div className="font-bold">{formatCurrency(day.revenue)}</div>
                  </div>
                  <div className="text-right">
                    <div className="text-muted-foreground text-xs">Profit</div>
                    <div className="font-bold text-green-600 dark:text-green-400">{formatCurrency(day.profit)}</div>
                  </div>
                  <div className="text-right">
                    <div className="text-muted-foreground text-xs">COGS</div>
                    <div className="font-medium">{formatCurrency(day.cogs)}</div>
                  </div>
                </div>
              </div>
            ))}
            
            {/* Grand Total Row - ALWAYS SHOW */}
            <div className="flex items-center justify-between text-sm p-4 bg-green-50 dark:bg-green-950/30 rounded-lg border-2 border-green-200 dark:border-green-800 mt-4">
              <div>
                <div className="font-bold text-lg text-green-900 dark:text-green-400">PERIOD TOTAL</div>
                <div className="text-xs text-green-700 dark:text-green-400 mt-1">
                  {period === 'today' && 'Today\'s Total'}
                  {period === 'yesterday' && 'Yesterday\'s Total'}
                  {period === 'this_week' && 'This Week\'s Total'}
                  {period === 'this_month' && 'This Month\'s Total'}
                  {period === 'custom' && 'Selected Period Total'}
                </div>
              </div>
              <div className="flex items-center gap-6">
                <div className="text-right">
                  <div className="text-green-700 dark:text-green-400 text-xs font-medium">Total Sales</div>
                  <div className="font-bold text-lg text-green-900 dark:text-green-400">{analytics.summary.total_sales}</div>
                </div>
                <div className="text-right">
                  <div className="text-green-700 dark:text-green-400 text-xs font-medium">Total Revenue</div>
                  <div className="font-bold text-lg text-green-900 dark:text-green-400">{formatCurrency(analytics.summary.total_revenue)}</div>
                </div>
                <div className="text-right">
                  <div className="text-green-700 dark:text-green-400 text-xs font-medium">Total Profit</div>
                  <div className="font-bold text-xl text-green-600 dark:text-green-400">{formatCurrency(analytics.summary.total_profit)}</div>
                </div>
                <div className="text-right">
                  <div className="text-green-700 dark:text-green-400 text-xs font-medium">Total COGS</div>
                  <div className="font-bold text-lg text-green-900 dark:text-green-400">{formatCurrency(analytics.summary.total_cogs)}</div>
                </div>
              </div>
            </div>
            
            {/* Helpful message for single day */}
            {analytics.daily_trend.length === 0 && (
              <p className="text-sm text-muted-foreground text-center py-4">
                Single day period - see totals above
              </p>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
