<?php

namespace Tests\Feature\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- financialOverview ----

    public function test_owner_can_get_financial_overview(): void
    {
        $this->actingAsOwner()->getJson('/api/reports/financial-overview')
            ->assertStatus(200);
    }

    public function test_manager_can_get_financial_overview(): void
    {
        $this->actingAsManager()->getJson('/api/reports/financial-overview')
            ->assertStatus(200);
    }

    public function test_cashier_cannot_get_financial_overview(): void
    {
        $this->actingAsCashier()->getJson('/api/reports/financial-overview')
            ->assertStatus(403);
    }

    public function test_financial_overview_with_date_range(): void
    {
        $this->actingAsOwner()->getJson('/api/reports/financial-overview?start_date=2026-01-01&end_date=2026-12-31')
            ->assertStatus(200);
    }

    public function test_financial_overview_includes_inventory_data(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 10, 'cost_price' => 100]);

        $response = $this->actingAsOwner()->getJson('/api/reports/financial-overview');

        $response->assertStatus(200);
        $response->assertJsonStructure(['period', 'inventory']);
        $this->assertEquals(1, $response->json('inventory.total_products'));
        $this->assertEqualsWithDelta(1000, $response->json('inventory.current_stock_value'), 0.01);
    }

    public function test_financial_overview_invalid_date_range_returns_422(): void
    {
        $this->actingAsOwner()->getJson('/api/reports/financial-overview?start_date=2026-12-31&end_date=2026-01-01')
            ->assertStatus(422);
    }

    // ---- cashierPerformance ----

    public function test_owner_can_get_cashier_performance(): void
    {
        $this->actingAsOwner()->getJson('/api/reports/cashier-performance')
            ->assertStatus(200);
    }

    public function test_cashier_performance_with_dates(): void
    {
        $this->actingAsOwner()->getJson('/api/reports/cashier-performance?start_date=2026-01-01&end_date=2026-12-31')
            ->assertStatus(200);
    }

    public function test_cashier_performance_returns_data_for_cashier_with_sales(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        Sale::factory()->create([
            'cashier_id' => $cashier->id,
            'total_amount' => 500,
            'gross_profit' => 200,
            'sale_date' => now(),
        ]);

        $response = $this->actingAsOwner()->getJson('/api/reports/cashier-performance');

        $response->assertStatus(200);
        $found = collect($response->json())->firstWhere('cashier_id', $cashier->id);
        $this->assertNotNull($found);
        $this->assertEquals(1, $found['total_sales']);
        $this->assertEqualsWithDelta(500, $found['total_revenue'], 0.01);
    }

    // ---- topSellingProducts ----

    public function test_owner_can_get_top_selling_products(): void
    {
        $this->actingAsOwner()->getJson('/api/reports/top-selling-products')
            ->assertStatus(200);
    }

    public function test_top_selling_products_respects_limit(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/reports/top-selling-products?limit=5');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(5, count($response->json()));
    }

    // ---- stockVariances ----

    public function test_owner_can_get_stock_variances(): void
    {
        $this->actingAsOwner()->getJson('/api/reports/stock-variances')
            ->assertStatus(200);
    }

    public function test_stock_variances_flags_discrepancies(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 100, 'cost_price' => 50]);
        $user = User::factory()->create(['is_active' => true]);
        StockMovement::create([
            'product_id' => $product->id, 'user_id' => $user->id, 'type' => 'purchase',
            'quantity' => 50, 'previous_stock' => 0, 'new_stock' => 50, 'unit_cost' => 50,
        ]);

        $response = $this->actingAsOwner()->getJson('/api/reports/stock-variances');

        $response->assertStatus(200);
    }

    // ---- reorderReport ----

    public function test_owner_can_get_reorder_report(): void
    {
        $this->actingAsOwner()->getJson('/api/reports/reorder')
            ->assertStatus(200);
    }

    public function test_reorder_report_includes_low_stock_products(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 2, 'reorder_point' => 10]);

        $response = $this->actingAsOwner()->getJson('/api/reports/reorder');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json()));
    }

    // ---- expiringProducts ----

    public function test_owner_can_get_expiring_products(): void
    {
        $this->actingAsOwner()->getJson('/api/reports/expiring-products')
            ->assertStatus(200);
    }

    public function test_expiring_products_with_custom_days(): void
    {
        $this->actingAsOwner()->getJson('/api/reports/expiring-products?days_ahead=60')
            ->assertStatus(200);
    }

    public function test_expiring_products_finds_near_expiry(): void
    {
        Product::factory()->create(['is_active' => true, 'expiry_date' => now()->addDays(7)->toDateString()]);

        $response = $this->actingAsOwner()->getJson('/api/reports/expiring-products?days_ahead=30');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json()));
    }

    public function test_expiring_products_invalid_days_returns_422(): void
    {
        $this->actingAsOwner()->getJson('/api/reports/expiring-products?days_ahead=0')
            ->assertStatus(422);
    }
}