<?php

namespace Tests\Feature\Middleware;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HideProfitDataTest extends TestCase
{
    use RefreshDatabase;

    private const SENSITIVE_FIELDS = [
        'cost_price',
        'last_purchase_price',
        'profit',
        'profit_margin',
        'profit_percentage',
        'total_profit',
        'unit_cost',
        'line_profit',
        'line_cost',
        'gross_profit',
        'net_profit',
        'cost_of_goods_sold',
        'discount_percentage',
        'discount_amount',
        'amount_due',
    ];

    public function test_owner_sees_all_profit_fields_on_product(): void
    {
        $this->actingAsOwner();
        $product = $this->createProduct();

        $response = $this->getJson('/api/admin/products');

        $response->assertStatus(200);
        $data = $response->json('data');
        $firstProduct = is_array($data) && isset($data[0]) ? $data[0] : $data;

        // Owner should see cost_price
        $this->assertArrayHasKey('cost_price', $firstProduct);
    }

    public function test_cashier_does_not_see_profit_fields_on_product(): void
    {
        $this->actingAsCashier();
        $this->createProduct();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);
        $products = $response->json();

        if (isset($products[0])) {
            foreach (self::SENSITIVE_FIELDS as $field) {
                $this->assertArrayNotHasKey($field, $products[0], "Cashier should not see {$field} in product response");
            }
        }
    }

    public function test_cashier_sees_basic_product_fields(): void
    {
        $this->actingAsCashier();
        $this->createProduct();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);
        $products = $response->json();

        if (isset($products[0])) {
            $this->assertArrayHasKey('id', $products[0]);
            $this->assertArrayHasKey('name', $products[0]);
            $this->assertArrayHasKey('price', $products[0]);
            $this->assertArrayHasKey('stock_quantity', $products[0]);
        }
    }

    public function test_manager_sees_profit_fields_on_product(): void
    {
        $this->actingAsManager();
        $product = $this->createProduct();

        $response = $this->getJson('/api/admin/products');

        $response->assertStatus(200);
        $data = $response->json('data');
        $firstProduct = is_array($data) && isset($data[0]) ? $data[0] : $data;

        $this->assertArrayHasKey('cost_price', $firstProduct);
    }

    public function test_cashier_sale_response_hides_profit_data(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $this->actingAs($cashier, 'sanctum');

        $product = $this->createProduct();
        $sale = Sale::factory()->create(['cashier_id' => $cashier->id]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
        ]);

        $response = $this->getJson('/api/sales');

        $response->assertStatus(200);

        // The HideProfitData middleware should strip profit fields from the response
        $json = $response->json();
        $saleData = $json['data'][0] ?? $json[0] ?? $json;

        foreach (['gross_profit', 'cost_of_goods_sold', 'net_profit'] as $field) {
            $this->assertArrayNotHasKey($field, $saleData, "Cashier should not see {$field} in sale response");
        }
    }
}