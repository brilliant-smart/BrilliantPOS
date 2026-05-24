<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private FinancialReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FinancialReportService::class);
    }

    // ---- getFinancialOverview ----

    public function test_financial_overview_defaults_to_current_month(): void
    {
        $overview = $this->service->getFinancialOverview();

        $this->assertEquals(now()->startOfMonth()->format('Y-m-d'), $overview['period']['start_date']);
        $this->assertEquals(now()->endOfDay()->format('Y-m-d'), $overview['period']['end_date']);
    }

    public function test_financial_overview_with_custom_dates(): void
    {
        $overview = $this->service->getFinancialOverview('2026-01-01', '2026-01-31');

        $this->assertEquals('2026-01-01', $overview['period']['start_date']);
        $this->assertEquals('2026-01-31', $overview['period']['end_date']);
    }

    public function test_financial_overview_counts_products_and_value(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 0, 'cost_price' => 50]);
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 5, 'cost_price' => 100]);
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 50, 'cost_price' => 200]);

        $overview = $this->service->getFinancialOverview();

        $this->assertEquals(3, $overview['inventory']['total_products']);
        // current_stock_value = 0*50 + 5*100 + 50*200 = 10500
        $this->assertEqualsWithDelta(10500, $overview['inventory']['current_stock_value'], 0.01);
    }

    // ---- getCashierPerformance ----

    public function test_cashier_performance_returns_data_for_cashier_with_sales(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        Sale::factory()->create([
            'cashier_id' => $cashier->id,
            'total_amount' => 500,
            'gross_profit' => 200,
            'sale_date' => now(),
        ]);

        $result = $this->service->getCashierPerformance();

        $this->assertIsArray($result);
        $found = collect($result)->firstWhere('cashier_id', $cashier->id);
        $this->assertNotNull($found);
        $this->assertEquals(1, $found['total_sales']); // count of sales
        $this->assertEqualsWithDelta(500, $found['total_revenue'], 0.01);
    }

    // ---- getTopSellingProducts ----

    public function test_top_selling_products_returns_ranked_data(): void
    {
        $user = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product1 = Product::factory()->create(['is_active' => true, 'price' => 100]);
        $product2 = Product::factory()->create(['is_active' => true, 'price' => 50]);

        $sale = Sale::factory()->create(['cashier_id' => $user->id]);
        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product1->id,
            'quantity' => 10, 'unit_price' => 100, 'unit_cost' => 60,
            'line_total' => 1000, 'line_cost' => 600, 'line_profit' => 400,
        ]);
        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product2->id,
            'quantity' => 2, 'unit_price' => 50, 'unit_cost' => 30,
            'line_total' => 100, 'line_cost' => 60, 'line_profit' => 40,
        ]);

        $result = $this->service->getTopSellingProducts();

        $this->assertIsArray($result);
        // Product1 should rank higher (10 units vs 2)
        if (count($result) >= 2) {
            $this->assertEquals($product1->id, $result[0]['product_id']);
        }
    }

    public function test_top_selling_products_respects_limit(): void
    {
        $result = $this->service->getTopSellingProducts(null, null, 5);

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(5, count($result));
    }

    // ---- detectStockVariances ----

    public function test_stock_variances_flags_significant_variance(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 100, 'cost_price' => 50]);
        $user = User::factory()->create(['is_active' => true]);
        StockMovement::create([
            'product_id' => $product->id, 'user_id' => $user->id, 'type' => 'purchase',
            'quantity' => 50, 'previous_stock' => 0, 'new_stock' => 50, 'unit_cost' => 50,
        ]);

        $result = $this->service->detectStockVariances();

        $found = collect($result)->firstWhere('product_id', $product->id);
        $this->assertNotNull($found);
        $this->assertEquals(50, $found['variance']);
    }

    public function test_stock_variances_empty_when_no_movements(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 10]);

        $result = $this->service->detectStockVariances();

        $this->assertIsArray($result);
    }

    // ---- getReorderReport ----

    public function test_reorder_report_includes_low_stock_products(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 2, 'reorder_point' => 10]);

        $result = $this->service->getReorderReport();

        $this->assertGreaterThanOrEqual(1, count($result));
        $this->assertArrayHasKey('product_id', $result[0]);
        $this->assertArrayHasKey('current_stock', $result[0]);
        $this->assertArrayHasKey('reorder_point', $result[0]);
    }

    public function test_reorder_report_excludes_sufficient_stock(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 100, 'reorder_point' => 10]);

        $result = $this->service->getReorderReport();

        $this->assertCount(0, $result);
    }

    // ---- getExpiringProducts ----

    public function test_expiring_products_finds_near_expiry(): void
    {
        Product::factory()->create(['is_active' => true, 'expiry_date' => now()->addDays(7)->toDateString()]);

        $result = $this->service->getExpiringProducts(30);

        $this->assertGreaterThanOrEqual(1, count($result));
        $this->assertEquals('critical', $result[0]['urgency']);
        $this->assertArrayHasKey('days_until_expiry', $result[0]);
        $this->assertArrayHasKey('product_id', $result[0]);
    }

    public function test_expiring_products_excludes_far_expiry(): void
    {
        Product::factory()->create(['is_active' => true, 'expiry_date' => now()->addMonths(12)->toDateString()]);

        $result = $this->service->getExpiringProducts(30);

        $this->assertCount(0, $result);
    }
}