<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'quantity' => 1,
            'unit_type' => 'piece',
            'conversion_factor' => 1,
            'unit_price' => 100.00,
            'unit_cost' => 60.00,
            'discount_percent' => 0,
            'line_total' => 100.00,
            'line_cost' => 60.00,
            'line_profit' => 40.00,
        ];
    }
}