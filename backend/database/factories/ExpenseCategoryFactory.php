<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Utilities', 'Rent', 'Maintenance', 'Office Supplies',
            'Transportation', 'Marketing', 'Insurance', 'Salaries',
            'Repairs', 'Miscellaneous',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'icon' => fake()->randomElement(['tag', 'receipt', 'wrench', 'truck', 'briefcase']),
            'color' => '#' . fake()->hexColor(),
            'is_active' => true,
        ];
    }
}