<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\HeldCart;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class POSController extends Controller
{
    /**
     * Validate stock availability before completing sale
     * POST /api/pos/validate-stock
     */
    public function validateStock(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $errors = [];

        foreach ($validated['items'] as $item) {
            $product = Product::find($item['product_id']);

            if ($product->stock_quantity < $item['quantity']) {
                $errors[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'requested' => $item['quantity'],
                    'available' => $product->stock_quantity,
                    'message' => "Insufficient stock for {$product->name}. Available: {$product->stock_quantity}, Requested: {$item['quantity']}",
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
    public function completeSale(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.unit_type' => 'nullable|string',
            'items.*.discount' => 'nullable|numeric|min:0',

            'payments' => 'required|array|min:1',
            'payments.*.method' => 'required|in:cash,card,pos,credit,bank_transfer',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:100',

            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // 1. Lock and validate inventory atomically
            $productData = [];

            foreach ($validated['items'] as $item) {
                $product = Product::where('id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    throw new \Exception("Product not found");
                }

                if ($product->stock_quantity < $item['quantity']) {
                    throw new \Exception("Insufficient stock for {$product->name}. Available: {$product->stock_quantity}, Requested: {$item['quantity']}");
                }

                // Cache product data
                $productData[$product->id] = $product;
            }

            // 2. Calculate totals
            $subtotal = 0;
            $totalCost = 0;
            $totalProfit = 0;

            foreach ($validated['items'] as $item) {
                $product = $productData[$item['product_id']];
                $lineTotal = $item['quantity'] * $item['unit_price'];
                $lineDiscount = $item['discount'] ?? 0;
                $lineNet = $lineTotal - $lineDiscount;

                $lineCost = $item['quantity'] * ($product->cost_price ?? 0);
                $lineProfit = $lineNet - $lineCost;

                $subtotal += $lineNet;
                $totalCost += $lineCost;
                $totalProfit += $lineProfit;
            }

            $discountAmount = $validated['discount_amount'] ?? 0;
            if (isset($validated['discount_percentage']) && $validated['discount_percentage'] > 0) {
                $discountAmount = ($subtotal * $validated['discount_percentage']) / 100;
            }

            $grandTotal = $subtotal - $discountAmount;
            $profitMargin = $grandTotal > 0 ? ($totalProfit / $grandTotal) * 100 : 0;

            // 3. Validate payment total
            $totalPaid = array_sum(array_column($validated['payments'], 'amount'));

            // Allow credit sales (payment can be less than total)
            $hasCreditPayment = collect($validated['payments'])->contains('method', 'credit');

            if (!$hasCreditPayment && $totalPaid < $grandTotal) {
                throw new \Exception("Payment amount ({$totalPaid}) is less than total ({$grandTotal})");
            }

            // 4. Create sale
            $sale = Sale::create([
                'sale_number' => $this->generateSaleNumber(),
                'cashier_id' => auth()->id(),
                'user_id' => auth()->id(),
                'customer_id' => $validated['customer_id'] ?? null,
                'customer_name' => $validated['customer_name'] ?? null,
                'sale_date' => now(),
                'subtotal' => $subtotal,
                'discount_percentage' => $validated['discount_percentage'] ?? 0,
                'discount_amount' => $discountAmount,
                'total_amount' => $grandTotal,
                'amount_paid' => $totalPaid, // Record total amount paid for receipt
                'amount_due' => max(0, $grandTotal - $totalPaid),
                'cost_of_goods_sold' => $totalCost,
                'gross_profit' => $totalProfit,
                'profit_margin' => $profitMargin,
                'payment_status' => $totalPaid >= $grandTotal ? 'paid' : 'unpaid',
                'sale_type' => $validated['payments'][0]['method'] ?? 'cash', // Primary payment method
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
            ]);

            // 5. Create sale items and deduct stock atomically
            foreach ($validated['items'] as $item) {
                $product = $productData[$item['product_id']];

                $lineTotal = $item['quantity'] * $item['unit_price'];
                $lineDiscount = $item['discount'] ?? 0;
                $lineNet = $lineTotal - $lineDiscount;
                $lineCost = $item['quantity'] * ($product->cost_price ?? 0);
                $lineProfit = $lineNet - $lineCost;

                // Create sale item
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $lineTotal,
                    'unit_type' => $item['unit_type'] ?? $product->unit_type ?? 'piece',
                    'unit_cost' => $product->cost_price ?? 0,
                    'line_cost' => $lineCost,
                    'line_profit' => $lineProfit,
                    'discount' => $lineDiscount,
                    'cost_price' => $product->cost_price ?? 0,
                    'profit' => $lineProfit,
                ]);

                // Deduct stock atomically
                Product::where('id', $product->id)
                    ->decrement('stock_quantity', $item['quantity']);

                // Create stock movement record
                $previousStock = $product->stock_quantity + $item['quantity']; // Before deduction
                $newStock = $product->stock_quantity; // After deduction (already decremented above)

                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'sale',
                    'quantity' => -$item['quantity'],
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

            Log::info('POS Sale completed', [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'total' => $grandTotal,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Sale completed successfully',
                'sale' => $sale->load(['items.product', 'payments', 'user']),
                'change' => max(0, $totalPaid - $grandTotal),
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
            ]);

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
            // Restore stock for each item
            foreach ($sale->items as $item) {
                Product::where('id', $item->product_id)
                    ->increment('stock_quantity', $item->quantity);

                // Create stock movement record
                $product = Product::find($item->product_id);
                $previousStock = $product->stock_quantity - $item->quantity; // Before restoration
                $newStock = $product->stock_quantity; // After restoration

                StockMovement::create([
                    'product_id' => $item->product_id,
                    'type' => 'void',
                    'quantity' => $item->quantity,
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'user_id' => auth()->id(),
                    'notes' => "Sale voided: {$validated['reason']}",
                ]);
            }

            // Mark sale as voided
            $sale->update([
                'status' => 'voided',
                'voided_by' => auth()->id(),
                'void_reason' => $validated['reason'],
                'voided_at' => now(),
            ]);

            DB::commit();

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

    /**
     * Generate unique sale number
     */
    private function generateSaleNumber(): string
    {
        $date = now()->format('Ymd');
        $count = Sale::whereDate('created_at', today())->count() + 1;

        return sprintf('SALE-%s-%04d', $date, $count);
    }
}