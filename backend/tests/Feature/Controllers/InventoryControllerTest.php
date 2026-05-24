<?php

namespace Tests\Feature\Controllers;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- addStock ----

    public function test_owner_can_add_stock(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 10]);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/inventory/products/{$product->id}/add-stock", [
            'quantity' => 5,
            'type' => 'purchase',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(15, $product->fresh()->stock_quantity);
    }

    public function test_add_stock_creates_audit_log(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 10]);

        $this->actingAs($owner, 'sanctum')->postJson("/api/inventory/products/{$product->id}/add-stock", [
            'quantity' => 5,
            'type' => 'purchase',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'stock.add']);
    }

    public function test_cashier_cannot_add_stock(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 10]);

        $this->actingAs($cashier, 'sanctum')
            ->postJson("/api/inventory/products/{$product->id}/add-stock", ['quantity' => 5])
            ->assertStatus(403);
    }

    // ---- reduceStock ----

    public function test_owner_can_reduce_stock(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/inventory/products/{$product->id}/reduce-stock", [
            'quantity' => 10,
            'type' => 'sale',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(40, $product->fresh()->stock_quantity);
    }

    public function test_reduce_stock_creates_audit_log(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        $this->actingAs($owner, 'sanctum')->postJson("/api/inventory/products/{$product->id}/reduce-stock", [
            'quantity' => 10,
            'type' => 'damage',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'stock.reduce']);
    }

    // ---- adjustStock ----

    public function test_owner_can_adjust_stock(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/inventory/products/{$product->id}/adjust-stock", [
            'quantity' => 25,
            'notes' => 'Manual count',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(25, $product->fresh()->stock_quantity);
    }

    public function test_adjust_stock_to_zero(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        $this->actingAs($owner, 'sanctum')->postJson("/api/inventory/products/{$product->id}/adjust-stock", [
            'quantity' => 0,
        ]);

        $this->assertEquals(0, $product->fresh()->stock_quantity);
    }

    public function test_adjust_stock_creates_audit_log(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        $this->actingAs($owner, 'sanctum')->postJson("/api/inventory/products/{$product->id}/adjust-stock", [
            'quantity' => 25,
            'notes' => 'Manual count',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'stock.adjust']);
    }

    // ---- getLowStockProducts ----

    public function test_low_stock_products_returns_items_below_threshold(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 2, 'low_stock_threshold' => 10]);
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 50, 'low_stock_threshold' => 10]);

        $response = $this->actingAsOwner()->getJson('/api/inventory/low-stock');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json()));
    }

    // ---- getOutOfStockProducts ----

    public function test_out_of_stock_products_returns_zero_stock_items(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 0]);
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 50]);

        $response = $this->actingAsOwner()->getJson('/api/inventory/out-of-stock');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json()));
    }

    // ---- getInventorySummary ----

    public function test_inventory_summary_returns_counts(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 50]);
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 0]);

        $response = $this->actingAsOwner()->getJson('/api/inventory/summary');

        $response->assertStatus(200);
        $response->assertJsonStructure(['total_products', 'total_stock_value', 'low_stock', 'out_of_stock']);
    }

    // ---- bulkUpdateStock ----

    public function test_bulk_update_adjusts_multiple_products(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $productA = $this->createProduct(['stock_quantity' => 50]);
        $productB = $this->createProduct(['stock_quantity' => 30]);

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/inventory/bulk-update', [
            'updates' => [
                ['product_id' => $productA->id, 'quantity' => 75],
                ['product_id' => $productB->id, 'quantity' => 10],
            ],
            'notes' => 'Bulk adjustment test',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(75, $productA->fresh()->stock_quantity);
        $this->assertEquals(10, $productB->fresh()->stock_quantity);
    }

    public function test_cashier_cannot_bulk_update(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        $this->actingAs($cashier, 'sanctum')->postJson('/api/inventory/bulk-update', [
            'updates' => [
                ['product_id' => $product->id, 'quantity' => 75],
            ],
        ])->assertStatus(403);
    }

    public function test_bulk_update_creates_audit_log(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        $this->actingAs($owner, 'sanctum')->postJson('/api/inventory/bulk-update', [
            'updates' => [
                ['product_id' => $product->id, 'quantity' => 75],
            ],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'stock.bulk_adjust',
        ]);
    }

    public function test_bulk_update_validates_input(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);

        $this->actingAs($owner, 'sanctum')->postJson('/api/inventory/bulk-update', [
            'updates' => [],
        ])->assertStatus(422);
    }

    public function test_bulk_update_validates_quantity_non_negative(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct();

        $this->actingAs($owner, 'sanctum')->postJson('/api/inventory/bulk-update', [
            'updates' => [
                ['product_id' => $product->id, 'quantity' => -5],
            ],
        ])->assertStatus(422);
    }
}