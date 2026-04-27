<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class BatchTrackingService
{
    /**
     * Create a new batch when receiving inventory
     */
    public function createBatch(array $data): ProductBatch
    {
        return ProductBatch::create($data);
    }

    /**
     * Allocate stock from batches using FEFO (First Expired, First Out)
     * 
     * @param Product $product
     * @param int $quantity
     * @return array Array of batch allocations
     */
    public function allocateStock(Product $product, int $quantity): array
    {
        $allocations = [];
        $remainingQuantity = $quantity;

        // Get active batches ordered by expiry date (FEFO)
        $batches = $product->activeBatches()->get();

        foreach ($batches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $allocatedQuantity = min($batch->quantity_remaining, $remainingQuantity);
            
            $allocations[] = [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'quantity' => $allocatedQuantity,
                'expiry_date' => $batch->expiry_date,
                'cost_price' => $batch->cost_price,
            ];

            $remainingQuantity -= $allocatedQuantity;
        }

        if ($remainingQuantity > 0) {
            throw new \Exception("Insufficient stock in batches. Required: {$quantity}, Available: " . ($quantity - $remainingQuantity));
        }

        return $allocations;
    }

    /**
     * Deduct stock from batches
     */
    public function deductStock(array $allocations): void
    {
        DB::transaction(function () use ($allocations) {
            foreach ($allocations as $allocation) {
                $batch = ProductBatch::findOrFail($allocation['batch_id']);
                $batch->quantity_remaining -= $allocation['quantity'];
                $batch->save();
            }
        });
    }

    /**
     * Get expiring batches
     */
    public function getExpiringBatches(int $days = 30)
    {
        return ProductBatch::expiringSoon($days)
            ->with(['product', 'supplier'])
            ->get();
    }

    /**
     * Get expired batches
     */
    public function getExpiredBatches()
    {
        return ProductBatch::expired()
            ->with(['product', 'supplier'])
            ->get();
    }

    /**
     * Mark batch as expired
     */
    public function markBatchAsExpired(ProductBatch $batch): void
    {
        DB::transaction(function () use ($batch) {
            $batch->status = 'expired';
            $batch->save();

            // Create stock movement for expired items
            StockMovement::create([
                'product_id' => $batch->product_id,
                'type' => 'damage', // Using damage type for expired items
                'quantity' => -$batch->quantity_remaining,
                'unit_cost' => $batch->cost_price,
                'reference_type' => 'ProductBatch',
                'reference_id' => $batch->id,
                'performed_by' => auth()->id(),
                'notes' => "Batch {$batch->batch_number} marked as expired",
            ]);

            // Update product stock
            $product = $batch->product;
            $product->stock_quantity -= $batch->quantity_remaining;
            $product->save();

            // Set batch remaining to 0
            $batch->quantity_remaining = 0;
            $batch->save();
        });
    }

    /**
     * Get batch inventory report
     */
    public function getBatchInventoryReport()
    {
        $query = ProductBatch::with(['product', 'supplier'])
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0);

        return $query->orderBy('expiry_date', 'asc')->get();
    }
}
