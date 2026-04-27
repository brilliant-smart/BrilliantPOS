import { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { 
  Download, 
  Search,
  TrendingDown,
  TrendingUp,
  Award,
  DollarSign,
  Package,
  BarChart3
} from 'lucide-react';
import { api } from '@/app/lib/api';
import { toast } from 'sonner';
import { format } from 'date-fns';

interface SupplierPrice {
  supplier_name: string;
  avg_price: number;
  min_price: number;
  max_price: number;
  purchase_count: number;
  last_purchase_date: string;
}

interface ProductComparison {
  product_id: number;
  product_name: string;
  product_sku: string;
  current_cost_price: number;
  suppliers: SupplierPrice[];
  price_range?: {
    lowest: number;
    highest: number;
    difference: number;
    savings_percentage: number;
  };
  best_supplier?: string;
}

interface SupplierPerformance {
  supplier_name: string;
  products_supplied: number;
  total_purchases: number;
  avg_price: number;
  first_purchase: string;
  last_purchase: string;
  price_trend: {
    increases: number;
    decreases: number;
    stability_score: number;
  };
}

export default function SupplierPriceComparison() {
  const [loading, setLoading] = useState(false);
  const [productComparisons, setProductComparisons] = useState<ProductComparison[]>([]);
  const [supplierPerformance, setSupplierPerformance] = useState<SupplierPerformance[]>([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [filteredComparisons, setFilteredComparisons] = useState<ProductComparison[]>([]);
  const [activeTab, setActiveTab] = useState('products');

  useEffect(() => {
    fetchData();
  }, []);

  useEffect(() => {
    if (searchTerm) {
      const filtered = productComparisons.filter(p =>
        p.product_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        p.product_sku.toLowerCase().includes(searchTerm.toLowerCase()) ||
        p.suppliers.some(s => s.supplier_name.toLowerCase().includes(searchTerm.toLowerCase()))
      );
      setFilteredComparisons(filtered);
    } else {
      setFilteredComparisons(productComparisons);
    }
  }, [searchTerm, productComparisons]);

  const fetchData = async () => {
    setLoading(true);
    try {
      const [comparisonResponse, performanceResponse] = await Promise.all([
        api.get('/reports/supplier-price-comparison', {
          params: { multiple_suppliers_only: true }
        }),
        api.get('/reports/supplier-performance')
      ]);

      setProductComparisons(comparisonResponse.data.data || []);
      setFilteredComparisons(comparisonResponse.data.data || []);
      setSupplierPerformance(performanceResponse.data.data || []);
    } catch (error: any) {
      console.error('Failed to fetch supplier comparison:', error);
      toast.error('Failed to load supplier comparison data');
    } finally {
      setLoading(false);
    }
  };

  const calculateTotalSavings = () => {
    return productComparisons.reduce((total, product) => {
      if (product.price_range) {
        return total + product.price_range.difference;
      }
      return total;
    }, 0);
  };

  const exportToCSV = () => {
    const headers = ['Product', 'SKU', 'Current Cost', 'Best Supplier', 'Best Price', 'Worst Price', 'Potential Savings', 'Savings %'];
    const rows = filteredComparisons.map(p => [
      p.product_name,
      p.product_sku,
      p.current_cost_price.toFixed(2),
      p.best_supplier || 'N/A',
      p.price_range?.lowest.toFixed(2) || 'N/A',
      p.price_range?.highest.toFixed(2) || 'N/A',
      p.price_range?.difference.toFixed(2) || '0.00',
      p.price_range?.savings_percentage.toFixed(2) + '%' || '0%',
    ]);

    const csvContent = [headers, ...rows].map(row => row.join(',')).join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `supplier-price-comparison-${format(new Date(), 'yyyy-MM-dd')}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  };

  const exportSupplierPerformance = () => {
    const headers = ['Supplier', 'Products', 'Purchases', 'Avg Price', 'Price Increases', 'Price Decreases', 'Stability Score'];
    const rows = supplierPerformance.map(s => [
      s.supplier_name,
      s.products_supplied,
      s.total_purchases,
      s.avg_price.toFixed(2),
      s.price_trend.increases,
      s.price_trend.decreases,
      s.price_trend.stability_score.toFixed(2) + '%',
    ]);

    const csvContent = [headers, ...rows].map(row => row.join(',')).join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `supplier-performance-${format(new Date(), 'yyyy-MM-dd')}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  };

  return (
    <div className="space-y-6 p-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Supplier Price Comparison</h1>
          <p className="text-muted-foreground">Compare prices across suppliers and identify savings opportunities</p>
        </div>
      </div>

      {/* Summary Statistics */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Products Analyzed</CardTitle>
            <Package className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{productComparisons.length}</div>
            <p className="text-xs text-muted-foreground">With multiple suppliers</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Suppliers</CardTitle>
            <Award className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{supplierPerformance.length}</div>
            <p className="text-xs text-muted-foreground">Active suppliers tracked</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Potential Savings</CardTitle>
            <DollarSign className="h-4 w-4 text-green-500 dark:text-green-400" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-green-600 dark:text-green-400">
              ₦{calculateTotalSavings().toFixed(2)}
            </div>
            <p className="text-xs text-muted-foreground">By choosing best prices</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Avg Savings</CardTitle>
            <BarChart3 className="h-4 w-4 text-blue-500 dark:text-blue-400" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">
              {productComparisons.length > 0 
                ? ((calculateTotalSavings() / productComparisons.length)).toFixed(2)
                : '0.00'}%
            </div>
            <p className="text-xs text-muted-foreground">Per product average</p>
          </CardContent>
        </Card>
      </div>

      {/* Tabs */}
      <Tabs value={activeTab} onValueChange={setActiveTab}>
        <TabsList>
          <TabsTrigger value="products">Product Comparison</TabsTrigger>
          <TabsTrigger value="suppliers">Supplier Performance</TabsTrigger>
        </TabsList>

        {/* Product Comparison Tab */}
        <TabsContent value="products" className="space-y-4">
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle>Price Comparison by Product</CardTitle>
                <div className="flex gap-2">
                  <div className="relative w-64">
                    <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                      placeholder="Search products..."
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      className="pl-8"
                    />
                  </div>
                  <Button onClick={exportToCSV} disabled={filteredComparisons.length === 0}>
                    <Download className="mr-2 h-4 w-4" />
                    Export
                  </Button>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              {loading ? (
                <div className="text-center py-8">Loading comparison data...</div>
              ) : filteredComparisons.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground">
                  No products found with multiple suppliers
                </div>
              ) : (
                <div className="space-y-4">
                  {filteredComparisons.map((product) => (
                    <Card key={product.product_id} className="border-l-4 border-l-blue-500">
                      <CardHeader>
                        <div className="flex items-start justify-between">
                          <div>
                            <h3 className="font-semibold text-lg">{product.product_name}</h3>
                            <p className="text-sm text-muted-foreground">SKU: {product.product_sku}</p>
                            <p className="text-sm">Current Cost: ₦{product.current_cost_price.toFixed(2)}</p>
                          </div>
                          {product.price_range && (
                            <div className="text-right">
                              <Badge variant="default" className="bg-green-600 dark:bg-green-700">
                                Best: {product.best_supplier}
                              </Badge>
                              <p className="text-sm font-semibold text-green-600 dark:text-green-400 mt-1">
                                Save ₦{Number(product.price_range.difference).toFixed(2)} ({Number(product.price_range.savings_percentage).toFixed(2)}%)
                              </p>
                            </div>
                          )}
                        </div>
                      </CardHeader>
                      <CardContent>
                        <Table>
                          <TableHeader>
                            <TableRow>
                              <TableHead>Supplier</TableHead>
                              <TableHead>Average Price</TableHead>
                              <TableHead>Best Price</TableHead>
                              <TableHead>Worst Price</TableHead>
                              <TableHead>Purchases</TableHead>
                              <TableHead>Last Purchase</TableHead>
                            </TableRow>
                          </TableHeader>
                          <TableBody>
                            {product.suppliers
                              .sort((a, b) => Number(a.avg_price) - Number(b.avg_price))
                              .map((supplier, idx) => (
                              <TableRow key={supplier.supplier_name} className={idx === 0 ? 'bg-green-50 dark:bg-green-950/30' : ''}>
                                <TableCell className="font-medium">
                                  {supplier.supplier_name}
                                  {idx === 0 && (
                                    <Badge variant="outline" className="ml-2 text-green-600 dark:text-green-400 border-green-600 dark:border-green-700">
                                      Best Price
                                    </Badge>
                                  )}
                                </TableCell>
                                <TableCell className="font-semibold">₦{Number(supplier.avg_price).toFixed(2)}</TableCell>
                                <TableCell className="text-green-600 dark:text-green-400">₦{Number(supplier.min_price).toFixed(2)}</TableCell>
                                <TableCell className="text-red-600 dark:text-red-400">₦{Number(supplier.max_price).toFixed(2)}</TableCell>
                                <TableCell>{supplier.purchase_count}</TableCell>
                                <TableCell className="text-sm text-muted-foreground">
                                  {format(new Date(supplier.last_purchase_date), 'MMM dd, yyyy')}
                                </TableCell>
                              </TableRow>
                            ))}
                          </TableBody>
                        </Table>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Supplier Performance Tab */}
        <TabsContent value="suppliers" className="space-y-4">
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle>Supplier Performance Overview</CardTitle>
                <Button onClick={exportSupplierPerformance} disabled={supplierPerformance.length === 0}>
                  <Download className="mr-2 h-4 w-4" />
                  Export
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              {loading ? (
                <div className="text-center py-8">Loading supplier performance...</div>
              ) : supplierPerformance.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground">
                  No supplier data available
                </div>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Supplier</TableHead>
                      <TableHead>Products</TableHead>
                      <TableHead>Purchases</TableHead>
                      <TableHead>Avg Price</TableHead>
                      <TableHead>Price Trend</TableHead>
                      <TableHead>Stability Score</TableHead>
                      <TableHead>Last Purchase</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {supplierPerformance
                      .sort((a, b) => Number(b.price_trend.stability_score) - Number(a.price_trend.stability_score))
                      .map((supplier) => (
                      <TableRow key={supplier.supplier_name}>
                        <TableCell className="font-medium">{supplier.supplier_name}</TableCell>
                        <TableCell>{supplier.products_supplied}</TableCell>
                        <TableCell>{supplier.total_purchases}</TableCell>
                        <TableCell className="font-semibold">₦{Number(supplier.avg_price).toFixed(2)}</TableCell>
                        <TableCell>
                          <div className="flex gap-2">
                            <Badge variant="destructive" className="flex items-center gap-1">
                              <TrendingUp className="h-3 w-3" />
                              {supplier.price_trend.increases}
                            </Badge>
                            <Badge variant="default" className="bg-green-600 dark:bg-green-700 flex items-center gap-1">
                              <TrendingDown className="h-3 w-3" />
                              {supplier.price_trend.decreases}
                            </Badge>
                          </div>
                        </TableCell>
                        <TableCell>
                          <Badge 
                            variant={Number(supplier.price_trend.stability_score) > 50 ? 'default' : 'outline'}
                            className={Number(supplier.price_trend.stability_score) > 50 ? 'bg-green-600 dark:bg-green-700' : ''}
                          >
                            {Number(supplier.price_trend.stability_score).toFixed(0)}%
                          </Badge>
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground">
                          {format(new Date(supplier.last_purchase), 'MMM dd, yyyy')}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}
