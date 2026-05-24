<?php

namespace Tests\Feature\Controllers;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoReorderControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- Suggestions ----

    public function test_owner_can_get_reorder_suggestions(): void
    {
        $this->createProduct(['stock_quantity' => 2, 'reorder_point' => 10, 'is_active' => true]);

        $response = $this->actingAsOwner()->getJson('/api/auto-reorder/suggestions');

        $response->assertStatus(200);
        $response->assertJsonStructure(['suggestions', 'total']);
        $this->assertGreaterThanOrEqual(1, $response->json('total'));
    }

    public function test_suggestions_empty_when_stock_sufficient(): void
    {
        $this->createProduct(['stock_quantity' => 100, 'reorder_point' => 10, 'is_active' => true]);

        $response = $this->actingAsOwner()->getJson('/api/auto-reorder/suggestions');

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('total'));
    }

    public function test_cashier_cannot_get_reorder_suggestions(): void
    {
        $response = $this->actingAsCashier()->getJson('/api/auto-reorder/suggestions');

        $response->assertStatus(403);
    }

    // ---- Trigger Check ----

    public function test_owner_can_trigger_reorder_check(): void
    {
        $response = $this->actingAsOwner()->postJson('/api/auto-reorder/trigger-check');

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'results']);
    }

    public function test_cashier_cannot_trigger_reorder_check(): void
    {
        $response = $this->actingAsCashier()->postJson('/api/auto-reorder/trigger-check');

        $response->assertStatus(403);
    }

    // ---- Logs ----

    public function test_owner_can_view_reorder_logs(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/auto-reorder/logs');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'current_page', 'total']);
    }

    // ---- Statistics ----

    public function test_owner_can_view_reorder_statistics(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/auto-reorder/statistics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_triggers',
            'pos_created',
            'notifications_sent',
            'manual_overrides',
        ]);
    }
}