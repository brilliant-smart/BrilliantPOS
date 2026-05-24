<?php

namespace Tests\Feature\Controllers;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchTrackingControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- Index ----

    public function test_owner_can_list_batches(): void
    {
        $product = $this->createProduct();
        \App\Models\ProductBatch::factory()->create([
            'product_id' => $product->id,
            'status' => 'active',
        ]);

        $response = $this->actingAsOwner()->getJson('/api/batches');

        $response->assertStatus(200);
    }

    public function test_manager_can_list_batches(): void
    {
        $response = $this->actingAsManager()->getJson('/api/batches');

        $response->assertStatus(200);
    }

    public function test_cashier_cannot_list_batches(): void
    {
        $response = $this->actingAsCashier()->getJson('/api/batches');

        $response->assertStatus(403);
    }

    public function test_filter_batches_by_status(): void
    {
        $product = $this->createProduct();
        \App\Models\ProductBatch::factory()->create(['product_id' => $product->id, 'status' => 'active']);
        \App\Models\ProductBatch::factory()->create(['product_id' => $product->id, 'status' => 'expired']);

        $response = $this->actingAsOwner()->getJson('/api/batches?status=active');

        $response->assertStatus(200);
    }

    // ---- Store ----

    public function test_owner_can_create_batch(): void
    {
        $product = $this->createProduct();

        $response = $this->actingAsOwner()->postJson('/api/batches', [
            'product_id' => $product->id,
            'batch_number' => 'BATCH-001',
            'quantity_received' => 100,
            'cost_price' => 50.00,
            'selling_price' => 75.00,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('product_batches', [
            'batch_number' => 'BATCH-001',
            'product_id' => $product->id,
        ]);
    }

    public function test_cashier_cannot_create_batch(): void
    {
        $product = $this->createProduct();

        $response = $this->actingAsCashier()->postJson('/api/batches', [
            'product_id' => $product->id,
            'batch_number' => 'BATCH-002',
            'quantity_received' => 50,
            'cost_price' => 30.00,
        ]);

        $response->assertStatus(403);
    }

    // ---- Mark Expired ----

    public function test_owner_can_mark_batch_as_expired(): void
    {
        $product = $this->createProduct(['stock_quantity' => 100]);
        $batch = \App\Models\ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 30,
            'status' => 'active',
            'expiry_date' => now()->subDay(),
        ]);

        $response = $this->actingAsOwner()->postJson("/api/batches/{$batch->id}/mark-expired");

        $response->assertStatus(200);
        $this->assertEquals('expired', $batch->fresh()->status);
    }

    public function test_cannot_mark_already_expired_batch(): void
    {
        $product = $this->createProduct(['stock_quantity' => 100]);
        $batch = \App\Models\ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 0,
            'status' => 'expired',
            'expiry_date' => now()->subDays(30),
        ]);

        $response = $this->actingAsOwner()->postJson("/api/batches/{$batch->id}/mark-expired");

        $response->assertStatus(422);
    }
}