<?php

namespace Tests\Feature\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- Dashboard Stats ----

    public function test_owner_can_get_dashboard_stats(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/admin/dashboard-stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'totalUsers',
            'activeUsers',
            'inactiveUsers',
            'totalProducts',
            'activeProducts',
            'recentProducts',
            'period',
        ]);
    }

    public function test_manager_can_get_dashboard_stats(): void
    {
        $response = $this->actingAsManager()->getJson('/api/admin/dashboard-stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'totalUsers',
            'totalProducts',
        ]);
    }

    public function test_cashier_can_get_dashboard_stats(): void
    {
        $response = $this->actingAsCashier()->getJson('/api/admin/dashboard-stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'totalUsers',
            'totalProducts',
        ]);
    }

    public function test_dashboard_stats_reflects_user_counts(): void
    {
        // The owner we create via actingAsOwner counts toward totalUsers
        $response = $this->actingAsOwner()->getJson('/api/admin/dashboard-stats');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('totalUsers'));
        $this->assertGreaterThanOrEqual(0, $response->json('totalProducts'));
    }
}