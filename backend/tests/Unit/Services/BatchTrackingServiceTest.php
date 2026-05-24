<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BatchTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchTrackingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BatchTrackingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BatchTrackingService();
    }

    // ---- createBatch ----

    public function test_create_batch_stores_data_correctly(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $supplier = Supplier::factory()->create(['is_active' => true]);

        $batch = $this->service->createBatch([
            'product_id' => $product->id,
            'batch_number' => 'BTH-001',
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'cost_price' => 50.00,
            'selling_price' => 75.00,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'supplier_id' => $supplier->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('product_batches', [
            'id' => $batch->id,
            'batch_number' => 'BTH-001',
            'quantity_remaining' => 100,
            'cost_price' => 50.00,
        ]);
    }

    public function test_create_batch_returns_product_batch_instance(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $batch = $this->service->createBatch([
            'product_id' => $product->id,
            'batch_number' => 'BTH-NEW',
            'quantity_received' => 50,
            'quantity_remaining' => 50,
            'cost_price' => 10.00,
            'selling_price' => 20.00,
            'status' => 'active',
        ]);

        $this->assertInstanceOf(ProductBatch::class, $batch);
    }

    // ---- allocateStock (FEFO) ----

    public function test_allocate_stock_from_single_batch(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $batch = ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 100,
            'expiry_date' => now()->addMonths(6),
            'status' => 'active',
        ]);

        $allocations = $this->service->allocateStock($product, 30);

        $this->assertCount(1, $allocations);
        $this->assertEquals($batch->id, $allocations[0]['batch_id']);
        $this->assertEquals(30, $allocations[0]['quantity']);
    }

    public function test_allocate_stock_across_multiple_batches_fefo(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $earlyBatch = ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 20,
            'expiry_date' => now()->addMonths(3),
            'cost_price' => 10.00,
            'status' => 'active',
        ]);
        $laterBatch = ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 50,
            'expiry_date' => now()->addMonths(12),
            'cost_price' => 15.00,
            'status' => 'active',
        ]);

        $allocations = $this->service->allocateStock($product, 50);

        $this->assertCount(2, $allocations);
        // First batch (expiring sooner) should be allocated first
        $this->assertEquals($earlyBatch->id, $allocations[0]['batch_id']);
        $this->assertEquals(20, $allocations[0]['quantity']);
        $this->assertEquals($laterBatch->id, $allocations[1]['batch_id']);
        $this->assertEquals(30, $allocations[1]['quantity']);
    }

    public function test_allocate_stock_insufficient_throws_exception(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 10,
            'expiry_date' => now()->addMonths(6),
            'status' => 'active',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock in batches');

        $this->service->allocateStock($product, 50);
    }

    public function test_allocate_stock_with_no_batches_throws_exception(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock in batches');

        $this->service->allocateStock($product, 1);
    }

    // ---- deductStock ----

    public function test_deduct_stock_reduces_quantity_remaining(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $batch = ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 100,
            'expiry_date' => now()->addMonths(6),
            'status' => 'active',
        ]);

        $allocations = $this->service->allocateStock($product, 30);
        $this->service->deductStock($allocations);

        $this->assertEquals(70, $batch->fresh()->quantity_remaining);
    }

    public function test_deduct_stock_across_multiple_batches(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $batch1 = ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 20,
            'expiry_date' => now()->addMonths(3),
            'status' => 'active',
        ]);
        $batch2 = ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 50,
            'expiry_date' => now()->addMonths(12),
            'status' => 'active',
        ]);

        $allocations = $this->service->allocateStock($product, 50);
        $this->service->deductStock($allocations);

        $this->assertEquals(0, $batch1->fresh()->quantity_remaining);
        $this->assertEquals(20, $batch2->fresh()->quantity_remaining);
    }

    // ---- getExpiringBatches ----

    public function test_get_expiring_batches_returns_within_window(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $supplier = Supplier::factory()->create(['is_active' => true]);
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 10,
            'expiry_date' => now()->addDays(15),
            'status' => 'active',
            'supplier_id' => $supplier->id,
        ]);
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 20,
            'expiry_date' => now()->addMonths(12),
            'status' => 'active',
            'supplier_id' => $supplier->id,
        ]);

        $result = $this->service->getExpiringBatches(30);

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]->relationLoaded('product'));
        $this->assertTrue($result[0]->relationLoaded('supplier'));
    }

    public function test_get_expiring_batches_custom_days_window(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 10,
            'expiry_date' => now()->addDays(45),
            'status' => 'active',
        ]);

        // Should not appear in 30-day window
        $this->assertCount(0, $this->service->getExpiringBatches(30));
        // Should appear in 60-day window
        $this->assertCount(1, $this->service->getExpiringBatches(60));
    }

    // ---- getExpiredBatches ----

    public function test_get_expired_batches_returns_past_expiry(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $supplier = Supplier::factory()->create(['is_active' => true]);
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 10,
            'expiry_date' => now()->subDays(5),
            'status' => 'active',
            'supplier_id' => $supplier->id,
        ]);
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 20,
            'expiry_date' => now()->addMonths(6),
            'status' => 'active',
            'supplier_id' => $supplier->id,
        ]);

        $result = $this->service->getExpiredBatches();

        $this->assertCount(1, $result);
    }

    // ---- markBatchAsExpired ----

    public function test_mark_batch_as_expired_updates_status_and_stock(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user, 'sanctum');

        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 100]);
        $batch = ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 30,
            'cost_price' => 50.00,
            'expiry_date' => now()->subDays(1),
            'status' => 'active',
        ]);

        $this->service->markBatchAsExpired($batch);

        $this->assertEquals('expired', $batch->fresh()->status);
        $this->assertEquals(0, $batch->fresh()->quantity_remaining);
        $this->assertEquals(70, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'damage',
            'quantity' => -30,
        ]);
    }

    // ---- getBatchInventoryReport ----

    public function test_batch_inventory_report_returns_active_batches_with_stock(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $supplier = Supplier::factory()->create(['is_active' => true]);
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 50,
            'expiry_date' => now()->addMonths(6),
            'status' => 'active',
            'supplier_id' => $supplier->id,
        ]);
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 0,
            'expiry_date' => now()->addMonths(3),
            'status' => 'active',
            'supplier_id' => $supplier->id,
        ]);
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 20,
            'expiry_date' => now()->subDays(1),
            'status' => 'expired',
            'supplier_id' => $supplier->id,
        ]);

        $report = $this->service->getBatchInventoryReport();

        // Only active batches with remaining stock
        $this->assertCount(1, $report);
        $this->assertEquals(50, $report[0]->quantity_remaining);
        $this->assertTrue($report[0]->relationLoaded('product'));
        $this->assertTrue($report[0]->relationLoaded('supplier'));
    }
}