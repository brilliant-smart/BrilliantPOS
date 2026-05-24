import { useState, useEffect } from 'react';
import { FileText, Download, Filter, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { auditLogApi, AuditLog, AuditLogFilters } from '@/app/api/auditLogs';
import { toast } from 'sonner';
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
import { format } from 'date-fns';
import { Badge } from '@/components/ui/badge';
import { DatePicker } from '@/components/DatePicker';
import { DataPagination } from '@/components/DataPagination';

const PER_PAGE = 50;

const ACTION_CATEGORIES = [
  { value: 'create', label: 'Created' },
  { value: 'update', label: 'Updated' },
  { value: 'delete', label: 'Deleted' },
  { value: 'auth', label: 'Sign In / Sign Out' },
  { value: 'security', label: 'Security Changes' },
  { value: 'backup', label: 'Backup Operations' },
  { value: 'stock', label: 'Stock Adjustments' },
  { value: 'void', label: 'Voided / Cancelled' },
  { value: 'approve', label: 'Approved' },
  { value: 'payment', label: 'Payments' },
];

const MODEL_TYPES = [
  { value: 'User', label: 'User' },
  { value: 'Product', label: 'Product' },
  { value: 'Sale', label: 'Sale' },
  { value: 'PurchaseOrder', label: 'Purchase Order' },
  { value: 'Supplier', label: 'Supplier' },
  { value: 'Expense', label: 'Expense' },
  { value: 'ExpenseCategory', label: 'Expense Category' },
  { value: 'BatchTracking', label: 'Batch' },
  { value: 'Setting', label: 'Settings' },
  { value: 'SecuritySetting', label: 'Security Settings' },
  { value: 'Backup', label: 'Backup' },
];

const CATEGORY_COLORS: Record<string, string> = {
  create: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-400',
  update: 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-400',
  delete: 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-400',
  auth: 'bg-cyan-100 dark:bg-cyan-900/40 text-cyan-800 dark:text-cyan-400',
  security: 'bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-400',
  backup: 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-400',
  stock: 'bg-teal-100 dark:bg-teal-900/40 text-teal-800 dark:text-teal-400',
  void: 'bg-orange-100 dark:bg-orange-900/40 text-orange-800 dark:text-orange-400',
  approve: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-400',
  payment: 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-800 dark:text-indigo-400',
  other: 'bg-muted text-foreground',
};

export default function AuditLogs() {
  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [loading, setLoading] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalRecords, setTotalRecords] = useState(0);
  const [filters, setFilters] = useState<AuditLogFilters>({
    page: 1,
    per_page: PER_PAGE,
  });
  const [statistics, setStatistics] = useState<any>(null);

  useEffect(() => {
    loadLogs(filters.page || 1);
    loadStatistics();
  }, [filters]);

  const loadLogs = async (page: number = 1) => {
    try {
      setLoading(true);
      const data = await auditLogApi.getAuditLogs({ ...filters, page, per_page: PER_PAGE });
      setLogs(data.data || []);
      setCurrentPage(data.meta?.current_page || data.current_page || page);
      setTotalPages(data.meta?.last_page || data.last_page || 1);
      setTotalRecords(data.meta?.total || data.total || 0);
    } catch (error: any) {
      toast.error('Failed to load audit logs');
      console.error('Audit log load error:', error);
    } finally {
      setLoading(false);
    }
  };

  const loadStatistics = async () => {
    try {
      const data = await auditLogApi.getStatistics();
      setStatistics(data);
    } catch (error: any) {
      console.error('Statistics load error:', error);
    }
  };

  const handleExport = async () => {
    try {
      const { page, per_page, ...exportFilters } = filters;
      const allData = await auditLogApi.getAuditLogs({ ...exportFilters, per_page: 5000 });
      const allLogs: AuditLog[] = allData.data || allData.audit_logs || [];

      const headers = ['Date', 'User', 'Action', 'Target', 'IP Address'];
      const rows = allLogs.map(log => [
        format(new Date(log.created_at), 'yyyy-MM-dd HH:mm:ss'),
        log.user?.name || 'System',
        log.action_label,
        log.model_label + (log.model_id ? ` #${log.model_id}` : ''),
        log.ip_address,
      ]);

      const csv = [
        headers.join(','),
        ...rows.map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')),
      ].join('\n');

      const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `audit_logs_${format(new Date(), 'yyyy-MM-dd')}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);

      toast.success(`Exported ${allLogs.length} audit logs`);
    } catch (error) {
      toast.error('Failed to export audit logs');
    }
  };

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold">Audit Logs</h1>
          <p className="text-muted-foreground dark:text-muted-foreground/80 mt-1">
            Track all system activities and changes
          </p>
        </div>
        <Button onClick={handleExport}>
          <Download className="h-4 w-4 mr-2" />
          Export CSV
        </Button>
      </div>

      {statistics && (
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <Card>
            <CardHeader className="pb-2">
              <CardDescription>Total Actions</CardDescription>
              <CardTitle className="text-3xl">{statistics.total_actions || 0}</CardTitle>
            </CardHeader>
          </Card>
          <Card>
            <CardHeader className="pb-2">
              <CardDescription>Today</CardDescription>
              <CardTitle className="text-3xl">{statistics.today_actions || 0}</CardTitle>
            </CardHeader>
          </Card>
          <Card>
            <CardHeader className="pb-2">
              <CardDescription>This Week</CardDescription>
              <CardTitle className="text-3xl">{statistics.week_actions || 0}</CardTitle>
            </CardHeader>
          </Card>
          <Card>
            <CardHeader className="pb-2">
              <CardDescription>Active Users</CardDescription>
              <CardTitle className="text-3xl">{statistics.active_users || 0}</CardTitle>
            </CardHeader>
          </Card>
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Filter className="h-5 w-5" />
            Filters
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className="space-y-2">
              <Label>Action Type</Label>
              <Select
                value={filters.action_category || 'all'}
                onValueChange={(v) =>
                  setFilters({ ...filters, action_category: v === 'all' ? undefined : v, page: 1 })
                }
              >
                <SelectTrigger>
                  <SelectValue placeholder="All actions" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Actions</SelectItem>
                  {ACTION_CATEGORIES.map(cat => (
                    <SelectItem key={cat.value} value={cat.value}>{cat.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label>Target</Label>
              <Select
                value={filters.model_type || 'all'}
                onValueChange={(v) =>
                  setFilters({ ...filters, model_type: v === 'all' ? undefined : v, page: 1 })
                }
              >
                <SelectTrigger>
                  <SelectValue placeholder="All targets" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Targets</SelectItem>
                  {MODEL_TYPES.map(model => (
                    <SelectItem key={model.value} value={model.value}>{model.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label>Start Date</Label>
              <DatePicker
                value={filters.start_date || ''}
                onChange={(v) => setFilters({ ...filters, start_date: v, page: 1 })}
              />
            </div>

            <div className="space-y-2">
              <Label>End Date</Label>
              <DatePicker
                value={filters.end_date || ''}
                onChange={(v) => setFilters({ ...filters, end_date: v, page: 1 })}
              />
            </div>
          </div>

          <div className="mt-4 flex justify-end">
            <Button
              variant="outline"
              onClick={() => { setFilters({ page: 1, per_page: PER_PAGE }); setCurrentPage(1); }}
            >
              Clear Filters
            </Button>
          </div>
        </CardContent>
      </Card>

      <DataPagination
        currentPage={currentPage}
        totalPages={totalPages}
        totalRecords={totalRecords}
        perPage={PER_PAGE}
        onPageChange={(page) => {
          setCurrentPage(page);
          setFilters({ ...filters, page });
        }}
      />

      <Card>
        <CardHeader>
          <CardTitle>Activity Log</CardTitle>
          <CardDescription>
            {logs.length} record{logs.length !== 1 ? 's' : ''} found
          </CardDescription>
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="text-center py-12">
              <p className="text-muted-foreground dark:text-muted-foreground/80">Loading...</p>
            </div>
          ) : logs.length === 0 ? (
            <div className="text-center py-12">
              <FileText className="h-12 w-12 text-muted-foreground dark:text-muted-foreground/80 mx-auto mb-4" />
              <p className="text-muted-foreground dark:text-muted-foreground/80">No audit logs found</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Date & Time</TableHead>
                    <TableHead>User</TableHead>
                    <TableHead>Action</TableHead>
                    <TableHead>Target</TableHead>
                    <TableHead>IP Address</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {logs.map((log) => (
                    <TableRow key={log.id}>
                      <TableCell className="text-sm">
                        {format(new Date(log.created_at), 'PPpp')}
                      </TableCell>
                      <TableCell>
                        <div>
                          <p className="font-medium">{log.user?.name || 'System'}</p>
                          <p className="text-xs text-muted-foreground dark:text-muted-foreground/80">
                            {log.user?.email || 'N/A'}
                          </p>
                        </div>
                      </TableCell>
                      <TableCell>
                        <Badge className={CATEGORY_COLORS[log.action_category] || CATEGORY_COLORS.other}>
                          {log.action_label}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-sm">
                        <span className="font-medium">{log.model_label}</span>
                        {log.model_id && (
                          <span className="text-muted-foreground dark:text-muted-foreground/80"> #{log.model_id}</span>
                        )}
                      </TableCell>
                      <TableCell className="font-mono text-xs text-muted-foreground dark:text-muted-foreground/80">
                        {log.ip_address}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}