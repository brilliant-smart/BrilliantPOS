<?php

namespace App\Services;

use App\Models\Product;
use App\Models\AutoReorderLog;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;

class AutoReorderService
{
    /**
     * Check all products and trigger reorders for those below reorder point
     */
    public function checkAndTriggerReorders(): array
    {
        $results = [
            'notifications_sent' => 0,
            'pos_created' => 0,
            'products_checked' => 0,
        ];

        $products = Product::where('auto_reorder_enabled', true)
            ->where('is_active', true)
            ->get();

        foreach ($products as $product) {
            $results['products_checked']++;

            if ($product->stock_quantity <= $product->reorder_point) {
                $action = $this->triggerReorder($product);
                
                if ($action === 'po_created') {
                    $results['pos_created']++;
                } else {
                    $results['notifications_sent']++;
                }
            }
        }

        return $results;
    }

    /**
     * Trigger reorder for a specific product
     */
    public function triggerReorder(Product $product, $userId = null): string
    {
        // Calculate suggested quantity
        $suggestedQuantity = $product->auto_reorder_quantity ?? ($product->max_stock_level - $product->stock_quantity);

        // Get preferred supplier (last supplier or first available)
        $preferredSupplier = $product->purchaseOrderItems()
            ->with('purchaseOrder.supplier')
            ->latest()
            ->first()
            ?->purchaseOrder
            ?->supplier;

        // Create log entry
        $log = AutoReorderLog::create([
            'product_id' => $product->id,
            'current_stock' => $product->stock_quantity,
            'reorder_point' => $product->reorder_point,
            'suggested_quantity' => $suggestedQuantity,
            'suggested_supplier_id' => $preferredSupplier?->id,
            'action_taken' => 'notification_sent',
            'triggered_by' => $userId,
            'notes' => 'Auto-reorder triggered: Stock level (' . $product->stock_quantity . ') below reorder point (' . $product->reorder_point . ')',
        ]);

        // For now, we just send notifications. Auto-PO creation can be added later
        // TODO: Implement automatic PO creation when enabled in settings
        
        return 'notification_sent';
    }

    /**
     * Create automatic purchase order for low stock products
     */
    public function createAutoPurchaseOrder(Product $product, $supplierId, $quantity): PurchaseOrder
    {
        return DB::transaction(function () use ($product, $supplierId, $quantity) {
            $po = PurchaseOrder::create([
                'po_number' => PurchaseOrder::generatePONumber(),
                'supplier_id' => $supplierId,
                'status' => 'draft',
                'order_date' => now(),
                'expected_delivery_date' => now()->addDays(7),
                'notes' => 'Auto-generated purchase order for low stock item',
                'created_by' => auth()->id(),
            ]);

            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'product_id' => $product->id,
                'quantity_ordered' => $quantity,
                'unit_cost' => $product->last_purchase_price ?? $product->cost_price,
                'line_total' => $quantity * ($product->last_purchase_price ?? $product->cost_price),
            ]);

            // Update log
            AutoReorderLog::where('product_id', $product->id)
                ->latest()
                ->first()
                ?->update([
                    'action_taken' => 'po_created',
                    'purchase_order_id' => $po->id,
                ]);

            return $po;
        });
    }

    /**
     * Get reorder suggestions
     */
    public function getReorderSuggestions()
    {
        $query = Product::where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'reorder_point');

        return $query->with(['purchaseOrderItems.purchaseOrder.supplier'])
            ->orderBy('stock_quantity', 'asc')
            ->get()
            ->map(function ($product) {
                $lastPO = $product->purchaseOrderItems()->with('purchaseOrder.supplier')->latest()->first();
                
                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'current_stock' => $product->stock_quantity,
                    'reorder_point' => $product->reorder_point,
                    'suggested_quantity' => $product->auto_reorder_quantity ?? ($product->max_stock_level - $product->stock_quantity),
                    'auto_reorder_enabled' => $product->auto_reorder_enabled,
                    'preferred_supplier' => $lastPO?->purchaseOrder?->supplier,
                    'last_purchase_price' => $product->last_purchase_price,
                ];
            });
    }

    /**
     * Get auto-reorder statistics
     */
    public function getStatistics($startDate = null, $endDate = null)
    {
        $query = AutoReorderLog::query();

        if ($startDate) {
            $query->where('triggered_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('triggered_at', '<=', $endDate);
        }

        return [
            'total_triggers' => $query->count(),
            'pos_created' => (clone $query)->where('action_taken', 'po_created')->count(),
            'notifications_sent' => (clone $query)->where('action_taken', 'notification_sent')->count(),
            'manual_overrides' => (clone $query)->where('action_taken', 'manual_override')->count(),
            'by_product' => (clone $query)->select('product_id', DB::raw('count(*) as trigger_count'))
                ->groupBy('product_id')
                ->with('product:id,name,sku')
                ->orderByDesc('trigger_count')
                ->limit(10)
                ->get(),
        ];
    }
}
