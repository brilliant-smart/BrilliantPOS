<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BatchTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\ProductBatch;
use Carbon\Carbon;

class AlertController extends Controller
{
    /**
     * Get low stock alerts
     */
    public function lowStock(Request $request)
    {
        // Products where current stock <= reorder level
        $lowStockProducts = Product::where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'reorder_point')
            ->where('reorder_point', '>', 0)
            ->orderBy('stock_quantity', 'asc')
            ->limit(20)
            ->get();

        return response()->json([
            'alerts' => $lowStockProducts->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'stock_quantity' => $product->stock_quantity,
                    'reorder_point' => $product->reorder_point,
                    'severity' => $product->stock_quantity == 0 ? 'critical' :
                                 ($product->stock_quantity <= ($product->reorder_point / 2) ? 'high' : 'medium'),
                ];
            }),
            'count' => $lowStockProducts->count(),
        ]);
    }

    /**
     * Get expiry alerts for batches
     */
    public function expiringBatches(Request $request)
    {
        $daysThreshold = (int) $request->input('days', 90); // Default: 90 days
        $expiryDate = Carbon::now()->addDays($daysThreshold);

        $expiringBatches = ProductBatch::where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $expiryDate)
            ->where('expiry_date', '>=', Carbon::now())
            ->with(['product', 'supplier'])
            ->orderBy('expiry_date', 'asc')
            ->limit(20)
            ->get();

        return response()->json([
            'alerts' => $expiringBatches->map(function ($batch) {
                $daysUntilExpiry = Carbon::now()->diffInDays(Carbon::parse($batch->expiry_date), false);
                
                return [
                    'id' => $batch->id,
                    'product_id' => $batch->product_id,
                    'product_name' => $batch->product?->name,
                    'batch_number' => $batch->batch_number,
                    'quantity_remaining' => $batch->quantity_remaining,
                    'expiry_date' => $batch->expiry_date,
                    'days_until_expiry' => $daysUntilExpiry,
                    'supplier' => $batch->supplier?->name,
                    'severity' => $daysUntilExpiry <= 30 ? 'critical' : 
                                 ($daysUntilExpiry <= 60 ? 'high' : 'medium'),
                ];
            }),
            'count' => $expiringBatches->count(),
        ]);
    }

    /**
     * Get expired batches (read-only, does NOT auto-mark)
     */
    public function expiredBatches(Request $request)
    {
        $expiredBatches = ProductBatch::where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', Carbon::now())
            ->with(['product', 'supplier'])
            ->orderBy('expiry_date', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'alerts' => $expiredBatches->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'product_id' => $batch->product_id,
                    'product_name' => $batch->product?->name,
                    'batch_number' => $batch->batch_number,
                    'quantity_remaining' => $batch->quantity_remaining,
                    'expiry_date' => $batch->expiry_date,
                    'supplier' => $batch->supplier?->name,
                ];
            }),
            'count' => $expiredBatches->count(),
        ]);
    }

    /**
     * Mark expired batches (explicit POST action)
     * POST /api/alerts/mark-expired
     */
    public function markExpiredBatches(Request $request)
    {
        $validated = $request->validate([
            'batch_ids' => 'nullable|array',
            'batch_ids.*' => 'integer|exists:product_batches,id',
        ]);

        $query = ProductBatch::where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', Carbon::now());

        if (!empty($validated['batch_ids'])) {
            $query->whereIn('id', $validated['batch_ids']);
        }

        $batches = $query->get();
        $marked = 0;

        foreach ($batches as $batch) {
            app(BatchTrackingService::class)->markBatchAsExpired($batch);
            $marked++;
        }

        return response()->json([
            'message' => "{$marked} batches marked as expired",
            'marked' => $marked,
        ]);
    }

    /**
     * Get all alerts summary
     */
    public function summary(Request $request)
    {
        $lowStockCount = Product::where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'reorder_point')
            ->where('reorder_point', '>', 0)
            ->count();

        $expiringCount = ProductBatch::where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', Carbon::now()->addDays(90))
            ->where('expiry_date', '>=', Carbon::now())
            ->count();

        $expiredCount = ProductBatch::where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', Carbon::now())
            ->count();

        return response()->json([
            'low_stock_count' => $lowStockCount,
            'expiring_count' => $expiringCount,
            'expired_count' => $expiredCount,
            'total_alerts' => $lowStockCount + $expiringCount + $expiredCount,
        ]);
    }
}
