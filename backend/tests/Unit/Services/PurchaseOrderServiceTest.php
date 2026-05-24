<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderService $service;
    private User $user;
    private Supplier $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PurchaseOrderService();
        $this->user = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->supplier = Supplier::factory()->create(['is_active' => true]);
        $this->product = Product::factory()->create([
            'is_active' => true,
            'stock_quantity' => 10,
            'cost_price' => 50.00,
            'price' => 100.00,
            'last_purchase_price' => 50.00,
        ]);
    }

    private function poData(array $overrides = []): array
    {
        return array_merge([
            'supplier_id' => $this->supplier->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity_ordered' => 20,
                    'unit_cost' => 50.00,
                    'discount_percent' => 0,
                ],
            ],
            'notes' => 'Test PO',
        ], $overrides);
    }

    // ---- createPurchaseOrder ----

    public function test_create_purchase_order_stores_po_and_items(): void
    {
        $po = $this->service->createPurchaseOrder($this->poData(), $this->user);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'draft',
            'payment_status' => 'unpaid',
        ]);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity_ordered' => 20,
        ]);
    }

    public function test_create_purchase_order_calculates_totals(): void
    {
        $data = $this->poData([
            'shipping_cost' => 100,
            'discount_amount' => 50,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity_ordered' => 10,
                    'unit_cost' => 50.00,
                    'discount_percent' => 0,
                ],
            ],
        ]);

        $po = $this->service->createPurchaseOrder($data, $this->user);

        // subtotal = 10 * 50 = 500, total = 500 + 100 - 50 = 550
        $this->assertEqualsWithDelta(500.00, (float) $po->subtotal, 0.01);
        $this->assertEqualsWithDelta(550.00, (float) $po->total_amount, 0.01);
    }

    public function test_create_purchase_order_with_batch_data_creates_batch(): void
    {
        $data = $this->poData([
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity_ordered' => 10,
                    'unit_cost' => 50.00,
                    'discount_percent' => 0,
                    'batch_number' => 'BTH-X001',
                    'expiry_date' => now()->addMonths(6)->toDateString(),
                ],
            ],
        ]);

        $po = $this->service->createPurchaseOrder($data, $this->user);

        $this->assertDatabaseHas('product_batches', [
            'purchase_order_id' => $po->id,
            'batch_number' => 'BTH-X001',
            'quantity_received' => 0,
            'quantity_remaining' => 0,
        ]);
    }

    public function test_create_purchase_order_without_batch_data_skips_batch(): void
    {
        $this->service->createPurchaseOrder($this->poData(), $this->user);

        $this->assertDatabaseCount('product_batches', 0);
    }

    public function test_create_purchase_order_with_line_item_discount(): void
    {
        $data = $this->poData([
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity_ordered' => 10,
                    'unit_cost' => 100.00,
                    'discount_percent' => 10,
                ],
            ],
        ]);

        $po = $this->service->createPurchaseOrder($data, $this->user);

        // line_total = 10 * 100 = 1000, minus 10% = 900
        $this->assertEqualsWithDelta(900.00, (float) $po->subtotal, 0.01);
    }

    // ---- approvePurchaseOrder ----

    public function test_approve_draft_purchase_order(): void
    {
        $po = $this->service->createPurchaseOrder($this->poData(), $this->user);

        $approved = $this->service->approvePurchaseOrder($po, $this->user);

        $this->assertEquals('approved', $approved->status);
        $this->assertEquals($this->user->id, $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
    }

    public function test_approve_pending_purchase_order(): void
    {
        $po = $this->service->createPurchaseOrder($this->poData(['status' => 'pending']), $this->user);

        $approved = $this->service->approvePurchaseOrder($po, $this->user);

        $this->assertEquals('approved', $approved->status);
    }

    public function test_approve_non_draft_non_pending_throws_exception(): void
    {
        $po = $this->service->createPurchaseOrder($this->poData(), $this->user);
        $this->service->approvePurchaseOrder($po, $this->user);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot approve PO with status');

        $this->service->approvePurchaseOrder($po->fresh(), $this->user);
    }

    // ---- receiveGoods ----

    public function test_receive_goods_updates_stock_and_status(): void
    {
        $po = $this->service->createPurchaseOrder($this->poData(), $this->user);
        $this->service->approvePurchaseOrder($po, $this->user);

        $received = $this->service->receiveGoods($po->fresh(), [
            ['product_id' => $this->product->id, 'quantity_received' => 20],
        ], $this->user);

        $this->assertEquals('received', $received->status);
        $this->assertEquals(30, $this->product->fresh()->stock_quantity); // 10 + 20
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'type' => 'purchase',
            'quantity' => 20,
        ]);
    }

    public function test_receive_goods_partial_sets_partially_received_status(): void
    {
        $po = $this->service->createPurchaseOrder($this->poData(), $this->user);
        $this->service->approvePurchaseOrder($po, $this->user);

        $received = $this->service->receiveGoods($po->fresh(), [
            ['product_id' => $this->product->id, 'quantity_received' => 10],
        ], $this->user);

        $this->assertEquals('partially_received', $received->status);
    }

    public function test_receive_goods_exceeds_ordered_quantity_throws_exception(): void
    {
        $po = $this->service->createPurchaseOrder($this->poData(), $this->user);
        $this->service->approvePurchaseOrder($po, $this->user);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot receive more than ordered quantity');

        $this->service->receiveGoods($po->fresh(), [
            ['product_id' => $this->product->id, 'quantity_received' => 50],
        ], $this->user);
    }

    public function test_receive_goods_from_invalid_status_throws_exception(): void
    {
        $po = $this->service->createPurchaseOrder($this->poData(), $this->user);
        // Still in 'draft' status, not approved

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot receive goods for PO with status');

        $this->service->receiveGoods($po, [
            ['product_id' => $this->product->id, 'quantity_received' => 10],
        ], $this->user);
    }

    public function test_receive_goods_updates_weighted_average_cost(): void
    {
        $po = $this->service->createPurchaseOrder($this->poData([
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity_ordered' => 20,
                    'unit_cost' => 80.00,
                    'discount_percent' => 0,
                ],
            ],
        ]), $this->user);
        $this->service->approvePurchaseOrder($po, $this->user);

        $this->service->receiveGoods($po->fresh(), [
            ['product_id' => $this->product->id, 'quantity_received' => 20],
        ], $this->user);

        // original: 10 units @ 50, received: 20 units @ 80 (per-piece = 80)
        // weighted avg = (10*50 + 20*80) / 30 = (500+1600)/30 = 70
        $this->assertEqualsWithDelta(70.00, (float) $this->product->fresh()->cost_price, 0.01);
    }

    // ---- recordPayment ----

    public function test_record_partial_payment(): void
    {
        $po = $this->service->createPurchaseOrder($this->poData(), $this->user);

        $updated = $this->service->recordPayment($po, 200.00, $this->user);

        $this->assertEqualsWithDelta(200.00, (float) $updated->amount_paid, 0.01);
        $this->assertEquals('partially_paid', $updated->payment_status);
    }

    public function test_record_full_payment(): void
    {
        $po = $this->service->createPurchaseOrder($this->poData(), $this->user);
        $totalAmount = (float) $po->total_amount;

        $updated = $this->service->recordPayment($po, $totalAmount, $this->user);

        $this->assertEquals('paid', $updated->payment_status);
    }

    public function test_record_payment_exceeds_total_throws_exception(): void
    {
        $po = $this->service->createPurchaseOrder($this->poData(), $this->user);
        $totalAmount = (float) $po->total_amount;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment amount exceeds total due');

        $this->service->recordPayment($po, $totalAmount + 1, $this->user);
    }

    public function test_record_zero_payment_throws_exception(): void
    {
        $po = $this->service->createPurchaseOrder($this->poData(), $this->user);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment amount must be greater than zero');

        $this->service->recordPayment($po, 0, $this->user);
    }

    public function test_record_negative_payment_throws_exception(): void
    {
        $po = $this->service->createPurchaseOrder($this->poData(), $this->user);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment amount must be greater than zero');

        $this->service->recordPayment($po, -50, $this->user);
    }
}