import { useState, useEffect } from 'react';
import { Package, Calendar, AlertTriangle, CheckCircle, XCircle, Filter, Search } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { api } from '@/app/lib/api';
import { toast } from 'sonner';
import { format } from 'date-fns';

interface Batch {
  id: number;
  batch_number: string;
  product_id: number;
  product_name: string;
  product_sku: string;
  quantity_received: number;
  quantity_remaining: number;
  cost_price: string | number;
  manufacturing_date: string | null;
  expiry_date: string | null;
  supplier_name: string;
  status: 'active' | 'expired' | 'depleted' | 'pending';
  created_at: string;
}

export default function BatchList() {
  const [batches, setBatches] = useState<Batch[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [expiryFilter, setExpiryFilter] = useState('all');

  useEffect(() => {
    loadBatches();
  }, []);

  const loadBatches = async () => {
    try {
      setLoading(true);
      const response = await api.get('/batches');
      setBatches(response.data.data || response.data);
    } catch (error: any) {
      toast.error('Failed to load batches');
      console.error('Error loading batches:', error);
    } finally {
      setLoading(false);
    }
  };

  const getExpiryStatus = (expiryDate: string | null) => {
    if (!expiryDate) return null;
    
    const expiry = new Date(expiryDate);
    const today = new Date();
    const daysUntilExpiry = Math.ceil((expiry.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));

    if (daysUntilExpiry < 0) {
      return { status: 'expired', label: 'Expired', color: 'destructive' as const };
    } else if (daysUntilExpiry <= 30) {
      return { status: 'expiring_soon', label: `${daysUntilExpiry}d left`, color: 'default' as const, className: 'bg-orange-500' };
    } else if (daysUntilExpiry <= 90) {
      return { status: 'warning', label: `${daysUntilExpiry}d left`, color: 'secondary' as const };
    }
    return { status: 'good', label: `${daysUntilExpiry}d left`, color: 'outline' as const };
  };

  const getStatusBadge = (batch: Batch) => {
    if (batch.status === 'expired') {
      return <Badge variant="destructive"><XCircle className="h-3 w-3 mr-1" />Expired</Badge>;
    }
    if (batch.status === 'depleted') {
      return <Badge variant="secondary"><XCircle className="h-3 w-3 mr-1" />Depleted</Badge>;
    }
    if (batch.status === 'pending') {
      return <Badge variant="outline"><AlertTriangle className="h-3 w-3 mr-1" />Pending</Badge>;
    }
    return <Badge variant="default" className="bg-green-600 dark:bg-green-700"><CheckCircle className="h-3 w-3 mr-1" />Active</Badge>;
  };

  const filteredBatches = batches.filter(batch => {
    // Search filter
    const matchesSearch = searchQuery === '' || 
      batch.batch_number.toLowerCase().includes(searchQuery.toLowerCase()) ||
      batch.product_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      batch.product_sku?.toLowerCase().includes(searchQuery.toLowerCase());

    // Status filter
    const matchesStatus = statusFilter === 'all' || batch.status === statusFilter;

    // Expiry filter
    let matchesExpiry = true;
    if (expiryFilter !== 'all' && batch.expiry_date) {
      const expiryStatus = getExpiryStatus(batch.expiry_date);
      if (expiryFilter === 'expiring_soon') {
        matchesExpiry = expiryStatus?.status === 'expiring_soon';
      } else if (expiryFilter === 'expired') {
        matchesExpiry = expiryStatus?.status === 'expired';
      }
    }

    return matchesSearch && matchesStatus && matchesExpiry;
  });

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto"></div>
          <p className="mt-4 text-muted-foreground">Loading batches...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Batch Management</h1>
          <p className="text-muted-foreground">Track and manage product batches with expiry dates</p>
        </div>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="pt-6">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search by batch number, product..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-10"
              />
            </div>

            <Select value={statusFilter} onValueChange={setStatusFilter}>
              <SelectTrigger>
                <Filter className="mr-2 h-4 w-4" />
                <SelectValue placeholder="Filter by status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Status</SelectItem>
                <SelectItem value="active">Active</SelectItem>
                <SelectItem value="pending">Pending</SelectItem>
                <SelectItem value="expired">Expired</SelectItem>
                <SelectItem value="depleted">Depleted</SelectItem>
              </SelectContent>
            </Select>

            <Select value={expiryFilter} onValueChange={setExpiryFilter}>
              <SelectTrigger>
                <Calendar className="mr-2 h-4 w-4" />
                <SelectValue placeholder="Filter by expiry" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Batches</SelectItem>
                <SelectItem value="expiring_soon">Expiring Soon (≤30d)</SelectItem>
                <SelectItem value="expired">Expired</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      {/* Statistics */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Total Batches</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{batches.length}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Active Batches</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-green-600 dark:text-green-400">
              {batches.filter(b => b.status === 'active').length}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Expiring Soon</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-orange-600 dark:text-orange-400">
              {batches.filter(b => {
                const status = b.expiry_date ? getExpiryStatus(b.expiry_date) : null;
                return status?.status === 'expiring_soon';
              }).length}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Expired</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-red-600 dark:text-red-400">
              {batches.filter(b => b.status === 'expired').length}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Batches Table */}
      <Card>
        <CardHeader>
          <CardTitle>Batch Records ({filteredBatches.length})</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Batch Number</TableHead>
                <TableHead>Product</TableHead>
                <TableHead>Supplier</TableHead>
                <TableHead>Qty Remaining</TableHead>
                <TableHead>Manufacturing</TableHead>
                <TableHead>Expiry Date</TableHead>
                <TableHead>Status</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredBatches.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className="text-center text-muted-foreground py-8">
                    No batches found
                  </TableCell>
                </TableRow>
              ) : (
                filteredBatches.map((batch) => {
                  const expiryStatus = batch.expiry_date ? getExpiryStatus(batch.expiry_date) : null;
                  
                  return (
                    <TableRow key={batch.id}>
                      <TableCell className="font-mono font-semibold">{batch.batch_number}</TableCell>
                      <TableCell>
                        <div>
                          <div className="font-medium">{batch.product_name}</div>
                          {batch.product_sku && (
                            <div className="text-xs text-muted-foreground">SKU: {batch.product_sku}</div>
                          )}
                        </div>
                      </TableCell>
                      <TableCell>{batch.supplier_name}</TableCell>
                      <TableCell>
                        <div className="font-semibold">
                          {batch.quantity_remaining} / {batch.quantity_received}
                        </div>
                      </TableCell>
                      <TableCell>
                        {batch.manufacturing_date ? (
                          <div className="text-sm">
                            {format(new Date(batch.manufacturing_date), 'MMMM yyyy')}
                          </div>
                        ) : (
                          <span className="text-muted-foreground">N/A</span>
                        )}
                      </TableCell>
                      <TableCell>
                        {batch.expiry_date ? (
                          <div className="space-y-1">
                            <div className="text-sm font-medium">
                              {format(new Date(batch.expiry_date), 'MMMM yyyy')}
                            </div>
                            {expiryStatus && (
                              <Badge variant={expiryStatus.color} className={expiryStatus.className}>
                                {expiryStatus.label}
                              </Badge>
                            )}
                          </div>
                        ) : (
                          <span className="text-muted-foreground">N/A</span>
                        )}
                      </TableCell>
                      <TableCell>{getStatusBadge(batch)}</TableCell>
                    </TableRow>
                  );
                })
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}
