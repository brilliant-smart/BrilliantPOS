<?php

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedReportControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- Inventory Aging ----

    public function test_owner_can_get_inventory_aging_report(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/advanced-reports/inventory-aging');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'products',
            'summary',
        ]);
    }

    public function test_cashier_cannot_get_inventory_aging_report(): void
    {
        $response = $this->actingAsCashier()->getJson('/api/advanced-reports/inventory-aging');

        $response->assertStatus(403);
    }

    // ---- Supplier Performance ----

    public function test_owner_can_get_supplier_performance_report(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/advanced-reports/supplier-performance');

        $response->assertStatus(200);
        $response->assertJsonIsArray();
    }

    // ---- Dead Stock ----

    public function test_owner_can_get_dead_stock_report(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/advanced-reports/dead-stock');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'products',
            'total_products',
            'total_stock_value',
            'days_threshold',
        ]);
    }

    public function test_dead_stock_with_custom_days(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/advanced-reports/dead-stock?days=60');

        $response->assertStatus(200);
        $this->assertEquals(60, $response->json('days_threshold'));
    }

    // ---- Stockout Report ----

    public function test_owner_can_get_stockout_report(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/advanced-reports/stockout');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'stockouts',
            'total_products',
            'total_estimated_lost_revenue',
        ]);
    }

    // ---- Sales Forecast ----

    public function test_owner_can_get_sales_forecast(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/advanced-reports/sales-forecast');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'historical_data',
            'forecast',
            'average_daily_sales',
            'forecast_period_days',
            'total_forecast',
        ]);
    }
}