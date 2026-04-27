<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add 'bank_transfer' to the sale_type enum to support POS transfer payments.
     * The POS system offers: cash, credit, pos, bank_transfer (online is removed).
     */
    public function up(): void
    {
        // MySQL doesn't support direct enum modification, need to use raw SQL
        DB::statement("ALTER TABLE sales MODIFY COLUMN sale_type ENUM('cash', 'credit', 'online', 'pos', 'bank_transfer') NOT NULL DEFAULT 'cash'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove bank_transfer from enum (only if no records use it)
        DB::statement("ALTER TABLE sales MODIFY COLUMN sale_type ENUM('cash', 'credit', 'online', 'pos') NOT NULL DEFAULT 'cash'");
    }
};
