import { useState, useEffect } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { format } from "date-fns";
import { CalendarIcon, Download, TrendingUp, Package, AlertTriangle, DollarSign, ArrowUpDown, Loader2 } from "lucide-react";
import { toast } from "sonner";
import { getDashboardStats, getMovementReport, getTurnoverRate, exportReport, type DashboardStats, type MovementReport, type TurnoverRate } from "@/app/api/inventoryAnalytics";
import { Badge } from "@/components/ui/badge";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { cn } from "@/lib/utils";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

export default function InventoryAnalytics() {
  const [activeTab, setActiveTab] = useState("dashboard");
  const [dateRange, setDateRange] = useState<{ start: Date | undefined; end: Date | undefined }>({
    start: new Date(new Date().setDate(new Date().getDate() - 30)),
    end: new Date(),
  });

  // Dashboard data
  const [dashboardData, setDashboardData] = useState<DashboardStats | null>(null);
  const [dashboardLoading, setDashboardLoading] = useState(false);

  // Movement report data
  const [movementData, setMovementData] = useState<MovementReport | null>(null);
  const [movementLoading, setMovementLoading] = useState(false);
  const [movementType, setMovementType] = useState<string>("all");

  // Turnover data
  const [turnoverData, setTurnoverData] = useState<TurnoverRate | null>(null);
  const [turnoverLoading, setTurnoverLoading] = useState(false);
  const [turnoverDays, setTurnoverDays] = useState<number>(30);

  useEffect(() => {
    loadDashboardData();
  }, [dateRange]);

  useEffect(() => {
    if (activeTab === "movements") {
      loadMovementData();
    }
  }, [activeTab, dateRange, movementType]);

  useEffect(() => {
    if (activeTab === "turnover") {
      loadTurnoverData();
    }
  }, [activeTab, turnoverDays]);

  const loadDashboardData = async () => {
    setDashboardLoading(true);
    try {
      const startDate = dateRange.start ? format(dateRange.start, "yyyy-MM-dd") : undefined;
      const endDate = dateRange.end ? format(dateRange.end, "yyyy-MM-dd") : undefined;
      const data = await getDashboardStats(startDate, endDate);
      setDashboardData(data);
    } catch (error) {
      console.error("Failed to load dashboard data:", error);
    } finally {
      setDashboardLoading(false);
    }
  };

  const loadMovementData = async () => {
    setMovementLoading(true);
    try {
      const startDate = dateRange.start ? format(dateRange.start, "yyyy-MM-dd") : undefined;
      const endDate = dateRange.end ? format(dateRange.end, "yyyy-MM-dd") : undefined;
      const filters: any = { start_date: startDate, end_date: endDate, limit: 100 };
      if (movementType !== "all") {
        filters.type = movementType;
      }
      const data = await getMovementReport(filters);
      setMovementData(data);
    } catch (error) {
      console.error("Failed to load movement data:", error);
    } finally {
      setMovementLoading(false);
    }
  };

  const loadTurnoverData = async () => {
    setTurnoverLoading(true);
    try {
      const data = await getTurnoverRate(turnoverDays);
      setTurnoverData(data);
    } catch (error) {
      console.error("Failed to load turnover data:", error);
    } finally {
      setTurnoverLoading(false);
    }
  };

  const formatCurrency = (value: number) => `₦${value.toLocaleString()}`;

  const [exporting, setExporting] = useState(false);

  const handleExport = async () => {
    try {
      setExporting(true);
      const startDate = dateRange.start ? format(dateRange.start, "yyyy-MM-dd") : undefined;
      const endDate = dateRange.end ? format(dateRange.end, "yyyy-MM-dd") : undefined;

      let exportType: "dashboard" | "movements" | "turnover" = "dashboard";
      if (activeTab === "movements") exportType = "movements";
      if (activeTab === "turnover") exportType = "turnover";

      const blob = await exportReport(exportType, "csv", startDate, endDate);
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `inventory-${exportType}-report-${new Date().toISOString().split('T')[0]}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);

      toast.success('CSV report has been downloaded.');
    } catch (error) {
      console.error("Export failed:", error);
      toast.error('Failed to export report. Please try again.');
    } finally {
      setExporting(false);
    }
  };

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold">Inventory Analytics</h1>
          <p className="text-muted-foreground dark:text-muted-foreground/80 mt-1">Comprehensive insights into your inventory performance</p>
        </div>
        <Button variant="outline" onClick={handleExport} disabled={exporting}>
          <Download className="h-4 w-4 mr-2" />
          Export Report
        </Button>
      </div>

      {/* Date Range Selector */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex items-center gap-4">
            <span className="text-sm font-medium">Date Range:</span>
            <Popover>
              <PopoverTrigger asChild>
                <Button variant="outline" className={cn("justify-start text-left font-normal", !dateRange.start && "text-muted-foreground dark:text-muted-foreground/80")}>
                  <CalendarIcon className="mr-2 h-4 w-4" />
                  {dateRange.start ? format(dateRange.start, "PPP") : "Start date"}
                </Button>
              </PopoverTrigger>
              <PopoverContent className="w-auto p-0">
                <Calendar mode="single" selected={dateRange.start} onSelect={(date) => setDateRange({ ...dateRange, start: date })} initialFocus />
              </PopoverContent>
            </Popover>
            <span className="text-sm text-muted-foreground dark:text-muted-foreground/80">to</span>
            <Popover>
              <PopoverTrigger asChild>
                <Button variant="outline" className={cn("justify-start text-left font-normal", !dateRange.end && "text-muted-foreground dark:text-muted-foreground/80")}>
                  <CalendarIcon className="mr-2 h-4 w-4" />
                  {dateRange.end ? format(dateRange.end, "PPP") : "End date"}
                </Button>
              </PopoverTrigger>
              <PopoverContent className="w-auto p-0">
                <Calendar mode="single" selected={dateRange.end} onSelect={(date) => setDateRange({ ...dateRange, end: date })} initialFocus />
              </PopoverContent>
            </Popover>
          </div>
        </CardContent>
      </Card>

      <Tabs value={activeTab} onValueChange={setActiveTab}>
        <TabsList className="grid w-full grid-cols-3">
          <TabsTrigger value="dashboard">Dashboard</TabsTrigger>
          <TabsTrigger value="movements">Movement Report</TabsTrigger>
          <TabsTrigger value="turnover">Turnover Analysis</TabsTrigger>
        </TabsList>

        {/* Dashboard Tab */}
        <TabsContent value="dashboard" className="space-y-6">
          {dashboardLoading ? (
            <div className="flex justify-center items-center py-12">
              <Loader2 className="h-8 w-8 animate-spin" />
            </div>
          ) : dashboardData ? (
            <>
              {/* Overview Cards */}
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <Card>
                  <CardHeader className="flex flex-row items-center justify-between pb-2">
                    <CardTitle className="text-sm font-medium">Total Products</CardTitle>
                    <Package className="h-4 w-4 text-muted-foreground dark:text-muted-foreground/80" />
                  </CardHeader>
                  <CardContent>
                    <div className="text-2xl font-bold">{dashboardData.overview.total_products}</div>
                    <p className="text-xs text-muted-foreground dark:text-muted-foreground/80 mt-1">{dashboardData.overview.total_stock_units} units in stock</p>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader className="flex flex-row items-center justify-between pb-2">
                    <CardTitle className="text-sm font-medium">Inventory Value</CardTitle>
                    <DollarSign className="h-4 w-4 text-muted-foreground dark:text-muted-foreground/80" />
                  </CardHeader>
                  <CardContent>
                    <div className="text-2xl font-bold">{formatCurrency(dashboardData.overview.total_stock_value)}</div>
                    <p className="text-xs text-muted-foreground dark:text-muted-foreground/80 mt-1">Total stock worth</p>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader className="flex flex-row items-center justify-between pb-2">
                    <CardTitle className="text-sm font-medium">Low Stock Items</CardTitle>
                    <AlertTriangle className="h-4 w-4 text-yellow-500 dark:text-yellow-400" />
                  </CardHeader>
                  <CardContent>
                    <div className="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{dashboardData.stock_status.low_stock}</div>
                    <p className="text-xs text-muted-foreground dark:text-muted-foreground/80 mt-1">Need restocking</p>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader className="flex flex-row items-center justify-between pb-2">
                    <CardTitle className="text-sm font-medium">Out of Stock</CardTitle>
                    <AlertTriangle className="h-4 w-4 text-red-500 dark:text-red-400" />
                  </CardHeader>
                  <CardContent>
                    <div className="text-2xl font-bold text-red-600 dark:text-red-400">{dashboardData.stock_status.out_of_stock}</div>
                    <p className="text-xs text-muted-foreground dark:text-muted-foreground/80 mt-1">Urgent action needed</p>
                  </CardContent>
                </Card>
              </div>

              {/* Stock Status Breakdown */}
              <Card>
                <CardHeader>
                  <CardTitle>Stock Status Overview</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="grid grid-cols-3 gap-4">
                    <div className="text-center p-4 bg-green-50 dark:bg-green-950/30 rounded-lg">
                      <div className="text-3xl font-bold text-green-600 dark:text-green-400">{dashboardData.stock_status.in_stock}</div>
                      <div className="text-sm text-muted-foreground dark:text-muted-foreground/80 mt-1">In Stock</div>
                    </div>
                    <div className="text-center p-4 bg-yellow-50 dark:bg-yellow-950/30 rounded-lg">
                      <div className="text-3xl font-bold text-yellow-600 dark:text-yellow-400">{dashboardData.stock_status.low_stock}</div>
                      <div className="text-sm text-muted-foreground dark:text-muted-foreground/80 mt-1">Low Stock</div>
                    </div>
                    <div className="text-center p-4 bg-red-50 dark:bg-red-950/30 rounded-lg">
                      <div className="text-3xl font-bold text-red-600 dark:text-red-400">{dashboardData.stock_status.out_of_stock}</div>
                      <div className="text-sm text-muted-foreground dark:text-muted-foreground/80 mt-1">Out of Stock</div>
                    </div>
                  </div>
                </CardContent>
              </Card>

              {/* Movement Summary */}
              <Card>
                <CardHeader>
                  <CardTitle>Movement Summary</CardTitle>
                  <CardDescription>Stock movements during selected period</CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="space-y-4">
                    <div className="flex justify-between items-center pb-2 border-b">
                      <span className="font-medium">Total Movements:</span>
                      <span className="text-xl font-bold">{dashboardData.movement_summary.total_movements}</span>
                    </div>
                    <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                      {Object.entries(dashboardData.movement_summary.by_type).map(([type, data]) => (
                        <div key={type} className="text-center p-3 bg-muted rounded-lg">
                          <div className="text-sm font-medium capitalize text-muted-foreground dark:text-muted-foreground/80">{type}</div>
                          <div className="text-xl font-bold mt-1">{data.count}</div>
                          <div className="text-xs text-muted-foreground dark:text-muted-foreground/80 mt-1">
                            {data.total_quantity > 0 ? "+" : ""}
                            {data.total_quantity} units
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                </CardContent>
              </Card>

              {/* Top Products */}
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card>
                  <CardHeader>
                    <CardTitle>Top Sold Products</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Product</TableHead>
                          <TableHead className="text-right">Sold</TableHead>
                          <TableHead className="text-right">Stock</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {dashboardData.top_products.top_sold.slice(0, 5).map((product) => (
                          <TableRow key={product.product_id}>
                            <TableCell className="font-medium">{product.product_name}</TableCell>
                            <TableCell className="text-right">{product.quantity_sold}</TableCell>
                            <TableCell className="text-right">{product.current_stock}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle>Top Purchased Products</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Product</TableHead>
                          <TableHead className="text-right">Purchased</TableHead>
                          <TableHead className="text-right">Stock</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {dashboardData.top_products.top_purchased.slice(0, 5).map((product) => (
                          <TableRow key={product.product_id}>
                            <TableCell className="font-medium">{product.product_name}</TableCell>
                            <TableCell className="text-right">{product.quantity_purchased}</TableCell>
                            <TableCell className="text-right">{product.current_stock}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>
              </div>

              {/* Alerts */}
              {(dashboardData.alerts.low_stock_items.length > 0 || dashboardData.alerts.out_of_stock_items.length > 0) && (
                <Card className="border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-950/30">
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                      <AlertTriangle className="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                      Stock Alerts
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    {dashboardData.alerts.out_of_stock_items.length > 0 && (
                      <div>
                        <h4 className="font-semibold text-red-700 dark:text-red-400 mb-2">Out of Stock ({dashboardData.alerts.out_of_stock_items.length})</h4>
                        <div className="space-y-1">
                          {dashboardData.alerts.out_of_stock_items.slice(0, 5).map((item) => (
                            <div key={item.id} className="text-sm bg-card p-2 rounded">
                              {item.name}
                            </div>
                          ))}
                        </div>
                      </div>
                    )}
                    {dashboardData.alerts.low_stock_items.length > 0 && (
                      <div>
                        <h4 className="font-semibold text-yellow-700 dark:text-yellow-400 mb-2">Low Stock ({dashboardData.alerts.low_stock_items.length})</h4>
                        <div className="space-y-1">
                          {dashboardData.alerts.low_stock_items.slice(0, 5).map((item) => (
                            <div key={item.id} className="text-sm bg-card p-2 rounded">
                              {item.name} - Stock: {item.current_stock}/{item.threshold}
                            </div>
                          ))}
                        </div>
                      </div>
                    )}
                  </CardContent>
                </Card>
              )}
            </>
          ) : (
            <div className="text-center py-12 text-muted-foreground dark:text-muted-foreground/80">No data available</div>
          )}
        </TabsContent>

        {/* Movement Report Tab */}
        <TabsContent value="movements" className="space-y-6">
          <Card>
            <CardHeader>
              <div className="flex justify-between items-center">
                <div>
                  <CardTitle>Stock Movement Report</CardTitle>
                  <CardDescription>Detailed log of all inventory movements</CardDescription>
                </div>
                <Select value={movementType} onValueChange={setMovementType}>
                  <SelectTrigger className="w-40">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Types</SelectItem>
                    <SelectItem value="purchase">Purchase</SelectItem>
                    <SelectItem value="sale">Sale</SelectItem>
                    <SelectItem value="adjustment">Adjustment</SelectItem>
                    <SelectItem value="damage">Damage</SelectItem>
                    <SelectItem value="return">Return</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </CardHeader>
            <CardContent>
              {movementLoading ? (
                <div className="flex justify-center items-center py-12">
                  <Loader2 className="h-8 w-8 animate-spin" />
                </div>
              ) : movementData && movementData.movements.length > 0 ? (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Date</TableHead>
                      <TableHead>Product</TableHead>
                      <TableHead>Type</TableHead>
                      <TableHead className="text-right">Quantity</TableHead>
                      <TableHead className="text-right">Before</TableHead>
                      <TableHead className="text-right">After</TableHead>
                      <TableHead>User</TableHead>
                      <TableHead>Notes</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {movementData.movements.map((movement) => (
                      <TableRow key={movement.id}>
                        <TableCell className="text-sm">{format(new Date(movement.created_at), "MMM dd, yyyy HH:mm")}</TableCell>
                        <TableCell className="font-medium">{movement.product_name}</TableCell>
                        <TableCell>
                          <Badge variant={movement.type === "sale" ? "destructive" : movement.type === "purchase" ? "default" : "secondary"}>{movement.type}</Badge>
                        </TableCell>
                        <TableCell className={cn("text-right font-semibold", movement.quantity > 0 ? "text-green-600 dark:text-green-400" : "text-red-600 dark:text-red-400")}>
                          {movement.quantity > 0 ? "+" : ""}
                          {movement.quantity}
                        </TableCell>
                        <TableCell className="text-right">{movement.previous_quantity}</TableCell>
                        <TableCell className="text-right">{movement.new_quantity}</TableCell>
                        <TableCell className="text-sm">{movement.user_name}</TableCell>
                        <TableCell className="text-sm text-muted-foreground dark:text-muted-foreground/80">{movement.notes || "-"}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              ) : (
                <div className="text-center py-12 text-muted-foreground dark:text-muted-foreground/80">No movements found</div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Turnover Analysis Tab */}
        <TabsContent value="turnover" className="space-y-6">
          <Card>
            <CardHeader>
              <div className="flex justify-between items-center">
                <div>
                  <CardTitle>Inventory Turnover Analysis</CardTitle>
                  <CardDescription>Product performance and stock velocity</CardDescription>
                </div>
                <Select value={turnoverDays.toString()} onValueChange={(value) => setTurnoverDays(parseInt(value))}>
                  <SelectTrigger className="w-40">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="7">Last 7 days</SelectItem>
                    <SelectItem value="30">Last 30 days</SelectItem>
                    <SelectItem value="60">Last 60 days</SelectItem>
                    <SelectItem value="90">Last 90 days</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </CardHeader>
            <CardContent>
              {turnoverLoading ? (
                <div className="flex justify-center items-center py-12">
                  <Loader2 className="h-8 w-8 animate-spin" />
                </div>
              ) : turnoverData ? (
                <>
                  <div className="mb-6 p-4 bg-blue-50 dark:bg-blue-950/30 rounded-lg">
                    <div className="text-sm text-muted-foreground dark:text-muted-foreground/80">Average Turnover Rate</div>
                    <div className="text-3xl font-bold text-blue-600 dark:text-blue-400">{turnoverData.average_turnover}x per year</div>
                  </div>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Product</TableHead>
                        <TableHead>SKU</TableHead>
                        <TableHead className="text-right">Units Sold</TableHead>
                        <TableHead className="text-right">Current Stock</TableHead>
                        <TableHead className="text-right">Turnover Rate</TableHead>
                        <TableHead className="text-right">Days of Stock</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {turnoverData.products.slice(0, 20).map((product) => (
                        <TableRow key={product.product_id}>
                          <TableCell className="font-medium">{product.product_name}</TableCell>
                          <TableCell className="text-sm text-muted-foreground dark:text-muted-foreground/80">{product.sku || "-"}</TableCell>
                          <TableCell className="text-right">{product.units_sold}</TableCell>
                          <TableCell className="text-right">{product.current_stock}</TableCell>
                          <TableCell className="text-right font-semibold">
                            <div className="flex items-center justify-end gap-1">
                              <TrendingUp className="h-3 w-3 text-green-500 dark:text-green-400" />
                              {product.turnover_rate}x
                            </div>
                          </TableCell>
                          <TableCell className="text-right">{product.days_of_stock} days</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </>
              ) : (
                <div className="text-center py-12 text-muted-foreground dark:text-muted-foreground/80">No data available</div>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}
