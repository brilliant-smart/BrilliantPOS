<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = 'Test Product ' . Str::random(6);
        return [
            'name' => $name,
            'sku' => 'SKU-' . Str::random(8),
            'price' => fake()->randomFloat(2, 50, 500),
            'cost_price' => fake()->randomFloat(2, 10, 80),
            'stock_quantity' => fake()->numberBetween(10, 100),
            'low_stock_threshold' => 5,
            'is_active' => true,
            'unit_type' => 'piece',
        ];
    }
}