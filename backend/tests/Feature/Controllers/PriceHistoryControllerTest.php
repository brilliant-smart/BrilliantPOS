<?php

namespace Tests\Feature\Controllers;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- Index ----

    public function test_owner_can_list_price_history(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/price-history');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_cashier_cannot_list_price_history(): void
    {
        $response = $this->actingAsCashier()->getJson('/api/price-history');

        $response->assertStatus(403);
    }

    public function test_price_history_with_date_filter(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/price-history?start_date=2026-01-01&end_date=2026-12-31');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_price_history_returns_created_records(): void
    {
        $product = $this->createProduct();
        $owner = \App\Models\User::factory()->create(['role' => 'owner', 'is_active' => true]);

        ProductPriceHistory::create([
            'product_id' => $product->id,
            'old_price' => 60,
            'new_price' => 80,
            'price_change' => 20,
            'percentage_change' => 33.33,
            'change_type' => 'purchase',
            'supplier_name' => 'Test Supplier',
            'reference_number' => 'PO-001',
            'changed_by' => $owner->id,
            'changed_at' => now(),
        ]);

        $response = $this->actingAsOwner()->getJson('/api/price-history');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    // ---- Product History ----

    public function test_owner_can_get_product_price_history(): void
    {
        $product = $this->createProduct();

        $response = $this->actingAsOwner()->getJson("/api/products/{$product->id}/price-history");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_product_price_history_contains_records(): void
    {
        $product = $this->createProduct();
        $owner = \App\Models\User::factory()->create(['role' => 'owner', 'is_active' => true]);

        ProductPriceHistory::create([
            'product_id' => $product->id,
            'old_price' => 60,
            'new_price' => 80,
            'price_change' => 20,
            'percentage_change' => 33.33,
            'change_type' => 'purchase',
            'supplier_name' => 'Test Supplier',
            'reference_number' => 'PO-001',
            'changed_by' => $owner->id,
            'changed_at' => now(),
        ]);

        $response = $this->actingAsOwner()->getJson("/api/products/{$product->id}/price-history");

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }
}