<?php

namespace Tests\Unit\Requests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CompleteSaleRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validate(array $data): \Illuminate\Contracts\Validation\Validator
    {
        $rules = (new \App\Http\Requests\CompleteSaleRequest)->rules();

        return Validator::make($data, $rules);
    }

    public function test_valid_sale_data_passes(): void
    {
        $product = $this->createProduct();

        $validator = $this->validate([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 200],
            ],
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_items_required(): void
    {
        $validator = $this->validate([
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('items', $validator->errors()->toArray());
    }

    public function test_items_must_have_at_least_one(): void
    {
        $validator = $this->validate([
            'items' => [],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_payments_required(): void
    {
        $product = $this->createProduct();

        $validator = $this->validate([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('payments', $validator->errors()->toArray());
    }

    public function test_negative_quantity_rejected(): void
    {
        $product = $this->createProduct();

        $validator = $this->validate([
            'items' => [
                ['product_id' => $product->id, 'quantity' => -1, 'unit_price' => 100],
            ],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_invalid_payment_method_rejected(): void
    {
        $product = $this->createProduct();

        $validator = $this->validate([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100],
            ],
            'payments' => [['method' => 'bitcoin', 'amount' => 100]],
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_discount_percentage_capped_at_100(): void
    {
        $product = $this->createProduct();

        $validator = $this->validate([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100],
            ],
            'payments' => [['method' => 'cash', 'amount' => 100]],
            'discount_percentage' => 150,
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_nonexistent_product_rejected(): void
    {
        $validator = $this->validate([
            'items' => [
                ['product_id' => 99999, 'quantity' => 1, 'unit_price' => 100],
            ],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ]);

        $this->assertTrue($validator->fails());
    }
}