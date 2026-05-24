<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleService
{
    /**
     * Create a new sale
     */
    public function createSale(array $data, $user): Sale
    {
        return DB::transaction(function () use ($data, $user) {
            // Generate sale number
            $saleNumber = Sale::generateSaleNumber();

            // Calculate totals
            $subtotal = 0;
            $totalCost = 0;
            $isCashier = in_array($user->role, ['cashier']);

            foreach ($data['items'] as &$item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                // Enforce stored prices for cashiers (prevent price manipulation)
                if ($isCashier) {
                    $item['unit_price'] = $product->price;
                }

                // Determine conversion factor for unit types
                $conversionFactor = $item['conversion_factor'] ?? 1;

                // Check stock availability in base units
                $requiredStock = $item['quantity'] * $conversionFactor;
                if ($product->stock_quantity < $requiredStock) {
                    throw new \Exception("Insufficient stock for product: {$product->name}. Available: {$product->stock_quantity}, Requested: {$requiredStock}");
                }

                $lineTotal = $item['quantity'] * $item['unit_price'];
                $lineTotal -= $lineTotal * ($item['discount_percent'] ?? 0) / 100;

                $lineCost = $item['quantity'] * $conversionFactor * $product->cost_price;

                $subtotal += $lineTotal;
                $totalCost += $lineCost;
            }
            unset($item); // Break reference

            $vatAmount = 0;
            $totalAmount = $subtotal - ($data['discount_amount'] ?? 0);
            $grossProfit = $totalAmount - $totalCost;

            // Derive payment_status from amount_paid vs total (prevent client-side spoofing)
            $amountPaid = (float) ($data['amount_paid'] ?? $totalAmount);
            $saleType = $data['sale_type'] ?? 'cash';

            if ($saleType === 'credit') {
                $paymentStatus = $amountPaid > 0 ? 'partially_paid' : 'unpaid';
            } elseif (bccomp((string) $amountPaid, (string) $totalAmount, 2) >= 0) {
                $paymentStatus = 'paid';
            } elseif (bccomp((string) $amountPaid, '0', 2) > 0) {
                $paymentStatus = 'partially_paid';
            } else {
                $paymentStatus = 'unpaid';
            }

            // Create sale
            $sale = Sale::create([
                'sale_number' => $saleNumber,
                'cashier_id' => $user->id,
                'sale_type' => $saleType,
                'payment_status' => $paymentStatus,
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'total_amount' => $totalAmount,
                'amount_paid' => $amountPaid,
                'amount_due' => round($totalAmount - $amountPaid, 2),
                'cost_of_goods_sold' => $totalCost,
                'gross_profit' => $grossProfit,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'sale_date' => $data['sale_date'] ?? now(),
            ]);

            // Create sale items and reduce stock
            foreach ($data['items'] as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                $conversionFactor = $item['conversion_factor'] ?? 1;

                $lineTotal = $item['quantity'] * $item['unit_price'];
                $lineTotal -= $lineTotal * ($item['discount_percent'] ?? 0) / 100;

                $lineCost = $item['quantity'] * $conversionFactor * $product->cost_price;
                $lineProfit = $lineTotal - $lineCost;

                // Create sale item
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'unit_cost' => $product->cost_price,
                    'unit_type' => $item['unit_type'] ?? 'piece',
                    'product_unit_type_id' => $item['product_unit_type_id'] ?? null,
                    'conversion_factor' => $conversionFactor,
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'line_total' => $lineTotal,
                    'line_cost' => $lineCost,
                    'line_profit' => $lineProfit,
                    'notes' => $item['notes'] ?? null,
                ]);

                // Reduce stock in base units
                $stockToDeduct = $item['quantity'] * $conversionFactor;
                $previousStock = $product->stock_quantity;
                $newStock = $previousStock - $stockToDeduct;

                $product->update([
                    'stock_quantity' => $newStock,
                ]);

                // Create stock movement
                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'type' => 'sale',
                    'quantity' => -$stockToDeduct,
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'notes' => "Sale: {$sale->sale_number}",
                ]);

                Log::info('Product sold', [
                    'sale_number' => $sale->sale_number,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'conversion_factor' => $conversionFactor,
                    'profit' => $lineProfit,
                ]);
            }

            // Create Payment record for the sale
            if ($amountPaid > 0) {
                \App\Models\Payment::create([
                    'sale_id' => $sale->id,
                    'method' => $saleType,
                    'amount' => $amountPaid,
                    'reference' => null,
                    'notes' => null,
                ]);
            }

            Log::info('Sale created', [
                'sale_number' => $sale->sale_number,
                'total_amount' => $sale->total_amount,
                'gross_profit' => $sale->gross_profit,
                'cashier_id' => $user->id,
            ]);

            return $sale->load(['items.product', 'cashier', 'payments']);
        });
    }

    /**
     * Record payment for credit sale
     */
    public function recordPayment(Sale $sale, float $amount, $user, ?string $method = null, ?string $reference = null, ?string $notes = null): Sale
    {
        if ($amount <= 0) {
            throw new \Exception('Payment amount must be greater than zero');
        }

        return DB::transaction(function () use ($sale, $amount, $user, $method, $reference, $notes) {
            // Lock the sale row to prevent concurrent payment race conditions
            $lockedSale = Sale::lockForUpdate()->findOrFail($sale->id);

            $newAmountPaid = round((float) $lockedSale->amount_paid + $amount, 2);
            $totalAmount = round((float) $lockedSale->total_amount, 2);

            if (bccomp((string) $newAmountPaid, (string) $totalAmount, 2) > 0) {
                throw new \Exception('Payment amount exceeds total due');
            }

            $paymentStatus = 'partially_paid';
            if (bccomp((string) $newAmountPaid, (string) $totalAmount, 2) >= 0) {
                $paymentStatus = 'paid';
            }

            $lockedSale->update([
                'amount_paid' => $newAmountPaid,
                'amount_due' => round($totalAmount - $newAmountPaid, 2),
                'payment_status' => $paymentStatus,
            ]);

            // Create Payment record if method is provided
            if ($method) {
                \App\Models\Payment::create([
                    'sale_id' => $lockedSale->id,
                    'method' => $method,
                    'amount' => $amount,
                    'reference' => $reference,
                    'notes' => $notes,
                ]);
            }

            Log::info('Payment recorded for sale', [
                'sale_number' => $lockedSale->sale_number,
                'amount' => $amount,
                'method' => $method,
                'new_total_paid' => $newAmountPaid,
                'user_id' => $user->id,
            ]);

            return $lockedSale;
        });
    }
}
