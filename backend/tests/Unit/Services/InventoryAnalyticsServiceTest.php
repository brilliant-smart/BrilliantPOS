<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InventoryAnalyticsService::class);
    }

    // ---- getDashboardStats ----

    public function test_dashboard_overview_counts_active_products(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 10]);
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 20]);
        Product::factory()->create(['is_active' => false, 'stock_quantity' => 30]);

        $stats = $this->service->getDashboardStats();

        $this->assertEquals(2, $stats['overview']['total_products']);
    }

    public function test_dashboard_overview_includes_stock_value(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 10, 'price' => 100]);

        $stats = $this->service->getDashboardStats();

        $this->assertArrayHasKey('total_stock_value', $stats['overview']);
        $this->assertEqualsWithDelta(1000, $stats['overview']['total_stock_value'], 0.01);
    }

    public function test_dashboard_stock_status_categorizes_products(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 50, 'low_stock_threshold' => 10]);
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 3, 'low_stock_threshold' => 10]);
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 0]);

        $stats = $this->service->getDashboardStats();

        $this->assertEquals(1, $stats['stock_status']['in_stock']);
        $this->assertEquals(1, $stats['stock_status']['low_stock']);
        $this->assertEquals(1, $stats['stock_status']['out_of_stock']);
    }

    public function test_dashboard_alerts_includes_low_stock(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 2, 'low_stock_threshold' => 5]);

        $stats = $this->service->getDashboardStats();

        $this->assertGreaterThanOrEqual(1, count($stats['alerts']['low_stock_items']));
    }

    // ---- getMovementReport ----

    public function test_movement_report_returns_empty_with_no_movements(): void
    {
        $report = $this->service->getMovementReport();

        $this->assertCount(0, $report['movements']);
        $this->assertEquals(0, $report['total_count']);
    }

    public function test_movement_report_returns_movements_with_details(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['is_active' => true]);
        StockMovement::create([
            'product_id' => $product->id, 'user_id' => $user->id, 'type' => 'purchase',
            'quantity' => 50, 'previous_stock' => 0, 'new_stock' => 50, 'unit_cost' => 10.00,
        ]);

        $report = $this->service->getMovementReport();

        $this->assertCount(1, $report['movements']);
        $movement = $report['movements'][0];
        $this->assertEquals('purchase', $movement['type']);
        $this->assertEquals($product->id, $movement['product_id']);
        $this->assertEquals(50, $movement['quantity']);
    }

    public function test_movement_report_filters_by_type(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['is_active' => true]);
        StockMovement::create([
            'product_id' => $product->id, 'user_id' => $user->id, 'type' => 'purchase',
            'quantity' => 50, 'previous_stock' => 0, 'new_stock' => 50, 'unit_cost' => 10,
        ]);
        StockMovement::create([
            'product_id' => $product->id, 'user_id' => $user->id, 'type' => 'sale',
            'quantity' => -10, 'previous_stock' => 50, 'new_stock' => 40, 'unit_cost' => 10,
        ]);

        $report = $this->service->getMovementReport(['type' => 'sale']);

        $this->assertCount(1, $report['movements']);
        $this->assertEquals('sale', $report['movements'][0]['type']);
    }

    // ---- getTurnoverRate ----

    public function test_turnover_rate_includes_product_data(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 100]);
        $user = User::factory()->create(['is_active' => true]);
        StockMovement::create([
            'product_id' => $product->id, 'user_id' => $user->id, 'type' => 'sale',
            'quantity' => -30, 'previous_stock' => 100, 'new_stock' => 70, 'unit_cost' => 50,
        ]);

        $result = $this->service->getTurnoverRate(30);

        $this->assertEquals(30, $result['period_days']);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result['products']);
        $this->assertArrayHasKey('average_turnover', $result);
        // Product should appear in turnover with sold units
        $productData = $result['products']->firstWhere('product_id', $product->id);
        $this->assertNotNull($productData);
        $this->assertEquals(30, $productData['units_sold']);
    }

    public function test_turnover_rate_empty_when_no_products(): void
    {
        $result = $this->service->getTurnoverRate(30);

        $this->assertEquals(30, $result['period_days']);
        $this->assertCount(0, $result['products']);
        $this->assertEquals(0, $result['average_turnover']);
    }
}