<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductBatchFactory extends Factory
{
    protected $model = ProductBatch::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'batch_number' => 'BTH-' . Str::random(6),
            'quantity_received' => fake()->numberBetween(10, 200),
            'quantity_remaining' => fake()->numberBetween(1, 200),
            'cost_price' => fake()->randomFloat(2, 50, 500),
            'selling_price' => fake()->randomFloat(2, 100, 1000),
            'expiry_date' => now()->addDays(fake()->numberBetween(30, 730)),
            'manufacturing_date' => now()->subDays(fake()->numberBetween(30, 365)),
            'status' => 'active',
        ];
    }
}