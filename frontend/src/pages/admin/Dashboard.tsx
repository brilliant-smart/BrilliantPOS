import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { useEffect, useState } from "react";
import { api } from "@/app/lib/api";
import { useNavigate } from "react-router-dom";
import {
  Package,
  Star,
  CheckCircle,
  XCircle,
  TrendingUp,
  TrendingDown,
  Users,
  UserCheck,
  UserPlus,
  PackagePlus,
  ShieldCheck,
  AlertCircle,
  FileText,
  DollarSign,
  AlertTriangle,
  Clock
} from "lucide-react";
import {
  PieChart,
  Pie,
  Cell,
  ResponsiveContainer,
  Tooltip,
  Legend,
} from "recharts";
import { useAuth } from "@/app/auth/AuthContext";

interface RecentProduct {
  id: number;
  name: string;
  price: number;
  is_active: boolean;
  is_featured: boolean;
  created_at: string;
}

interface UserRoleStat {
  name: string;
  value: number;
  role: string;
}

interface Stats {
  // User stats
  totalUsers: number;
  activeUsers: number;
  inactiveUsers: number;
  owners: number;
  managers: number;
  cashiers: number;
  recentUsers: number;
  userTrend: number;
  usersByRole: UserRoleStat[];

  // Product stats
  totalProducts: number;
  activeProducts: number;
  inactiveProducts: number;
  featuredProducts: number;
  recentlyAdded: number;
  productTrend: number;

  // Recent activity
  recentProducts: RecentProduct[];

  // Period
  period: {
    days: number;
    startDate: string;
    endDate: string;
  };
}

const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];

const ROLE_LABELS: Record<string, string> = {
  owner: "Owner",
  manager: "Manager",
  cashier: "Cashier",
};

export default function Dashboard() {
  const [stats, setStats] = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);
  const [dateRange, setDateRange] = useState(30);
  const [pendingPOs, setPendingPOs] = useState<any[]>([]);
  const [unpaidPOs, setUnpaidPOs] = useState<any[]>([]);
  const [lowStockAlerts, setLowStockAlerts] = useState<any[]>([]);
  const [expiryAlerts, setExpiryAlerts] = useState<any[]>([]);
  const navigate = useNavigate();
  const { user } = useAuth();

  const loadStats = (days: number = 30) => {
    setLoading(true);
    api.get(`/admin/dashboard-stats?days=${days}`)
      .then((res) => {
        setStats(res.data);
        setLoading(false);
      })
      .catch((err) => {
        console.error("Error loading dashboard stats:", err);
        setLoading(false);
      });
  };

  const loadPOAlerts = async () => {
    try {
      // Load pending POs for approval (Owner only)
      if (user?.role === 'owner') {
        const pendingResponse = await api.get('/purchase-orders', {
          params: { status: 'pending', per_page: 5 }
        });
        setPendingPOs(pendingResponse.data.data || []);
      }

      // Load unpaid/partially paid POs
      const unpaidResponse = await api.get('/purchase-orders', {
        params: { per_page: 100 }
      });
      const allPOs = unpaidResponse.data.data || [];
      const needsPayment = allPOs.filter((po: any) =>
        ['approved', 'received'].includes(po.status) &&
        ['unpaid', 'partially_paid'].includes(po.payment_status)
      );
      setUnpaidPOs(needsPayment.slice(0, 5));

      // Load low stock alerts
      const lowStockResponse = await api.get('/alerts/low-stock');
      setLowStockAlerts((lowStockResponse.data.alerts || []).slice(0, 5));

      // Load expiring batches
      const expiryResponse = await api.get('/alerts/expiring-batches', {
        params: { days: 90 }
      });
      setExpiryAlerts((expiryResponse.data.alerts || []).slice(0, 5));
    } catch (error) {
      console.error('Failed to load alerts:', error);
    }
  };

  useEffect(() => {
    loadStats(dateRange);
    loadPOAlerts();
  }, [dateRange]);

  const handleDateRangeChange = (days: number) => {
    setDateRange(days);
  };

  if (loading) {
    return (
      <div className="p-4 md:p-6">
        <h1 className="text-3xl font-display mb-6">Dashboard</h1>
        <div className="text-muted-foreground">Loading analytics...</div>
      </div>
    );
  }

  const isOwner = user?.role === "owner";

  return (
    <div className="space-y-6">
      {/* Header with Quick Actions */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 className="text-3xl font-display">Dashboard</h1>
          <p className="text-muted-foreground mt-1">
            Overview of your store's performance and analytics
          </p>
        </div>

        {/* Quick Actions */}
        <div className="flex flex-wrap gap-2">
          <Button
            onClick={() => navigate('/admin/products/create')}
            className="gap-2"
          >
            <PackagePlus className="h-4 w-4" />
            Add Product
          </Button>
          {isOwner && (
            <Button
              onClick={() => navigate('/admin/users/create')}
              variant="secondary"
              className="gap-2"
            >
              <UserPlus className="h-4 w-4" />
              Add User
            </Button>
          )}
        </div>
      </div>

      {/* PO Alerts */}
      {isOwner && pendingPOs.length > 0 && (
        <Card className="border-orange-200 bg-orange-50 dark:border-orange-800 dark:bg-orange-950/30">
          <CardHeader>
            <div className="flex items-start justify-between">
              <div className="flex items-center gap-2">
                <AlertCircle className="h-5 w-5 text-orange-600 dark:text-orange-400" />
                <CardTitle className="text-orange-900 dark:text-orange-300">
                  {pendingPOs.length} Purchase Order{pendingPOs.length > 1 ? 's' : ''} Awaiting Approval
                </CardTitle>
              </div>
              <Button
                size="sm"
                onClick={() => navigate('/admin/purchase-orders')}
                className="bg-orange-600 hover:bg-orange-700 dark:bg-orange-700 dark:hover:bg-orange-600"
              >
                <FileText className="h-4 w-4 mr-2" />
                View All
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              {pendingPOs.map((po) => (
                <div
                  key={po.id}
                  className="flex items-center justify-between p-2 bg-card rounded cursor-pointer hover:bg-orange-100 dark:hover:bg-orange-900/30"
                  onClick={() => navigate(`/admin/purchase-orders/${po.id}`)}
                >
                  <div>
                    <p className="font-medium">{po.po_number}</p>
                    <p className="text-sm text-muted-foreground">
                      {po.supplier?.name} - #{po.total_amount?.toLocaleString()}
                    </p>
                  </div>
                  <Badge variant="secondary" className="bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300">
                    Pending
                  </Badge>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {unpaidPOs.length > 0 && (
        <Card className="border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/30">
          <CardHeader>
            <div className="flex items-start justify-between">
              <div className="flex items-center gap-2">
                <DollarSign className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                <CardTitle className="text-blue-900 dark:text-blue-300">
                  {unpaidPOs.length} Purchase Order{unpaidPOs.length > 1 ? 's' : ''} with Pending Payment
                </CardTitle>
              </div>
              <Button
                size="sm"
                onClick={() => navigate('/admin/purchase-orders')}
                className="bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600"
              >
                <FileText className="h-4 w-4 mr-2" />
                View All
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              {unpaidPOs.map((po) => (
                <div
                  key={po.id}
                  className="flex items-center justify-between p-2 bg-card rounded cursor-pointer hover:bg-blue-100 dark:hover:bg-blue-900/30"
                  onClick={() => navigate(`/admin/purchase-orders/${po.id}`)}
                >
                  <div>
                    <p className="font-medium">{po.po_number}</p>
                    <p className="text-sm text-muted-foreground">
                      Balance: #{((po.total_amount || 0) - (po.amount_paid || 0)).toLocaleString()}
                    </p>
                  </div>
                  <Badge variant="secondary" className="bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                    {po.payment_status === 'unpaid' ? 'Unpaid' : 'Partially Paid'}
                  </Badge>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Low Stock Alerts */}
      {lowStockAlerts.length > 0 && (
        <Card className="border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/30">
          <CardHeader>
            <div className="flex items-start justify-between">
              <div className="flex items-center gap-2">
                <AlertTriangle className="h-5 w-5 text-red-600 dark:text-red-400" />
                <CardTitle className="text-red-900 dark:text-red-300">
                  {lowStockAlerts.length} Product{lowStockAlerts.length > 1 ? 's' : ''} Low on Stock
                </CardTitle>
              </div>
              <Button
                size="sm"
                onClick={() => navigate('/admin/products')}
                className="bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600"
              >
                <Package className="h-4 w-4 mr-2" />
                View Products
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              {lowStockAlerts.map((alert) => (
                <div
                  key={alert.id}
                  className="flex items-center justify-between p-2 bg-card rounded cursor-pointer hover:bg-red-100 dark:hover:bg-red-900/30"
                  onClick={() => navigate('/admin/products')}
                >
                  <div>
                    <p className="font-medium">{alert.name}</p>
                    <p className="text-sm text-muted-foreground">
                      Stock: {alert.stock_quantity} (Reorder at: {alert.reorder_level})
                    </p>
                  </div>
                  <Badge
                    variant="secondary"
                    className={
                      alert.severity === 'critical'
                        ? 'bg-red-600 text-white dark:bg-red-700'
                        : alert.severity === 'high'
                        ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'
                        : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300'
                    }
                  >
                    {alert.stock_quantity === 0 ? 'Out of Stock' : 'Low Stock'}
                  </Badge>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Expiry Alerts */}
      {expiryAlerts.length > 0 && (
        <Card className="border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-950/30">
          <CardHeader>
            <div className="flex items-start justify-between">
              <div className="flex items-center gap-2">
                <Clock className="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                <CardTitle className="text-yellow-900 dark:text-yellow-300">
                  {expiryAlerts.length} Batch{expiryAlerts.length > 1 ? 'es' : ''} Expiring Soon
                </CardTitle>
              </div>
              <Button
                size="sm"
                onClick={() => navigate('/admin/batches')}
                className="bg-yellow-600 hover:bg-yellow-700 dark:bg-yellow-700 dark:hover:bg-yellow-600"
              >
                <Package className="h-4 w-4 mr-2" />
                View Batches
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              {expiryAlerts.map((alert) => (
                <div
                  key={alert.id}
                  className="flex items-center justify-between p-2 bg-card rounded cursor-pointer hover:bg-yellow-100 dark:hover:bg-yellow-900/30"
                  onClick={() => navigate('/admin/batches')}
                >
                  <div>
                    <p className="font-medium">{alert.product_name}</p>
                    <p className="text-sm text-muted-foreground">
                      Batch: {alert.batch_number} - Qty: {alert.quantity_remaining} - Expires in {Math.round(alert.days_until_expiry)} days
                    </p>
                  </div>
                  <Badge
                    variant="secondary"
                    className={
                      alert.severity === 'critical'
                        ? 'bg-red-600 text-white dark:bg-red-700'
                        : alert.severity === 'high'
                        ? 'bg-yellow-600 text-white dark:bg-yellow-700'
                        : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300'
                    }
                  >
                    {alert.days_until_expiry <= 30 ? 'Urgent' : 'Soon'}
                  </Badge>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Date Range Filter */}
      <div className="flex items-center gap-2">
        <span className="text-sm text-muted-foreground">Time Period:</span>
        <div className="flex gap-2">
          <Button
            size="sm"
            variant={dateRange === 7 ? "default" : "outline"}
            onClick={() => handleDateRangeChange(7)}
          >
            7 Days
          </Button>
          <Button
            size="sm"
            variant={dateRange === 30 ? "default" : "outline"}
            onClick={() => handleDateRangeChange(30)}
          >
            30 Days
          </Button>
          <Button
            size="sm"
            variant={dateRange === 90 ? "default" : "outline"}
            onClick={() => handleDateRangeChange(90)}
          >
            90 Days
          </Button>
        </div>
      </div>

      {/* User Statistics */}
      <div>
        <h2 className="text-xl font-semibold mb-4">User Statistics</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Users</CardTitle>
              <Users className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold">{stats?.totalUsers ?? 0}</div>
              <p className="text-xs text-muted-foreground mt-1">
                All registered users
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Active Users</CardTitle>
              <UserCheck className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-green-600 dark:text-green-400">{stats?.activeUsers ?? 0}</div>
              <p className="text-xs text-muted-foreground mt-1">
                Currently active accounts
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Owners</CardTitle>
              <ShieldCheck className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-blue-600 dark:text-blue-400">{stats?.owners ?? 0}</div>
              <p className="text-xs text-muted-foreground mt-1">
                System owners
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Managers & Cashiers</CardTitle>
              <Users className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-purple-600 dark:text-purple-400">
                {((stats?.managers ?? 0) + (stats?.cashiers ?? 0))}
              </div>
              <p className="text-xs text-muted-foreground mt-1">
                {(stats?.managers ?? 0)} managers, {(stats?.cashiers ?? 0)} cashiers
              </p>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* User Analytics Charts */}
      {isOwner && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Users by Role Pie Chart */}
          <Card>
            <CardHeader>
              <CardTitle>Users by Role</CardTitle>
            </CardHeader>
            <CardContent>
              {stats?.usersByRole && stats.usersByRole.length > 0 ? (
                <ResponsiveContainer width="100%" height={300}>
                  <PieChart>
                    <Pie
                      data={stats.usersByRole}
                      cx="50%"
                      cy="50%"
                      labelLine={false}
                      label={({ percent }) =>
                        percent > 0.05 ? `${(percent * 100).toFixed(0)}%` : ''
                      }
                      outerRadius={90}
                      fill="#8884d8"
                      dataKey="value"
                    >
                      {stats.usersByRole.map((entry, index) => (
                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                      ))}
                    </Pie>
                    <Tooltip formatter={(value, name) => [value, name]} />
                    <Legend
                      verticalAlign="bottom"
                      height={36}
                      formatter={(value, entry: any) => `${entry.payload.name}: ${entry.payload.value}`}
                    />
                  </PieChart>
                </ResponsiveContainer>
              ) : (
                <p className="text-center text-muted-foreground py-12">No user data available</p>
              )}
            </CardContent>
          </Card>

          {/* Active vs Inactive Users */}
          <Card>
            <CardHeader>
              <CardTitle>User Activity</CardTitle>
            </CardHeader>
            <CardContent>
              {stats ? (
                <ResponsiveContainer width="100%" height={300}>
                  <PieChart>
                    <Pie
                      data={[
                        { name: 'Active', value: stats.activeUsers },
                        { name: 'Inactive', value: stats.inactiveUsers },
                      ]}
                      cx="50%"
                      cy="50%"
                      labelLine={false}
                      label={({ percent }) =>
                        percent > 0.05 ? `${(percent * 100).toFixed(0)}%` : ''
                      }
                      outerRadius={90}
                      fill="#8884d8"
                      dataKey="value"
                    >
                      <Cell fill="#10b981" />
                      <Cell fill="#ef4444" />
                    </Pie>
                    <Tooltip formatter={(value, name) => [value, name]} />
                    <Legend
                      verticalAlign="bottom"
                      height={36}
                    />
                  </PieChart>
                </ResponsiveContainer>
              ) : (
                <p className="text-center text-muted-foreground py-12">No user data available</p>
              )}
            </CardContent>
          </Card>
        </div>
      )}

      {/* Product Overview with Trends */}
      <div>
        <h2 className="text-xl font-semibold mb-4">Product Overview</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Products</CardTitle>
              <Package className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold">{stats?.totalProducts ?? 0}</div>
              <p className="text-xs text-muted-foreground mt-1">
                All products in catalog
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Active Products</CardTitle>
              <CheckCircle className="h-4 w-4 text-green-600 dark:text-green-400" />
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-green-600 dark:text-green-400">
                {stats?.activeProducts ?? 0}
              </div>
              <p className="text-xs text-muted-foreground mt-1">
                Currently available
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Featured Products</CardTitle>
              <Star className="h-4 w-4 text-yellow-600 dark:text-yellow-400" />
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-yellow-600 dark:text-yellow-400">
                {stats?.featuredProducts ?? 0}
              </div>
              <p className="text-xs text-muted-foreground mt-1">
                Highlighted on homepage
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Recently Added</CardTitle>
              {stats?.productTrend && stats.productTrend > 0 ? (
                <TrendingUp className="h-4 w-4 text-green-600 dark:text-green-400" />
              ) : (
                <TrendingDown className="h-4 w-4 text-red-600 dark:text-red-400" />
              )}
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-blue-600 dark:text-blue-400">
                {stats?.recentlyAdded ?? 0}
              </div>
              <div className="flex items-center gap-1 text-xs mt-1">
                {stats?.productTrend !== undefined && stats.productTrend !== 0 && (
                  <>
                    {stats.productTrend > 0 ? (
                      <span className="text-green-600 dark:text-green-400 flex items-center gap-0.5">
                        <TrendingUp className="h-3 w-3" />
                        +{stats.productTrend}%
                      </span>
                    ) : (
                      <span className="text-red-600 dark:text-red-400 flex items-center gap-0.5">
                        <TrendingDown className="h-3 w-3" />
                        {stats.productTrend}%
                      </span>
                    )}
                    <span className="text-muted-foreground">vs previous period</span>
                  </>
                )}
                {(stats?.productTrend === undefined || stats.productTrend === 0) && (
                  <span className="text-muted-foreground">Last {stats?.period?.days ?? 30} days</span>
                )}
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Recent Activity */}
      <div>
        <h2 className="text-xl font-semibold mb-4">Recent Activity</h2>
        <Card>
          <CardHeader>
            <CardTitle>Recently Added Products</CardTitle>
          </CardHeader>
          <CardContent>
            {stats?.recentProducts && stats.recentProducts.length > 0 ? (
              <div className="space-y-3">
                {stats.recentProducts.map((product) => (
                  <div
                    key={product.id}
                    className="flex items-center justify-between border-b pb-3 last:border-0 last:pb-0"
                  >
                    <div className="flex-1">
                      <div className="flex items-center gap-2">
                        <span className="font-medium">{product.name}</span>
                        {product.is_featured && (
                          <Badge variant="secondary" className="bg-yellow-100 text-yellow-800 border-yellow-300 dark:bg-yellow-900/40 dark:text-yellow-300 dark:border-yellow-800">
                            <Star className="h-3 w-3 mr-1" />
                            Featured
                          </Badge>
                        )}
                        {!product.is_active && (
                          <Badge variant="secondary" className="bg-muted text-foreground">
                            Inactive
                          </Badge>
                        )}
                      </div>
                      <div className="flex items-center gap-3 text-sm text-muted-foreground mt-1">
                        <span>#{product.price?.toLocaleString() ?? 0}</span>
                        <span>-</span>
                        <span>{new Date(product.created_at).toLocaleDateString()}</span>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-muted-foreground text-center py-8">
                No recent products added
              </p>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}