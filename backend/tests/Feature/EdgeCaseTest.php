<?php

namespace Tests\Feature\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Edge-case tests that document known behavioral gaps and boundary conditions.
 * Some tests verify correct behavior; others document known bugs.
 */
class EdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
    }

    // ---- Discount exceeding subtotal (known bug: no max discount validation) ----

    public function test_discount_exceeding_subtotal_produces_zero_or_negative_total(): void
    {
        // Known bug: discount_amount is validated as min:0 but has no maximum.
        // SaleController calculates total = subtotal - discount_amount with no floor.
        $product = Product::factory()->create(['is_active' => true, 'price' => 100, 'cost_price' => 60, 'stock_quantity' => 50]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/sales', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100],
                ],
                'discount_amount' => 200, // Exceeds subtotal of 100
                'amount_paid' => 0,
            ]);

        $response->assertStatus(201);
        $totalAmount = (float) $response->json('sale.total_amount');
        // Documents that total can be zero or negative
        $this->assertLessThanOrEqual(0, $totalAmount);
    }

    // ---- Payment on voided sale (known gap: no status check) ----

    public function test_payment_on_voided_sale_is_accepted(): void
    {
        // Known gap: recordPayment does not check sale status.
        $product = Product::factory()->create(['is_active' => true, 'price' => 100, 'cost_price' => 60, 'stock_quantity' => 50]);

        $saleResponse = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/sales', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100],
                ],
                'amount_paid' => 50,
            ]);

        $saleId = $saleResponse->json('sale.id');

        // Void the sale
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/pos/void-sale/{$saleId}", ['reason' => 'Test void'])
            ->assertStatus(200);

        // Payment on voided sale is currently accepted (documented gap)
        $paymentResponse = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/sales/{$saleId}/payment", [
                'amount' => 50,
                'method' => 'cash',
            ]);

        $paymentResponse->assertStatus(200);
    }

    // ---- Multi-payment on credit sale ----

    public function test_credit_sale_multiple_partial_payments(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price' => 200, 'cost_price' => 120, 'stock_quantity' => 50]);

        $saleResponse = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/sales', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 200],
                ],
                'sale_type' => 'credit',
                'amount_paid' => 0,
            ]);

        $saleResponse->assertStatus(201);
        $saleId = $saleResponse->json('sale.id');
        $this->assertEquals('unpaid', $saleResponse->json('sale.payment_status'));

        // First partial payment
        $payment1 = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/sales/{$saleId}/payment", [
                'amount' => 80,
                'method' => 'cash',
            ]);
        $payment1->assertStatus(200);
        $this->assertEquals('partially_paid', $payment1->json('sale.payment_status'));

        // Second partial payment — still not fully paid
        $payment2 = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/sales/{$saleId}/payment", [
                'amount' => 80,
                'method' => 'cash',
            ]);
        $payment2->assertStatus(200);
        $this->assertEquals('partially_paid', $payment2->json('sale.payment_status'));

        // Final payment — fully paid
        $payment3 = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/sales/{$saleId}/payment", [
                'amount' => 40,
                'method' => 'cash',
            ]);
        $payment3->assertStatus(200);
        $this->assertEquals('paid', $payment3->json('sale.payment_status'));
    }

    public function test_overpayment_on_record_payment_rejected(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price' => 100, 'cost_price' => 60, 'stock_quantity' => 50]);

        $saleResponse = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/sales', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100],
                ],
                'amount_paid' => 50,
            ]);

        $saleId = $saleResponse->json('sale.id');

        // Try to pay more than the remaining balance (50 remaining, pay 60)
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/sales/{$saleId}/payment", [
                'amount' => 60,
                'method' => 'cash',
            ])->assertStatus(422); // Exception returns 422 from SaleController
    }

    // ---- Void sale restores stock ----

    public function test_void_sale_restores_product_stock(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price' => 100, 'cost_price' => 60, 'stock_quantity' => 50]);

        $saleResponse = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/sales', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 100],
                ],
                'amount_paid' => 500,
            ]);

        $saleId = $saleResponse->json('sale.id');
        $this->assertEquals(45, $product->fresh()->stock_quantity);

        // Void the sale
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/pos/void-sale/{$saleId}", ['reason' => 'Customer cancellation'])
            ->assertStatus(200);

        // Stock should be restored
        $this->assertEquals(50, $product->fresh()->stock_quantity);
    }

    public function test_void_sale_sets_status_to_voided(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price' => 100, 'cost_price' => 60, 'stock_quantity' => 50]);

        $saleResponse = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/sales', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100],
                ],
                'amount_paid' => 100,
            ]);

        $saleId = $saleResponse->json('sale.id');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/pos/void-sale/{$saleId}", ['reason' => 'Error'])
            ->assertStatus(200);

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'status' => 'voided',
        ]);
    }

    public function test_void_sale_already_voided_returns_422(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price' => 100, 'cost_price' => 60, 'stock_quantity' => 50]);

        $saleResponse = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/sales', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100],
                ],
                'amount_paid' => 100,
            ]);

        $saleId = $saleResponse->json('sale.id');

        // First void succeeds
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/pos/void-sale/{$saleId}", ['reason' => 'First void'])
            ->assertStatus(200);

        // Second void fails — already voided
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/pos/void-sale/{$saleId}", ['reason' => 'Second void'])
            ->assertStatus(422);
    }

    // ---- POS cashier price enforcement ----

    public function test_cashier_cannot_override_price_in_pos(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price' => 100, 'cost_price' => 60, 'stock_quantity' => 50]);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/complete-sale', [
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 1,
                        'unit_price' => 1, // Cashier tries to set price to 1
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount' => 100],
                ],
            ]);

        $response->assertSuccessful();
        // Cashier's unit_price override is ignored — stored price (100) is used
        $subtotal = (float) ($response->json('sale.subtotal') ?? $response->json('subtotal'));
        $this->assertEqualsWithDelta(100.00, $subtotal, 0.01);
    }

    // ---- Validate stock endpoint ----

    public function test_validate_stock_available(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price' => 100, 'stock_quantity' => 50]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/pos/validate-stock', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 10],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJson(['valid' => true]);
    }

    public function test_validate_stock_insufficient(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price' => 100, 'stock_quantity' => 5]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/pos/validate-stock', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 10],
                ],
            ]);

        // Insufficient stock returns 422 with validation errors
        $response->assertStatus(422);
        $response->assertJson(['valid' => false]);
    }

    // ---- Purchase order overpayment ----

    public function test_purchase_order_overpayment_rejected(): void
    {
        $supplier = Supplier::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 10]);

        // Create PO with cash payment method so overpayment check applies
        $poResponse = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/purchase-orders', [
                'supplier_id' => $supplier->id,
                'payment_method' => 'cash',
                'items' => [
                    ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 50],
                ],
            ]);

        $poId = $poResponse->json('purchase_order.id');

        // Try to pay more than total
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/purchase-orders/{$poId}/record-payment", [
                'amount' => 999999,
                'payment_method' => 'cash',
            ])->assertStatus(422);
    }

    // ---- Sale with multiple items ----

    public function test_sale_with_multiple_items(): void
    {
        $product1 = Product::factory()->create(['is_active' => true, 'price' => 100, 'cost_price' => 60, 'stock_quantity' => 50]);
        $product2 = Product::factory()->create(['is_active' => true, 'price' => 50, 'cost_price' => 30, 'stock_quantity' => 50]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/sales', [
                'items' => [
                    ['product_id' => $product1->id, 'quantity' => 2, 'unit_price' => 100],
                    ['product_id' => $product2->id, 'quantity' => 3, 'unit_price' => 50],
                ],
                'amount_paid' => 350,
            ]);

        $response->assertStatus(201);
        // subtotal = 2*100 + 3*50 = 350
        $this->assertEqualsWithDelta(350.00, (float) $response->json('sale.subtotal'), 0.01);
        // Stock deductions
        $this->assertEquals(48, $product1->fresh()->stock_quantity);
        $this->assertEquals(47, $product2->fresh()->stock_quantity);
    }

    // ---- Inventory adjustment ----

    public function test_inventory_adjustment_to_zero(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 50]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/inventory/products/{$product->id}/adjust-stock", [
                'quantity' => 0,
                'notes' => 'Write-off',
            ]);

        $response->assertStatus(200);
        $this->assertEquals(0, $product->fresh()->stock_quantity);
    }

    public function test_inventory_adjustment_to_higher_quantity(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 10]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/inventory/products/{$product->id}/adjust-stock", [
                'quantity' => 25,
                'notes' => 'Cycle count found more',
            ]);

        $response->assertStatus(200);
        $this->assertEquals(25, $product->fresh()->stock_quantity);
    }

    // ---- Insufficient stock rejection ----

    public function test_cannot_sell_more_than_available_stock(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price' => 100, 'cost_price' => 60, 'stock_quantity' => 3]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/sales', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 100],
                ],
                'amount_paid' => 1000,
            ]);

        // SaleService throws exception, SaleController returns 422
        $response->assertStatus(422);
    }

    // ---- Credit sale payment statuses ----

    public function test_credit_sale_starts_unpaid(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price' => 100, 'cost_price' => 60, 'stock_quantity' => 50]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/sales', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100],
                ],
                'sale_type' => 'credit',
                'amount_paid' => 0,
            ]);

        $response->assertStatus(201);
        $this->assertEquals('unpaid', $response->json('sale.payment_status'));
    }

    public function test_credit_sale_with_partial_payment(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price' => 100, 'cost_price' => 60, 'stock_quantity' => 50]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/sales', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100],
                ],
                'sale_type' => 'credit',
                'amount_paid' => 40,
            ]);

        $response->assertStatus(201);
        $this->assertEquals('partially_paid', $response->json('sale.payment_status'));
    }

    // ---- Purchase order full cycle ----

    public function test_purchase_order_full_cycle_create_approve_receive(): void
    {
        $supplier = Supplier::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 10, 'cost_price' => 50]);

        // Create PO with credit payment method so receive-goods doesn't require payment first
        $poResponse = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/purchase-orders', [
                'supplier_id' => $supplier->id,
                'payment_method' => 'credit',
                'items' => [
                    ['product_id' => $product->id, 'quantity_ordered' => 20, 'unit_cost' => 50],
                ],
            ]);
        $poResponse->assertStatus(201);
        $poId = $poResponse->json('purchase_order.id');
        $this->assertEquals('draft', $poResponse->json('purchase_order.status'));

        // Approve PO
        $approveResponse = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/purchase-orders/{$poId}/approve");
        $approveResponse->assertStatus(200);
        $this->assertEquals('approved', $approveResponse->json('purchase_order.status'));

        // Receive goods
        $receiveResponse = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/purchase-orders/{$poId}/receive", [
                'items' => [
                    ['product_id' => $product->id, 'quantity_received' => 20],
                ],
            ]);
        $receiveResponse->assertStatus(200);
        $this->assertEquals('received', $receiveResponse->json('purchase_order.status'));

        // Stock should increase
        $this->assertEquals(30, $product->fresh()->stock_quantity);
    }

    // ---- Hold and recall cart ----

    public function test_hold_and_recall_cart(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price' => 100, 'stock_quantity' => 50]);

        // Hold a cart
        $holdResponse = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/hold-cart', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100],
                ],
                'notes' => 'Customer cart hold',
            ]);

        $holdResponse->assertStatus(201);
        $cartId = $holdResponse->json('held_cart.id');

        // List held carts
        $listResponse = $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/pos/held-carts');
        $listResponse->assertStatus(200);

        // Recall the cart
        $recallResponse = $this->actingAs($this->cashier, 'sanctum')
            ->getJson("/api/pos/held-carts/{$cartId}");
        $recallResponse->assertStatus(200);
    }
}