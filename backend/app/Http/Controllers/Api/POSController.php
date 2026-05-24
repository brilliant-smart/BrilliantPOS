<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductUnitType;
use App\Models\HeldCart;
use App\Models\StockMovement;
use App\Models\AuditLog;
use App\Http\Requests\ValidateStockRequest;
use App\Http\Requests\CompleteSaleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class POSController extends Controller
{
    /**
     * Validate stock availability before completing sale
     * POST /api/pos/validate-stock
     */
    public function validateStock(ValidateStockRequest $request)
    {
        $validated = $request->validated();

        $errors = [];

        foreach ($validated['items'] as $item) {
            $product = Product::find($item['product_id']);
            $conversionFactor = $item['conversion_factor'] ?? 1;
            $quantityInBaseUnits = $item['quantity'] * $conversionFactor;

            if ($product->stock_quantity < $quantityInBaseUnits) {
                $unitTypeName = $item['unit_type'] ?? 'piece';
                $errors[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'requested' => $item['quantity'],
                    'requested_base' => $quantityInBaseUnits,
                    'available' => $product->stock_quantity,
                    'message' => "Insufficient stock for {$product->name}. Available: {$product->stock_quantity} pcs, Requested: {$item['quantity']} {$unitTypeName}(s) = {$quantityInBaseUnits} pcs",
                ];
            }
        }

        if (!empty($errors)) {
            return response()->json([
                'valid' => false,
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Stock validation passed',
        ]);
    }

    /**
     * Complete POS sale with transaction safety
     * POST /api/pos/complete-sale
     */
    public function completeSale(CompleteSaleRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            // 1. Lock and validate inventory atomically
            $productData = [];

            foreach ($validated['items'] as &$item) {
                $product = Product::where('id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    throw new \Exception("Product not found");
                }

                // Resolve unit type and conversion factor
                $unitTypeId = $item['product_unit_type_id'] ?? null;
                $conversionFactor = 1;
                $unitType = null;

                if ($unitTypeId) {
                    $unitType = ProductUnitType::find($unitTypeId);
                    if ($unitType && $unitType->product_id === $product->id) {
                        $conversionFactor = (float) $unitType->conversion_factor;
                    }
                } else {
                    $conversionFactor = $item['conversion_factor'] ?? 1;
                }

                $quantityInBaseUnits = $item['quantity'] * $conversionFactor;

                if ($product->stock_quantity < $quantityInBaseUnits) {
                    $unitTypeName = $unitType ? $unitType->name : ($item['unit_type'] ?? 'piece');
                    throw new \Exception("Insufficient stock for {$product->name}. Available: {$product->stock_quantity} pcs, Requested: {$item['quantity']} {$unitTypeName}(s)");
                }

                // Cashiers cannot modify prices — enforce the product's stored price
                if (!in_array(auth()->user()->role, ['owner', 'manager'])) {
                    if ($unitType) {
                        $item['unit_price'] = (float) $unitType->selling_price;
                    } else {
                        $item['unit_price'] = $product->price;
                    }
                }

                // Store resolved values for later use
                $item['resolved_conversion_factor'] = $conversionFactor;
                $item['resolved_unit_type_id'] = $unitTypeId;
                $item['resolved_unit_type'] = $unitType;

                // Cache product data
                $productData[$product->id] = $product;
            }
            unset($item);

            // 2. Calculate totals using bcmath for monetary precision
            $subtotal = '0';
            $totalCost = '0';
            $totalProfit = '0';

            foreach ($validated['items'] as $item) {
                $product = $productData[$item['product_id']];
                $conversionFactor = $item['resolved_conversion_factor'] ?? 1;
                $lineTotal = bcmul((string) $item['quantity'], (string) $item['unit_price'], 2);
                $lineDiscount = (string) ($item['discount'] ?? 0);
                $lineNet = bcsub($lineTotal, $lineDiscount, 2);

                $lineCost = bcmul((string) ($item['quantity'] * $conversionFactor), (string) ($product->cost_price ?? 0), 2);
                $lineProfit = bcsub($lineNet, $lineCost, 2);

                $subtotal = bcadd($subtotal, $lineNet, 2);
                $totalCost = bcadd($totalCost, $lineCost, 2);
                $totalProfit = bcadd($totalProfit, $lineProfit, 2);
            }

            $discountAmount = (string) ($validated['discount_amount'] ?? 0);
            if (isset($validated['discount_percentage']) && $validated['discount_percentage'] > 0) {
                $discountAmount = bcdiv(bcmul($subtotal, (string) $validated['discount_percentage'], 2), '100', 2);
            }

            $grandTotal = bcsub($subtotal, $discountAmount, 2);
            $profitMargin = bccomp($grandTotal, '0', 2) > 0
                ? (float) bcmul(bcdiv($totalProfit, $grandTotal, 4), '100', 2)
                : 0;

            // 3. Validate payment total
            $totalPaid = (string) array_sum(array_column($validated['payments'], 'amount'));

            // Separate credit vs non-credit payments
            $hasCreditPayment = collect($validated['payments'])->contains('method', 'credit');
            $actualPaid = (string) collect($validated['payments'])
                ->filter(fn($p) => $p['method'] !== 'credit')
                ->sum('amount');

            if (!$hasCreditPayment && bccomp($totalPaid, $grandTotal, 2) < 0) {
                throw new \Exception("Payment amount ({$totalPaid}) is less than total ({$grandTotal})");
            }

            // Determine sale type: if any credit payment, the sale is a credit sale
            $saleType = $hasCreditPayment ? 'credit' : ($validated['payments'][0]['method'] ?? 'cash');

            // Determine payment status based on actual money collected (excluding credit)
            if (bccomp($actualPaid, $grandTotal, 2) >= 0) {
                $paymentStatus = 'paid';
            } elseif (bccomp($actualPaid, '0', 2) > 0) {
                $paymentStatus = 'partially_paid';
            } else {
                $paymentStatus = 'unpaid';
            }

            // 4. Create sale
            $sale = Sale::create([
                'sale_number' => Sale::generateSaleNumber(),
                'cashier_id' => auth()->id(),
                'customer_name' => $validated['customer_name'] ?? null,
                'sale_date' => now(),
                'subtotal' => $subtotal,
                'discount_percentage' => $validated['discount_percentage'] ?? 0,
                'discount_amount' => $discountAmount,
                'total_amount' => $grandTotal,
                'amount_paid' => $actualPaid,
                'amount_due' => max(0, (float) bcsub($grandTotal, $actualPaid, 2)),
                'cost_of_goods_sold' => $totalCost,
                'gross_profit' => $totalProfit,
                'payment_status' => $paymentStatus,
                'sale_type' => $saleType,
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
            ]);

            // 5. Create sale items and deduct stock atomically
            foreach ($validated['items'] as $item) {
                $product = $productData[$item['product_id']];
                $conversionFactor = $item['resolved_conversion_factor'] ?? 1;
                $unitType = $item['resolved_unit_type'] ?? null;
                $quantityInBaseUnits = $item['quantity'] * $conversionFactor;

                $lineTotal = bcmul((string) $item['quantity'], (string) $item['unit_price'], 2);
                $lineDiscount = (string) ($item['discount'] ?? 0);
                $lineNet = bcsub($lineTotal, $lineDiscount, 2);
                $lineCost = bcmul((string) $quantityInBaseUnits, (string) ($product->cost_price ?? 0), 2);
                $lineProfit = bcsub($lineNet, $lineCost, 2);

                // Create sale item
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'product_unit_type_id' => $item['resolved_unit_type_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_type' => $unitType ? $unitType->name : ($item['unit_type'] ?? $product->unit_type ?? 'piece'),
                    'conversion_factor' => $conversionFactor,
                    'unit_price' => $item['unit_price'],
                    'line_total' => $lineNet,
                    'unit_cost' => $product->cost_price ?? 0,
                    'discount_percent' => (bccomp($lineDiscount, '0', 2) > 0 && bccomp($lineTotal, '0', 2) > 0)
                        ? (float) bcmul(bcdiv($lineDiscount, $lineTotal, 4), '100', 2)
                        : 0,
                    'line_cost' => $lineCost,
                    'line_profit' => $lineProfit,
                ]);

                // Deduct stock in base units within the same lock scope
                $previousStock = $product->stock_quantity;
                $newStock = $previousStock - $quantityInBaseUnits;
                $product->update(['stock_quantity' => $newStock]);

                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'sale',
                    'quantity' => -$quantityInBaseUnits,
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'user_id' => auth()->id(),
                    'notes' => "POS Sale: {$sale->sale_number}",
                ]);
            }

            // 6. Record payments
            foreach ($validated['payments'] as $payment) {
                Payment::create([
                    'sale_id' => $sale->id,
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                ]);
            }

            DB::commit();

            AuditLog::log('sale.create', $sale, null, $sale->toArray(), "POS Sale {$sale->sale_number}");

            Log::info('POS Sale completed', [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'total' => $grandTotal,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Sale completed successfully',
                'sale' => $sale->load(['items.product', 'payments', 'user']),
                'change' => max(0, (float) bcsub($totalPaid, $grandTotal, 2)),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('POS Sale failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Sale failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Hold current cart for later recall
     * POST /api/pos/hold-cart
     */
    public function holdCart(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.unit_type' => 'nullable|string',
            'items.*.product_unit_type_id' => 'nullable|integer',
            'items.*.conversion_factor' => 'nullable|numeric|min:0.01',
            'items.*.discount' => 'nullable|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        try {
            $heldCart = HeldCart::create([
                'user_id' => auth()->id(),
                'items' => $validated['items'],
                'discount_percentage' => $validated['discount_percentage'] ?? 0,
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'reference' => HeldCart::generateReference(),
                'notes' => $validated['notes'] ?? null,
                'held_at' => now(),
            ]);

            return response()->json([
                'message' => 'Cart held successfully',
                'held_cart' => $heldCart,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to hold cart: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all held carts for current user
     * GET /api/pos/held-carts
     */
    public function getHeldCarts(Request $request)
    {
        $heldCarts = HeldCart::forUser(auth()->id())
            ->active()
            ->orderBy('held_at', 'desc')
            ->get();

        return response()->json($heldCarts);
    }

    /**
     * Recall a held cart
     * GET /api/pos/held-carts/{id}
     */
    public function recallCart($id)
    {
        $heldCart = HeldCart::where('id', $id)
            ->forUser(auth()->id())
            ->active()
            ->firstOrFail();

        // Mark as recalled
        $heldCart->update(['recalled_at' => now()]);

        return response()->json([
            'message' => 'Cart recalled successfully',
            'cart' => $heldCart,
        ]);
    }

    /**
     * Delete a held cart
     * DELETE /api/pos/held-carts/{id}
     */
    public function deleteHeldCart($id)
    {
        $heldCart = HeldCart::where('id', $id)
            ->forUser(auth()->id())
            ->firstOrFail();

        $heldCart->delete();

        return response()->json([
            'message' => 'Held cart deleted successfully',
        ]);
    }

    /**
     * Void a completed sale
     * POST /api/pos/void-sale/{sale}
     */
    public function voidSale(Request $request, Sale $sale)
    {
        // Check authorization (only owner and manager can void)
        if (!in_array(auth()->user()->role, ['owner', 'manager'])) {
            return response()->json(['message' => 'Unauthorized to void sales'], 403);
        }

        // Check if already voided
        if ($sale->status === 'voided') {
            return response()->json(['message' => 'Sale is already voided'], 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            // Restore stock for each item within lock scope
            foreach ($sale->items as $item) {
                $product = Product::where('id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($product) {
                    $conversionFactor = $item->conversion_factor ?? 1;
                    $restockQuantity = $item->quantity * $conversionFactor;

                    $previousStock = $product->stock_quantity;
                    $newStock = $previousStock + $restockQuantity;
                    $product->update(['stock_quantity' => $newStock]);

                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'type' => 'void',
                        'quantity' => $restockQuantity,
                        'previous_stock' => $previousStock,
                        'new_stock' => $newStock,
                        'reference_type' => Sale::class,
                        'reference_id' => $sale->id,
                        'user_id' => auth()->id(),
                        'notes' => "Sale voided: {$validated['reason']}",
                    ]);
                }
            }

            // Mark sale as voided
            $sale->update([
                'status' => 'voided',
                'voided_by' => auth()->id(),
                'void_reason' => $validated['reason'],
                'voided_at' => now(),
            ]);

            DB::commit();

            AuditLog::log('sale.void', $sale, null, null, "Sale {$sale->sale_number} voided: {$validated['reason']}");

            Log::warning('Sale voided', [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'voided_by' => auth()->id(),
                'reason' => $validated['reason'],
            ]);

            return response()->json([
                'message' => 'Sale voided successfully',
                'sale' => $sale->load(['items.product', 'voidedBy']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to void sale: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sale for reprint
     * GET /api/pos/reprint/{sale}
     */
    public function getSaleForReprint(Sale $sale)
    {
        // Load relationships
        $sale->load(['items.product', 'payments', 'user']);

        return response()->json($sale);
    }

}