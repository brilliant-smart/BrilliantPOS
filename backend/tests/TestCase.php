<?php

namespace Tests;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable FK and CHECK constraints on SQLite for enum/compatibility
        if (\DB::getDriverName() === 'sqlite') {
            \DB::statement('PRAGMA foreign_keys = OFF');
            \DB::statement('PRAGMA ignore_check_constraints = ON');
        }
    }

    protected function actingAsOwner(): static
    {
        return $this->actingAs(User::factory()->create(['role' => 'owner', 'is_active' => true]), 'sanctum');
    }

    protected function actingAsManager(): static
    {
        return $this->actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]), 'sanctum');
    }

    protected function actingAsCashier(): static
    {
        return $this->actingAs(User::factory()->create(['role' => 'cashier', 'is_active' => true]), 'sanctum');
    }

    protected function createProduct(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'price' => 100.00,
            'cost_price' => 60.00,
            'stock_quantity' => 50,
            'is_active' => true,
        ], $overrides));
    }
}