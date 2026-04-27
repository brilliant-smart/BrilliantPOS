<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add batch tracking fields to purchase_order_items table
     */
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->string('batch_number')->nullable()->after('notes');
            $table->date('manufacturing_date')->nullable()->after('batch_number');
            $table->date('expiry_date')->nullable()->after('manufacturing_date');
            
            // Index for batch tracking
            $table->index('batch_number');
            $table->index('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropIndex(['batch_number']);
            $table->dropIndex(['expiry_date']);
            $table->dropColumn(['batch_number', 'manufacturing_date', 'expiry_date']);
        });
    }
};
