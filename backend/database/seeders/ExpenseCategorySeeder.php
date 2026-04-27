<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ExpenseCategory;
use Illuminate\Support\Str;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Seed default expense categories for shop operations.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Utilities',
                'description' => 'Electricity, water, internet, phone bills',
                'icon' => 'Zap',
                'color' => '#F59E0B', // Amber
            ],
            [
                'name' => 'Maintenance & Repairs',
                'description' => 'Plumbing, electrical work, painting, equipment repairs',
                'icon' => 'Wrench',
                'color' => '#EF4444', // Red
            ],
            [
                'name' => 'Cleaning Supplies',
                'description' => 'Detergents, mops, brooms, disinfectants, cleaning equipment',
                'icon' => 'Sparkles',
                'color' => '#06B6D4', // Cyan
            ],
            [
                'name' => 'Staff Welfare',
                'description' => 'Staff meals, refreshments, tea, coffee',
                'icon' => 'Users',
                'color' => '#8B5CF6', // Purple
            ],
            [
                'name' => 'Fuel & Transportation',
                'description' => 'Generator fuel, vehicle fuel, delivery costs',
                'icon' => 'Fuel',
                'color' => '#059669', // Green
            ],
            [
                'name' => 'Office Supplies',
                'description' => 'Stationery, printer ink, paper, pens',
                'icon' => 'FileText',
                'color' => '#3B82F6', // Blue
            ],
            [
                'name' => 'Light Bulbs & Fixtures',
                'description' => 'Replacement bulbs, light fixtures, electrical fittings',
                'icon' => 'Lightbulb',
                'color' => '#FBBF24', // Yellow
            ],
            [
                'name' => 'Security',
                'description' => 'Security guards, CCTV maintenance, alarm systems',
                'icon' => 'Shield',
                'color' => '#DC2626', // Red
            ],
            [
                'name' => 'Rent & Lease',
                'description' => 'Shop rent, storage rent, equipment lease',
                'icon' => 'Home',
                'color' => '#7C3AED', // Violet
            ],
            [
                'name' => 'Marketing & Advertising',
                'description' => 'Flyers, banners, social media ads, promotions',
                'icon' => 'Megaphone',
                'color' => '#EC4899', // Pink
            ],
            [
                'name' => 'Equipment',
                'description' => 'Computers, furniture, fixtures, appliances',
                'icon' => 'Monitor',
                'color' => '#6366F1', // Indigo
            ],
            [
                'name' => 'Professional Services',
                'description' => 'Accounting, legal fees, consulting',
                'icon' => 'Briefcase',
                'color' => '#0891B2', // Cyan
            ],
            [
                'name' => 'Insurance',
                'description' => 'Shop insurance, product insurance, liability coverage',
                'icon' => 'ShieldCheck',
                'color' => '#10B981', // Emerald
            ],
            [
                'name' => 'Bank Charges',
                'description' => 'Transaction fees, account maintenance, transfer charges',
                'icon' => 'CreditCard',
                'color' => '#F97316', // Orange
            ],
            [
                'name' => 'Miscellaneous',
                'description' => 'Other operational expenses not categorized',
                'icon' => 'MoreHorizontal',
                'color' => '#6B7280', // Gray
            ],
        ];

        foreach ($categories as $category) {
            ExpenseCategory::create([
                'name' => $category['name'],
                'slug' => Str::slug($category['name']),
                'description' => $category['description'],
                'icon' => $category['icon'],
                'color' => $category['color'],
                'is_active' => true,
            ]);
        }
    }
}
