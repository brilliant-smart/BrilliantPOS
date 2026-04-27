<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add unit_type to products table
        Schema::table('products', function (Blueprint $table) {
            $table->enum('unit_type', ['piece', 'carton', 'box', 'pack', 'dozen', 'kg', 'liter', 'meter'])
                  ->default('piece')
                  ->after('stock_quantity');
        });

        // Add unit_type to purchase_order_items table
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->enum('unit_type', ['piece', 'carton', 'box', 'pack', 'dozen', 'kg', 'liter', 'meter'])
                  ->default('piece')
                  ->after('quantity_received');
        });

        // Add unit_type to sale_items table
        Schema::table('sale_items', function (Blueprint $table) {
            $table->enum('unit_type', ['piece', 'carton', 'box', 'pack', 'dozen', 'kg', 'liter', 'meter'])
                  ->default('piece')
                  ->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('unit_type');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('unit_type');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('unit_type');
        });
    }
};
