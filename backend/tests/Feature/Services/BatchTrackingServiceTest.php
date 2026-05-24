<?php

namespace Tests\Feature\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
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
        $this->service = app(BatchTrackingService::class);
    }

    public function test_create_batch_creates_record(): void
    {
        $product = $this->createProduct();
        $supplier = Supplier::factory()->create();

        $batch = $this->service->createBatch([
            'product_id' => $product->id,
            'batch_number' => 'BTH-TEST-001',
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'cost_price' => 50.00,
            'selling_price' => 100.00,
            'expiry_date' => now()->addYear(),
            'supplier_id' => $supplier->id,
        ]);

        $this->assertDatabaseHas('product_batches', [
            'id' => $batch->id,
            'batch_number' => 'BTH-TEST-001',
            'quantity_remaining' => 100,
        ]);
    }

    public function test_allocate_stock_uses_fefo(): void
    {
        $product = $this->createProduct();

        // Batch1 expires sooner (30 days) — should be allocated first
        $batch1 = ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 5,
            'expiry_date' => now()->addDays(30),
        ]);
        // Batch2 expires later (60 days) — second priority
        $batch2 = ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 10,
            'expiry_date' => now()->addDays(60),
        ]);

        $allocations = $this->service->allocateStock($product, 8);

        // Batch1 gets 5 (fully allocated), batch2 gets 3
        $this->assertCount(2, $allocations);
        $this->assertEquals($batch1->id, $allocations[0]['batch_id']);
        $this->assertEquals(5, $allocations[0]['quantity']);
        $this->assertEquals($batch2->id, $allocations[1]['batch_id']);
        $this->assertEquals(3, $allocations[1]['quantity']);
    }

    public function test_allocate_stock_throws_when_insufficient(): void
    {
        $product = $this->createProduct();

        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 6,
            'expiry_date' => now()->addDays(30),
        ]);
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 4,
            'expiry_date' => now()->addDays(60),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient');

        $this->service->allocateStock($product, 25);
    }

    public function test_deduct_stock_reduces_batch_quantities(): void
    {
        $product = $this->createProduct();

        $batch1 = ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 5,
            'expiry_date' => now()->addDays(30),
        ]);
        $batch2 = ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 10,
            'expiry_date' => now()->addDays(60),
        ]);

        $allocations = $this->service->allocateStock($product, 8);
        $this->service->deductStock($allocations);

        $this->assertEquals(0, $batch1->fresh()->quantity_remaining);
        $this->assertEquals(7, $batch2->fresh()->quantity_remaining);
    }

    public function test_mark_batch_as_expired_writes_off_stock(): void
    {
        $user = \App\Models\User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->actingAs($user, 'sanctum');

        $product = $this->createProduct(['stock_quantity' => 10]);

        $batch = ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 5,
            'cost_price' => 80.00,
            'expiry_date' => now()->subDay(),
        ]);

        $this->service->markBatchAsExpired($batch);

        $batch->refresh();
        $this->assertEquals('expired', $batch->status);
        $this->assertEquals(0, $batch->quantity_remaining);

        $product->refresh();
        $this->assertEquals(5, $product->stock_quantity);

        // StockMovement type='damage' with negative quantity
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'damage',
            'quantity' => -5,
        ]);
    }

    public function test_get_expiring_batches_returns_correct_batches(): void
    {
        $product = $this->createProduct();

        // Expiring in 10 days — within 30-day window
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 10,
            'expiry_date' => now()->addDays(10),
        ]);
        // Expiring in 90 days — outside 30-day window
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 20,
            'expiry_date' => now()->addDays(90),
        ]);

        $results = $this->service->getExpiringBatches(30);

        $this->assertCount(1, $results);
    }

    public function test_get_expired_batches_returns_correct_batches(): void
    {
        $product = $this->createProduct();

        // Already expired
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 10,
            'expiry_date' => now()->subDays(5),
        ]);
        // Still valid
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 20,
            'expiry_date' => now()->addDays(30),
        ]);

        $results = $this->service->getExpiredBatches();

        $this->assertCount(1, $results);
    }

    public function test_get_batch_inventory_report_returns_active_only(): void
    {
        $product = $this->createProduct();

        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 50,
            'expiry_date' => now()->addDays(90),
        ]);
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 30,
            'expiry_date' => now()->addDays(180),
        ]);
        // Expired batch — should be excluded
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 0,
            'status' => 'expired',
            'expiry_date' => now()->subDays(10),
        ]);

        $results = $this->service->getBatchInventoryReport();

        $this->assertCount(2, $results);
    }
}