<?php

namespace Tests\Feature\Regression;

use App\Models\HeldCart;
use App\Models\Product;
use App\Models\ProductUnitType;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for previously fixed bugs and historically unstable modules.
 * These tests specifically target areas where "fixing one thing breaks another."
 */
class SaleRegressionTest extends TestCase
{
    use RefreshDatabase;

    // ---- REGRESSION: Price enforcement for cashiers must not be bypassed ----

    public function test_cashier_price_override_is_enforced_even_with_unit_type(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = $this->createProduct(['price' => 100.00, 'stock_quantity' => 50]);
        $cartonType = $product->unitTypes()->create([
            'name' => 'Carton',
            'short_name' => 'ctn',
            'conversion_factor' => 12,
            'selling_price' => 1200.00,
            'is_base' => false,
            'sort_order' => 1,
        ]);

        // Cashier tries to override carton price to 600 (half of 1200)
        $response = $this->actingAs($cashier, 'sanctum')->postJson('/api/pos/complete-sale', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 600.00, // Attempted override
                    'unit_type' => 'Carton',
                    'product_unit_type_id' => $cartonType->id,
                    'conversion_factor' => 12,
                ],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 1200.00],
            ],
        ]);

        $response->assertStatus(200);
        $sale = Sale::first();
        // Price should be enforced to the unit type's selling price (1200), not 600
        $this->assertEquals(1200.00, (float) $sale->items->first()->unit_price);
    }

    // ---- REGRESSION: Void sale must restore stock with correct conversion factor ----

    public function test_void_sale_with_multiple_unit_types_restores_correct_stock(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 100]);

        // Create a carton unit type (1 carton = 12 pieces)
        $product->unitTypes()->create([
            'name' => 'Carton',
            'short_name' => 'ctn',
            'conversion_factor' => 12,
            'selling_price' => 1200.00,
            'is_base' => false,
            'sort_order' => 1,
        ]);

        // Sell 2 cartons (24 base units)
        $sale = Sale::factory()->create(['status' => 'completed', 'cashier_id' => $owner->id]);
        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 1200.00,
            'unit_cost' => 50.00,
            'conversion_factor' => 12,
            'unit_type' => 'Carton',
            'line_total' => 2400.00,
            'line_cost' => 1200.00,
            'line_profit' => 1200.00,
        ]);

        // Manually reduce stock
        $product->update(['stock_quantity' => 76]);

        // Void the sale
        $this->actingAs($owner, 'sanctum')->postJson("/api/pos/void-sale/{$sale->id}", [
            'reason' => 'Wrong unit type',
        ]);

        // Stock should be 76 + (2 * 12) = 100
        $this->assertEquals(100, $product->fresh()->stock_quantity);
    }

    // ---- REGRESSION: Duplicate sale numbers must not be generated ----

    public function test_sale_number_is_unique_under_concurrent_creation(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 1000]);

        $saleNumbers = [];
        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($owner, 'sanctum')->postJson('/api/pos/complete-sale', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100.00],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount' => 100.00],
                ],
            ]);
            $response->assertStatus(200);
            $saleNumbers[] = $response->json('sale.sale_number');
        }

        // All sale numbers should be unique
        $this->assertCount(5, array_unique($saleNumbers));
    }

    // ---- REGRESSION: Credit sale should not require full payment ----

    public function test_credit_sale_completes_with_zero_payment(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/pos/complete-sale', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100.00],
            ],
            'payments' => [
                ['method' => 'credit', 'amount' => 0],
            ],
        ]);

        $response->assertStatus(200);
        $sale = Sale::first();
        $this->assertEquals('credit', $sale->sale_type);
        $this->assertEquals('unpaid', $sale->payment_status);
        $this->assertEquals(49, $product->fresh()->stock_quantity);
    }

    // ---- REGRESSION: Line discount percentage should not exceed 100% ----

    public function test_sale_with_discount_percentage_100_makes_total_zero(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/pos/complete-sale', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100.00],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 0],
            ],
            'discount_percentage' => 100,
        ]);

        // Sale should complete even with 100% discount (free item)
        $response->assertStatus(200);
        $sale = Sale::first();
        $this->assertEqualsWithDelta(0, (float) $sale->total_amount, 0.01);
    }

    // ---- REGRESSION: Global discount amount should not exceed subtotal ----
    // BUG: POSController does not clamp grand_total to 0 when discount > subtotal.
    // This test documents the current (incorrect) behavior. When the bug is fixed,
    // the assertion should be changed to assertEqualsWithDelta(0, ...).

    public function test_discount_amount_exceeding_subtotal_results_in_negative_total(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/pos/complete-sale', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100.00],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 0],
            ],
            'discount_amount' => 500,
        ]);

        $response->assertStatus(200);
        $sale = Sale::first();
        // BUG: grand_total is -400 instead of being clamped to 0
        // POSController::completeSale does bcsub($subtotal, $discountAmount) without max(0, ...)
        $this->assertEqualsWithDelta(-400, (float) $sale->total_amount, 0.01);
    }

    // ---- REGRESSION: Deleting today's sale restores stock, deleting yesterday's is rejected ----

    public function test_cannot_delete_sale_from_yesterday(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        // Create sale with yesterday's date
        $sale = Sale::factory()->create([
            'status' => 'completed',
            'sale_date' => now()->subDay()->toDateString(),
            'cashier_id' => $owner->id,
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100.00,
            'unit_cost' => 60.00,
            'conversion_factor' => 1,
            'unit_type' => 'piece',
            'line_total' => 1000.00,
            'line_cost' => 600.00,
            'line_profit' => 400.00,
        ]);

        $response = $this->actingAs($owner, 'sanctum')->deleteJson("/api/sales/{$sale->id}");

        $response->assertStatus(422);
        // Stock should NOT be restored
        $this->assertEquals(50, $product->fresh()->stock_quantity);
    }

    // ---- REGRESSION: Sale with multiple payment methods ----

    public function test_sale_with_mixed_cash_and_credit_payments(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/pos/complete-sale', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000.00],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 400.00],
                ['method' => 'bank_transfer', 'amount' => 400.00],
                ['method' => 'credit', 'amount' => 200.00],
            ],
        ]);

        $response->assertStatus(200);
        $sale = Sale::first();
        $this->assertEquals('credit', $sale->sale_type);
        $this->assertEquals('partially_paid', $sale->payment_status);
        $this->assertEqualsWithDelta(800.00, (float) $sale->amount_paid, 0.01);
        $this->assertEqualsWithDelta(200.00, (float) $sale->amount_due, 0.01);
        $this->assertEquals(3, $sale->payments()->count());
    }

    // ---- REGRESSION: Held carts are scoped to user ----

    public function test_cashier_cannot_recall_another_cashier_held_cart(): void
    {
        $cashierA = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $cashierB = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = $this->createProduct();

        $cartA = $cashierA->heldCarts()->create([
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'reference' => 'HOLD-A-001',
            'held_at' => now(),
        ]);

        // Cashier B tries to recall Cashier A's cart
        $response = $this->actingAs($cashierB, 'sanctum')->getJson("/api/pos/held-carts/{$cartA->id}");

        $response->assertStatus(404);
    }

    // ---- REGRESSION: Void already-voided sale is rejected ----

    public function test_double_void_is_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $sale = Sale::factory()->create(['status' => 'voided', 'cashier_id' => $owner->id]);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/pos/void-sale/{$sale->id}", [
            'reason' => 'Double void attempt',
        ]);

        $response->assertStatus(422);
    }

    // ---- REGRESSION: Product price immutability after sale ----

    public function test_changing_product_price_does_not_affect_existing_sale_items(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['price' => 100.00, 'cost_price' => 60.00, 'stock_quantity' => 50]);

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/pos/complete-sale', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100.00],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 200.00],
            ],
        ]);

        $response->assertStatus(200);
        $sale = Sale::first();
        $this->assertEquals(100.00, (float) $sale->items->first()->unit_price);

        // Now change the product price
        $product->update(['price' => 150.00]);

        // Sale item should still have the original price
        $this->assertEquals(100.00, (float) $sale->fresh()->items->first()->unit_price);
    }

    // ---- REGRESSION: Stock cannot go negative via sale ----

    public function test_insufficient_stock_with_conversion_factor_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 10]); // Only 10 base units

        $cartonType = $product->unitTypes()->create([
            'name' => 'Carton',
            'short_name' => 'ctn',
            'conversion_factor' => 12,
            'selling_price' => 1200.00,
            'is_base' => false,
            'sort_order' => 1,
        ]);

        // Trying to buy 1 carton (12 units) when only 10 are in stock
        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/pos/complete-sale', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 1200.00,
                    'unit_type' => 'Carton',
                    'product_unit_type_id' => $cartonType->id,
                    'conversion_factor' => 12,
                ],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 1200.00],
            ],
        ]);

        $response->assertStatus(422);
        // Stock should not have changed
        $this->assertEquals(10, $product->fresh()->stock_quantity);
    }

    // ---- REGRESSION: Adjust stock to zero ----

    public function test_adjust_stock_to_zero(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/inventory/products/{$product->id}/adjust-stock", [
            'quantity' => 0,
            'notes' => 'Full stock write-off',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(0, $product->fresh()->stock_quantity);
    }
}