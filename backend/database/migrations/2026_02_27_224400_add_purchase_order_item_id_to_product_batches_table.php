<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add purchase_order_item_id to product_batches table for better tracking
     */
    public function up(): void
    {
        Schema::table('product_batches', function (Blueprint $table) {
            $table->foreignId('purchase_order_item_id')->nullable()->after('purchase_order_id')->constrained()->onDelete('set null');
            
            // Index for performance
            $table->index('purchase_order_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_batches', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_item_id']);
            $table->dropIndex(['purchase_order_item_id']);
            $table->dropColumn('purchase_order_item_id');
        });
    }
};
