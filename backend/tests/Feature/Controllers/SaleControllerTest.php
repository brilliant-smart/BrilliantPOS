<?php

namespace Tests\Feature\Controllers;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_delete_todays_sale(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 40]);

        $sale = Sale::factory()->create([
            'status' => 'completed',
            'sale_date' => now()->toDateString(),
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100.00,
            'unit_cost' => 60.00,
            'conversion_factor' => 1,
            'unit_type' => 'piece',
            'line_total' => 1000.00,
            'line_cost' => 600.00,
            'line_profit' => 400.00,
        ]);

        // Simulate stock already deducted
        $product->update(['stock_quantity' => 30]);

        $response = $this->actingAs($owner, 'sanctum')->deleteJson("/api/sales/{$sale->id}");

        $response->assertStatus(200);
        $this->assertEquals(40, $product->fresh()->stock_quantity);
    }

    public function test_deleting_sale_creates_return_stock_movement(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 30]);

        $sale = Sale::factory()->create([
            'status' => 'completed',
            'sale_date' => now()->toDateString(),
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100.00,
            'unit_cost' => 60.00,
            'conversion_factor' => 1,
            'unit_type' => 'piece',
            'line_total' => 1000.00,
            'line_cost' => 600.00,
            'line_profit' => 400.00,
        ]);

        $this->actingAs($owner, 'sanctum')->deleteJson("/api/sales/{$sale->id}");

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'return',
            'quantity' => 10,
            'reference_type' => 'sale_delete',
            'reference_id' => $sale->id,
        ]);
    }

    public function test_deleting_sale_creates_audit_log(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 30]);

        $sale = Sale::factory()->create([
            'status' => 'completed',
            'sale_date' => now()->toDateString(),
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 100.00,
            'unit_cost' => 60.00,
            'conversion_factor' => 1,
            'unit_type' => 'piece',
            'line_total' => 500.00,
            'line_cost' => 300.00,
            'line_profit' => 200.00,
        ]);

        $this->actingAs($owner, 'sanctum')->deleteJson("/api/sales/{$sale->id}");

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'sale.delete',
        ]);
    }

    public function test_non_owner_cannot_delete_sale(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $sale = Sale::factory()->create([
            'status' => 'completed',
            'sale_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($manager, 'sanctum')->deleteJson("/api/sales/{$sale->id}");

        $response->assertStatus(403);
    }

    public function test_cannot_delete_sale_from_another_day(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct();

        $sale = Sale::factory()->create([
            'status' => 'completed',
            'sale_date' => now()->subDay()->toDateString(),
        ]);
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

        $response = $this->actingAs($owner, 'sanctum')->deleteJson("/api/sales/{$sale->id}");

        $response->assertStatus(422);
    }

    public function test_delete_sale_with_multiple_items_restores_all_stock(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $productA = $this->createProduct(['stock_quantity' => 30]);
        $productB = $this->createProduct(['stock_quantity' => 20]);

        $sale = Sale::factory()->create([
            'status' => 'completed',
            'sale_date' => now()->toDateString(),
        ]);
        $sale->items()->create([
            'product_id' => $productA->id,
            'quantity' => 10,
            'unit_price' => 100.00,
            'unit_cost' => 60.00,
            'conversion_factor' => 1,
            'unit_type' => 'piece',
            'line_total' => 1000.00,
            'line_cost' => 600.00,
            'line_profit' => 400.00,
        ]);
        $sale->items()->create([
            'product_id' => $productB->id,
            'quantity' => 5,
            'unit_price' => 100.00,
            'unit_cost' => 60.00,
            'conversion_factor' => 1,
            'unit_type' => 'piece',
            'line_total' => 500.00,
            'line_cost' => 300.00,
            'line_profit' => 200.00,
        ]);

        $this->actingAs($owner, 'sanctum')->deleteJson("/api/sales/{$sale->id}");

        $this->assertEquals(40, $productA->fresh()->stock_quantity);
        $this->assertEquals(25, $productB->fresh()->stock_quantity);
    }

    public function test_sale_is_soft_deleted_after_destruction(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = $this->createProduct(['stock_quantity' => 30]);

        $sale = Sale::factory()->create([
            'status' => 'completed',
            'sale_date' => now()->toDateString(),
        ]);
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

        $this->actingAs($owner, 'sanctum')->deleteJson("/api/sales/{$sale->id}");

        $this->assertSoftDeleted('sales', ['id' => $sale->id]);
    }
}