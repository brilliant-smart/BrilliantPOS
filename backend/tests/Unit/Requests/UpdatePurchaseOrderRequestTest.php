<?php

namespace Tests\Unit\Requests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdatePurchaseOrderRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validate(array $data): \Illuminate\Contracts\Validation\Validator
    {
        $rules = (new \App\Http\Requests\UpdatePurchaseOrderRequest)->rules();

        return Validator::make($data, $rules);
    }

    public function test_valid_update_data_passes(): void
    {
        $supplier = \App\Models\Supplier::factory()->create(['is_active' => true]);

        $validator = $this->validate([
            'supplier_id' => $supplier->id,
            'notes' => 'Updated notes',
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_nonexistent_supplier_rejected(): void
    {
        $validator = $this->validate([
            'supplier_id' => 99999,
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_negative_shipping_cost_rejected(): void
    {
        $validator = $this->validate([
            'shipping_cost' => -100,
        ]);

        $this->assertTrue($validator->fails());
    }
}