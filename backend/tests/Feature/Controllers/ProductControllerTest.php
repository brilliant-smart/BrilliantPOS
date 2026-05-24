<?php

namespace Tests\Feature\Controllers;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_index_with_search(): void
    {
        $this->actingAsOwner();
        $this->createProduct(['name' => 'Paracetamol 500mg', 'is_active' => true]);
        $this->createProduct(['name' => 'Ibuprofen 200mg', 'is_active' => true]);

        $response = $this->getJson('/api/admin/products?search=Paracetamol');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_index_with_is_active_filter(): void
    {
        $this->actingAsOwner();
        $this->createProduct(['name' => 'Active Product', 'is_active' => true]);
        $this->createProduct(['name' => 'Inactive Product', 'is_active' => false]);

        $response = $this->getJson('/api/admin/products?is_active=0');

        $response->assertStatus(200);
    }

    public function test_search_returns_only_active_products(): void
    {
        $this->actingAsCashier();
        $this->createProduct(['name' => 'Active Test Product', 'is_active' => true]);
        $this->createProduct(['name' => 'Inactive Test Product', 'is_active' => false]);

        $response = $this->getJson('/api/products?search=Test');

        $response->assertStatus(200);
        $products = $response->json();
        foreach ($products as $product) {
            $this->assertTrue($product['is_active']);
        }
    }

    public function test_search_excludes_cost_profit_fields_for_cashier(): void
    {
        $this->actingAsCashier();
        $this->createProduct(['is_active' => true]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);
        $products = $response->json();
        if (isset($products[0])) {
            $this->assertArrayNotHasKey('cost_price', $products[0]);
            $this->assertArrayNotHasKey('last_purchase_price', $products[0]);
            $this->assertArrayNotHasKey('profit_margin', $products[0]);
        }
    }

    public function test_store_creates_product_with_auto_slug(): void
    {
        $this->actingAsOwner();

        $response = $this->postJson('/api/products', [
            'name' => 'Test Product Unique',
            'sku' => 'SKU-TEST-001',
            'price' => 100.00,
            'cost_price' => 60.00,
            'stock_quantity' => 50,
            'unit_type' => 'piece',
            'is_active' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', ['name' => 'Test Product Unique']);
        $product = Product::where('name', 'Test Product Unique')->first();
        $this->assertNotNull($product->slug);
    }

    public function test_store_duplicate_sku_returns_422(): void
    {
        $this->actingAsOwner();
        $existing = $this->createProduct(['sku' => 'SKU-DUP-TEST']);

        $response = $this->postJson('/api/products', [
            'name' => 'Another Product',
            'sku' => 'SKU-DUP-TEST',
            'price' => 50.00,
            'cost_price' => 30.00,
            'stock_quantity' => 10,
            'unit_type' => 'piece',
            'is_active' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_soft_delete_product(): void
    {
        $this->actingAsOwner();
        $product = $this->createProduct();

        $response = $this->deleteJson("/api/admin/products/{$product->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_barcode_search_finds_product(): void
    {
        $this->actingAsCashier();
        $product = $this->createProduct(['is_active' => true]);
        $baseType = $product->unitTypes()->where('is_base', true)->first();
        ProductBarcode::create([
            'product_id' => $product->id,
            'product_unit_type_id' => $baseType->id,
            'barcode' => '1234567890',
        ]);

        $response = $this->getJson('/api/products/barcode/search?barcode=1234567890');

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $product->id]);
    }

    public function test_barcode_search_returns_404_for_unknown(): void
    {
        $this->actingAsCashier();

        $response = $this->getJson('/api/products/barcode/search?barcode=NOTFOUND999');

        $response->assertStatus(404);
    }
}