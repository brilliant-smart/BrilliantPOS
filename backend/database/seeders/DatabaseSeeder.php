<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Owner
        User::firstOrCreate(
            ['email' => 'admin@brilliantpos.com'],
            [
                'name' => 'Owner',
                'password' => Hash::make('password'),
                'role' => 'owner',
                'is_active' => true,
            ]
        );

        // Create Manager
        User::firstOrCreate(
            ['email' => 'manager@brilliantpos.com'],
            [
                'name' => 'Manager',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'is_active' => true,
            ]
        );

        // Create Cashier
        User::firstOrCreate(
            ['email' => 'cashier@brilliantpos.com'],
            [
                'name' => 'Cashier',
                'password' => Hash::make('password'),
                'role' => 'cashier',
                'is_active' => true,
            ]
        );

        // Create sample suppliers
        $supplier1 = Supplier::firstOrCreate(
            ['code' => 'SUP-001'],
            [
            'name' => 'MedSupply Nigeria Ltd',
            'contact_person' => 'John Okafor',
            'email' => 'contact@medsupply.ng',
            'phone' => '+234 803 123 4567',
            'address' => '12 Medical Road, Ikeja, Lagos',
            'city' => 'Lagos',
            'state' => 'Lagos',
            'payment_terms' => 'credit_30',
            'is_active' => true,
            ]
        );

        $supplier2 = Supplier::firstOrCreate(
            ['code' => 'SUP-002'],
            [
            'name' => 'Global Foods Distribution',
            'contact_person' => 'Mary Adeyemi',
            'email' => 'sales@globalfoods.ng',
            'phone' => '+234 805 987 6543',
            'address' => '45 Commerce Avenue, VI, Lagos',
            'city' => 'Lagos',
            'state' => 'Lagos',
            'payment_terms' => 'credit_14',
            'is_active' => true,
            ]
        );

        // Create sample products
        Product::firstOrCreate(
            ['sku' => 'PHM-PAR-500'],
            [
                'name' => 'Paracetamol 500mg (Pack of 100)',
                'slug' => 'paracetamol-500mg',
                'description' => 'Pain relief and fever reducer',
                'price' => 2500.00,
                'cost_price' => 1800.00,
                'last_purchase_price' => 1800.00,
                'stock_quantity' => 150,
                'low_stock_threshold' => 20,
                'reorder_point' => 30,
                'max_stock_level' => 300,
                'is_active' => true,
                'is_featured' => true,
            ]
        );

        Product::firstOrCreate(
            ['sku' => 'SS-GP-SEM-2'],
            [
                'name' => 'Golden Penny Semovita 2kg',
                'slug' => 'golden-penny-semovita-2kg',
                'description' => 'Premium quality semovita',
                'price' => 3200.00,
                'cost_price' => 2400.00,
                'last_purchase_price' => 2400.00,
                'stock_quantity' => 80,
                'low_stock_threshold' => 15,
                'reorder_point' => 20,
                'max_stock_level' => 200,
                'is_active' => true,
                'is_featured' => false,
            ]
        );

        $this->command->info('Database seeded successfully!');
        $this->command->info('Login credentials:');
        $this->command->info('Owner: admin@brilliantpos.com / password');
        $this->command->info('Manager: manager@brilliantpos.com / password');
        $this->command->info('Cashier: cashier@brilliantpos.com / password');
    }
}