<?php

namespace Tests\Unit\Requests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CreatePurchaseOrderRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validate(array $data): \Illuminate\Contracts\Validation\Validator
    {
        $rules = (new \App\Http\Requests\CreatePurchaseOrderRequest)->rules();

        return Validator::make($data, $rules);
    }

    public function test_valid_po_data_passes(): void
    {
        $supplier = \App\Models\Supplier::factory()->create(['is_active' => true]);
        $product = $this->createProduct();

        $validator = $this->validate([
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 50],
            ],
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_supplier_id_required(): void
    {
        $product = $this->createProduct();

        $validator = $this->validate([
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 50],
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('supplier_id', $validator->errors()->toArray());
    }

    public function test_items_required(): void
    {
        $supplier = \App\Models\Supplier::factory()->create(['is_active' => true]);

        $validator = $this->validate([
            'supplier_id' => $supplier->id,
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_invalid_payment_method_rejected(): void
    {
        $supplier = \App\Models\Supplier::factory()->create(['is_active' => true]);
        $product = $this->createProduct();

        $validator = $this->validate([
            'supplier_id' => $supplier->id,
            'payment_method' => 'invalid_method',
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 50],
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('payment_method', $validator->errors()->toArray());
    }

    public function test_delivery_date_must_be_after_order_date(): void
    {
        $supplier = \App\Models\Supplier::factory()->create(['is_active' => true]);
        $product = $this->createProduct();

        $validator = $this->validate([
            'supplier_id' => $supplier->id,
            'order_date' => '2026-06-15',
            'expected_delivery_date' => '2026-06-01',
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 50],
            ],
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_discount_percent_capped_at_100(): void
    {
        $supplier = \App\Models\Supplier::factory()->create(['is_active' => true]);
        $product = $this->createProduct();

        $validator = $this->validate([
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 50, 'discount_percent' => 150],
            ],
        ]);

        $this->assertTrue($validator->fails());
    }
}