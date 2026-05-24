<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'sale_number' => 'SALE-' . date('Y') . '-' . str_pad(fake()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'cashier_id' => User::factory(),
            'status' => 'completed',
            'payment_status' => 'paid',
            'sale_type' => 'pos',
            'subtotal' => 100.00,
            'vat_amount' => 0,
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'total_amount' => 100.00,
            'amount_paid' => 100.00,
            'amount_due' => 0,
            'cost_of_goods_sold' => 60.00,
            'gross_profit' => 40.00,
            'sale_date' => now(),
        ];
    }
}