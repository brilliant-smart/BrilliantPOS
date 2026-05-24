<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InventoryService();
        $this->user = User::factory()->create(['is_active' => true]);
    }

    // ---- addStock ----

    public function test_add_stock_increases_quantity(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 10]);

        $movement = $this->service->addStock($product, 25, 'purchase', 'Received shipment', 50.00, $this->user);

        $this->assertEquals(35, $product->fresh()->stock_quantity);
        $this->assertEquals('purchase', $movement->type);
        $this->assertEquals(25, $movement->quantity);
        $this->assertEquals(10, $movement->previous_stock);
        $this->assertEquals(35, $movement->new_stock);
        $this->assertDatabaseHas('stock_movements', [
            'id' => $movement->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 25,
        ]);
    }

    public function test_add_stock_defaults_to_purchase_type(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 0]);

        $movement = $this->service->addStock($product, 10, type: 'return', user: $this->user);

        $this->assertEquals('return', $movement->type);
    }

    public function test_add_stock_records_unit_cost(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 5]);

        $movement = $this->service->addStock($product, 10, 'purchase', null, 45.50, $this->user);

        $this->assertEqualsWithDelta(45.50, (float) $movement->unit_cost, 0.01);
    }

    // ---- reduceStock ----

    public function test_reduce_stock_decreases_quantity(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 50]);

        $movement = $this->service->reduceStock($product, 20, 'sale', 'POS sale', $this->user);

        $this->assertEquals(30, $product->fresh()->stock_quantity);
        $this->assertEquals(-20, $movement->quantity);
        $this->assertEquals(50, $movement->previous_stock);
        $this->assertEquals(30, $movement->new_stock);
    }

    public function test_reduce_stock_insufficient_throws_exception(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 5]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->service->reduceStock($product, 10, 'sale', null, $this->user);
    }

    public function test_reduce_stock_exact_quantity_succeeds(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 10]);

        $movement = $this->service->reduceStock($product, 10, 'sale', null, $this->user);

        $this->assertEquals(0, $product->fresh()->stock_quantity);
        $this->assertEquals(-10, $movement->quantity);
    }

    // ---- adjustStock ----

    public function test_adjust_stock_sets_absolute_quantity(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 50]);

        $movement = $this->service->adjustStock($product, 30, 'Cycle count adjustment', $this->user);

        $this->assertEquals(30, $product->fresh()->stock_quantity);
        $this->assertEquals('adjustment', $movement->type);
        $this->assertEquals(-20, $movement->quantity); // difference = 30 - 50 = -20
        $this->assertEquals(50, $movement->previous_stock);
        $this->assertEquals(30, $movement->new_stock);
    }

    public function test_adjust_stock_increase(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 10]);

        $movement = $this->service->adjustStock($product, 25, null, $this->user);

        $this->assertEquals(25, $product->fresh()->stock_quantity);
        $this->assertEquals(15, $movement->quantity); // difference = 25 - 10 = 15
    }

    public function test_adjust_stock_to_zero(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 20]);

        $movement = $this->service->adjustStock($product, 0, 'Written off', $this->user);

        $this->assertEquals(0, $product->fresh()->stock_quantity);
        $this->assertEquals(-20, $movement->quantity);
    }

    // ---- getStockHistory ----

    public function test_get_stock_history_returns_movements(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 50]);

        $this->service->addStock($product, 10, 'purchase', null, null, $this->user);
        $this->service->reduceStock($product, 5, 'sale', null, $this->user);

        $history = $this->service->getStockHistory($product);

        $this->assertCount(2, $history);
        // Default ordering is desc — most recent first
        $this->assertTrue($history->first()->relationLoaded('user'));
    }

    public function test_get_stock_history_respects_limit(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 100]);

        for ($i = 0; $i < 5; $i++) {
            $this->service->addStock($product, 1, 'purchase', null, null, $this->user);
        }

        $history = $this->service->getStockHistory($product, 3);

        $this->assertCount(3, $history);
    }

    // ---- getLowStockProducts ----

    public function test_get_low_stock_products_returns_below_threshold(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 3, 'low_stock_threshold' => 10]);
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 50, 'low_stock_threshold' => 10]);
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 0, 'low_stock_threshold' => 10]);

        $lowStock = $this->service->getLowStockProducts();

        // Only the product with stock > 0 and <= threshold
        $this->assertCount(1, $lowStock);
        $this->assertEquals(3, $lowStock->first()->stock_quantity);
    }

    public function test_get_low_stock_products_empty_when_none_low(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 100, 'low_stock_threshold' => 10]);

        $this->assertCount(0, $this->service->getLowStockProducts());
    }

    // ---- getOutOfStockProducts ----

    public function test_get_out_of_stock_products_returns_zero_stock(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 0]);
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 50]);

        $outOfStock = $this->service->getOutOfStockProducts();

        $this->assertCount(1, $outOfStock);
        $this->assertEquals(0, $outOfStock->first()->stock_quantity);
    }

    public function test_get_out_of_stock_products_includes_negative_stock(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => -5]);

        $outOfStock = $this->service->getOutOfStockProducts();

        $this->assertCount(1, $outOfStock);
    }

    // ---- getInventorySummary ----

    public function test_get_inventory_summary_returns_all_keys(): void
    {
        $summary = $this->service->getInventorySummary();

        $this->assertArrayHasKey('total_products', $summary);
        $this->assertArrayHasKey('in_stock', $summary);
        $this->assertArrayHasKey('low_stock', $summary);
        $this->assertArrayHasKey('out_of_stock', $summary);
        $this->assertArrayHasKey('total_stock_value', $summary);
    }

    public function test_get_inventory_summary_counts_correctly(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 50, 'price' => 100, 'low_stock_threshold' => 10]);
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 3, 'price' => 50, 'low_stock_threshold' => 10]);
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 0, 'price' => 20, 'low_stock_threshold' => 10]);

        $summary = $this->service->getInventorySummary();

        $this->assertEquals(3, $summary['total_products']);
        $this->assertEquals(2, $summary['in_stock']); // 50 and 3 are > 0
        $this->assertEquals(1, $summary['low_stock']); // 3 is > 0 and <= 10
        $this->assertEquals(1, $summary['out_of_stock']); // 0 is <= 0
        $this->assertEqualsWithDelta(5150.00, (float) $summary['total_stock_value'], 0.01); // 50*100 + 3*50 + 0*20
    }

    public function test_get_inventory_summary_empty_database(): void
    {
        $summary = $this->service->getInventorySummary();

        $this->assertEquals(0, $summary['total_products']);
        $this->assertEquals(0, $summary['in_stock']);
        $this->assertEquals(0, $summary['out_of_stock']);
        $this->assertEquals(0, $summary['total_stock_value']);
    }
}