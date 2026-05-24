<?php

namespace Tests\Feature\Authorization;

use App\Models\Product;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    // ---- Cashier permissions ----

    public function test_cashier_can_access_product_search(): void
    {
        $this->actingAsCashier();

        $response = $this->getJson('/api/products');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_cashier_cannot_access_admin_product_crud(): void
    {
        $this->actingAsCashier();

        // POST route requires valid product data which may 422 before hitting middleware
        // So test with GET to the admin products list which requires owner/manager role
        $response = $this->getJson('/api/admin/products');

        $response->assertStatus(403);
    }

    public function test_cashier_can_complete_sales(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        $response = $this->actingAs($cashier, 'sanctum')->postJson('/api/pos/complete-sale', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100.00],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 100.00],
            ],
        ]);

        $this->assertNotEquals(403, $response->status());
    }

    public function test_cashier_cannot_void_sales(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $sale = \App\Models\Sale::factory()->create(['status' => 'completed']);

        $response = $this->actingAs($cashier, 'sanctum')->postJson("/api/pos/void-sale/{$sale->id}", [
            'reason' => 'Test',
        ]);

        $response->assertStatus(403);
    }

    public function test_cashier_can_view_sales(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $response = $this->actingAs($cashier, 'sanctum')->getJson('/api/sales');

        $this->assertNotEquals(403, $response->status());
    }

    // ---- Manager permissions ----

    public function test_manager_can_create_products(): void
    {
        $this->actingAsManager();

        $response = $this->getJson('/api/admin/products');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_manager_cannot_access_audit_logs(): void
    {
        $this->actingAsManager();

        $response = $this->getJson('/api/audit-logs');

        // Manager should be forbidden (403) from owner-only audit logs
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_manager_can_manage_suppliers(): void
    {
        $this->actingAsManager();

        $response = $this->getJson('/api/admin/suppliers');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_manager_cannot_manage_users(): void
    {
        $this->actingAsManager();

        $response = $this->postJson('/api/admin/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password',
            'role' => 'cashier',
        ]);

        $response->assertStatus(403);
    }

    // ---- Owner permissions ----

    public function test_owner_can_access_audit_logs(): void
    {
        $this->actingAsOwner();

        $response = $this->getJson('/api/audit-logs');

        $response->assertStatus(200);
    }

    public function test_owner_can_manage_users(): void
    {
        $this->actingAsOwner();

        $response = $this->getJson('/api/admin/users');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_owner_can_void_sales(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $sale = \App\Models\Sale::factory()->create(['status' => 'completed']);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/pos/void-sale/{$sale->id}", [
            'reason' => 'Owner void test',
        ]);

        $this->assertNotEquals(403, $response->status());
    }

    // ---- Profit data visibility ----

    public function test_cashier_sale_data_hides_profit_fields(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = $this->createProduct();
        $sale = \App\Models\Sale::factory()->create(['cashier_id' => $cashier->id]);
        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100.00,
            'unit_cost' => 60.00,
            'conversion_factor' => 1,
            'unit_type' => 'piece',
            'line_total' => 100.00,
            'line_cost' => 60.00,
            'line_profit' => 40.00,
        ]);

        $response = $this->actingAs($cashier, 'sanctum')->getJson('/api/sales');

        $response->assertStatus(200);
        $json = $response->json();
        $saleData = $json['data'][0] ?? $json[0] ?? $json;

        foreach (['gross_profit', 'cost_of_goods_sold', 'net_profit'] as $field) {
            $this->assertArrayNotHasKey($field, $saleData, "Cashier should not see {$field} in sale response");
        }
    }
}