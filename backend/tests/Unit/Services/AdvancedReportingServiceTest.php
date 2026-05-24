<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AdvancedReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedReportingServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdvancedReportingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AdvancedReportingService::class);
    }

    // ---- inventoryAgingReport ----

    public function test_inventory_aging_categorizes_recent_stock(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 10, 'cost_price' => 50]);
        $user = User::factory()->create(['is_active' => true]);
        $movement = StockMovement::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'type' => 'purchase',
            'quantity' => 10,
            'previous_stock' => 0,
            'new_stock' => 10,
            'unit_cost' => 50,
        ]);
        $movement->created_at = now()->subDays(5);
        $movement->saveQuietly();

        $result = $this->service->inventoryAgingReport();

        $found = $result['products']->firstWhere('product.id', $product->id);
        $this->assertNotNull($found);
        $this->assertEquals('0-30 days', $found['aging_category']);
        $this->assertEqualsWithDelta(500.00, $found['stock_value'], 0.01);
    }

    public function test_inventory_aging_categorizes_old_stock(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 20, 'cost_price' => 30]);
        $user = User::factory()->create(['is_active' => true]);
        $movement = StockMovement::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'type' => 'purchase',
            'quantity' => 20,
            'previous_stock' => 0,
            'new_stock' => 20,
            'unit_cost' => 30,
        ]);
        $movement->created_at = now()->subDays(120);
        $movement->saveQuietly();

        $result = $this->service->inventoryAgingReport();

        $found = $result['products']->firstWhere('product.id', $product->id);
        $this->assertNotNull($found);
        $this->assertEquals('91-180 days', $found['aging_category']);
    }

    public function test_inventory_aging_summary_counts_categories(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $recent = Product::factory()->create(['is_active' => true, 'stock_quantity' => 10, 'cost_price' => 50]);
        $recentMovement = StockMovement::create([
            'product_id' => $recent->id, 'user_id' => $user->id, 'type' => 'purchase',
            'quantity' => 10, 'previous_stock' => 0, 'new_stock' => 10, 'unit_cost' => 50,
        ]);
        $recentMovement->created_at = now()->subDays(5);
        $recentMovement->saveQuietly();

        $old = Product::factory()->create(['is_active' => true, 'stock_quantity' => 20, 'cost_price' => 30]);
        $oldMovement = StockMovement::create([
            'product_id' => $old->id, 'user_id' => $user->id, 'type' => 'purchase',
            'quantity' => 20, 'previous_stock' => 0, 'new_stock' => 20, 'unit_cost' => 30,
        ]);
        $oldMovement->created_at = now()->subDays(120);
        $oldMovement->saveQuietly();

        $result = $this->service->inventoryAgingReport();

        $this->assertCount(2, $result['summary']);
    }

    // ---- deadStockReport ----

    public function test_dead_stock_identifies_unsold_products(): void
    {
        // Product with stock but no sales = dead stock
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 15, 'cost_price' => 40]);

        $result = $this->service->deadStockReport(90);

        $this->assertEquals(1, $result['total_products']);
        $this->assertEqualsWithDelta(600.00, $result['total_stock_value'], 0.01);
        $this->assertEquals(90, $result['days_threshold']);
    }

    public function test_dead_stock_excludes_recently_sold_products(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 15, 'cost_price' => 40]);
        $user = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $sale = Sale::factory()->create(['cashier_id' => $user->id]);
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50,
            'unit_cost' => 40,
            'line_total' => 50,
            'line_cost' => 40,
            'line_profit' => 10,
        ]);

        $result = $this->service->deadStockReport(90);

        $this->assertEquals(0, $result['total_products']);
    }

    public function test_dead_stock_respects_custom_days_threshold(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 10, 'cost_price' => 20]);

        $result = $this->service->deadStockReport(30);

        $this->assertEquals(30, $result['days_threshold']);
    }

    // ---- stockoutReport ----

    public function test_stockout_report_includes_out_of_stock_products(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 0, 'price' => 100]);

        $result = $this->service->stockoutReport();

        $this->assertEquals(1, $result['total_products']);
        $this->assertCount(1, $result['stockouts']);
    }

    public function test_stockout_report_excludes_in_stock_products(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 50, 'price' => 100, 'low_stock_threshold' => 5]);

        $result = $this->service->stockoutReport();

        $this->assertEquals(0, $result['total_products']);
    }

    // ---- salesForecast ----

    public function test_sales_forecast_with_sales_data(): void
    {
        $user = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        Sale::factory()->create([
            'cashier_id' => $user->id,
            'total_amount' => 500,
            'sale_date' => now()->subDays(2),
        ]);
        Sale::factory()->create([
            'cashier_id' => $user->id,
            'total_amount' => 300,
            'sale_date' => now()->subDays(1),
        ]);

        $result = $this->service->salesForecast();

        $this->assertEquals(30, $result['forecast_period_days']);
        $this->assertArrayHasKey('forecast', $result);
        $this->assertCount(30, $result['forecast']);
        $this->assertGreaterThan(0, $result['total_forecast']);
    }

    public function test_sales_forecast_custom_period(): void
    {
        $result = $this->service->salesForecast(null, 14);

        $this->assertEquals(14, $result['forecast_period_days']);
        $this->assertCount(14, $result['forecast']);
    }

    public function test_sales_forecast_empty_data_returns_zero_average(): void
    {
        $result = $this->service->salesForecast();

        $this->assertEquals(0, $result['average_daily_sales']);
    }

    // ---- supplierPerformanceAnalytics ----

    public function test_supplier_performance_with_purchase_orders(): void
    {
        $supplier = Supplier::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['is_active' => true]);
        \App\Models\PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'created_by' => $user->id,
            'status' => 'completed',
            'total_amount' => 1000,
        ]);

        $result = $this->service->supplierPerformanceAnalytics();

        $found = collect($result)->first(fn($item) => $item['supplier']->id === $supplier->id);
        $this->assertNotNull($found);
        $this->assertEquals(1, $found['total_orders']);
        $this->assertEquals(1, $found['completed_orders']);
    }

    // ---- abcAnalysis ----

    public function test_abc_analysis_classifies_products(): void
    {
        $user = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $productA = Product::factory()->create(['is_active' => true, 'price' => 100]);
        $sale = Sale::factory()->create(['cashier_id' => $user->id]);
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productA->id,
            'quantity' => 10,
            'unit_price' => 100,
            'unit_cost' => 60,
            'line_total' => 1000,
            'line_cost' => 600,
            'line_profit' => 400,
        ]);

        $result = $this->service->abcAnalysis();

        $this->assertArrayHasKey('products', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('total_revenue', $result);
        $this->assertEqualsWithDelta(1000, $result['total_revenue'], 0.01);
    }
}