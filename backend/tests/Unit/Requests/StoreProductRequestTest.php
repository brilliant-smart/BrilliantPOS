<?php

namespace Tests\Unit\Requests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreProductRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validate(array $data): \Illuminate\Contracts\Validation\Validator
    {
        $rules = (new \App\Http\Requests\StoreProductRequest)->rules();

        return Validator::make($data, $rules);
    }

    public function test_valid_product_data_passes(): void
    {
        $validator = $this->validate([
            'name' => 'Test Product',
            'sku' => 'TP-001',
            'price' => 100,
            'cost_price' => 60,
            'stock_quantity' => 50,
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_name_required(): void
    {
        $validator = $this->validate([
            'sku' => 'TP-001',
            'price' => 100,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_price_required(): void
    {
        $validator = $this->validate([
            'name' => 'Test Product',
            'sku' => 'TP-001',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('price', $validator->errors()->toArray());
    }

    public function test_negative_price_rejected(): void
    {
        $validator = $this->validate([
            'name' => 'Test Product',
            'price' => -10,
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_expiry_date_must_be_future(): void
    {
        $validator = $this->validate([
            'name' => 'Test Product',
            'price' => 100,
            'expiry_date' => '2020-01-01',
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_duplicate_sku_rejected(): void
    {
        $this->createProduct(['sku' => 'UNIQUE-001']);

        $validator = $this->validate([
            'name' => 'Duplicate SKU',
            'sku' => 'UNIQUE-001',
            'price' => 100,
        ]);

        $this->assertTrue($validator->fails());
    }
}