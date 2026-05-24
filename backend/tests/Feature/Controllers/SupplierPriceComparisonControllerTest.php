<?php

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPriceComparisonControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- Index (price comparison) ----

    public function test_owner_can_get_supplier_price_comparison(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/reports/supplier-price-comparison');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'summary' => ['total_products', 'products_with_multiple_suppliers'],
        ]);
    }

    public function test_cashier_cannot_get_supplier_price_comparison(): void
    {
        $response = $this->actingAsCashier()->getJson('/api/reports/supplier-price-comparison');

        $response->assertStatus(403);
    }

    // ---- Supplier Performance ----

    public function test_owner_can_get_supplier_performance(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/reports/supplier-performance');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ---- Best Supplier ----

    public function test_owner_can_get_best_supplier_for_product(): void
    {
        $product = $this->createProduct();

        $response = $this->actingAsOwner()->getJson("/api/reports/best-supplier/{$product->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['product']);
    }

    public function test_best_supplier_for_nonexistent_product(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/reports/best-supplier/99999');

        $response->assertStatus(404);
    }
}