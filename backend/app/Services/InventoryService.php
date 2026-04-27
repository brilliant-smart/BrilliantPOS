<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    /**
     * Add stock to a product.
     */
    public function addStock(
        Product $product,
        int $quantity,
        string $type = 'purchase',
        ?string $notes = null,
        ?float $unitCost = null,
        ?User $user = null
    ): StockMovement {
        return DB::transaction(function () use ($product, $quantity, $type, $notes, $unitCost, $user) {
            // Lock the product row to prevent concurrent modifications
            $product = Product::where('id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStock = $product->stock_quantity;
            $newStock = $previousStock + $quantity;

            // Update product stock
            $product->update(['stock_quantity' => $newStock]);

            // Create stock movement record
            $movement = StockMovement::create([
                'product_id' => $product->id,
                'user_id' => $user?->id ?? auth()->id(),
                'type' => $type,
                'quantity' => $quantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'notes' => $notes,
                'unit_cost' => $unitCost,
            ]);

            // Log stock addition
            Log::info('Stock added to product', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'type' => $type,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'user_id' => $user?->id ?? auth()->id(),
                'notes' => $notes,
            ]);

            return $movement;
        });
    }

    /**
     * Reduce stock from a product.
     */
    public function reduceStock(
        Product $product,
        int $quantity,
        string $type = 'sale',
        ?string $notes = null,
        ?User $user = null
    ): StockMovement {
        return DB::transaction(function () use ($product, $quantity, $type, $notes, $user) {
            // Lock the product row to prevent concurrent modifications
            $product = Product::where('id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStock = $product->stock_quantity;
            
            // Validate sufficient stock before reduction
            if ($previousStock < $quantity) {
                Log::warning('Insufficient stock attempt', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'available_stock' => $previousStock,
                    'requested_quantity' => $quantity,
                    'user_id' => $user?->id ?? auth()->id(),
                ]);
                
                throw new \Exception(
                    "Insufficient stock. Available: {$previousStock}, Requested: {$quantity}"
                );
            }

            $newStock = $previousStock - $quantity;

            // Update product stock
            $product->update(['stock_quantity' => $newStock]);

            // Create stock movement record (negative quantity)
            $movement = StockMovement::create([
                'product_id' => $product->id,
                'user_id' => $user?->id ?? auth()->id(),
                'type' => $type,
                'quantity' => -$quantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'notes' => $notes,
            ]);

            // Log stock reduction
            Log::info('Stock reduced from product', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'type' => $type,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'user_id' => $user?->id ?? auth()->id(),
                'notes' => $notes,
            ]);

            return $movement;
        });
    }

    /**
     * Adjust stock to a specific quantity.
     */
    public function adjustStock(
        Product $product,
        int $newQuantity,
        ?string $notes = null,
        ?User $user = null
    ): StockMovement {
        return DB::transaction(function () use ($product, $newQuantity, $notes, $user) {
            // Lock the product row to prevent concurrent modifications
            $product = Product::where('id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStock = $product->stock_quantity;
            $difference = $newQuantity - $previousStock;

            // Update product stock
            $product->update(['stock_quantity' => $newQuantity]);

            // Create stock movement record
            $movement = StockMovement::create([
                'product_id' => $product->id,
                'user_id' => $user?->id ?? auth()->id(),
                'type' => 'adjustment',
                'quantity' => $difference,
                'previous_stock' => $previousStock,
                'new_stock' => $newQuantity,
                'notes' => $notes,
            ]);

            // Log stock adjustment
            Log::info('Stock adjusted for product', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'difference' => $difference,
                'previous_stock' => $previousStock,
                'new_stock' => $newQuantity,
                'user_id' => $user?->id ?? auth()->id(),
                'notes' => $notes,
            ]);

            return $movement;
        });
    }

    /**
     * Get stock movement history for a product.
     */
    public function getStockHistory(Product $product, int $limit = 50)
    {
        return $product->stockMovements()
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get low stock products.
     */
    public function getLowStockProducts()
    {
        $query = Product::whereRaw('stock_quantity > 0 AND stock_quantity <= low_stock_threshold')
            ->orderBy('stock_quantity', 'asc');

        return $query->get();
    }

    /**
     * Get out of stock products.
     */
    public function getOutOfStockProducts()
    {
        $query = Product::where('stock_quantity', '<=', 0)
            ->orderBy('name', 'asc');

        return $query->get();
    }

    /**
     * Get inventory summary statistics.
     */
    public function getInventorySummary()
    {
        $query = Product::query();

        $totalProducts = $query->count();
        $inStock = (clone $query)->where('stock_quantity', '>', 0)->count();
        $lowStock = (clone $query)->whereRaw('stock_quantity > 0 AND stock_quantity <= low_stock_threshold')->count();
        $outOfStock = (clone $query)->where('stock_quantity', '<=', 0)->count();
        $totalStockValue = (clone $query)->sum(DB::raw('stock_quantity * price'));

        return [
            'total_products' => $totalProducts,
            'in_stock' => $inStock,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'total_stock_value' => $totalStockValue,
        ];
    }
}
