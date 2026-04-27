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
            // Drop the existing unique constraint on slug
            $table->dropUnique('products_slug_unique');

            // Create a new composite unique constraint that ignores soft-deleted records
            // This allows the same slug to be reused after a product is soft-deleted
            $table->unique(['slug', 'deleted_at'], 'products_slug_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique('products_slug_unique');

            // Restore the original simple unique constraint on slug
            $table->unique('slug', 'products_slug_unique');
        });
    }
};
