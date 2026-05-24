<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') { return; }

        // MySQL 8.0.16+ supports CHECK constraints. These enforce data integrity
        // at the database level, complementing application-level validation.

        // Products stock_quantity cannot go negative
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_stock_nonnegative CHECK (stock_quantity >= 0)');

        // Sale item quantities must be positive
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_item_quantity_positive CHECK (quantity > 0)');

        // Payment amounts must be positive
        DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_payment_amount_positive CHECK (amount > 0)');

        // Product prices must be non-negative
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_price_nonnegative CHECK (price >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_cost_price_nonnegative CHECK (cost_price >= 0)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') { return; }

        DB::statement('ALTER TABLE products DROP CONSTRAINT chk_products_stock_nonnegative');
        DB::statement('ALTER TABLE sale_items DROP CONSTRAINT chk_sale_item_quantity_positive');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT chk_payment_amount_positive');
        DB::statement('ALTER TABLE products DROP CONSTRAINT chk_products_price_nonnegative');
        DB::statement('ALTER TABLE products DROP CONSTRAINT chk_products_cost_price_nonnegative');
    }
};