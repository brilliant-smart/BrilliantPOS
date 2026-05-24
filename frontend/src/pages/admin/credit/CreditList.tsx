import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Phone, CreditCard, Eye, AlertTriangle, Clock, DollarSign, Pencil } from 'lucide-react';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog';
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
import { toast } from 'sonner';

interface CreditSummary {
  total_outstanding: number;
  overdue_count: number;
  overdue_amount: number;
  pending_count: number;
  pending_amount: number;
  credits: CreditItem[] | { data: CreditItem[]; current_page: number; last_page: number; total: number };
}

interface CreditItem {
  id: number;
  sale_number: string;
  sale_date: string;
  customer_name: string | null;
  customer_phone: string | null;
  contact_name: string | null;
  total_amount: number;
  amount_paid: number;
  balance: number;
  days_outstanding: number;
  status: 'pending' | 'overdue';
  cashier_name: string | null;
}

export default function CreditList() {
  const [data, setData] = useState<CreditSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<'all' | 'pending' | 'overdue'>('all');

  // Record payment dialog
  const [paymentOpen, setPaymentOpen] = useState(false);
  const [selectedCredit, setSelectedCredit] = useState<CreditItem | null>(null);
  const [paymentAmount, setPaymentAmount] = useState('');
  const [paymentMethod, setPaymentMethod] = useState('cash');
  const [paymentReference, setPaymentReference] = useState('');
  const [paymentNotes, setPaymentNotes] = useState('');
  const [recording, setRecording] = useState(false);

  // Inline contact name editing
  const [editingContactId, setEditingContactId] = useState<number | null>(null);
  const [editingContactName, setEditingContactName] = useState('');
  const [editingPhoneId, setEditingPhoneId] = useState<number | null>(null);
  const [editingPhoneValue, setEditingPhoneValue] = useState('');

  useEffect(() => {
    loadCredits();
  }, []);

  const loadCredits = async () => {
    try {
      setLoading(true);
      const result = await salesApi.getCreditSummary();
      setData(result);
    } catch {
      toast.error('Failed to load credit data');
    } finally {
      setLoading(false);
    }
  };

  const handleRecordPayment = async () => {
    if (!selectedCredit || !paymentAmount) return;
    try {
      setRecording(true);
      await salesApi.recordPaymentExtended(selectedCredit.id, {
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
      loadCredits();
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to record payment');
    } finally {
      setRecording(false);
    }
  };

  const openPaymentDialog = (credit: CreditItem) => {
    setSelectedCredit(credit);
    setPaymentAmount(String(credit.balance));
    setPaymentOpen(true);
  };

  const startEditingContact = (credit: CreditItem) => {
    setEditingContactId(credit.id);
    setEditingContactName(credit.contact_name || '');
  };

  const saveContactName = async (creditId: number) => {
    try {
      await salesApi.updateContact(creditId, editingContactName);
      toast.success('Contact name saved');
      setEditingContactId(null);
      loadCredits();
    } catch {
      toast.error('Failed to save contact name');
    }
  };

  const startEditingPhone = (credit: CreditItem) => {
    setEditingPhoneId(credit.id);
    setEditingPhoneValue(credit.customer_phone || '');
  };

  const savePhone = async (creditId: number) => {
    try {
      await salesApi.updateContact(creditId, undefined, editingPhoneValue);
      toast.success('Phone number saved');
      setEditingPhoneId(null);
      loadCredits();
    } catch {
      toast.error('Failed to save phone number');
    }
  };

  const formatCurrency = (amount: number | null | undefined) => {
    if (amount == null) return '₦0.00';
    return `₦${Number(amount).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  };

  // Handle both flat array and paginated response
  const creditsArray = Array.isArray(data?.credits) ? data.credits : (data?.credits as any)?.data ?? [];
  const filteredCredits = creditsArray.filter((c: CreditItem) => {
    if (filter === 'all') return true;
    return c.status === filter;
  });

  if (loading) {
    return (
      <div className="p-4 md:p-6">
        <div className="text-center py-12">
          <p className="text-muted-foreground">Loading credit data...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Outstanding Credit</h1>
          <p className="text-muted-foreground dark:text-muted-foreground/80">Track and manage unpaid credit sales</p>
        </div>
      </div>

      {/* Summary Cards */}
      {data && (
        <div className="grid gap-4 md:grid-cols-3">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium">Total Outstanding</CardTitle>
              <DollarSign className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{formatCurrency(data.total_outstanding)}</div>
              <p className="text-xs text-muted-foreground">{data.overdue_count + data.pending_count} credit sale{(data.overdue_count + data.pending_count) !== 1 ? 's' : ''}</p>
            </CardContent>
          </Card>
          <Card className="border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/30">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-red-900 dark:text-red-400">Overdue</CardTitle>
              <AlertTriangle className="h-4 w-4 text-red-600 dark:text-red-400" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-red-900 dark:text-red-400">{formatCurrency(data.overdue_amount)}</div>
              <p className="text-xs text-red-700 dark:text-red-400">{data.overdue_count} overdue sale{data.overdue_count !== 1 ? 's' : ''}</p>
            </CardContent>
          </Card>
          <Card className="border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-950/30">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-yellow-900 dark:text-yellow-400">Pending</CardTitle>
              <Clock className="h-4 w-4 text-yellow-600 dark:text-yellow-400" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-yellow-900 dark:text-yellow-400">{formatCurrency(data.pending_amount)}</div>
              <p className="text-xs text-yellow-700 dark:text-yellow-400">{data.pending_count} pending sale{data.pending_count !== 1 ? 's' : ''}</p>
            </CardContent>
          </Card>
        </div>
      )}

      {/* Filter Tabs */}
      <div className="flex gap-2">
        <Button variant={filter === 'all' ? 'default' : 'outline'} size="sm" onClick={() => setFilter('all')}>All</Button>
        <Button variant={filter === 'overdue' ? 'destructive' : 'outline'} size="sm" onClick={() => setFilter('overdue')}>Overdue</Button>
        <Button variant={filter === 'pending' ? 'secondary' : 'outline'} size="sm" onClick={() => setFilter('pending')}>Pending</Button>
      </div>

      {/* Data Table */}
      {filteredCredits.length === 0 ? (
        <div className="text-center py-12">
          <p className="text-muted-foreground">No outstanding credit sales</p>
        </div>
      ) : (
        <div className="border rounded-lg overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Sale #</TableHead>
                <TableHead>Customer</TableHead>
                <TableHead>Name</TableHead>
                <TableHead>Phone</TableHead>
                <TableHead className="text-right">Total</TableHead>
                <TableHead className="text-right">Paid</TableHead>
                <TableHead className="text-right">Balance</TableHead>
                <TableHead className="text-center">Days</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredCredits.map((credit) => (
                <TableRow key={credit.id}>
                  <TableCell className="font-medium">
                    <Link to="/admin/sales" className="text-primary hover:underline">{credit.sale_number}</Link>
                  </TableCell>
                  <TableCell>{credit.customer_name || 'Walk-in'}</TableCell>
                  <TableCell>
                    {editingContactId === credit.id ? (
                      <Input
                        autoFocus
                        className="h-7 text-sm w-32"
                        value={editingContactName}
                        onChange={(e) => setEditingContactName(e.target.value)}
                        onBlur={() => saveContactName(credit.id)}
                        onKeyDown={(e) => {
                          if (e.key === 'Enter') saveContactName(credit.id);
                          if (e.key === 'Escape') setEditingContactId(null);
                        }}
                      />
                    ) : (
                      <span
                        className="cursor-pointer group inline-flex items-center gap-1"
                        onClick={() => startEditingContact(credit)}
                      >
                        {credit.contact_name || <span className="text-muted-foreground">Add</span>}
                        <Pencil className="h-3 w-3 opacity-0 group-hover:opacity-100 transition-opacity" />
                      </span>
                    )}
                  </TableCell>
                  <TableCell>
                    {editingPhoneId === credit.id ? (
                      <Input
                        autoFocus
                        className="h-7 text-sm w-36"
                        value={editingPhoneValue}
                        onChange={(e) => setEditingPhoneValue(e.target.value)}
                        onBlur={() => savePhone(credit.id)}
                        onKeyDown={(e) => {
                          if (e.key === 'Enter') savePhone(credit.id);
                          if (e.key === 'Escape') setEditingPhoneId(null);
                        }}
                      />
                    ) : (
                      <span
                        className="cursor-pointer group inline-flex items-center gap-1"
                        onClick={() => startEditingPhone(credit)}
                      >
                        {credit.customer_phone ? (
                          <a href={`tel:${credit.customer_phone}`} className="text-primary hover:underline flex items-center gap-1" onClick={(e) => e.stopPropagation()}>
                            <Phone className="h-3 w-3" />
                            {credit.customer_phone}
                          </a>
                        ) : (
                          <span className="text-muted-foreground">Add</span>
                        )}
                        <Pencil className="h-3 w-3 opacity-0 group-hover:opacity-100 transition-opacity" />
                      </span>
                    )}
                  </TableCell>
                  <TableCell className="text-right">{formatCurrency(credit.total_amount)}</TableCell>
                  <TableCell className="text-right">{formatCurrency(credit.amount_paid)}</TableCell>
                  <TableCell className="text-right font-medium text-red-600 dark:text-red-400">{formatCurrency(credit.balance)}</TableCell>
                  <TableCell className="text-center">{credit.days_outstanding}</TableCell>
                  <TableCell>
                    {credit.status === 'overdue' ? (
                      <Badge variant="destructive">Overdue</Badge>
                    ) : (
                      <Badge variant="secondary">Pending</Badge>
                    )}
                  </TableCell>
                  <TableCell className="text-right">
                    <div className="flex justify-end gap-1">
                      <Button variant="outline" size="sm" onClick={() => openPaymentDialog(credit)}>
                        <CreditCard className="h-3 w-3 mr-1" />
                        Pay
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      {/* Record Payment Dialog */}
      <Dialog open={paymentOpen} onOpenChange={setPaymentOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Record Payment</DialogTitle>
          </DialogHeader>
          {selectedCredit && (
            <div className="space-y-4">
              <div className="p-3 bg-muted rounded-lg text-sm">
                <div className="flex justify-between">
                  <span>Sale</span>
                  <span className="font-medium">{selectedCredit.sale_number}</span>
                </div>
                <div className="flex justify-between">
                  <span>Customer</span>
                  <span className="font-medium">{selectedCredit.customer_name || 'Walk-in'}</span>
                </div>
                <div className="flex justify-between text-red-600 dark:text-red-400 font-medium">
                  <span>Balance Due</span>
                  <span>{formatCurrency(selectedCredit.balance)}</span>
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
                <Input value={paymentReference} onChange={(e) => setPaymentReference(e.target.value)} placeholder="Transaction reference..." />
              </div>
              <div>
                <Label>Notes (Optional)</Label>
                <Input value={paymentNotes} onChange={(e) => setPaymentNotes(e.target.value)} placeholder="Payment notes..." />
              </div>
            </div>
          )}
          <DialogFooter>
            <Button variant="outline" onClick={() => setPaymentOpen(false)}>Cancel</Button>
            <Button onClick={handleRecordPayment} disabled={recording || !paymentAmount}>
              {recording ? 'Recording...' : 'Record Payment'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}