import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Search, Calendar, Filter, Trash2, Edit, TrendingUp, Wallet, Receipt } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Badge } from '@/components/ui/badge';
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
import { fetchExpenses, deleteExpense, fetchExpenseCategories, fetchExpenseAnalytics, type Expense, type ExpenseCategory } from '@/app/api/expenses';
import { useToast } from '@/hooks/use-toast';
import { Banknote, Building2, CreditCard, User, Store, MoreHorizontal } from 'lucide-react';
import { DataPagination } from '@/components/DataPagination';

const PAYMENT_METHOD_ICONS: Record<string, JSX.Element> = {
  cash: <Banknote className="h-4 w-4" />,
  bank_transfer: <Building2 className="h-4 w-4" />,
  pos_terminal: <CreditCard className="h-4 w-4" />,
  personal_payment: <User className="h-4 w-4" />,
  shop_account: <Store className="h-4 w-4" />,
  other: <MoreHorizontal className="h-4 w-4" />,
};

const PAYMENT_METHOD_LABELS: Record<string, string> = {
  cash: 'Cash',
  bank_transfer: 'Bank Transfer',
  pos_terminal: 'POS Terminal',
  personal_payment: 'Personal Payment',
  shop_account: 'Shop Account',
  other: 'Other',
};

export default function ExpenseList() {
  const [expenses, setExpenses] = useState<Expense[]>([]);
  const [categories, setCategories] = useState<ExpenseCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  
  // Filters
  const [search, setSearch] = useState('');
  const [categoryFilter, setCategoryFilter] = useState<string>('');
  const [paymentMethodFilter, setPaymentMethodFilter] = useState<string>('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  
  // Summary stats
  const [todayTotal, setTodayTotal] = useState(0);
  const [weekTotal, setWeekTotal] = useState(0);
  const [monthTotal, setMonthTotal] = useState(0);
  
  // Delete dialog
  const [expenseToDelete, setExpenseToDelete] = useState<Expense | null>(null);
  
  const { toast } = useToast();

  useEffect(() => {
    loadCategories();
    loadSummaryStats();
  }, []);

  useEffect(() => {
    loadExpenses();
  }, [currentPage, search, categoryFilter, paymentMethodFilter, startDate, endDate]);

  const loadCategories = async () => {
    try {
      const data = await fetchExpenseCategories(true);
      setCategories(data);
    } catch (error) {
      console.error('Failed to load categories:', error);
    }
  };

  const loadSummaryStats = async () => {
    try {
      const [today, week, month] = await Promise.all([
        fetchExpenseAnalytics('today'),
        fetchExpenseAnalytics('this_week'),
        fetchExpenseAnalytics('this_month'),
      ]);
      
      setTodayTotal(today.summary.total_amount);
      setWeekTotal(week.summary.total_amount);
      setMonthTotal(month.summary.total_amount);
    } catch (error) {
      console.error('Failed to load summary stats:', error);
    }
  };

  const loadExpenses = async () => {
    try {
      setLoading(true);
      const response = await fetchExpenses({
        search: search || undefined,
        category_id: categoryFilter ? parseInt(categoryFilter) : undefined,
        payment_method: paymentMethodFilter || undefined,
        start_date: startDate || undefined,
        end_date: endDate || undefined,
        page: currentPage,
        per_page: 15,
      });
      
      setExpenses(response.data);
      setCurrentPage(response.current_page);
      setTotalPages(response.last_page);
      setTotal(response.total);
    } catch (error) {
      console.error('Failed to load expenses:', error);
      toast({
        title: 'Error',
        description: 'Failed to load expenses',
        variant: 'destructive',
      });
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async () => {
    if (!expenseToDelete) return;
    
    try {
      await deleteExpense(expenseToDelete.id);
      toast({
        title: 'Success',
        description: 'Expense deleted successfully',
      });
      setExpenseToDelete(null);
      loadExpenses();
      loadSummaryStats();
    } catch (error) {
      toast({
        title: 'Error',
        description: 'Failed to delete expense',
        variant: 'destructive',
      });
    }
  };

  const clearFilters = () => {
    setSearch('');
    setCategoryFilter('');
    setPaymentMethodFilter('');
    setStartDate('');
    setEndDate('');
    setCurrentPage(1);
  };

  const formatCurrency = (amount: string | number) => {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    return `₦${num.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-NG', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  };

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Expenses</h1>
          <p className="text-muted-foreground dark:text-muted-foreground/80">Track and manage shop operational expenses</p>
        </div>
        <Link to="/admin/expenses/create">
          <Button className="gap-2">
            <Plus className="h-4 w-4" />
            Add Expense
          </Button>
        </Link>
      </div>

      {/* Summary Cards */}
      <div className="grid gap-4 md:grid-cols-3">
        <Card className="border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950/30">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-green-900 dark:text-green-400">Today's Expenses</CardTitle>
            <Wallet className="h-4 w-4 text-green-600 dark:text-green-400" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-green-900 dark:text-green-400">{formatCurrency(todayTotal)}</div>
          </CardContent>
        </Card>

        <Card className="border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/30">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-blue-900 dark:text-blue-400">This Week</CardTitle>
            <TrendingUp className="h-4 w-4 text-blue-600 dark:text-blue-400" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-blue-900 dark:text-blue-400">{formatCurrency(weekTotal)}</div>
          </CardContent>
        </Card>

        <Card className="border-purple-200 dark:border-purple-800 bg-purple-50 dark:bg-purple-950/30">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-purple-900 dark:text-purple-400">This Month</CardTitle>
            <Receipt className="h-4 w-4 text-purple-600 dark:text-purple-400" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-purple-900 dark:text-purple-400">{formatCurrency(monthTotal)}</div>
          </CardContent>
        </Card>
      </div>

      {/* Filters */}
      <Card>
        <CardHeader>
          <CardTitle className="text-lg flex items-center gap-2">
            <Filter className="h-5 w-5" />
            Filters
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
            <div>
              <label className="text-sm font-medium mb-2 block">Search</label>
              <div className="relative">
                <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground dark:text-muted-foreground/80" />
                <Input
                  placeholder="Search expenses..."
                  value={search}
                  onChange={(e) => {
                    setSearch(e.target.value);
                    setCurrentPage(1);
                  }}
                  className="pl-9"
                />
              </div>
            </div>

            <div>
              <label className="text-sm font-medium mb-2 block">Category</label>
              <Select value={categoryFilter || 'all'} onValueChange={(value) => {
                setCategoryFilter(value === 'all' ? '' : value);
                setCurrentPage(1);
              }}>
                <SelectTrigger>
                  <SelectValue placeholder="All Categories" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Categories</SelectItem>
                  {categories.map((cat) => (
                    <SelectItem key={cat.id} value={cat.id.toString()}>
                      {cat.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div>
              <label className="text-sm font-medium mb-2 block">Payment Method</label>
              <Select value={paymentMethodFilter || 'all'} onValueChange={(value) => {
                setPaymentMethodFilter(value === 'all' ? '' : value);
                setCurrentPage(1);
              }}>
                <SelectTrigger>
                  <SelectValue placeholder="All Methods" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Methods</SelectItem>
                  <SelectItem value="cash">Cash</SelectItem>
                  <SelectItem value="bank_transfer">Bank Transfer</SelectItem>
                  <SelectItem value="pos_terminal">POS Terminal</SelectItem>
                  <SelectItem value="personal_payment">Personal Payment</SelectItem>
                  <SelectItem value="shop_account">Shop Account</SelectItem>
                  <SelectItem value="other">Other</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div>
              <label className="text-sm font-medium mb-2 block">Start Date</label>
              <Input
                type="date"
                value={startDate}
                onChange={(e) => {
                  setStartDate(e.target.value);
                  setCurrentPage(1);
                }}
              />
            </div>

            <div>
              <label className="text-sm font-medium mb-2 block">End Date</label>
              <Input
                type="date"
                value={endDate}
                onChange={(e) => {
                  setEndDate(e.target.value);
                  setCurrentPage(1);
                }}
              />
            </div>
          </div>

          {(search || categoryFilter || paymentMethodFilter || startDate || endDate) && (
            <div className="mt-4">
              <Button variant="outline" size="sm" onClick={clearFilters}>
                Clear Filters
              </Button>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Expenses Table */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>Expense Records</CardTitle>
              <CardDescription>
                {total > 0 ? `Showing ${expenses.length} of ${total} expenses` : 'No expenses found'}
              </CardDescription>
            </div>
            <Link to="/admin/expenses/analytics">
              <Button variant="outline" size="sm" className="gap-2">
                <TrendingUp className="h-4 w-4" />
                View Analytics
              </Button>
            </Link>
          </div>
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="text-center py-12">
              <p className="text-muted-foreground dark:text-muted-foreground/80">Loading expenses...</p>
            </div>
          ) : expenses.length === 0 ? (
            <div className="text-center py-12">
              <Receipt className="h-12 w-12 mx-auto text-muted-foreground dark:text-muted-foreground/80 mb-4" />
              <p className="text-muted-foreground dark:text-muted-foreground/80 mb-4">No expenses found</p>
              <Link to="/admin/expenses/create">
                <Button variant="outline">
                  <Plus className="h-4 w-4 mr-2" />
                  Add First Expense
                </Button>
              </Link>
            </div>
          ) : (
            <>
              <div className="rounded-md border overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Date</TableHead>
                      <TableHead>Expense #</TableHead>
                      <TableHead>Title</TableHead>
                      <TableHead>Category</TableHead>
                      <TableHead>Vendor</TableHead>
                      <TableHead className="text-right">Amount</TableHead>
                      <TableHead>Payment</TableHead>
                      <TableHead>Recorded By</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {expenses.map((expense) => (
                      <TableRow key={expense.id}>
                        <TableCell className="font-medium">
                          {formatDate(expense.expense_date)}
                        </TableCell>
                        <TableCell className="font-mono text-sm">
                          {expense.expense_number}
                        </TableCell>
                        <TableCell>
                          <div className="max-w-[200px]">
                            <div className="font-medium truncate">{expense.title}</div>
                            {expense.description && (
                              <div className="text-sm text-muted-foreground dark:text-muted-foreground/80 truncate">
                                {expense.description}
                              </div>
                            )}
                          </div>
                        </TableCell>
                        <TableCell>
                          {expense.category ? (
                            <Badge
                              variant="outline"
                              style={{
                                backgroundColor: expense.category.color + '20',
                                color: expense.category.color,
                                borderColor: expense.category.color,
                              }}
                            >
                              {expense.category.name}
                            </Badge>
                          ) : (
                            <Badge variant="outline">Uncategorized</Badge>
                          )}
                        </TableCell>
                        <TableCell>
                          <div className="text-sm">{expense.vendor || '-'}</div>
                        </TableCell>
                        <TableCell className="text-right font-bold">
                          {formatCurrency(expense.amount)}
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center gap-2">
                            {PAYMENT_METHOD_ICONS[expense.payment_method]}
                            <span className="text-sm">
                              {PAYMENT_METHOD_LABELS[expense.payment_method]}
                            </span>
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="text-sm">{expense.recorder?.name || 'Unknown'}</div>
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="flex items-center justify-end gap-2">
                            <Link to={`/admin/expenses/${expense.id}/edit`}>
                              <Button variant="ghost" size="sm">
                                <Edit className="h-4 w-4" />
                              </Button>
                            </Link>
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => setExpenseToDelete(expense)}
                            >
                              <Trash2 className="h-4 w-4 text-destructive" />
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>

              {/* Pagination */}
              <DataPagination
                currentPage={currentPage}
                totalPages={totalPages}
                totalRecords={total}
                perPage={15}
                onPageChange={setCurrentPage}
              />
            </>
          )}
        </CardContent>
      </Card>

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={!!expenseToDelete} onOpenChange={() => setExpenseToDelete(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Expense</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete "{expenseToDelete?.title}"? This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
