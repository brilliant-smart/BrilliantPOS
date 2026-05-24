<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 100, 10000),
            'payment_method' => 'cash',
            'recorded_by' => User::factory(),
            'expense_date' => now(),
            'vendor' => fake()->company(),
        ];
    }
}