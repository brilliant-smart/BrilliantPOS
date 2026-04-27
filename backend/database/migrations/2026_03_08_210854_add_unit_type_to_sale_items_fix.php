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
        // Check if column exists before adding
        if (!Schema::hasColumn('sale_items', 'unit_type')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->enum('unit_type', ['piece', 'carton', 'box', 'pack', 'dozen', 'kg', 'liter', 'meter'])
                    ->default('piece')
                    ->after('unit_price');
            });
        }
        
        // Also check purchase_order_items
        if (!Schema::hasColumn('purchase_order_items', 'unit_type')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->enum('unit_type', ['piece', 'carton', 'box', 'pack', 'dozen', 'kg', 'liter', 'meter'])
                    ->default('piece')
                    ->after('unit_cost');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_items', 'unit_type')) {
                $table->dropColumn('unit_type');
            }
        });
        
        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_items', 'unit_type')) {
                $table->dropColumn('unit_type');
            }
        });
    }
};
