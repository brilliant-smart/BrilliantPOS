<?php

namespace Tests\Feature\Controllers;

use App\Models\NotificationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- Dashboard ----

    public function test_owner_can_get_inventory_dashboard(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/inventory/analytics/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'overview' => ['total_products', 'total_stock_units', 'total_stock_value'],
            'stock_status',
            'movement_summary',
            'top_products',
            'inventory_value',
            'alerts',
        ]);
    }

    public function test_manager_can_get_inventory_dashboard(): void
    {
        $response = $this->actingAsManager()->getJson('/api/inventory/analytics/dashboard');

        $response->assertStatus(200);
    }

    public function test_cashier_cannot_get_inventory_dashboard(): void
    {
        $response = $this->actingAsCashier()->getJson('/api/inventory/analytics/dashboard');

        $response->assertStatus(403);
    }

    // ---- Movements ----

    public function test_owner_can_get_inventory_movements(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/inventory/analytics/movements');

        $response->assertStatus(200);
        $response->assertJsonStructure(['movements', 'total_count']);
    }

    public function test_inventory_movements_with_filters(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/inventory/analytics/movements?type=sale&limit=10');

        $response->assertStatus(200);
        $response->assertJsonStructure(['movements', 'total_count']);
    }

    // ---- Turnover ----

    public function test_owner_can_get_inventory_turnover(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/inventory/analytics/turnover');

        $response->assertStatus(200);
        $response->assertJsonStructure(['period_days', 'products', 'average_turnover']);
    }

    // ---- Export ----

    public function test_owner_can_export_inventory_csv(): void
    {
        $response = $this->actingAsOwner()->get('/api/inventory/analytics/export?type=dashboard&format=csv');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_export_requires_type_parameter(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/inventory/analytics/export?format=csv');

        $response->assertStatus(422);
    }

    public function test_export_requires_format_parameter(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/inventory/analytics/export?type=dashboard');

        $response->assertStatus(422);
    }
}