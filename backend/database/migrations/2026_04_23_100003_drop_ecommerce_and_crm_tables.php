<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Disable FK checks to drop tables with circular/referencing constraints
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $tables = [
            // Leaf tables first (no other tables in this list depend on them)
            'email_campaigns',
            'customer_notes',
            'coupon_usages',
            'loyalty_transactions',
            'customer_loyalty_points',
            'product_reviews',
            'order_items',
            'wishlists',
            'cart_items',
            'prescription_items',
            'controlled_substance_logs',
            // Mid-level (referenced by leaf tables above)
            'coupons',
            'orders',
            'prescriptions',
            'customer_addresses',
            'customer_segments',
            'customer_segment_members',
            'crm_settings',
            'ecommerce_settings',
            // Root tables (referenced by most others)
            'customers',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        // Remove customer_id from held_carts
        if (Schema::hasColumn('held_carts', 'customer_id')) {
            Schema::table('held_carts', function ($table) {
                try {
                    $table->dropForeign('held_carts_customer_id_foreign');
                } catch (\Exception $e) {}
                $table->dropColumn('customer_id');
            });
        }

        // Remove customer_id from sales
        if (Schema::hasColumn('sales', 'customer_id')) {
            Schema::table('sales', function ($table) {
                try {
                    $table->dropForeign('sales_customer_id_foreign');
                } catch (\Exception $e) {}
                $table->dropColumn('customer_id');
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        // This migration is not reversible
    }
};