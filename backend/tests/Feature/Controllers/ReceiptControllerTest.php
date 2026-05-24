<?php

namespace Tests\Feature\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ReceiptControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- generateToken ----

    public function test_owner_can_generate_receipt_token(): void
    {
        $sale = $this->createCompletedSale();

        $response = $this->actingAsOwner()->postJson("/api/pos/sales/{$sale->id}/receipt-token");

        $response->assertStatus(200);
        $response->assertJsonStructure(['token']);
        $this->assertNotEmpty($response->json('token'));
        $this->assertEquals(32, strlen($response->json('token')));
    }

    public function test_cashier_can_generate_receipt_token(): void
    {
        $sale = $this->createCompletedSale();

        $response = $this->actingAsCashier()->postJson("/api/pos/sales/{$sale->id}/receipt-token");

        $response->assertStatus(200);
    }

    public function test_receipt_token_is_cached_with_sale_id(): void
    {
        $sale = $this->createCompletedSale();

        $response = $this->actingAsOwner()->postJson("/api/pos/sales/{$sale->id}/receipt-token");

        $token = $response->json('token');
        $this->assertEquals($sale->id, Cache::get("receipt_token:{$token}"));
    }

    // ---- download ----

    public function test_download_with_valid_token_returns_receipt(): void
    {
        $sale = $this->createCompletedSale();

        $tokenResponse = $this->actingAsOwner()->postJson("/api/pos/sales/{$sale->id}/receipt-token");
        $token = $tokenResponse->json('token');

        $response = $this->get("/receipt/{$token}");

        $response->assertStatus(200);
    }

    public function test_download_with_invalid_token_returns_404(): void
    {
        $response = $this->get('/receipt/nonexistent_token_1234567890');

        $response->assertStatus(404);
    }

    public function test_download_with_missing_token_returns_404(): void
    {
        $response = $this->get('/receipt/');

        $response->assertStatus(404);
    }

    public function test_receipt_token_is_single_use(): void
    {
        $sale = $this->createCompletedSale();

        $tokenResponse = $this->actingAsOwner()->postJson("/api/pos/sales/{$sale->id}/receipt-token");
        $token = $tokenResponse->json('token');

        // First use should work
        $this->get("/receipt/{$token}")->assertStatus(200);

        // Second use should fail (token consumed)
        $this->get("/receipt/{$token}")->assertStatus(404);
    }

    private function createCompletedSale(): Sale
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

        $this->assertEquals(200, $response->status(), 'Sale creation should succeed');

        return Sale::first();
    }
}