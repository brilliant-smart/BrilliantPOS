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

export default function AuditLogs() {
  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [loading, setLoading] = useState(false);
  const [filters, setFilters] = useState<AuditLogFilters>({
    page: 1,
    per_page: 50,
  });
  const [totalRecords, setTotalRecords] = useState(0);
  const [statistics, setStatistics] = useState<any>(null);

  useEffect(() => {
    loadLogs();
    loadStatistics();
  }, [filters]);

  const loadLogs = async () => {
    try {
      setLoading(true);
      const data = await auditLogApi.getAuditLogs(filters);
      setLogs(data.data || data.audit_logs || []);
      setTotalRecords(data.total || data.data?.length || 0);
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
      const { per_page, page, ...exportFilters } = filters;
      const allData = await auditLogApi.getAuditLogs({ ...exportFilters, per_page: 10000 });
      const allLogs: AuditLog[] = allData.data || allData.audit_logs || [];

      const headers = ['Date', 'User', 'Action', 'Model', 'IP Address'];
      const rows = allLogs.map(log => [
        format(new Date(log.created_at), 'yyyy-MM-dd HH:mm:ss'),
        log.user?.name || 'N/A',
        log.action,
        log.model_type,
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

  const getActionColor = (action: string) => {
    const colors: Record<string, string> = {
      created: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-400',
      updated: 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-400',
      deleted: 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-400',
      restored: 'bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-400',
      login: 'bg-cyan-100 dark:bg-cyan-900/40 text-cyan-800 dark:text-cyan-400',
      logout: 'bg-muted text-foreground',
    };
    return colors[action.toLowerCase()] || 'bg-muted text-foreground';
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold">Audit Logs</h1>
          <p className="text-muted-foreground mt-1">
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
              <Label>Action</Label>
              <Select
                value={filters.action || 'all'}
                onValueChange={(v) =>
                  setFilters({ ...filters, action: v === 'all' ? undefined : v, page: 1 })
                }
              >
                <SelectTrigger>
                  <SelectValue placeholder="All actions" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Actions</SelectItem>
                  <SelectItem value="created">Created</SelectItem>
                  <SelectItem value="updated">Updated</SelectItem>
                  <SelectItem value="deleted">Deleted</SelectItem>
                  <SelectItem value="login">Login</SelectItem>
                  <SelectItem value="logout">Logout</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label>Model</Label>
              <Select
                value={filters.model_type || 'all'}
                onValueChange={(v) =>
                  setFilters({ ...filters, model_type: v === 'all' ? undefined : v, page: 1 })
                }
              >
                <SelectTrigger>
                  <SelectValue placeholder="All models" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Models</SelectItem>
                  <SelectItem value="Product">Product</SelectItem>
                  <SelectItem value="Sale">Sale</SelectItem>
                  <SelectItem value="PurchaseOrder">Purchase Order</SelectItem>
                  <SelectItem value="User">User</SelectItem>
                  <SelectItem value="Supplier">Supplier</SelectItem>
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
              onClick={() => setFilters({ page: 1, per_page: 50 })}
            >
              Clear Filters
            </Button>
          </div>
        </CardContent>
      </Card>

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
              <p className="text-muted-foreground">Loading...</p>
            </div>
          ) : logs.length === 0 ? (
            <div className="text-center py-12">
              <FileText className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
              <p className="text-muted-foreground">No audit logs found</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Date & Time</TableHead>
                    <TableHead>User</TableHead>
                    <TableHead>Action</TableHead>
                    <TableHead>Model</TableHead>
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
                          <p className="text-xs text-muted-foreground">
                            {log.user?.email || 'N/A'}
                          </p>
                        </div>
                      </TableCell>
                      <TableCell>
                        <Badge className={getActionColor(log.action)}>
                          {log.action}
                        </Badge>
                      </TableCell>
                      <TableCell className="font-mono text-sm">
                        {log.model_type}
                        {log.model_id && (
                          <span className="text-muted-foreground"> #{log.model_id}</span>
                        )}
                      </TableCell>
                      <TableCell className="font-mono text-xs text-muted-foreground">
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
