<?php

namespace Tests\Feature\Regression;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for purchase order lifecycle: creation, approval, receiving, payment, and WAC calculations.
 */
class PurchaseOrderRegressionTest extends TestCase
{
    use RefreshDatabase;

    // ---- REGRESSION: PO approval only valid from draft/pending status ----

    public function test_cannot_approve_already_received_po(): void
    {
        $user = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $po = PurchaseOrder::factory()->create(['status' => 'received']);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/purchase-orders/{$po->id}/approve");

        $response->assertStatus(422);
    }

    public function test_cannot_approve_cancelled_po(): void
    {
        $user = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $po = PurchaseOrder::factory()->create(['status' => 'cancelled']);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/purchase-orders/{$po->id}/approve");

        $response->assertStatus(422);
    }

    // ---- REGRESSION: Cannot receive more than ordered quantity ----

    public function test_receiving_more_than_ordered_quantity_is_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $supplier = Supplier::factory()->create();
        $product = $this->createProduct(['stock_quantity' => 0]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'approved',
            'created_by' => $owner->id,
        ]);
        $po->items()->create([
            'product_id' => $product->id,
            'quantity_ordered' => 10,
            'unit_cost' => 50.00,
            'line_total' => 500.00,
        ]);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/purchase-orders/{$po->id}/receive", [
            'items' => [
                ['product_id' => $product->id, 'quantity_received' => 20],
            ],
        ]);

        $response->assertStatus(422);
    }

    // ---- REGRESSION: Receiving partial quantity updates status correctly ----

    public function test_partial_receiving_sets_partially_received_status(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $supplier = Supplier::factory()->create();
        $product = $this->createProduct(['stock_quantity' => 0, 'cost_price' => 0]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'approved',
            'created_by' => $owner->id,
        ]);
        $po->items()->create([
            'product_id' => $product->id,
            'quantity_ordered' => 100,
            'unit_cost' => 50.00,
            'line_total' => 5000.00,
        ]);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/purchase-orders/{$po->id}/receive", [
            'items' => [
                ['product_id' => $product->id, 'quantity_received' => 50],
            ],
        ]);

        $response->assertStatus(200);
        $po->refresh();
        $this->assertEquals('partially_received', $po->status);
        $this->assertEquals(50, $product->fresh()->stock_quantity);
    }

    // ---- REGRESSION: PO payment tracking ----

    public function test_po_partial_payment_updates_amount_paid(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $supplier = Supplier::factory()->create();
        $product = $this->createProduct();

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'approved',
            'total_amount' => 1000.00,
            'amount_paid' => 0,
            'payment_status' => 'unpaid',
            'created_by' => $owner->id,
        ]);
        $po->items()->create([
            'product_id' => $product->id,
            'quantity_ordered' => 10,
            'unit_cost' => 100.00,
            'line_total' => 1000.00,
        ]);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/purchase-orders/{$po->id}/record-payment", [
            'amount' => 400.00,
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertStatus(200);
        $po->refresh();
        $this->assertEqualsWithDelta(400.00, (float) $po->amount_paid, 0.01);
        $this->assertEquals('partially_paid', $po->payment_status);
    }

    // ---- REGRESSION: PO cannot be deleted if not in draft status ----

    public function test_cannot_delete_approved_po(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $supplier = Supplier::factory()->create();
        $product = $this->createProduct();

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'approved',
            'created_by' => $owner->id,
        ]);
        $po->items()->create([
            'product_id' => $product->id,
            'quantity_ordered' => 10,
            'unit_cost' => 50.00,
            'line_total' => 500.00,
        ]);

        $response = $this->actingAs($owner, 'sanctum')->deleteJson("/api/purchase-orders/{$po->id}");

        $response->assertStatus(422);
    }

    // ---- REGRESSION: Cashier cannot approve PO ----

    public function test_cashier_cannot_approve_purchase_order(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $supplier = Supplier::factory()->create();
        $product = $this->createProduct();

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'pending',
            'created_by' => $cashier->id,
        ]);
        $po->items()->create([
            'product_id' => $product->id,
            'quantity_ordered' => 10,
            'unit_cost' => 50.00,
            'line_total' => 500.00,
        ]);

        $response = $this->actingAs($cashier, 'sanctum')->postJson("/api/purchase-orders/{$po->id}/approve");

        $response->assertStatus(403);
    }

    // ---- REGRESSION: WAC calculation accuracy with mixed unit types ----

    public function test_wac_calculation_with_carton_unit_type(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $supplier = Supplier::factory()->create();
        // Product: 100 units at cost 50 per unit
        $product = $this->createProduct([
            'stock_quantity' => 100,
            'cost_price' => 50.00,
            'last_purchase_price' => 50.00,
        ]);

        $cartonType = $product->unitTypes()->create([
            'name' => 'Carton',
            'short_name' => 'ctn',
            'conversion_factor' => 12,
            'selling_price' => 1200.00,
            'is_base' => false,
            'sort_order' => 1,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'approved',
            'created_by' => $owner->id,
        ]);
        $po->items()->create([
            'product_id' => $product->id,
            'quantity_ordered' => 5,
            'unit_cost' => 600.00, // 600 per carton = 50 per piece
            'product_unit_type_id' => $cartonType->id,
            'conversion_factor' => 12,
            'unit_type' => 'Carton',
            'line_total' => 3000.00,
        ]);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/purchase-orders/{$po->id}/receive", [
            'items' => [
                ['product_id' => $product->id, 'quantity_received' => 5],
            ],
        ]);

        $response->assertStatus(200);
        $product->refresh();

        // 5 cartons * 12 = 60 base units added
        // Total stock: 100 + 60 = 160
        // WAC: (100*50 + 60*50) / 160 = 8000/160 = 50.00
        $this->assertEquals(160, $product->stock_quantity);
        $this->assertEqualsWithDelta(50.00, (float) $product->cost_price, 0.01);
    }
}