<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdvancedReportingService
{
    /**
     * Get inventory aging report
     */
    public function inventoryAgingReport()
    {
        $query = Product::where('is_active', true)
            ->where('stock_quantity', '>', 0);

        $products = $query->get()->map(function ($product) {
            // Get the last stock movement date
            $lastMovement = StockMovement::where('product_id', $product->id)
                ->latest('created_at')
                ->first();

            $daysInStock = $lastMovement 
                ? Carbon::parse($lastMovement->created_at)->diffInDays(now())
                : 0;

            // Categorize aging
            if ($daysInStock <= 30) {
                $category = '0-30 days';
            } elseif ($daysInStock <= 60) {
                $category = '31-60 days';
            } elseif ($daysInStock <= 90) {
                $category = '61-90 days';
            } elseif ($daysInStock <= 180) {
                $category = '91-180 days';
            } else {
                $category = 'Over 180 days';
            }

            return [
                'product' => $product,
                'days_in_stock' => $daysInStock,
                'last_movement_date' => $lastMovement?->created_at,
                'aging_category' => $category,
                'stock_value' => $product->stock_quantity * $product->cost_price,
            ];
        });

        // Group by aging category
        $summary = $products->groupBy('aging_category')->map(function ($items, $category) {
            return [
                'category' => $category,
                'product_count' => $items->count(),
                'total_quantity' => $items->sum('product.stock_quantity'),
                'total_value' => $items->sum('stock_value'),
            ];
        });

        return [
            'products' => $products,
            'summary' => $summary->values(),
        ];
    }

    /**
     * Get supplier performance analytics
     */
    public function supplierPerformanceAnalytics($startDate = null, $endDate = null)
    {
        $query = Supplier::with('purchaseOrders');

        $suppliers = $query->get()->map(function ($supplier) use ($startDate, $endDate) {
            $posQuery = $supplier->purchaseOrders();

            if ($startDate) {
                $posQuery->where('order_date', '>=', $startDate);
            }
            if ($endDate) {
                $posQuery->where('order_date', '<=', $endDate);
            }

            $purchaseOrders = $posQuery->get();
            $completedPOs = $purchaseOrders->where('status', 'completed');

            // Calculate average delivery time
            $deliveryTimes = $completedPOs->filter(function ($po) {
                return $po->actual_delivery_date && $po->expected_delivery_date;
            })->map(function ($po) {
                return Carbon::parse($po->expected_delivery_date)
                    ->diffInDays(Carbon::parse($po->actual_delivery_date), false);
            });

            $avgDeliveryDays = $deliveryTimes->isNotEmpty() ? $deliveryTimes->average() : 0;
            $onTimeDeliveries = $deliveryTimes->filter(fn($days) => $days <= 0)->count();
            $lateDeliveries = $deliveryTimes->filter(fn($days) => $days > 0)->count();

            return [
                'supplier' => $supplier,
                'total_orders' => $purchaseOrders->count(),
                'completed_orders' => $completedPOs->count(),
                'pending_orders' => $purchaseOrders->whereIn('status', ['draft', 'pending', 'approved', 'ordered'])->count(),
                'total_amount' => $completedPOs->sum('total_amount'),
                'average_order_value' => $completedPOs->isNotEmpty() ? $completedPOs->average('total_amount') : 0,
                'on_time_deliveries' => $onTimeDeliveries,
                'late_deliveries' => $lateDeliveries,
                'on_time_percentage' => $deliveryTimes->isNotEmpty() ? ($onTimeDeliveries / $deliveryTimes->count()) * 100 : 0,
                'average_delivery_delay_days' => $avgDeliveryDays,
                'quality_score' => $this->calculateSupplierQualityScore($onTimeDeliveries, $lateDeliveries, $completedPOs->count()),
            ];
        });

        return $suppliers->sortByDesc('total_amount')->values();
    }

    /**
     * Calculate supplier quality score (0-100)
     */
    private function calculateSupplierQualityScore($onTime, $late, $total)
    {
        if ($total === 0) return 0;

        $onTimeRate = ($onTime / max($onTime + $late, 1)) * 100;
        $completionRate = ($total / max($total, 1)) * 100;

        return ($onTimeRate * 0.7) + ($completionRate * 0.3);
    }

    /**
     * Get ABC product analysis
     */
    public function abcAnalysis($startDate = null, $endDate = null)
    {
        $query = Sale::with('items.product');

        if ($startDate) {
            $query->where('sale_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('sale_date', '<=', $endDate);
        }

        $sales = $query->get();

        // Calculate revenue per product
        $productRevenue = [];
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $productId = $item->product_id;
                if (!isset($productRevenue[$productId])) {
                    $productRevenue[$productId] = [
                        'product' => $item->product,
                        'revenue' => 0,
                        'quantity_sold' => 0,
                    ];
                }
                $productRevenue[$productId]['revenue'] += $item->line_total;
                $productRevenue[$productId]['quantity_sold'] += $item->quantity;
            }
        }

        // Sort by revenue
        usort($productRevenue, fn($a, $b) => $b['revenue'] <=> $a['revenue']);

        // Calculate cumulative percentage
        $totalRevenue = array_sum(array_column($productRevenue, 'revenue'));
        $cumulativeRevenue = 0;

        foreach ($productRevenue as $index => &$item) {
            $cumulativeRevenue += $item['revenue'];
            $cumulativePercentage = ($cumulativeRevenue / $totalRevenue) * 100;

            // Classify into ABC
            if ($cumulativePercentage <= 80) {
                $item['category'] = 'A';
            } elseif ($cumulativePercentage <= 95) {
                $item['category'] = 'B';
            } else {
                $item['category'] = 'C';
            }

            $item['revenue_percentage'] = ($item['revenue'] / $totalRevenue) * 100;
            $item['cumulative_percentage'] = $cumulativePercentage;
        }

        $summary = [
            'A' => ['count' => 0, 'revenue' => 0],
            'B' => ['count' => 0, 'revenue' => 0],
            'C' => ['count' => 0, 'revenue' => 0],
        ];

        foreach ($productRevenue as $item) {
            $summary[$item['category']]['count']++;
            $summary[$item['category']]['revenue'] += $item['revenue'];
        }

        return [
            'products' => $productRevenue,
            'summary' => $summary,
            'total_revenue' => $totalRevenue,
        ];
    }

    /**
     * Get dead stock report (no sales in X days)
     */
    public function deadStockReport($days = 90)
    {
        $cutoffDate = Carbon::now()->subDays($days);

        $query = Product::where('is_active', true)
            ->where('stock_quantity', '>', 0);

        $products = $query->get()->filter(function ($product) use ($cutoffDate) {
            $lastSale = $product->saleItems()->latest('created_at')->first();
            return !$lastSale || Carbon::parse($lastSale->created_at)->lt($cutoffDate);
        })->map(function ($product) {
            $lastSale = $product->saleItems()->latest('created_at')->first();

            return [
                'product' => $product,
                'last_sale_date' => $lastSale?->created_at,
                'days_since_last_sale' => $lastSale ? Carbon::parse($lastSale->created_at)->diffInDays(now()) : null,
                'stock_quantity' => $product->stock_quantity,
                'stock_value' => $product->stock_quantity * $product->cost_price,
            ];
        });

        return [
            'products' => $products->values(),
            'total_products' => $products->count(),
            'total_stock_value' => $products->sum('stock_value'),
            'days_threshold' => $days,
        ];
    }

    /**
     * Get stockout report (products that went out of stock)
     */
    public function stockoutReport($startDate = null, $endDate = null)
    {
        $query = StockMovement::with('product')
            ->where('type', 'sale');

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        // Get products that are currently out of stock or low stock
        $outOfStockProducts = Product::where('stock_quantity', '<=', 0)
            ->orWhereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->get();

        $stockouts = $outOfStockProducts->map(function ($product) use ($startDate, $endDate) {
            $salesQuery = $product->saleItems();
            
            if ($startDate) {
                $salesQuery->where('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $salesQuery->where('created_at', '<=', $endDate);
            }

            $totalSales = $salesQuery->sum('quantity');
            $lostRevenue = $totalSales * $product->price;

            return [
                'product' => $product,
                'current_stock' => $product->stock_quantity,
                'stock_status' => $product->stock_status,
                'reorder_point' => $product->reorder_point,
                'sales_during_period' => $totalSales,
                'estimated_lost_revenue' => $lostRevenue,
            ];
        });

        return [
            'stockouts' => $stockouts->values(),
            'total_products' => $stockouts->count(),
            'total_estimated_lost_revenue' => $stockouts->sum('estimated_lost_revenue'),
        ];
    }

    /**
     * Simple sales forecasting using linear regression
     */
    public function salesForecast($productId = null, $forecastDays = 30)
    {
        // Get historical sales data (last 90 days)
        $historicalDays = 90;
        $startDate = Carbon::now()->subDays($historicalDays);

        $query = Sale::with('items')
            ->where('sale_date', '>=', $startDate);

        $sales = $query->get();

        if ($productId) {
            // Forecast for specific product
            $dailySales = [];
            foreach ($sales as $sale) {
                $date = Carbon::parse($sale->sale_date)->format('Y-m-d');
                foreach ($sale->items as $item) {
                    if ($item->product_id == $productId) {
                        if (!isset($dailySales[$date])) {
                            $dailySales[$date] = 0;
                        }
                        $dailySales[$date] += $item->quantity;
                    }
                }
            }
        } else {
            // Forecast for overall sales revenue
            $dailySales = [];
            foreach ($sales as $sale) {
                $date = Carbon::parse($sale->sale_date)->format('Y-m-d');
                if (!isset($dailySales[$date])) {
                    $dailySales[$date] = 0;
                }
                $dailySales[$date] += $sale->total_amount;
            }
        }

        // Calculate moving average
        $movingAverage = array_sum($dailySales) / max(count($dailySales), 1);

        // Simple forecast: use moving average
        $forecast = [];
        for ($i = 1; $i <= $forecastDays; $i++) {
            $forecastDate = Carbon::now()->addDays($i)->format('Y-m-d');
            $forecast[$forecastDate] = $movingAverage;
        }

        return [
            'historical_data' => $dailySales,
            'forecast' => $forecast,
            'average_daily_sales' => $movingAverage,
            'forecast_period_days' => $forecastDays,
            'total_forecast' => array_sum($forecast),
        ];
    }
}
