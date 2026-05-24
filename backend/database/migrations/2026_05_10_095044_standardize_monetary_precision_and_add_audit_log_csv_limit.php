<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite does not support ALTER TABLE CHANGE COLUMN
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Standardize monetary columns from decimal(10,2) to decimal(15,2)
        // to prevent overflow on large transaction amounts

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 15, 2)->change();
            $table->decimal('cost_price', 15, 2)->change();
            $table->decimal('last_purchase_price', 15, 2)->nullable()->change();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('subtotal', 15, 2)->change();
            $table->decimal('discount_amount', 15, 2)->change();
            $table->decimal('total_amount', 15,  2)->change();
            $table->decimal('amount_paid', 15, 2)->change();
            $table->decimal('amount_due', 15, 2)->change();
            $table->decimal('cost_of_goods_sold', 15, 2)->change();
            $table->decimal('gross_profit', 15, 2)->change();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 2)->change();
            $table->decimal('line_total', 15, 2)->change();
            $table->decimal('unit_cost', 15, 2)->change();
            $table->decimal('line_cost', 15, 2)->change();
            $table->decimal('line_profit', 15, 2)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });

        Schema::table('held_carts', function (Blueprint $table) {
            $table->decimal('discount_amount', 15, 2)->change();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('shipping_cost', 15, 2)->change();
            $table->decimal('discount_amount', 15, 2)->change();
            $table->decimal('total_amount', 15, 2)->change();
            $table->decimal('amount_paid', 15, 2)->change();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->change();
            $table->decimal('line_total', 15, 2)->change();
        });

        // product_price_history columns are nullable and may have data truncation issues
        // Leave them at decimal(10,2) since they're historical records, not transactional
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->change();
            $table->decimal('cost_price', 10, 2)->change();
            $table->decimal('last_purchase_price', 10, 2)->nullable()->change();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('subtotal', 10, 2)->change();
            $table->decimal('discount_amount', 10, 2)->change();
            $table->decimal('total_amount', 10, 2)->change();
            $table->decimal('amount_paid', 10, 2)->change();
            $table->decimal('amount_due', 10, 2)->change();
            $table->decimal('cost_of_goods_sold', 10, 2)->change();
            $table->decimal('gross_profit', 10, 2)->change();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->change();
            $table->decimal('line_total', 10, 2)->change();
            $table->decimal('unit_cost', 10, 2)->change();
            $table->decimal('line_cost', 10, 2)->change();
            $table->decimal('line_profit', 10, 2)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
        });

        Schema::table('held_carts', function (Blueprint $table) {
            $table->decimal('discount_amount', 10, 2)->change();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('shipping_cost', 10, 2)->change();
            $table->decimal('discount_amount', 10, 2)->change();
            $table->decimal('total_amount', 10, 2)->change();
            $table->decimal('amount_paid', 10, 2)->change();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 10, 2)->change();
            $table->decimal('total_cost', 10, 2)->change();
        });

        Schema::table('product_price_history', function (Blueprint $table) {
            $table->decimal('old_price', 10, 2)->change();
            $table->decimal('new_price', 10, 2)->change();
        });
    }
};