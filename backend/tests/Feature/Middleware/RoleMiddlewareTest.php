<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/admin/products');

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_owner_can_access_owner_only_routes(): void
    {
        $this->actingAsOwner();

        $response = $this->getJson('/api/audit-logs');

        $response->assertStatus(200);
    }

    public function test_manager_can_access_manager_routes(): void
    {
        $this->actingAsManager();

        $response = $this->getJson('/api/admin/products');

        // Should not be 403 - may be 200 or other status
        $this->assertNotEquals(403, $response->status());
    }

    public function test_cashier_cannot_access_owner_only_routes(): void
    {
        $this->actingAsCashier();

        $response = $this->getJson('/api/audit-logs');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'You do not have permission to access this resource.']);
    }

    public function test_cashier_cannot_access_manager_routes(): void
    {
        $this->actingAsCashier();

        // Use GET since POST may return 405 (method not allowed) or 422 before middleware
        $response = $this->getJson('/api/admin/products');

        $response->assertStatus(403);
    }

    public function test_owner_can_access_product_management(): void
    {
        $this->actingAsOwner();

        $response = $this->getJson('/api/admin/products');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_cashier_can_access_product_search(): void
    {
        $this->actingAsCashier();

        $response = $this->getJson('/api/products');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_inactive_user_with_valid_token_gets_authenticated(): void
    {
        $user = User::factory()->create(['role' => 'owner', 'is_active' => false]);

        // Sanctum still authenticates the token; role middleware checks role, not is_active
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/admin/products');

        // Middleware only checks role, not is_active
        $this->assertNotEquals(401, $response->status());
    }
}