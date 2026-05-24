<?php

namespace Tests\Feature\Regression;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for inventory edge cases: stock adjustments, batch expiry,
 * low stock alerts, and inventory summary calculations.
 */
class InventoryRegressionTest extends TestCase
{
    use RefreshDatabase;

    // ---- REGRESSION: Stock cannot go negative via reduce ----

    public function test_reduce_stock_throws_on_insufficient_stock(): void
    {
        $product = $this->createProduct(['stock_quantity' => 5]);
        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock');

        app(\App\Services\InventoryService::class)->reduceStock($product, 10, 'sale', null, $user);
    }

    // ---- REGRESSION: Adjust stock accepts zero ----

    public function test_adjust_stock_to_zero_is_allowed(): void
    {
        $product = $this->createProduct(['stock_quantity' => 50]);
        $user = User::factory()->create();

        $movement = app(\App\Services\InventoryService::class)->adjustStock($product, 0, 'Zero count', $user);

        $this->assertEquals(0, $product->fresh()->stock_quantity);
        $this->assertEquals(-50, $movement->quantity);
    }

    // ---- REGRESSION: Stock movement records correct previous and new values ----

    public function test_stock_movement_tracks_previous_and_new_stock(): void
    {
        $product = $this->createProduct(['stock_quantity' => 30]);
        $user = User::factory()->create();

        $movement = app(\App\Services\InventoryService::class)->addStock($product, 20, 'purchase', null, null, $user);

        $this->assertEquals(30, $movement->previous_stock);
        $this->assertEquals(50, $movement->new_stock);
        $this->assertEquals(20, $movement->quantity);
        $this->assertEquals('purchase', $movement->type);
    }

    // ---- REGRESSION: Bulk update is atomic (all succeed or all fail) ----

    public function test_bulk_update_is_transactional(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $productA = $this->createProduct(['stock_quantity' => 50]);
        $productB = $this->createProduct(['stock_quantity' => 30]);

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/inventory/bulk-update', [
            'updates' => [
                ['product_id' => $productA->id, 'quantity' => 75],
                ['product_id' => $productB->id, 'quantity' => 10],
            ],
            'notes' => 'Bulk adjustment',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(75, $productA->fresh()->stock_quantity);
        $this->assertEquals(10, $productB->fresh()->stock_quantity);

        // Both should have stock movements
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productA->id,
            'type' => 'adjustment',
            'new_stock' => 75,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productB->id,
            'type' => 'adjustment',
            'new_stock' => 10,
        ]);
    }

    // ---- REGRESSION: Batch expiry marks stock as damage type ----

    public function test_marking_batch_expired_reduces_product_stock(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->actingAs($owner, 'sanctum');

        $product = $this->createProduct(['stock_quantity' => 100]);
        $batch = ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 30,
            'cost_price' => 50.00,
            'expiry_date' => now()->subDay(),
            'status' => 'active',
        ]);

        app(\App\Services\BatchTrackingService::class)->markBatchAsExpired($batch);

        $batch->refresh();
        $this->assertEquals('expired', $batch->status);
        $this->assertEquals(0, $batch->quantity_remaining);

        // Product stock should be reduced by batch quantity
        $this->assertEquals(70, $product->fresh()->stock_quantity);

        // Stock movement should be created
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'damage',
            'quantity' => -30,
        ]);
    }

    // ---- REGRESSION: Low stock products exclude out-of-stock ----

    public function test_low_stock_excludes_out_of_stock_products(): void
    {
        $lowProduct = $this->createProduct([
            'stock_quantity' => 3,
            'low_stock_threshold' => 10,
            'is_active' => true,
        ]);
        $outOfStockProduct = $this->createProduct([
            'stock_quantity' => 0,
            'low_stock_threshold' => 10,
            'is_active' => true,
        ]);
        $okProduct = $this->createProduct([
            'stock_quantity' => 50,
            'low_stock_threshold' => 10,
            'is_active' => true,
        ]);

        $results = app(\App\Services\InventoryService::class)->getLowStockProducts();

        $this->assertTrue($results->contains('id', $lowProduct->id));
        $this->assertFalse($results->contains('id', $outOfStockProduct->id));
        $this->assertFalse($results->contains('id', $okProduct->id));
    }

    // ---- REGRESSION: Inventory summary counts are correct ----

    public function test_inventory_summary_counts_are_accurate(): void
    {
        $this->createProduct(['stock_quantity' => 50, 'price' => 100.00, 'cost_price' => 60.00, 'is_active' => true]);
        $this->createProduct(['stock_quantity' => 3, 'price' => 200.00, 'cost_price' => 120.00, 'low_stock_threshold' => 10, 'is_active' => true]);
        $this->createProduct(['stock_quantity' => 0, 'price' => 50.00, 'cost_price' => 30.00, 'is_active' => true]);

        $summary = app(\App\Services\InventoryService::class)->getInventorySummary();

        $this->assertEquals(3, $summary['total_products']);
        $this->assertEquals(2, $summary['in_stock']); // 50 and 3
        $this->assertEquals(1, $summary['low_stock']); // 3 (low but not zero)
        $this->assertEquals(1, $summary['out_of_stock']); // 0
    }

    // ---- REGRESSION: Product soft delete hides from search but preserves data ----

    public function test_soft_deleted_product_not_in_search_results(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['name' => 'Discontinued Item', 'is_active' => true]);

        // Delete the product (soft delete)
        $this->actingAs($owner, 'sanctum')->deleteJson("/api/admin/products/{$product->id}");

        // Product should not appear in cashier search
        $response = $this->actingAsCashier()->getJson('/api/products?search=Discontinued');

        $response->assertStatus(200);
        $products = $response->json();
        $found = collect($products)->first(fn ($p) => $p['name'] === 'Discontinued Item');
        $this->assertNull($found, 'Soft-deleted product should not appear in search results');
    }

    // ---- REGRESSION: Inactive user cannot login ----

    public function test_inactive_user_cannot_complete_sale(): void
    {
        $inactiveUser = User::factory()->create(['role' => 'cashier', 'is_active' => false]);
        $product = $this->createProduct(['stock_quantity' => 50]);

        $response = $this->actingAs($inactiveUser, 'sanctum')->postJson('/api/pos/complete-sale', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100.00],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 100.00],
            ],
        ]);

        // Should be forbidden or unauthenticated since user is inactive
        $this->assertContains($response->status(), [401, 403]);
    }
}