import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { TrendingUp, Search, Filter, Eye } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { DataPagination } from '@/components/DataPagination';
import { salesApi } from '@/app/api/sales';
import { useAuth } from '@/app/auth/AuthContext';
import { isOwner, isManager } from '@/app/auth/guards';
import { toast } from 'sonner';
import SaleDetailDrawer from './SaleDetailDrawer';

const PER_PAGE = 15;

const SALE_TYPE_LABELS: Record<string, string> = {
  cash: 'Cash',
  credit: 'Credit',
  online: 'Online Transfer',
  pos: 'POS Terminal',
};

const PAYMENT_STATUS_VARIANTS: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  paid: 'default',
  partially_paid: 'secondary',
  unpaid: 'destructive',
};

export default function SalesList() {
  const { user } = useAuth();
  const canViewProfit = isOwner(user) || isManager(user);

  const [sales, setSales] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalRecords, setTotalRecords] = useState(0);

  // Filters
  const [search, setSearch] = useState('');
  const [paymentStatus, setPaymentStatus] = useState<string>('');
  const [saleType, setSaleType] = useState<string>('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [showFilters, setShowFilters] = useState(false);

  // Drawer
  const [selectedSaleId, setSelectedSaleId] = useState<number | null>(null);
  const [drawerOpen, setDrawerOpen] = useState(false);

  useEffect(() => {
    loadSales(1);
  }, []);

  const loadSales = async (page: number = 1) => {
    try {
      setLoading(true);
      const result = await salesApi.getAll({
        page,
        per_page: PER_PAGE,
        search: search || undefined,
        payment_status: paymentStatus || undefined,
        sale_type: saleType || undefined,
        start_date: startDate || undefined,
        end_date: endDate || undefined,
      });
      setSales(result.data);
      setCurrentPage(result.current_page);
      setTotalPages(result.last_page);
      setTotalRecords(result.total);
    } catch (error: any) {
      toast.error('Failed to load sales');
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = () => {
    setCurrentPage(1);
    loadSales(1);
  };

  const handleRowClick = (saleId: number) => {
    setSelectedSaleId(saleId);
    setDrawerOpen(true);
  };

  const formatCurrency = (amount: number | string | null | undefined) => {
    if (amount == null) return '₦0.00';
    return `₦${Number(amount).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  };

  const getPaymentStatusBadge = (status: string) => {
    const variant = PAYMENT_STATUS_VARIANTS[status] || 'outline';
    const label = status === 'partially_paid' ? 'Partial' : status.charAt(0).toUpperCase() + status.slice(1);
    return <Badge variant={variant}>{label}</Badge>;
  };

  const getSaleTypeBadge = (type: string, paymentStatus?: string) => {
    const label = SALE_TYPE_LABELS[type] || type;
    if (type === 'credit') {
      if (paymentStatus === 'paid') return <Badge variant="secondary">{label}</Badge>;
      return <Badge variant="destructive">{label}</Badge>;
    }
    return <Badge variant="outline">{label}</Badge>;
  };

  const getPrimaryPaymentMethod = (sale: any) => {
    if (sale.payments && sale.payments.length > 0) {
      if (sale.payments.length === 1) {
        return SALE_TYPE_LABELS[sale.payments[0].method] || sale.payments[0].method;
      }
      return 'Split';
    }
    return SALE_TYPE_LABELS[sale.sale_type] || sale.sale_type;
  };

  return (
    <div className="p-4 md:p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Sales</h1>
          <p className="text-muted-foreground dark:text-muted-foreground/80">Transaction history and management</p>
        </div>
        <div className="flex gap-2">
          {canViewProfit && (
            <Link to="/admin/sales/analytics">
              <Button variant="outline">
                <TrendingUp className="mr-2 h-4 w-4" />
                Analytics
              </Button>
            </Link>
          )}
          <Link to="/admin/sales/create">
            <Button>
              Record Sale
            </Button>
          </Link>
        </div>
      </div>

      {/* Search and Filters */}
      <div className="flex flex-col sm:flex-row gap-2">
        <div className="flex gap-2 flex-1">
          <div className="relative flex-1 max-w-sm">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search sale #, customer..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
              className="pl-9"
            />
          </div>
          <Button variant="outline" size="icon" onClick={() => setShowFilters(!showFilters)}>
            <Filter className="h-4 w-4" />
          </Button>
          <Button variant="outline" onClick={handleSearch}>Go</Button>
        </div>
      </div>

      {showFilters && (
        <div className="grid grid-cols-1 sm:grid-cols-4 gap-2 p-3 border rounded-lg bg-muted/50">
          <div>
            <label className="text-xs text-muted-foreground mb-1 block">Payment Status</label>
            <Select value={paymentStatus} onValueChange={(v) => { setPaymentStatus(v === 'all' ? '' : v); }}>
              <SelectTrigger><SelectValue placeholder="All Statuses" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Statuses</SelectItem>
                <SelectItem value="paid">Paid</SelectItem>
                <SelectItem value="partially_paid">Partially Paid</SelectItem>
                <SelectItem value="unpaid">Unpaid</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div>
            <label className="text-xs text-muted-foreground mb-1 block">Payment Method</label>
            <Select value={saleType} onValueChange={(v) => { setSaleType(v === 'all' ? '' : v); }}>
              <SelectTrigger><SelectValue placeholder="All Methods" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Methods</SelectItem>
                <SelectItem value="cash">Cash</SelectItem>
                <SelectItem value="pos">POS Terminal</SelectItem>
                <SelectItem value="bank_transfer">Transfer</SelectItem>
                <SelectItem value="credit">Credit</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div>
            <label className="text-xs text-muted-foreground mb-1 block">From</label>
            <Input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} />
          </div>
          <div>
            <label className="text-xs text-muted-foreground mb-1 block">To</label>
            <Input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} />
          </div>
        </div>
      )}

      {/* Data Table */}
      {loading ? (
        <div className="text-center py-12">
          <p className="text-muted-foreground">Loading sales...</p>
        </div>
      ) : sales.length === 0 ? (
        <div className="text-center py-12">
          <p className="text-muted-foreground">No sales found. Record your first sale!</p>
        </div>
      ) : (
        <div className="border rounded-lg overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Sale #</TableHead>
                <TableHead>Date</TableHead>
                <TableHead>Customer</TableHead>
                <TableHead className="text-center">Items</TableHead>
                <TableHead>Method</TableHead>
                <TableHead className="text-right">Amount</TableHead>
                <TableHead>Status</TableHead>
                {canViewProfit && <TableHead className="text-right">Profit</TableHead>}
                <TableHead>Cashier</TableHead>
                <TableHead className="w-10"></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {sales.map((sale) => (
                <TableRow
                  key={sale.id}
                  className="cursor-pointer hover:bg-muted/50"
                  onClick={() => handleRowClick(sale.id)}
                >
                  <TableCell className="font-medium">{sale.sale_number}</TableCell>
                  <TableCell>{new Date(sale.sale_date).toLocaleDateString('en-NG', { month: 'short', day: 'numeric', year: 'numeric' })}</TableCell>
                  <TableCell>{sale.customer_name || 'Walk-in'}</TableCell>
                  <TableCell className="text-center">
                    <Badge variant="secondary">{sale.items_count ?? 0}</Badge>
                  </TableCell>
                  <TableCell>{getSaleTypeBadge(sale.sale_type, sale.payment_status)}</TableCell>
                  <TableCell className="text-right font-medium">{formatCurrency(sale.total_amount)}</TableCell>
                  <TableCell>{getPaymentStatusBadge(sale.payment_status)}</TableCell>
                  {canViewProfit && (
                    <TableCell className="text-right text-green-600 dark:text-green-400">
                      {formatCurrency(sale.gross_profit || sale.total_profit)}
                    </TableCell>
                  )}
                  <TableCell>{sale.cashier?.name || '—'}</TableCell>
                  <TableCell>
                    <Eye className="h-4 w-4 text-muted-foreground" />
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      <DataPagination
        currentPage={currentPage}
        totalPages={totalPages}
        totalRecords={totalRecords}
        perPage={PER_PAGE}
        onPageChange={(page) => loadSales(page)}
      />

      <SaleDetailDrawer
        open={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        saleId={selectedSaleId}
      />
    </div>
  );
}