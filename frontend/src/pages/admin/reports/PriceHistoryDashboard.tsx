import { useState, useEffect, useMemo } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { 
  TrendingUp, 
  TrendingDown, 
  Minus, 
  Download, 
  Search,
  Calendar,
  DollarSign,
  AlertTriangle 
} from 'lucide-react';
import { format } from 'date-fns';
import { DatePicker } from '@/components/DatePicker';
import { api } from '@/app/lib/api';
import { toast } from 'sonner';
import { DataPagination } from '@/components/DataPagination';

interface PriceHistoryRecord {
  id: number;
  product_id: number;
  product_name: string;
  product_sku: string;
  old_price: number | string;
  new_price: number | string;
  price_change: number | string;
  percentage_change: number | string;
  change_type: string;
  supplier_name: string;
  reference_number: string;
  changed_at: string;
  notes: string;
}

// Helper function to convert string/number to number
const toNumber = (value: number | string | null | undefined): number => {
  if (value === null || value === undefined) return 0;
  return typeof value === 'string' ? parseFloat(value) : value;
};

export default function PriceHistoryDashboard() {
  const [loading, setLoading] = useState(false);
  const [priceHistory, setPriceHistory] = useState<PriceHistoryRecord[]>([]);
  const [filteredHistory, setFilteredHistory] = useState<PriceHistoryRecord[]>([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [changeTypeFilter, setChangeTypeFilter] = useState<string>('all');
  const [trendFilter, setTrendFilter] = useState<string>('all'); // all, increase, decrease
  const [startDate, setStartDate] = useState<Date | undefined>();
  const [endDate, setEndDate] = useState<Date | undefined>();
  const [currentPage, setCurrentPage] = useState(1);
  const PER_PAGE = 20;
  const [stats, setStats] = useState({
    totalChanges: 0,
    priceIncreases: 0,
    priceDecreases: 0,
    avgIncrease: 0,
    avgDecrease: 0,
    largestIncrease: 0,
    largestDecrease: 0,
  });

  useEffect(() => {
    fetchPriceHistory();
  }, []);

  useEffect(() => {
    applyFilters();
    setCurrentPage(1);
  }, [priceHistory, searchTerm, changeTypeFilter, trendFilter, startDate, endDate]);

  const fetchPriceHistory = async () => {
    setLoading(true);
    try {
      // This endpoint would need to be created in the backend
      const response = await api.get('/price-history');
      const data = response.data.data || response.data;
      setPriceHistory(data);
      calculateStats(data);
    } catch (error: any) {
      console.error('Failed to fetch price history:', error);
      toast.error('Failed to load price history');
    } finally {
      setLoading(false);
    }
  };

  const calculateStats = (data: PriceHistoryRecord[]) => {
    const increases = data.filter(h => toNumber(h.price_change) > 0);
    const decreases = data.filter(h => toNumber(h.price_change) < 0);

    const avgIncrease = increases.length > 0
      ? increases.reduce((sum, h) => sum + toNumber(h.price_change), 0) / increases.length
      : 0;

    const avgDecrease = decreases.length > 0
      ? Math.abs(decreases.reduce((sum, h) => sum + toNumber(h.price_change), 0) / decreases.length)
      : 0;

    const largestIncrease = increases.length > 0
      ? Math.max(...increases.map(h => toNumber(h.price_change)))
      : 0;

    const largestDecrease = decreases.length > 0
      ? Math.abs(Math.min(...decreases.map(h => toNumber(h.price_change))))
      : 0;

    setStats({
      totalChanges: data.length,
      priceIncreases: increases.length,
      priceDecreases: decreases.length,
      avgIncrease,
      avgDecrease,
      largestIncrease,
      largestDecrease,
    });
  };

  const applyFilters = () => {
    let filtered = [...priceHistory];

    // Search filter
    if (searchTerm) {
      filtered = filtered.filter(h =>
        h.product_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        h.product_sku.toLowerCase().includes(searchTerm.toLowerCase()) ||
        h.supplier_name?.toLowerCase().includes(searchTerm.toLowerCase())
      );
    }

    // Change type filter
    if (changeTypeFilter !== 'all') {
      filtered = filtered.filter(h => h.change_type === changeTypeFilter);
    }

    // Trend filter
    if (trendFilter === 'increase') {
      filtered = filtered.filter(h => toNumber(h.price_change) > 0);
    } else if (trendFilter === 'decrease') {
      filtered = filtered.filter(h => toNumber(h.price_change) < 0);
    }

    // Date range filter
    if (startDate) {
      filtered = filtered.filter(h => new Date(h.changed_at) >= startDate);
    }
    if (endDate) {
      filtered = filtered.filter(h => new Date(h.changed_at) <= endDate);
    }

    setFilteredHistory(filtered);
  };

  const paginatedHistory = useMemo(() => {
    const start = (currentPage - 1) * PER_PAGE;
    return filteredHistory.slice(start, start + PER_PAGE);
  }, [filteredHistory, currentPage]);

  const totalPages = Math.max(1, Math.ceil(filteredHistory.length / PER_PAGE));

  const getPriceChangeBadge = (change: number | string, percentChange: number | string) => {
    const changeNum = toNumber(change);
    const percentNum = toNumber(percentChange);
    
    if (Math.abs(changeNum) < 0.01) {
      return <Badge variant="outline">No Change</Badge>;
    }

    if (changeNum > 0) {
      return (
        <Badge variant="destructive" className="flex items-center gap-1 w-fit">
          <TrendingUp className="h-3 w-3" />
          +₦{changeNum.toFixed(2)} (+{Math.abs(percentNum).toFixed(2)}%)
        </Badge>
      );
    }

    return (
      <Badge variant="default" className="bg-green-600 dark:bg-green-700 flex items-center gap-1 w-fit">
        <TrendingDown className="h-3 w-3" />
        -₦{Math.abs(changeNum).toFixed(2)} ({Math.abs(percentNum).toFixed(2)}%)
      </Badge>
    );
  };

  const exportToCSV = () => {
    const headers = ['Date', 'Product', 'SKU', 'Old Price', 'New Price', 'Change', '% Change', 'Supplier', 'Reference'];
    const rows = filteredHistory.map(h => [
      format(new Date(h.changed_at), 'yyyy-MM-dd HH:mm'),
      h.product_name,
      h.product_sku,
      toNumber(h.old_price).toFixed(2),
      toNumber(h.new_price).toFixed(2),
      toNumber(h.price_change).toFixed(2),
      toNumber(h.percentage_change).toFixed(2),
      h.supplier_name || 'N/A',
      h.reference_number || 'N/A',
    ]);

    const csvContent = [headers, ...rows].map(row => row.join(',')).join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `price-history-${format(new Date(), 'yyyy-MM-dd')}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  };

  return (
    <div className="space-y-6 p-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Price History Dashboard</h1>
          <p className="text-muted-foreground">Track and analyze product price changes over time</p>
        </div>
        <Button onClick={exportToCSV} disabled={filteredHistory.length === 0}>
          <Download className="mr-2 h-4 w-4" />
          Export CSV
        </Button>
      </div>

      {/* Statistics Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Changes</CardTitle>
            <Calendar className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.totalChanges}</div>
            <p className="text-xs text-muted-foreground">Price modifications recorded</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Price Increases</CardTitle>
            <TrendingUp className="h-4 w-4 text-red-500 dark:text-red-400" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-red-600 dark:text-red-400">{stats.priceIncreases}</div>
            <p className="text-xs text-muted-foreground">
              Avg: ₦{stats.avgIncrease.toFixed(2)} | Max: ₦{stats.largestIncrease.toFixed(2)}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Price Decreases</CardTitle>
            <TrendingDown className="h-4 w-4 text-green-500 dark:text-green-400" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-green-600 dark:text-green-400">{stats.priceDecreases}</div>
            <p className="text-xs text-muted-foreground">
              Avg: ₦{stats.avgDecrease.toFixed(2)} | Max: ₦{stats.largestDecrease.toFixed(2)}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Alert Status</CardTitle>
            <AlertTriangle className="h-4 w-4 text-yellow-500 dark:text-yellow-400" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {filteredHistory.filter(h => Math.abs(h.percentage_change) > 10).length}
            </div>
            <p className="text-xs text-muted-foreground">Changes over 10%</p>
          </CardContent>
        </Card>
      </div>

      {/* Filters */}
      <Card>
        <CardHeader>
          <CardTitle>Filters</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div className="space-y-2">
              <Label>Search</Label>
              <div className="relative">
                <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder="Product, SKU, Supplier..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="pl-8"
                />
              </div>
            </div>

            <div className="space-y-2">
              <Label>Change Type</Label>
              <Select value={changeTypeFilter} onValueChange={setChangeTypeFilter}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Types</SelectItem>
                  <SelectItem value="purchase">Purchase Order</SelectItem>
                  <SelectItem value="manual">Manual Adjustment</SelectItem>
                  <SelectItem value="sale">Sale</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label>Trend</Label>
              <Select value={trendFilter} onValueChange={setTrendFilter}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Trends</SelectItem>
                  <SelectItem value="increase">Increases Only</SelectItem>
                  <SelectItem value="decrease">Decreases Only</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label>Start Date</Label>
              <DatePicker
                value={startDate ? format(startDate, 'yyyy-MM-dd') : ''}
                onChange={(v) => setStartDate(v ? new Date(v) : null)}
              />
            </div>

            <div className="space-y-2">
              <Label>End Date</Label>
              <DatePicker
                value={endDate ? format(endDate, 'yyyy-MM-dd') : ''}
                onChange={(v) => setEndDate(v ? new Date(v) : null)}
              />
            </div>
          </div>

          {(searchTerm || changeTypeFilter !== 'all' || trendFilter !== 'all' || startDate || endDate) && (
            <div className="mt-4">
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  setSearchTerm('');
                  setChangeTypeFilter('all');
                  setTrendFilter('all');
                  setStartDate(undefined);
                  setEndDate(undefined);
                }}
              >
                Clear Filters
              </Button>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Price History Table */}
      <Card>
        <CardHeader>
          <CardTitle>Price Change History ({filteredHistory.length} records)</CardTitle>
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="text-center py-8">Loading price history...</div>
          ) : filteredHistory.length === 0 ? (
            <div className="text-center py-8 text-muted-foreground">
              No price changes found matching your filters
            </div>
          ) : (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Date</TableHead>
                    <TableHead>Product</TableHead>
                    <TableHead>Old Price</TableHead>
                    <TableHead>New Price</TableHead>
                    <TableHead>Change</TableHead>
                    <TableHead>Supplier</TableHead>
                    <TableHead>Reference</TableHead>
                    <TableHead>Notes</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {paginatedHistory.map((record) => (
                    <TableRow key={record.id}>
                      <TableCell className="text-sm">
                        {format(new Date(record.changed_at), 'MMM dd, yyyy HH:mm')}
                      </TableCell>
                      <TableCell>
                        <div>
                          <p className="font-medium">{record.product_name}</p>
                          <p className="text-xs text-muted-foreground">{record.product_sku}</p>
                        </div>
                      </TableCell>
                      <TableCell className="font-semibold">₦{toNumber(record.old_price).toFixed(2)}</TableCell>
                      <TableCell className="font-semibold">₦{toNumber(record.new_price).toFixed(2)}</TableCell>
                      <TableCell>
                        {getPriceChangeBadge(record.price_change, record.percentage_change)}
                      </TableCell>
                      <TableCell>{record.supplier_name || '-'}</TableCell>
                      <TableCell className="text-sm text-muted-foreground">
                        {record.reference_number || '-'}
                      </TableCell>
                      <TableCell className="text-sm text-muted-foreground max-w-xs truncate">
                        {record.notes || '-'}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>

      <DataPagination
        currentPage={currentPage}
        totalPages={totalPages}
        totalRecords={filteredHistory.length}
        perPage={PER_PAGE}
        onPageChange={setCurrentPage}
      />
    </div>
  );
}
