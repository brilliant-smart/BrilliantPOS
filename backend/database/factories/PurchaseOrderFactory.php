<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'po_number' => 'PO-' . date('Y') . '-' . str_pad(fake()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'supplier_id' => Supplier::factory(),
            'created_by' => User::factory(),
            'status' => 'draft',
            'order_date' => now()->toDateString(),
            'subtotal' => 0,
            'vat_amount' => 0,
            'discount_amount' => 0,
            'shipping_cost' => 0,
            'total_amount' => 0,
            'payment_status' => 'unpaid',
            'payment_method' => 'credit',
            'amount_paid' => 0,
        ];
    }
}