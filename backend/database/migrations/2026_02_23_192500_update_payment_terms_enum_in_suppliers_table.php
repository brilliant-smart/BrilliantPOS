<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL doesn't support direct ENUM modification, so we need to use ALTER TABLE
        DB::statement("
            ALTER TABLE suppliers 
            MODIFY COLUMN payment_terms ENUM(
                'cash',
                'bank_transfer',
                'cheque',
                'card',
                'credit',
                'credit_7',
                'credit_14',
                'credit_30',
                'credit_60',
                'custom'
            ) NOT NULL DEFAULT 'cash'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First update any new values to 'cash' before rolling back
        DB::statement("
            UPDATE suppliers 
            SET payment_terms = 'cash'
            WHERE payment_terms NOT IN ('cash', 'credit_7', 'credit_14', 'credit_30', 'credit_60', 'custom')
        ");
        
        // Restore original ENUM values
        DB::statement("
            ALTER TABLE suppliers 
            MODIFY COLUMN payment_terms ENUM(
                'cash',
                'credit_7',
                'credit_14',
                'credit_30',
                'credit_60',
                'custom'
            ) NOT NULL DEFAULT 'cash'
        ");
    }
};
