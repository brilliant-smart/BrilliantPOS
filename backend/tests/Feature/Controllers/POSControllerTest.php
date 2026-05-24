<?php

namespace Tests\Feature\Controllers;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductUnitType;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class POSControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- completeSale ----

    public function test_complete_sale_with_single_item(): void
    {
        $user = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/pos/complete-sale', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 100.00,
                ],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 200.00],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals(48, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('sales', ['status' => 'completed']);
    }

    public function test_complete_sale_with_carton_unit_type(): void
    {
        $user = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 100]);

        $cartonType = $product->unitTypes()->create([
            'name' => 'Carton',
            'short_name' => 'ctn',
            'conversion_factor' => 12,
            'selling_price' => 1200.00,
            'is_base' => false,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/pos/complete-sale', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 1200.00,
                    'unit_type' => 'Carton',
                    'product_unit_type_id' => $cartonType->id,
                    'conversion_factor' => 12,
                ],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 2400.00],
            ],
        ]);

        $response->assertStatus(200);
        // 2 cartons * 12 = 24 base units deducted from 100 = 76
        $this->assertEquals(76, $product->fresh()->stock_quantity);
    }

    public function test_cashier_cannot_override_price(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = $this->createProduct(['price' => 100.00, 'stock_quantity' => 50]);

        $response = $this->actingAs($cashier, 'sanctum')->postJson('/api/pos/complete-sale', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 10.00, // Trying to override to a lower price
                ],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 100.00],
            ],
        ]);

        $response->assertStatus(200);
        // Cashier price should be overridden to product's stored price
        $sale = Sale::first();
        $this->assertEquals(100.00, (float) $sale->items->first()->unit_price);
    }

    public function test_owner_can_override_price(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['price' => 100.00, 'stock_quantity' => 50]);

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/pos/complete-sale', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 80.00, // Owner overrides price
                ],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 80.00],
            ],
        ]);

        $response->assertStatus(200);
        $sale = Sale::first();
        $this->assertEquals(80.00, (float) $sale->items->first()->unit_price);
    }

    public function test_sale_discount_percentage_applied(): void
    {
        $user = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/pos/complete-sale', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 100.00,
                ],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 90.00],
            ],
            'discount_percentage' => 10,
        ]);

        $response->assertStatus(200);
        $sale = Sale::first();
        // 100 - 10% = 90
        $this->assertEqualsWithDelta(90.00, (float) $sale->total_amount, 0.01);
    }

    public function test_multiple_payments_recorded(): void
    {
        $user = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/pos/complete-sale', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 5000.00,
                ],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 3000.00],
                ['method' => 'bank_transfer', 'amount' => 2000.00],
            ],
        ]);

        $response->assertStatus(200);
        $sale = Sale::first();
        $this->assertEquals(2, $sale->payments()->count());
        $this->assertEquals(5000.00, (float) $sale->amount_paid);
    }

    public function test_insufficient_stock_rejected(): void
    {
        $user = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 5]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/pos/complete-sale', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_price' => 100.00,
                ],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 1000.00],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_complete_sale_creates_audit_log(): void
    {
        $user = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        $this->actingAs($user, 'sanctum')->postJson('/api/pos/complete-sale', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 100.00,
                ],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 100.00],
            ],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'sale.create',
            'user_id' => $user->id,
        ]);
    }

    // ---- voidSale ----

    public function test_owner_can_void_sale(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $sale = Sale::factory()->create(['status' => 'completed']);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/pos/void-sale/{$sale->id}", [
            'reason' => 'Customer requested cancellation',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('voided', $sale->fresh()->status);
    }

    public function test_manager_can_void_sale(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $sale = Sale::factory()->create(['status' => 'completed']);

        $response = $this->actingAs($manager, 'sanctum')->postJson("/api/pos/void-sale/{$sale->id}", [
            'reason' => 'Mistake in order',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('voided', $sale->fresh()->status);
    }

    public function test_cashier_cannot_void_sale(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $sale = Sale::factory()->create(['status' => 'completed']);

        $response = $this->actingAs($cashier, 'sanctum')->postJson("/api/pos/void-sale/{$sale->id}", [
            'reason' => 'Test',
        ]);

        $response->assertStatus(403);
    }

    public function test_void_restores_stock(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 40]);

        // Create a sale that deducted stock
        $sale = Sale::factory()->create(['status' => 'completed', 'cashier_id' => $owner->id]);
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

        // Manually reduce stock to simulate completed sale
        $product->update(['stock_quantity' => 30]);

        $this->actingAs($owner, 'sanctum')->postJson("/api/pos/void-sale/{$sale->id}", [
            'reason' => 'Void test',
        ]);

        $this->assertEquals(40, $product->fresh()->stock_quantity);
    }

    public function test_void_restores_stock_with_unit_conversion(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 76]);

        $sale = Sale::factory()->create(['status' => 'completed', 'cashier_id' => $owner->id]);
        // Sale was 2 cartons = 24 base units
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

        // Stock was already deducted (100 - 24 = 76)
        $this->actingAs($owner, 'sanctum')->postJson("/api/pos/void-sale/{$sale->id}", [
            'reason' => 'Void test with unit conversion',
        ]);

        // 76 + (2 * 12) = 100
        $this->assertEquals(100, $product->fresh()->stock_quantity);
    }

    public function test_void_requires_reason(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $sale = Sale::factory()->create(['status' => 'completed']);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/pos/void-sale/{$sale->id}", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);
    }

    public function test_cannot_void_already_voided_sale(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $sale = Sale::factory()->create(['status' => 'voided']);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/pos/void-sale/{$sale->id}", [
            'reason' => 'Already voided',
        ]);

        $response->assertStatus(422);
    }

    public function test_void_creates_audit_log(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $sale = Sale::factory()->create(['status' => 'completed']);

        $this->actingAs($owner, 'sanctum')->postJson("/api/pos/void-sale/{$sale->id}", [
            'reason' => 'Audit log test',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'sale.void',
            'user_id' => $owner->id,
        ]);
    }

    // ---- validateStock ----

    public function test_validate_stock_sufficient(): void
    {
        $user = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/pos/validate-stock', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10],
            ],
        ]);

        $response->assertStatus(200);
    }

    public function test_validate_stock_insufficient(): void
    {
        $user = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 5]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/pos/validate-stock', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10],
            ],
        ]);

        $response->assertStatus(422);
    }

    // ---- holdCart / recallCart ----

    public function test_hold_cart(): void
    {
        $user = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = $this->createProduct();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/pos/hold-cart', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100.00],
            ],
            'reference' => 'Test hold',
            'notes' => 'Customer stepped away',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('held_carts', ['user_id' => $user->id]);
    }

    public function test_recall_cart(): void
    {
        $user = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $cart = $user->heldCarts()->create([
            'items' => [['product_id' => 1, 'quantity' => 2]],
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'reference' => 'Test',
            'held_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/pos/held-carts/{$cart->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['reference' => 'Test']);
    }

    public function test_delete_held_cart(): void
    {
        $user = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $cart = $user->heldCarts()->create([
            'items' => [['product_id' => 1, 'quantity' => 2]],
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'reference' => 'HOLD-TEST-001',
            'held_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/pos/held-carts/{$cart->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('held_carts', ['id' => $cart->id]);
    }

    public function test_held_carts_scoped_to_user(): void
    {
        $userA = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $userB = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $cartA = $userA->heldCarts()->create([
            'items' => [['product_id' => 1]],
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'reference' => 'HOLD-USERA-001',
            'held_at' => now(),
        ]);
        $userB->heldCarts()->create([
            'items' => [['product_id' => 2]],
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'reference' => 'HOLD-USERB-001',
            'held_at' => now(),
        ]);

        $response = $this->actingAs($userA, 'sanctum')->getJson('/api/pos/held-carts');

        $response->assertStatus(200);
        // Should only see user A's carts
        $carts = $response->json();
        foreach ($carts as $cart) {
            $this->assertEquals($userA->id, $cart['user_id']);
        }
    }
}