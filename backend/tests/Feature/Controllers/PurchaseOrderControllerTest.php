<?php

namespace Tests\Feature\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createSupplier(): Supplier
    {
        return Supplier::factory()->create(['is_active' => true]);
    }

    private function createDraftPO(array $overrides = []): PurchaseOrder
    {
        $supplier = $this->createSupplier();
        $owner = \App\Models\User::factory()->create(['role' => 'owner', 'is_active' => true]);

        return PurchaseOrder::create(array_merge([
            'po_number' => 'PO-' . now()->format('Ymd') . '-0001',
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => now()->addDays(14)->toDateString(),
            'status' => 'draft',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 1000.00,
            'total_amount' => 1000.00,
            'amount_paid' => 0,
            'created_by' => $owner->id,
        ], $overrides));
    }

    // ---- Index ----

    public function test_owner_can_list_purchase_orders(): void
    {
        $this->createDraftPO();

        $response = $this->actingAsOwner()->getJson('/api/purchase-orders');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'current_page', 'total']);
        $this->assertGreaterThanOrEqual(1, $response->json('total'));
    }

    public function test_cashier_cannot_list_purchase_orders(): void
    {
        $response = $this->actingAsCashier()->getJson('/api/purchase-orders');

        $response->assertStatus(403);
    }

    public function test_index_filters_by_status(): void
    {
        $this->createDraftPO(['status' => 'draft']);
        $this->createDraftPO(['status' => 'approved', 'po_number' => 'PO-' . now()->format('Ymd') . '-0002']);

        $response = $this->actingAsOwner()->getJson('/api/purchase-orders?status=draft');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
    }

    // ---- Store ----

    public function test_owner_can_create_purchase_order(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct();

        $response = $this->actingAsOwner()->postJson('/api/purchase-orders', [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => now()->addDays(14)->toDateString(),
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity_ordered' => 10,
                    'unit_cost' => 50.00,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'Purchase order created successfully');
        $response->assertJsonStructure(['message', 'purchase_order']);
        $this->assertDatabaseHas('purchase_orders', ['supplier_id' => $supplier->id]);
    }

    public function test_create_purchase_order_requires_supplier(): void
    {
        $product = $this->createProduct();

        $response = $this->actingAsOwner()->postJson('/api/purchase-orders', [
            'order_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 50.00],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['supplier_id']);
    }

    public function test_create_purchase_order_requires_items(): void
    {
        $supplier = $this->createSupplier();

        $response = $this->actingAsOwner()->postJson('/api/purchase-orders', [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items']);
    }

    public function test_create_purchase_order_rejects_invalid_payment_method(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct();

        $response = $this->actingAsOwner()->postJson('/api/purchase-orders', [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'payment_method' => 'invalid_method',
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 50.00],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_method']);
    }

    public function test_cashier_cannot_create_purchase_order(): void
    {
        $response = $this->actingAsCashier()->postJson('/api/purchase-orders', []);

        $response->assertStatus(403);
    }

    // ---- Show ----

    public function test_owner_can_view_purchase_order(): void
    {
        $po = $this->createDraftPO();

        $response = $this->actingAsOwner()->getJson("/api/purchase-orders/{$po->id}");

        $response->assertStatus(200);
        $this->assertEquals($po->po_number, $response->json('po_number'));
    }

    // ---- Approve ----

    public function test_owner_can_approve_draft_purchase_order(): void
    {
        $po = $this->createDraftPO(['status' => 'draft']);

        $response = $this->actingAsOwner()->postJson("/api/purchase-orders/{$po->id}/approve");

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Purchase order approved successfully');
        $this->assertEquals('approved', $po->fresh()->status);
    }

    public function test_cannot_approve_already_approved_purchase_order(): void
    {
        $po = $this->createDraftPO(['status' => 'approved']);

        $response = $this->actingAsOwner()->postJson("/api/purchase-orders/{$po->id}/approve");

        $response->assertStatus(422);
        $response->assertJsonPath('message', "Purchase order with status 'approved' cannot be approved");
    }

    // ---- Reject ----

    public function test_owner_can_reject_draft_purchase_order(): void
    {
        $po = $this->createDraftPO(['status' => 'draft']);

        $response = $this->actingAsOwner()->postJson("/api/purchase-orders/{$po->id}/reject", [
            'rejection_reason' => 'Supplier unreliable',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Purchase order rejected');
        $this->assertEquals('rejected', $po->fresh()->status);
        $this->assertEquals('Supplier unreliable', $po->fresh()->rejection_reason);
    }

    public function test_reject_requires_reason(): void
    {
        $po = $this->createDraftPO(['status' => 'draft']);

        $response = $this->actingAsOwner()->postJson("/api/purchase-orders/{$po->id}/reject");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['rejection_reason']);
    }

    // ---- Cancel ----

    public function test_owner_can_cancel_draft_purchase_order(): void
    {
        $po = $this->createDraftPO(['status' => 'draft']);

        $response = $this->actingAsOwner()->postJson("/api/purchase-orders/{$po->id}/cancel", [
            'cancellation_reason' => 'No longer needed',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Purchase order cancelled successfully');
        $this->assertEquals('cancelled', $po->fresh()->status);
    }

    public function test_cancel_requires_reason(): void
    {
        $po = $this->createDraftPO(['status' => 'draft']);

        $response = $this->actingAsOwner()->postJson("/api/purchase-orders/{$po->id}/cancel");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cancellation_reason']);
    }

    public function test_cannot_cancel_received_purchase_order(): void
    {
        $po = $this->createDraftPO(['status' => 'received']);

        $response = $this->actingAsOwner()->postJson("/api/purchase-orders/{$po->id}/cancel", [
            'cancellation_reason' => 'Try canceling',
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_cancel_completed_purchase_order(): void
    {
        $po = $this->createDraftPO(['status' => 'completed']);

        $response = $this->actingAsOwner()->postJson("/api/purchase-orders/{$po->id}/cancel", [
            'cancellation_reason' => 'Try canceling',
        ]);

        $response->assertStatus(422);
    }

    // ---- Delete ----

    public function test_owner_can_delete_draft_purchase_order(): void
    {
        $po = $this->createDraftPO(['status' => 'draft']);

        $response = $this->actingAsOwner()->deleteJson("/api/purchase-orders/{$po->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Purchase order deleted successfully');
        $this->assertSoftDeleted('purchase_orders', ['id' => $po->id]);
    }

    public function test_cannot_delete_approved_purchase_order(): void
    {
        $po = $this->createDraftPO(['status' => 'approved']);

        $response = $this->actingAsOwner()->deleteJson("/api/purchase-orders/{$po->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id]);
    }

    // ---- PDF Token ----

    public function test_owner_can_generate_pdf_token(): void
    {
        $po = $this->createDraftPO();

        $response = $this->actingAsOwner()->postJson("/api/purchase-orders/{$po->id}/pdf-token");

        $response->assertStatus(200);
        $response->assertJsonStructure(['token']);
        $this->assertNotEmpty($response->json('token'));
        $this->assertEquals(32, strlen($response->json('token')));
    }

    // ---- Record Payment ----

    public function test_owner_can_record_partial_payment(): void
    {
        $po = $this->createDraftPO([
            'status' => 'approved',
            'payment_method' => 'cash',
            'total_amount' => 1000.00,
            'amount_paid' => 0,
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAsOwner()->postJson("/api/purchase-orders/{$po->id}/record-payment", [
            'amount' => 500.00,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Payment recorded successfully');
        $this->assertEquals(500.00, $po->fresh()->amount_paid);
        $this->assertEquals('partially_paid', $po->fresh()->payment_status);
    }

    public function test_full_payment_marks_po_as_paid(): void
    {
        $po = $this->createDraftPO([
            'status' => 'approved',
            'payment_method' => 'cash',
            'total_amount' => 1000.00,
            'amount_paid' => 0,
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAsOwner()->postJson("/api/purchase-orders/{$po->id}/record-payment", [
            'amount' => 1000.00,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('paid', $po->fresh()->payment_status);
        $this->assertEquals(1000.00, $po->fresh()->amount_paid);
    }

    public function test_overpayment_rejected(): void
    {
        $po = $this->createDraftPO([
            'status' => 'approved',
            'total_amount' => 1000.00,
            'amount_paid' => 0,
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAsOwner()->postJson("/api/purchase-orders/{$po->id}/record-payment", [
            'amount' => 1500.00,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
    }

    public function test_cashier_cannot_record_payment(): void
    {
        $po = $this->createDraftPO();

        $response = $this->actingAsCashier()->postJson("/api/purchase-orders/{$po->id}/record-payment", [
            'amount' => 500.00,
        ]);

        $response->assertStatus(403);
    }
}