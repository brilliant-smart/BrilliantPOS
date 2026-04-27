<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductPriceHistory;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierPriceComparisonController extends Controller
{
    /**
     * Get supplier price comparison report
     * Compares prices across different suppliers for the same products
     * 
     * GET /api/reports/supplier-price-comparison
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'supplier_ids' => 'nullable|array',
            'supplier_ids.*' => 'exists:suppliers,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        // Get products that have been purchased from multiple suppliers
        $query = ProductPriceHistory::query()
            ->select([
                'product_id',
                'supplier_name',
                DB::raw('AVG(new_price) as avg_price'),
                DB::raw('MIN(new_price) as min_price'),
                DB::raw('MAX(new_price) as max_price'),
                DB::raw('COUNT(*) as purchase_count'),
                DB::raw('MAX(changed_at) as last_purchase_date'),
            ])
            ->where('change_type', 'purchase')
            ->whereNotNull('supplier_name')
            ->groupBy('product_id', 'supplier_name');

        // Apply filters
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('changed_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('changed_at', '<=', $request->end_date);
        }

        $priceData = $query->get();

        // Group by product
        $productComparisons = [];
        foreach ($priceData as $data) {
            $productId = $data->product_id;
            
            if (!isset($productComparisons[$productId])) {
                $product = Product::find($productId);
                $productComparisons[$productId] = [
                    'product_id' => $productId,
                    'product_name' => $product->name ?? 'Unknown',
                    'product_sku' => $product->sku ?? 'N/A',
                    'current_cost_price' => $product->cost_price ?? 0,
                    'suppliers' => [],
                ];
            }

            $productComparisons[$productId]['suppliers'][] = [
                'supplier_name' => $data->supplier_name,
                'avg_price' => round($data->avg_price, 2),
                'min_price' => round($data->min_price, 2),
                'max_price' => round($data->max_price, 2),
                'purchase_count' => $data->purchase_count,
                'last_purchase_date' => $data->last_purchase_date,
            ];
        }

        // Calculate savings opportunities
        foreach ($productComparisons as &$comparison) {
            if (count($comparison['suppliers']) > 1) {
                $prices = array_column($comparison['suppliers'], 'avg_price');
                $lowestPrice = min($prices);
                $highestPrice = max($prices);
                
                $comparison['price_range'] = [
                    'lowest' => $lowestPrice,
                    'highest' => $highestPrice,
                    'difference' => $highestPrice - $lowestPrice,
                    'savings_percentage' => $highestPrice > 0 
                        ? round((($highestPrice - $lowestPrice) / $highestPrice) * 100, 2)
                        : 0,
                ];

                // Find cheapest supplier
                $cheapestSupplier = collect($comparison['suppliers'])
                    ->sortBy('avg_price')
                    ->first();
                
                $comparison['best_supplier'] = $cheapestSupplier['supplier_name'];
            }
        }

        // Filter out products with only one supplier if requested
        if ($request->boolean('multiple_suppliers_only', false)) {
            $productComparisons = array_filter($productComparisons, function($comp) {
                return count($comp['suppliers']) > 1;
            });
        }

        return response()->json([
            'data' => array_values($productComparisons),
            'summary' => [
                'total_products' => count($productComparisons),
                'products_with_multiple_suppliers' => count(array_filter($productComparisons, function($comp) {
                    return count($comp['suppliers']) > 1;
                })),
            ],
        ]);
    }

    /**
     * Get supplier performance summary
     * Shows overall pricing performance for each supplier
     * 
     * GET /api/reports/supplier-performance
     */
    public function supplierPerformance(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $query = ProductPriceHistory::query()
            ->select([
                'supplier_name',
                DB::raw('COUNT(DISTINCT product_id) as products_supplied'),
                DB::raw('COUNT(*) as total_purchases'),
                DB::raw('AVG(new_price) as avg_price'),
                DB::raw('MIN(changed_at) as first_purchase'),
                DB::raw('MAX(changed_at) as last_purchase'),
            ])
            ->where('change_type', 'purchase')
            ->whereNotNull('supplier_name')
            ->groupBy('supplier_name');

        if ($request->filled('start_date')) {
            $query->whereDate('changed_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('changed_at', '<=', $request->end_date);
        }

        $supplierPerformance = $query->get();

        // Calculate price trends for each supplier
        foreach ($supplierPerformance as &$supplier) {
            // Get recent price changes
            $recentChanges = ProductPriceHistory::query()
                ->where('supplier_name', $supplier->supplier_name)
                ->where('change_type', 'purchase')
                ->orderBy('changed_at', 'desc')
                ->limit(10)
                ->get();

            $priceIncreases = $recentChanges->filter(fn($c) => $c->price_change > 0)->count();
            $priceDecreases = $recentChanges->filter(fn($c) => $c->price_change < 0)->count();

            $supplier->price_trend = [
                'increases' => $priceIncreases,
                'decreases' => $priceDecreases,
                'stability_score' => $recentChanges->count() > 0 
                    ? round(($priceDecreases / $recentChanges->count()) * 100, 2)
                    : 0,
            ];
        }

        return response()->json([
            'data' => $supplierPerformance,
        ]);
    }

    /**
     * Get best supplier recommendation for a product
     * 
     * GET /api/reports/best-supplier/{productId}
     */
    public function bestSupplier($productId)
    {
        $product = Product::findOrFail($productId);

        $suppliers = ProductPriceHistory::query()
            ->select([
                'supplier_name',
                DB::raw('AVG(new_price) as avg_price'),
                DB::raw('MIN(new_price) as best_price'),
                DB::raw('COUNT(*) as purchase_count'),
                DB::raw('MAX(changed_at) as last_purchase'),
            ])
            ->where('product_id', $productId)
            ->where('change_type', 'purchase')
            ->whereNotNull('supplier_name')
            ->groupBy('supplier_name')
            ->orderBy('avg_price', 'asc')
            ->get();

        if ($suppliers->isEmpty()) {
            return response()->json([
                'message' => 'No purchase history available for this product',
                'product' => $product,
            ]);
        }

        $bestSupplier = $suppliers->first();
        $avgMarketPrice = $suppliers->avg('avg_price');

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'current_cost_price' => $product->cost_price,
            ],
            'recommendation' => [
                'supplier' => $bestSupplier->supplier_name,
                'avg_price' => round($bestSupplier->avg_price, 2),
                'best_price' => round($bestSupplier->best_price, 2),
                'purchase_count' => $bestSupplier->purchase_count,
                'last_purchase' => $bestSupplier->last_purchase,
                'savings_vs_current' => $product->cost_price > 0
                    ? round($product->cost_price - $bestSupplier->avg_price, 2)
                    : 0,
                'savings_vs_market' => round($avgMarketPrice - $bestSupplier->avg_price, 2),
            ],
            'all_suppliers' => $suppliers->map(function($s) {
                return [
                    'supplier' => $s->supplier_name,
                    'avg_price' => round($s->avg_price, 2),
                    'best_price' => round($s->best_price, 2),
                    'purchase_count' => $s->purchase_count,
                ];
            }),
        ]);
    }
}
