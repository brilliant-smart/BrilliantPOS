<?php

namespace Tests\Feature\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleServiceTest extends TestCase
{
    use RefreshDatabase;

    private SaleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SaleService::class);
    }

    public function test_create_sale_with_correct_totals(): void
    {
        $user = User::factory()->create();
        $productA = $this->createProduct(['price' => 100.00, 'cost_price' => 60.00, 'stock_quantity' => 50]);
        $productB = $this->createProduct(['price' => 50.00, 'cost_price' => 30.00, 'stock_quantity' => 50]);

        $sale = $this->service->createSale([
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 2, 'unit_price' => 100.00],
                ['product_id' => $productB->id, 'quantity' => 1, 'unit_price' => 50.00],
            ],
        ], $user);

        $this->assertEquals(250.00, (float) $sale->subtotal);
        $this->assertEquals(250.00, (float) $sale->total_amount);
        $this->assertEquals(2, $sale->items->count());
    }

    public function test_create_sale_calculates_line_discount(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct(['price' => 100.00, 'cost_price' => 60.00, 'stock_quantity' => 50]);

        $sale = $this->service->createSale([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100.00, 'discount_percent' => 10],
            ],
        ], $user);

        // 100 - 10% = 90
        $this->assertEquals(90.00, (float) $sale->subtotal);
        $this->assertEquals(90.00, (float) $sale->total_amount);
    }

    public function test_create_sale_deducts_stock(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct(['stock_quantity' => 50]);

        $this->service->createSale([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 100.00],
            ],
        ], $user);

        $this->assertEquals(47, $product->fresh()->stock_quantity);
    }

    public function test_create_sale_calculates_gross_profit(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct(['price' => 100.00, 'cost_price' => 60.00, 'stock_quantity' => 50]);

        $sale = $this->service->createSale([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100.00],
            ],
        ], $user);

        // total = 200, cost = 2 * 60 = 120, profit = 80
        $this->assertEquals(200.00, (float) $sale->total_amount);
        $this->assertEquals(80.00, (float) $sale->gross_profit);
    }

    public function test_create_sale_throws_on_insufficient_stock(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct(['stock_quantity' => 2]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->service->createSale([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 100.00],
            ],
        ], $user);
    }

    public function test_create_sale_generates_sale_number(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct();

        $sale = $this->service->createSale([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100.00],
            ],
        ], $user);

        $this->assertMatchesRegularExpression('/^SALE-\d{4}-\d{4}$/', $sale->sale_number);
    }

    public function test_record_payment_updates_amount_paid(): void
    {
        $user = User::factory()->create();
        $sale = Sale::factory()->create([
            'total_amount' => 500.00,
            'amount_paid' => 0,
            'amount_due' => 500.00,
            'payment_status' => 'unpaid',
        ]);

        $this->service->recordPayment($sale, 300.00, $user);

        $sale->refresh();
        $this->assertEquals(300.00, (float) $sale->amount_paid);
        $this->assertEquals(200.00, (float) $sale->amount_due);
        $this->assertEquals('partially_paid', $sale->payment_status);
    }

    public function test_record_payment_marks_as_paid_when_fully_paid(): void
    {
        $user = User::factory()->create();
        $sale = Sale::factory()->create([
            'total_amount' => 500.00,
            'amount_paid' => 0,
            'amount_due' => 500.00,
            'payment_status' => 'unpaid',
        ]);

        $this->service->recordPayment($sale, 500.00, $user);

        $sale->refresh();
        $this->assertEquals('paid', $sale->payment_status);
        $this->assertEquals(0, (float) $sale->amount_due);
    }

    public function test_record_payment_throws_on_overpayment(): void
    {
        $user = User::factory()->create();
        $sale = Sale::factory()->create([
            'total_amount' => 500.00,
            'amount_paid' => 0,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('exceeds total');

        $this->service->recordPayment($sale, 600.00, $user);
    }

    public function test_record_payment_throws_on_zero_amount(): void
    {
        $user = User::factory()->create();
        $sale = Sale::factory()->create(['total_amount' => 500.00]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('must be greater than zero');

        $this->service->recordPayment($sale, 0, $user);
    }

    public function test_product_price_change_does_not_affect_completed_sale(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct(['price' => 100.00, 'cost_price' => 60.00, 'stock_quantity' => 50]);

        $sale = $this->service->createSale([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100.00],
            ],
        ], $user);

        // Sale item should have the price at sale time
        $this->assertEquals(100.00, (float) $sale->items->first()->unit_price);

        // Change product price after the sale
        $product->update(['price' => 200.00]);

        // Sale item price should remain unchanged
        $this->assertEquals(100.00, (float) $sale->fresh()->items->first()->unit_price);
    }
}