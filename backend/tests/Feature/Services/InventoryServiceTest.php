<?php

namespace Tests\Feature\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InventoryService::class);
    }

    public function test_add_stock_increases_quantity(): void
    {
        $product = $this->createProduct(['stock_quantity' => 10]);
        $user = User::factory()->create();

        $movement = $this->service->addStock($product, 5, 'purchase', null, null, $user);

        $this->assertEquals(15, $product->fresh()->stock_quantity);
        $this->assertEquals(5, $movement->quantity);
        $this->assertEquals('purchase', $movement->type);
        $this->assertEquals(10, $movement->previous_stock);
        $this->assertEquals(15, $movement->new_stock);
    }

    public function test_add_stock_with_unit_cost_records_it(): void
    {
        $product = $this->createProduct(['stock_quantity' => 10]);
        $user = User::factory()->create();

        $movement = $this->service->addStock($product, 10, 'purchase', null, 25.00, $user);

        $this->assertEquals(25.00, (float) $movement->unit_cost);
    }

    public function test_reduce_stock_decreases_quantity(): void
    {
        $product = $this->createProduct(['stock_quantity' => 20]);
        $user = User::factory()->create();

        $movement = $this->service->reduceStock($product, 5, 'sale', null, $user);

        $this->assertEquals(15, $product->fresh()->stock_quantity);
        $this->assertEquals(-5, $movement->quantity);
        $this->assertEquals('sale', $movement->type);
        $this->assertEquals(20, $movement->previous_stock);
        $this->assertEquals(15, $movement->new_stock);
    }

    public function test_reduce_stock_throws_on_insufficient_stock(): void
    {
        $product = $this->createProduct(['stock_quantity' => 3]);
        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->service->reduceStock($product, 5, 'sale', null, $user);
    }

    public function test_adjust_stock_sets_absolute_quantity(): void
    {
        $product = $this->createProduct(['stock_quantity' => 30]);
        $user = User::factory()->create();

        $movement = $this->service->adjustStock($product, 10, 'Manual count', $user);

        $this->assertEquals(10, $product->fresh()->stock_quantity);
        $this->assertEquals(-20, $movement->quantity);
        $this->assertEquals('adjustment', $movement->type);
        $this->assertEquals(30, $movement->previous_stock);
        $this->assertEquals(10, $movement->new_stock);
    }

    public function test_adjust_stock_with_higher_quantity_increases(): void
    {
        $product = $this->createProduct(['stock_quantity' => 10]);
        $user = User::factory()->create();

        $movement = $this->service->adjustStock($product, 25, null, $user);

        $this->assertEquals(25, $product->fresh()->stock_quantity);
        $this->assertEquals(15, $movement->quantity);
    }

    public function test_get_low_stock_products(): void
    {
        $lowProduct = $this->createProduct([
            'stock_quantity' => 3,
            'low_stock_threshold' => 10,
            'is_active' => true,
        ]);
        $okProduct = $this->createProduct([
            'stock_quantity' => 50,
            'low_stock_threshold' => 10,
            'is_active' => true,
        ]);
        $outProduct = $this->createProduct([
            'stock_quantity' => 0,
            'low_stock_threshold' => 10,
            'is_active' => true,
        ]);

        $results = $this->service->getLowStockProducts();

        $this->assertTrue($results->contains('id', $lowProduct->id));
        $this->assertFalse($results->contains('id', $okProduct->id));
        $this->assertFalse($results->contains('id', $outProduct->id));
    }

    public function test_get_out_of_stock_products(): void
    {
        $outProduct = $this->createProduct(['stock_quantity' => 0, 'is_active' => true]);
        $okProduct = $this->createProduct(['stock_quantity' => 10, 'is_active' => true]);

        $results = $this->service->getOutOfStockProducts();

        $this->assertTrue($results->contains('id', $outProduct->id));
        $this->assertFalse($results->contains('id', $okProduct->id));
    }

    public function test_get_inventory_summary(): void
    {
        $this->createProduct(['stock_quantity' => 50, 'price' => 100.00, 'cost_price' => 60.00, 'is_active' => true]);
        $this->createProduct(['stock_quantity' => 3, 'price' => 200.00, 'cost_price' => 120.00, 'low_stock_threshold' => 10, 'is_active' => true]);
        $this->createProduct(['stock_quantity' => 0, 'price' => 50.00, 'cost_price' => 30.00, 'is_active' => true]);

        $summary = $this->service->getInventorySummary();

        $this->assertEquals(3, $summary['total_products']);
        $this->assertEquals(2, $summary['in_stock']);
        $this->assertEquals(1, $summary['low_stock']);
        $this->assertEquals(1, $summary['out_of_stock']);
        // stock value = 50*100 + 3*200 + 0*50 = 5600
        $this->assertEquals(5600, (float) $summary['total_stock_value']);
    }
}