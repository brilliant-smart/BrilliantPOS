<?php

namespace Tests\Unit\Services;

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
    private User $owner;
    private User $cashier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SaleService();
        $this->owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $this->product = Product::factory()->create([
            'is_active' => true,
            'price' => 100.00,
            'cost_price' => 60.00,
            'stock_quantity' => 50,
        ]);
    }

    private function saleData(array $overrides = []): array
    {
        return array_merge([
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 100.00,
                    'discount_percent' => 0,
                    'conversion_factor' => 1,
                ],
            ],
            'discount_amount' => 0,
            'amount_paid' => 200.00,
        ], $overrides);
    }

    // ---- createSale ----

    public function test_create_sale_stores_sale_and_items(): void
    {
        $sale = $this->service->createSale($this->saleData(), $this->owner);

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'cashier_id' => $this->owner->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);
    }

    public function test_create_sale_deducts_stock(): void
    {
        $this->service->createSale($this->saleData(), $this->owner);

        $this->assertEquals(48, $this->product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'type' => 'sale',
            'quantity' => -2,
        ]);
    }

    public function test_create_sale_with_conversion_factor(): void
    {
        $data = $this->saleData([
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'discount_percent' => 0,
                    'conversion_factor' => 12, // e.g. 1 case = 12 pieces
                ],
            ],
            'amount_paid' => 100.00,
        ]);

        $sale = $this->service->createSale($data, $this->owner);

        // Stock should be deducted by quantity * conversion_factor = 1 * 12 = 12
        $this->assertEquals(38, $this->product->fresh()->stock_quantity);
    }

    public function test_create_sale_insufficient_stock_throws_exception(): void
    {
        $data = $this->saleData([
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 100, // More than stock of 50
                    'unit_price' => 100.00,
                    'discount_percent' => 0,
                    'conversion_factor' => 1,
                ],
            ],
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->service->createSale($data, $this->owner);
    }

    public function test_create_sale_calculates_totals(): void
    {
        $data = $this->saleData([
            'discount_amount' => 50,
        ]);

        $sale = $this->service->createSale($data, $this->owner);

        // subtotal = 2 * 100 = 200, total = 200 - 50 = 150
        $this->assertEqualsWithDelta(200.00, (float) $sale->subtotal, 0.01);
        $this->assertEqualsWithDelta(150.00, (float) $sale->total_amount, 0.01);
        // gross_profit = total_amount - totalCost = 150 - 120 = 30
        $this->assertEqualsWithDelta(30.00, (float) $sale->gross_profit, 0.01);
    }

    public function test_create_sale_cashier_price_enforcement(): void
    {
        $data = $this->saleData([
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'unit_price' => 1.00, // Cashier tries to set price to 1
                    'discount_percent' => 0,
                    'conversion_factor' => 1,
                ],
            ],
            'amount_paid' => 100.00,
        ]);

        $sale = $this->service->createSale($data, $this->cashier);

        // Cashier's price override should be ignored — stored price used
        $this->assertEqualsWithDelta(100.00, (float) $sale->subtotal, 0.01);
    }

    public function test_create_sale_paid_status_when_fully_paid(): void
    {
        $data = $this->saleData([
            'amount_paid' => 200.00,
        ]);

        $sale = $this->service->createSale($data, $this->owner);

        $this->assertEquals('paid', $sale->payment_status);
        $this->assertEqualsWithDelta(0, (float) $sale->amount_due, 0.01);
    }

    public function test_create_sale_partially_paid_status(): void
    {
        $data = $this->saleData([
            'amount_paid' => 100.00,
        ]);

        $sale = $this->service->createSale($data, $this->owner);

        $this->assertEquals('partially_paid', $sale->payment_status);
        $this->assertEqualsWithDelta(100.00, (float) $sale->amount_due, 0.01);
    }

    public function test_create_sale_credit_type_sets_unpaid_when_zero_payment(): void
    {
        $data = $this->saleData([
            'sale_type' => 'credit',
            'amount_paid' => 0,
        ]);

        $sale = $this->service->createSale($data, $this->owner);

        $this->assertEquals('unpaid', $sale->payment_status);
    }

    public function test_create_sale_credit_type_partially_paid(): void
    {
        $data = $this->saleData([
            'sale_type' => 'credit',
            'amount_paid' => 50.00,
        ]);

        $sale = $this->service->createSale($data, $this->owner);

        $this->assertEquals('partially_paid', $sale->payment_status);
    }

    public function test_create_sale_creates_payment_record(): void
    {
        $data = $this->saleData(['amount_paid' => 200.00]);

        $sale = $this->service->createSale($data, $this->owner);

        $this->assertCount(1, $sale->payments);
        $this->assertEqualsWithDelta(200.00, (float) $sale->payments->first()->amount, 0.01);
    }

    public function test_create_sale_no_payment_when_zero_paid(): void
    {
        $data = $this->saleData(['amount_paid' => 0]);

        $sale = $this->service->createSale($data, $this->owner);

        $this->assertCount(0, $sale->payments);
    }

    // ---- recordPayment ----

    public function test_record_payment_marks_paid(): void
    {
        $sale = $this->service->createSale($this->saleData(['amount_paid' => 100.00]), $this->owner);

        $updated = $this->service->recordPayment($sale, 100.00, $this->owner, 'cash');

        $this->assertEquals('paid', $updated->payment_status);
        $this->assertEqualsWithDelta(200.00, (float) $updated->amount_paid, 0.01);
    }

    public function test_record_payment_marks_partially_paid(): void
    {
        $sale = $this->service->createSale($this->saleData(['amount_paid' => 50.00]), $this->owner);

        $updated = $this->service->recordPayment($sale, 50.00, $this->owner, 'cash');

        $this->assertEquals('partially_paid', $updated->payment_status);
    }

    public function test_record_payment_exceeds_total_throws_exception(): void
    {
        $sale = $this->service->createSale($this->saleData(['amount_paid' => 200.00]), $this->owner);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment amount exceeds total due');

        $this->service->recordPayment($sale, 1.00, $this->owner, 'cash');
    }

    public function test_record_payment_zero_amount_throws_exception(): void
    {
        $sale = $this->service->createSale($this->saleData(), $this->owner);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment amount must be greater than zero');

        $this->service->recordPayment($sale, 0, $this->owner);
    }

    public function test_record_payment_creates_payment_record_when_method_provided(): void
    {
        $sale = $this->service->createSale($this->saleData(['amount_paid' => 100.00]), $this->owner);

        $this->service->recordPayment($sale, 100.00, $this->owner, 'card', 'REF-123');

        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale->id,
            'method' => 'card',
            'reference' => 'REF-123',
        ]);
    }

    public function test_record_payment_no_payment_record_without_method(): void
    {
        $sale = $this->service->createSale($this->saleData(['amount_paid' => 100.00]), $this->owner);
        $paymentsBefore = $sale->payments()->count();

        $this->service->recordPayment($sale, 100.00, $this->owner);

        // No new payment record should be created when method is null
        $this->assertEquals($paymentsBefore, $sale->fresh()->payments()->count());
    }
}