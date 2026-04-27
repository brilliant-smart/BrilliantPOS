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
        // Remove the global unique constraint on barcode
        Schema::table('product_barcodes', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
        });

        // Add a regular index for fast lookups (not unique)
        Schema::table('product_barcodes', function (Blueprint $table) {
            $table->index('barcode');
        });

        // Clean up orphaned barcodes from soft-deleted products
        DB::table('product_barcodes')
            ->join('products', 'product_barcodes.product_id', '=', 'products.id')
            ->whereNotNull('products.deleted_at')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_barcodes', function (Blueprint $table) {
            $table->dropIndex(['barcode']);
            $table->unique('barcode');
        });
    }
};