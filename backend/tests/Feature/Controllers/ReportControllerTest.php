<?php

namespace Tests\Feature\Controllers;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- Financial Overview ----

    public function test_owner_can_get_financial_overview(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/reports/financial-overview');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'period' => ['start_date', 'end_date'],
            'revenue' => ['total_sales_count', 'total_revenue', 'average_sale_value'],
            'costs' => ['cost_of_goods_sold', 'total_purchases'],
            'profit' => ['gross_profit', 'profit_margin_percent'],
            'cash_flow' => ['cash_in', 'cash_out', 'net_cash_flow'],
            'outstanding' => ['receivables', 'payables'],
            'inventory' => ['current_stock_value', 'total_products'],
        ]);
    }

    public function test_cashier_cannot_get_financial_overview(): void
    {
        $response = $this->actingAsCashier()->getJson('/api/reports/financial-overview');

        $response->assertStatus(403);
    }

    // ---- Top Selling Products ----

    public function test_owner_can_get_top_selling_products(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/reports/top-selling-products');

        $response->assertStatus(200);
        $response->assertJsonIsArray();
    }

    public function test_top_selling_products_with_date_range(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/reports/top-selling-products?start_date=2026-01-01&end_date=2026-12-31');

        $response->assertStatus(200);
        $response->assertJsonIsArray();
    }

    // ---- Reorder Report ----

    public function test_owner_can_get_reorder_report(): void
    {
        $this->createProduct(['stock_quantity' => 2, 'reorder_point' => 10, 'is_active' => true]);

        $response = $this->actingAsOwner()->getJson('/api/reports/reorder');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'products',
            'total_products_to_reorder',
            'total_estimated_cost',
        ]);
        $this->assertGreaterThanOrEqual(1, $response->json('total_products_to_reorder'));
    }

    // ---- Expiring Products ----

    public function test_owner_can_get_expiring_products(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/reports/expiring-products');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'products',
            'total_expiring_products',
            'critical_count',
            'total_stock_value_at_risk',
        ]);
    }

    // ---- Cashier Performance ----

    public function test_owner_can_get_cashier_performance(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/reports/cashier-performance');

        $response->assertStatus(200);
        $response->assertJsonIsArray();
    }

    public function test_cashier_cannot_get_cashier_performance(): void
    {
        $response = $this->actingAsCashier()->getJson('/api/reports/cashier-performance');

        $response->assertStatus(403);
    }

    // ---- Stock Variances ----

    public function test_owner_can_get_stock_variances(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/reports/stock-variances');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'variances',
            'total_variances',
            'high_severity_count',
            'total_variance_value',
        ]);
    }
}