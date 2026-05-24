<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\ProductPriceHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseOrderService
{
    /**
     * Create a new purchase order
     */
    public function createPurchaseOrder(array $data, $user): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $user) {
            // Generate PO number
            $poNumber = PurchaseOrder::generatePoNumber();

            // Calculate totals
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $lineTotal = $item['quantity_ordered'] * $item['unit_cost'];
                $lineTotal -= $lineTotal * ($item['discount_percent'] ?? 0) / 100;
                $subtotal += $lineTotal;
            }

            $totalAmount = $subtotal + ($data['shipping_cost'] ?? 0) - ($data['discount_amount'] ?? 0);

            // Create PO
            $po = PurchaseOrder::create([
                'po_number' => $poNumber,
                'supplier_id' => $data['supplier_id'],
                'created_by' => $user->id,
                'status' => $data['status'] ?? 'draft',
                'order_date' => $data['order_date'] ?? now(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'subtotal' => $subtotal,
                'vat_amount' => 0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'shipping_cost' => $data['shipping_cost'] ?? 0,
                'total_amount' => $totalAmount,
                'payment_method' => $data['payment_method'] ?? 'credit',
                'payment_status' => 'unpaid',
                'amount_paid' => 0,
                'notes' => $data['notes'] ?? null,
            ]);

            // Create PO items with batch/expiry tracking
            foreach ($data['items'] as $item) {
                $lineTotal = $item['quantity_ordered'] * $item['unit_cost'];
                $lineTotal -= $lineTotal * ($item['discount_percent'] ?? 0) / 100;

                $poItem = PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'quantity_ordered' => $item['quantity_ordered'],
                    'unit_cost' => $item['unit_cost'],
                    'vat_rate' => 0,
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'line_total' => $lineTotal,
                    'notes' => $item['notes'] ?? null,
                    'unit_type' => $item['unit_type'] ?? 'piece',
                    'product_unit_type_id' => $item['product_unit_type_id'] ?? null,
                    'conversion_factor' => $item['conversion_factor'] ?? 1,
                    'batch_number' => $item['batch_number'] ?? null,
                    'manufacturing_date' => $item['manufacturing_date'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                ]);

                // Create batch record if batch/expiry data provided
                if (!empty($item['batch_number']) || !empty($item['expiry_date'])) {
                    // Get product to retrieve selling price
                    $product = Product::findOrFail($item['product_id']);
                    
                    ProductBatch::create([
                        'product_id' => $item['product_id'],
                        'purchase_order_id' => $po->id,
                        'purchase_order_item_id' => $poItem->id,
                        'batch_number' => $item['batch_number'] ?? 'PO-' . $poNumber . '-' . $item['product_id'],
                        'quantity_received' => 0, // Will be updated when goods are received
                        'quantity_remaining' => 0,
                        'cost_price' => $item['unit_cost'],
                        'selling_price' => $product->price ?? $item['unit_cost'], // Use product's selling price or fallback to cost
                        'manufacturing_date' => $item['manufacturing_date'] ?? null,
                        'expiry_date' => $item['expiry_date'] ?? null,
                        'supplier_id' => $data['supplier_id'],
                        'status' => 'active', // Batch is created as active, will track expiry separately
                    ]);
                }
            }

            Log::info('Purchase Order created', [
                'po_number' => $po->po_number,
                'supplier_id' => $po->supplier_id,
                'total_amount' => $po->total_amount,
                'user_id' => $user->id,
            ]);

            return $po->load(['items.product', 'supplier']);
        });
    }

    /**
     * Approve purchase order
     */
    public function approvePurchaseOrder(PurchaseOrder $po, $user): PurchaseOrder
    {
        if (!in_array($po->status, ['draft', 'pending'])) {
            throw new \Exception("Cannot approve PO with status: {$po->status}");
        }

        $po->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        Log::info('Purchase Order approved', [
            'po_number' => $po->po_number,
            'approved_by' => $user->id,
        ]);

        return $po;
    }

    /**
     * Receive goods (full or partial)
     */
    public function receiveGoods(PurchaseOrder $po, array $receivedItems, $user): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $receivedItems, $user) {
            if (!in_array($po->status, ['approved', 'ordered', 'partially_received'])) {
                throw new \Exception("Cannot receive goods for PO with status: {$po->status}");
            }

            foreach ($receivedItems as $itemData) {
                $poItem = PurchaseOrderItem::lockForUpdate()
                    ->where('purchase_order_id', $po->id)
                    ->where('product_id', $itemData['product_id'])
                    ->firstOrFail();

                $quantityToReceive = $itemData['quantity_received'];

                if ($quantityToReceive <= 0) {
                    continue;
                }

                if (($poItem->quantity_received + $quantityToReceive) > $poItem->quantity_ordered) {
                    throw new \Exception("Cannot receive more than ordered quantity for product ID: {$itemData['product_id']}");
                }

                // Update PO item with batch/expiry data if provided during receiving
                $updateData = ['quantity_received' => $poItem->quantity_received + $quantityToReceive];
                
                // Update batch info if provided (overrides original data)
                if (isset($itemData['batch_number'])) {
                    $updateData['batch_number'] = $itemData['batch_number'];
                }
                if (isset($itemData['manufacturing_date'])) {
                    $updateData['manufacturing_date'] = $itemData['manufacturing_date'];
                }
                if (isset($itemData['expiry_date'])) {
                    $updateData['expiry_date'] = $itemData['expiry_date'];
                }
                
                $poItem->update($updateData);

                // Update or create batch records
                $batchNumber = $itemData['batch_number'] ?? $poItem->batch_number ?? null;
                $expiryDate = $itemData['expiry_date'] ?? $poItem->expiry_date ?? null;
                
                // Find existing batch - check multiple ways to find it
                $batch = ProductBatch::where('purchase_order_id', $po->id)
                    ->where('product_id', $itemData['product_id'])
                    ->first();
                
                // If no batch found by PO, try to find by item_id
                if (!$batch) {
                    $batch = ProductBatch::where('purchase_order_item_id', $poItem->id)
                        ->where('product_id', $itemData['product_id'])
                        ->first();
                }
                
                if ($batch) {
                    // Update existing batch
                    $batch->increment('quantity_received', $quantityToReceive);
                    $batch->increment('quantity_remaining', $quantityToReceive);
                    $batch->update([
                        'status' => 'active',
                        'batch_number' => $batchNumber ?? $batch->batch_number,
                        'manufacturing_date' => $itemData['manufacturing_date'] ?? $batch->manufacturing_date,
                        'expiry_date' => $expiryDate ?? $batch->expiry_date,
                    ]);
                    
                    Log::info('Batch updated', [
                        'batch_number' => $batch->batch_number,
                        'quantity_received' => $quantityToReceive,
                        'expiry_date' => $batch->expiry_date,
                    ]);
                } elseif ($batchNumber || $expiryDate) {
                    // Create new batch if batch/expiry data provided during receiving
                    // Get product to retrieve selling price
                    $product = Product::findOrFail($itemData['product_id']);
                    
                    ProductBatch::create([
                        'product_id' => $itemData['product_id'],
                        'purchase_order_id' => $po->id,
                        'purchase_order_item_id' => $poItem->id,
                        'batch_number' => $batchNumber ?? 'PO-' . $po->po_number . '-' . $itemData['product_id'],
                        'quantity_received' => $quantityToReceive,
                        'quantity_remaining' => $quantityToReceive,
                        'cost_price' => $poItem->unit_cost,
                        'selling_price' => $product->price ?? $poItem->unit_cost, // Use product's selling price or fallback to cost
                        'manufacturing_date' => $itemData['manufacturing_date'] ?? null,
                        'expiry_date' => $expiryDate,
                        'supplier_id' => $po->supplier_id,
                        'status' => 'active',
                    ]);
                    
                    Log::info('Batch created during receiving', [
                        'batch_number' => $batchNumber,
                        'quantity_received' => $quantityToReceive,
                        'expiry_date' => $expiryDate,
                    ]);
                }

                // Update product stock and cost
                $product = Product::lockForUpdate()->findOrFail($itemData['product_id']);

                // Resolve conversion factor for base unit calculation
                $conversionFactor = $poItem->conversion_factor ?? 1;
                if ($poItem->product_unit_type_id) {
                    $unitType = \App\Models\ProductUnitType::find($poItem->product_unit_type_id);
                    if ($unitType) {
                        $conversionFactor = (float) $unitType->conversion_factor;
                    }
                }

                $quantityInBaseUnits = $quantityToReceive * $conversionFactor;

                // Calculate per-piece cost from unit cost
                $costPerPiece = (float) $poItem->unit_cost / $conversionFactor;

                $previousStock = $product->stock_quantity;
                $newStock = $previousStock + $quantityInBaseUnits;

                // Update average cost price (weighted average) using per-piece cost
                $totalValue = ($product->stock_quantity * $product->cost_price) + ($quantityInBaseUnits * $costPerPiece);
                $newCostPrice = $newStock > 0 ? $totalValue / $newStock : $costPerPiece;

                // Record price history if per-piece cost changed
                $oldPrice = $product->last_purchase_price;
                if (abs($oldPrice - $costPerPiece) > 0.001) {
                    $priceChange = $costPerPiece - $oldPrice;
                    $percentageChange = $oldPrice > 0 ? (($priceChange / $oldPrice) * 100) : 0;

                    ProductPriceHistory::create([
                        'product_id' => $product->id,
                        'old_price' => $oldPrice,
                        'new_price' => $costPerPiece,
                        'price_change' => $priceChange,
                        'percentage_change' => $percentageChange,
                        'change_type' => 'purchase',
                        'supplier_name' => $po->supplier->name ?? null,
                        'reference_number' => $po->po_number,
                        'changed_by' => $user->id,
                        'notes' => $priceChange > 0
                            ? "Price increased by ₦" . number_format(abs($priceChange), 2) . " (" . number_format(abs($percentageChange), 2) . "%)"
                            : "Price decreased by ₦" . number_format(abs($priceChange), 2) . " (" . number_format(abs($percentageChange), 2) . "%)",
                        'changed_at' => now(),
                    ]);
                }

                $product->update([
                    'stock_quantity' => $newStock,
                    'cost_price' => $newCostPrice,
                    'last_purchase_price' => $costPerPiece,
                ]);

                // Create stock movement (in base units)
                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'type' => 'purchase',
                    'quantity' => $quantityInBaseUnits,
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'unit_cost' => $costPerPiece,
                    'notes' => "Received from PO: {$po->po_number}",
                ]);

                Log::info('Goods received', [
                    'po_number' => $po->po_number,
                    'product_id' => $product->id,
                    'quantity' => $quantityToReceive,
                    'new_cost_price' => $newCostPrice,
                ]);
            }

            // Update PO status
            $allItemsReceived = $po->items()
                ->whereColumn('quantity_received', '<', 'quantity_ordered')
                ->count() === 0;

            $po->update([
                'status' => $allItemsReceived ? 'received' : 'partially_received',
                'received_by' => $user->id,
                'received_at' => now(),
                'actual_delivery_date' => now()->toDateString(),
            ]);

            return $po->fresh(['items.product', 'supplier']);
        });
    }

    /**
     * Record payment (with transaction and row locking for concurrency safety)
     */
    public function recordPayment(PurchaseOrder $po, float $amount, $user): PurchaseOrder
    {
        if ($amount <= 0) {
            throw new \Exception('Payment amount must be greater than zero');
        }

        return DB::transaction(function () use ($po, $amount, $user) {
            // Lock the PO row to prevent concurrent payment race conditions
            $lockedPo = PurchaseOrder::lockForUpdate()->findOrFail($po->id);

            $newAmountPaid = round((float) $lockedPo->amount_paid + $amount, 2);
            $totalAmount = round((float) $lockedPo->total_amount, 2);

            if (bccomp((string) $newAmountPaid, (string) $totalAmount, 2) > 0) {
                throw new \Exception('Payment amount exceeds total due');
            }

            $paymentStatus = 'partially_paid';
            if (bccomp((string) $newAmountPaid, (string) $totalAmount, 2) >= 0) {
                $paymentStatus = 'paid';
            }

            $lockedPo->update([
                'amount_paid' => $newAmountPaid,
                'payment_status' => $paymentStatus,
            ]);

            Log::info('Payment recorded for PO', [
                'po_number' => $lockedPo->po_number,
                'amount' => $amount,
                'new_total_paid' => $newAmountPaid,
                'user_id' => $user->id,
            ]);

            return $lockedPo;
        });
    }
}
