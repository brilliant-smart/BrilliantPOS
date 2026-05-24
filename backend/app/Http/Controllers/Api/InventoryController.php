<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InventoryController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Add stock to a product.
     */
    public function addStock(Request $request, Product $product)
    {
        Gate::authorize('update', $product);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'type' => 'nullable|in:purchase,return,adjustment,initial',
            'notes' => 'nullable|string|max:1000',
            'unit_cost' => 'nullable|numeric|min:0',
        ]);

        $movement = $this->inventoryService->addStock(
            $product,
            $validated['quantity'],
            $validated['type'] ?? 'purchase',
            $validated['notes'] ?? null,
            $validated['unit_cost'] ?? null
        );

        AuditLog::log('stock.add', $product, null, ['quantity_added' => $validated['quantity']], "Added {$validated['quantity']} to {$product->name}");

        return response()->json([
            'message' => 'Stock added successfully',
            'product' => $product->fresh(),
            'movement' => $movement->load('user:id,name'),
        ]);
    }

    /**
     * Reduce stock from a product.
     */
    public function reduceStock(Request $request, Product $product)
    {
        Gate::authorize('update', $product);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'type' => 'nullable|in:sale,damage,adjustment',
            'notes' => 'nullable|string|max:1000',
        ]);

        $movement = $this->inventoryService->reduceStock(
            $product,
            $validated['quantity'],
            $validated['type'] ?? 'sale',
            $validated['notes'] ?? null
        );

        AuditLog::log('stock.reduce', $product, null, ['quantity_reduced' => $validated['quantity']], "Reduced {$validated['quantity']} from {$product->name}");

        return response()->json([
            'message' => 'Stock reduced successfully',
            'product' => $product->fresh(),
            'movement' => $movement->load('user:id,name'),
        ]);
    }

    /**
     * Adjust stock to a specific quantity.
     */
    public function adjustStock(Request $request, Product $product)
    {
        Gate::authorize('update', $product);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $movement = $this->inventoryService->adjustStock(
            $product,
            $validated['quantity'],
            $validated['notes'] ?? null
        );

        AuditLog::log('stock.adjust', $product, null, $validated, "Stock adjusted for {$product->name}");

        return response()->json([
            'message' => 'Stock adjusted successfully',
            'product' => $product->fresh(),
            'movement' => $movement->load('user:id,name'),
        ]);
    }

    /**
     * Get stock movement history for a product.
     */
    public function getStockHistory(Product $product)
    {
        Gate::authorize('view', $product);

        $history = $this->inventoryService->getStockHistory($product);

        return response()->json($history);
    }

    /**
     * Get low stock products.
     */
    public function getLowStockProducts(Request $request)
    {
        $products = $this->inventoryService->getLowStockProducts();

        return response()->json($products);
    }

    /**
     * Get out of stock products.
     */
    public function getOutOfStockProducts(Request $request)
    {
        $products = $this->inventoryService->getOutOfStockProducts();

        return response()->json($products);
    }

    /**
     * Get inventory summary.
     */
    public function getInventorySummary(Request $request)
    {
        $summary = $this->inventoryService->getInventorySummary();

        return response()->json($summary);
    }

    /**
     * Bulk update stock quantities.
     */
    public function bulkUpdateStock(Request $request)
    {
        $validated = $request->validate([
            'updates' => 'required|array',
            'updates.*.product_id' => 'required|exists:products,id',
            'updates.*.quantity' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Authorize all products first
        foreach ($validated['updates'] as $update) {
            $product = Product::findOrFail($update['product_id']);
            Gate::authorize('update', $product);
        }

        // Wrap entire bulk operation in a single transaction
        $results = \DB::transaction(function () use ($validated) {
            $results = [];

            foreach ($validated['updates'] as $update) {
                $product = Product::findOrFail($update['product_id']);

                $movement = $this->inventoryService->adjustStock(
                    $product,
                    $update['quantity'],
                    $validated['notes'] ?? 'Bulk stock update'
                );

                $results[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'new_stock' => $product->fresh()->stock_quantity,
                ];
            }

            return $results;
        });

        AuditLog::logAction('stock.bulk_adjust', 'Product', null, null, null, "Bulk stock adjustment for " . count($validated['updates']) . " products");

        return response()->json([
            'message' => 'Bulk stock update completed',
            'results' => $results,
        ]);
    }
}