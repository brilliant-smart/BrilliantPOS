<?php

namespace Tests\Unit\Requests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidateStockRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validate(array $data): \Illuminate\Contracts\Validation\Validator
    {
        $rules = (new \App\Http\Requests\ValidateStockRequest)->rules();

        return Validator::make($data, $rules);
    }

    public function test_valid_stock_data_passes(): void
    {
        $product = $this->createProduct();

        $validator = $this->validate([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_items_required(): void
    {
        $validator = $this->validate([]);

        $this->assertTrue($validator->fails());
    }

    public function test_quantity_must_be_positive(): void
    {
        $product = $this->createProduct();

        $validator = $this->validate([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 0],
            ],
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_nonexistent_product_rejected(): void
    {
        $validator = $this->validate([
            'items' => [
                ['product_id' => 99999, 'quantity' => 5],
            ],
        ]);

        $this->assertTrue($validator->fails());
    }
}