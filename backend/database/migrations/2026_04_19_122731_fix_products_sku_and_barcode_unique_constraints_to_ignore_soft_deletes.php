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
        Schema::table('products', function (Blueprint $table) {
            // Drop the existing unique constraints on sku and barcode
            $table->dropUnique('products_sku_unique');
            $table->dropUnique('products_barcode_unique');

            // Create new composite unique constraints that ignore soft-deleted records
            // This allows the same sku and barcode to be reused after a product is soft-deleted
            $table->unique(['sku', 'deleted_at'], 'products_sku_unique');
            $table->unique(['barcode', 'deleted_at'], 'products_barcode_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop the composite unique constraints
            $table->dropUnique('products_sku_unique');
            $table->dropUnique('products_barcode_unique');

            // Restore the original simple unique constraints
            $table->unique('sku', 'products_sku_unique');
            $table->unique('barcode', 'products_barcode_unique');
        });
    }
};
