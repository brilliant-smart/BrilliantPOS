<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') { return; }

        DB::statement("ALTER TABLE purchase_orders MODIFY COLUMN payment_method ENUM('cash','bank_transfer','cheque','card','credit','credit_7','credit_14','credit_30','credit_60') NOT NULL DEFAULT 'credit'");
        DB::statement("ALTER TABLE purchase_orders MODIFY COLUMN amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') { return; }

        DB::statement("ALTER TABLE purchase_orders MODIFY COLUMN payment_method ENUM('cash','bank_transfer','cheque','card','credit') NOT NULL DEFAULT 'credit'");
    }
};