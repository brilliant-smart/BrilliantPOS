<?php

namespace Tests\Feature\Services;

use App\Models\Product;
use App\Models\ProductPriceHistory;
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PurchaseOrderService::class);
    }

    public function test_create_purchase_order_with_items(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = $this->createProduct();

        $po = $this->service->createPurchaseOrder([
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity_ordered' => 10,
                    'unit_cost' => 50.00,
                ],
            ],
        ], $user);

        $this->assertEquals('draft', $po->status);
        $this->assertEquals(500.00, (float) $po->subtotal);
        $this->assertEquals(1, $po->items->count());
        $this->assertMatchesRegularExpression('/^PO-\d{4}-\d{4}$/', $po->po_number);
    }

    public function test_approve_purchase_order(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = $this->createProduct();

        $po = $this->service->createPurchaseOrder([
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 50.00],
            ],
            'status' => 'pending',
        ], $user);

        $result = $this->service->approvePurchaseOrder($po, $user);

        $this->assertEquals('approved', $result->status);
        $this->assertEquals($user->id, $result->approved_by);
        $this->assertNotNull($result->approved_at);
    }

    public function test_approve_purchase_order_rejects_invalid_status(): void
    {
        $user = User::factory()->create();
        $po = PurchaseOrder::factory()->create(['status' => 'received']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot approve PO');

        $this->service->approvePurchaseOrder($po, $user);
    }

    public function test_receive_goods_updates_stock(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = $this->createProduct(['stock_quantity' => 0, 'cost_price' => 0, 'last_purchase_price' => 0]);

        $po = $this->service->createPurchaseOrder([
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 50.00],
            ],
            'status' => 'approved',
        ], $user);

        // Manually set to approved since factory overrides status
        $po->update(['status' => 'approved']);

        $result = $this->service->receiveGoods($po, [
            ['product_id' => $product->id, 'quantity_received' => 10],
        ], $user);

        $this->assertEquals('received', $result->status);
        $this->assertEquals(10, $product->fresh()->stock_quantity);
    }

    public function test_receive_goods_partial_status(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = $this->createProduct(['stock_quantity' => 0, 'cost_price' => 0, 'last_purchase_price' => 0]);

        $po = $this->service->createPurchaseOrder([
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 50.00],
            ],
        ], $user);

        $po->update(['status' => 'approved']);

        $result = $this->service->receiveGoods($po, [
            ['product_id' => $product->id, 'quantity_received' => 5],
        ], $user);

        $this->assertEquals('partially_received', $result->status);
        $this->assertEquals(5, $product->fresh()->stock_quantity);
    }

    public function test_receive_goods_rejects_over_receiving(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = $this->createProduct(['stock_quantity' => 0, 'cost_price' => 0, 'last_purchase_price' => 0]);

        $po = $this->service->createPurchaseOrder([
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 5, 'unit_cost' => 50.00],
            ],
        ], $user);

        $po->update(['status' => 'approved']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot receive more');

        $this->service->receiveGoods($po, [
            ['product_id' => $product->id, 'quantity_received' => 10],
        ], $user);
    }

    public function test_wac_calculation_accuracy(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        // Product with existing stock: 100 units at cost 50
        $product = $this->createProduct([
            'stock_quantity' => 100,
            'cost_price' => 50.00,
            'last_purchase_price' => 50.00,
        ]);

        // Create a Carton unit type (12 pieces per carton)
        $cartonType = $product->unitTypes()->create([
            'name' => 'Carton',
            'short_name' => 'ctn',
            'conversion_factor' => 12,
            'selling_price' => 1200.00,
            'is_base' => false,
            'sort_order' => 1,
        ]);

        $po = $this->service->createPurchaseOrder([
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity_ordered' => 5,
                    'unit_cost' => 600.00, // 600 per carton
                    'product_unit_type_id' => $cartonType->id,
                    'conversion_factor' => 12,
                    'unit_type' => 'Carton',
                ],
            ],
        ], $user);

        $po->update(['status' => 'approved']);

        $this->service->receiveGoods($po, [
            ['product_id' => $product->id, 'quantity_received' => 5],
        ], $user);

        $product->refresh();
        // 5 cartons * 12 = 60 base units added to 100 = 160 total
        $this->assertEquals(160, $product->stock_quantity);
        // costPerPiece = 600/12 = 50.00
        // WAC = (100*50 + 60*50) / 160 = 8000/160 = 50.00
        $this->assertEquals(50.00, (float) $product->cost_price);
        $this->assertEquals(50.00, (float) $product->last_purchase_price);
    }

    public function test_wac_with_different_costs(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        // Product: 100 units at cost 40
        $product = $this->createProduct([
            'stock_quantity' => 100,
            'cost_price' => 40.00,
            'last_purchase_price' => 40.00,
        ]);

        $po = $this->service->createPurchaseOrder([
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity_ordered' => 50,
                    'unit_cost' => 60.00,
                ],
            ],
        ], $user);

        $po->update(['status' => 'approved']);

        $this->service->receiveGoods($po, [
            ['product_id' => $product->id, 'quantity_received' => 50],
        ], $user);

        $product->refresh();
        // WAC = (100*40 + 50*60) / 150 = 7000/150 = 46.67
        $this->assertEquals(150, $product->stock_quantity);
        $this->assertEqualsWithDelta(46.67, (float) $product->cost_price, 0.01);
    }

    public function test_receive_goods_creates_price_history(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = $this->createProduct([
            'stock_quantity' => 10,
            'cost_price' => 40.00,
            'last_purchase_price' => 40.00,
        ]);

        $po = $this->service->createPurchaseOrder([
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 80.00],
            ],
        ], $user);

        $po->update(['status' => 'approved']);

        $this->service->receiveGoods($po, [
            ['product_id' => $product->id, 'quantity_received' => 10],
        ], $user);

        $this->assertDatabaseHas('product_price_history', [
            'product_id' => $product->id,
            'change_type' => 'purchase',
        ]);
    }

    public function test_record_payment(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = $this->createProduct();

        $po = $this->service->createPurchaseOrder([
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 50.00],
            ],
        ], $user);

        $this->service->recordPayment($po, 250.00, $user);

        $po->refresh();
        $this->assertEquals(250.00, (float) $po->amount_paid);
        $this->assertEquals('partially_paid', $po->payment_status);
    }

    public function test_record_payment_marks_as_paid(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = $this->createProduct();

        $po = $this->service->createPurchaseOrder([
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 50.00],
            ],
        ], $user);

        $this->service->recordPayment($po, 500.00, $user);

        $po->refresh();
        $this->assertEquals('paid', $po->payment_status);
    }
}