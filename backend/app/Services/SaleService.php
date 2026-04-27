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

            foreach ($data['items'] as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                // Check stock availability
                if ($product->stock_quantity < $item['quantity']) {
                    throw new \Exception("Insufficient stock for product: {$product->name}. Available: {$product->stock_quantity}, Requested: {$item['quantity']}");
                }

                $lineTotal = $item['quantity'] * $item['unit_price'];
                $lineTotal -= $lineTotal * ($item['discount_percent'] ?? 0) / 100;
                
                $lineCost = $item['quantity'] * $product->cost_price;

                $subtotal += $lineTotal;
                $totalCost += $lineCost;
            }

            $vatAmount = 0;
            $totalAmount = $subtotal - ($data['discount_amount'] ?? 0);
            $grossProfit = $totalAmount - $totalCost;

            // Create sale
            $sale = Sale::create([
                'sale_number' => $saleNumber,
                'cashier_id' => $user->id,
                'sale_type' => $data['sale_type'] ?? 'cash',
                'payment_status' => $data['payment_status'] ?? 'paid',
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'total_amount' => $totalAmount,
                'amount_paid' => $data['amount_paid'] ?? $totalAmount,
                'amount_due' => $totalAmount - ($data['amount_paid'] ?? $totalAmount),
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

                $lineTotal = $item['quantity'] * $item['unit_price'];
                $lineTotal -= $lineTotal * ($item['discount_percent'] ?? 0) / 100;
                
                $lineCost = $item['quantity'] * $product->cost_price;
                $lineProfit = $lineTotal - $lineCost;

                // Create sale item
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'unit_cost' => $product->cost_price,
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'line_total' => $lineTotal,
                    'line_cost' => $lineCost,
                    'line_profit' => $lineProfit,
                    'notes' => $item['notes'] ?? null,
                ]);

                // Reduce stock
                $previousStock = $product->stock_quantity;
                $newStock = $previousStock - $item['quantity'];

                $product->update([
                    'stock_quantity' => $newStock,
                ]);

                // Create stock movement
                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'type' => 'sale',
                    'quantity' => -$item['quantity'],
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'notes' => "Sale: {$sale->sale_number}",
                ]);

                Log::info('Product sold', [
                    'sale_number' => $sale->sale_number,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'profit' => $lineProfit,
                ]);
            }

            Log::info('Sale created', [
                'sale_number' => $sale->sale_number,
                'total_amount' => $sale->total_amount,
                'gross_profit' => $sale->gross_profit,
                'cashier_id' => $user->id,
            ]);

            return $sale->load(['items.product', 'cashier']);
        });
    }

    /**
     * Record payment for credit sale
     */
    public function recordPayment(Sale $sale, float $amount, $user): Sale
    {
        if ($amount <= 0) {
            throw new \Exception('Payment amount must be greater than zero');
        }

        $newAmountPaid = $sale->amount_paid + $amount;

        if ($newAmountPaid > $sale->total_amount) {
            throw new \Exception('Payment amount exceeds total due');
        }

        $paymentStatus = 'partially_paid';
        if ($newAmountPaid >= $sale->total_amount) {
            $paymentStatus = 'paid';
        }

        $sale->update([
            'amount_paid' => $newAmountPaid,
            'amount_due' => $sale->total_amount - $newAmountPaid,
            'payment_status' => $paymentStatus,
        ]);

        Log::info('Payment recorded for sale', [
            'sale_number' => $sale->sale_number,
            'amount' => $amount,
            'new_total_paid' => $newAmountPaid,
            'user_id' => $user->id,
        ]);

        return $sale;
    }
}
