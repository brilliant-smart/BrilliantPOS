<?php

namespace Tests\Unit\Requests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateProductRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validate(array $data): \Illuminate\Contracts\Validation\Validator
    {
        $rules = (new \App\Http\Requests\UpdateProductRequest)->rules();

        return Validator::make($data, $rules);
    }

    public function test_partial_update_passes(): void
    {
        $validator = $this->validate([
            'name' => 'Updated Product',
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_negative_price_rejected(): void
    {
        $validator = $this->validate([
            'price' => -10,
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_negative_stock_rejected(): void
    {
        $validator = $this->validate([
            'stock_quantity' => -5,
        ]);

        $this->assertTrue($validator->fails());
    }
}