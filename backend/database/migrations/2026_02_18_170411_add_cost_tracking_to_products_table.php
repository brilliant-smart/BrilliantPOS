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
            // Cost tracking
            $table->decimal('cost_price', 12, 2)->default(0)->after('price'); // Average cost price
            $table->decimal('last_purchase_price', 12, 2)->nullable()->after('cost_price'); // Last purchase cost
            
            // Pharmacy specific
            $table->date('expiry_date')->nullable()->after('low_stock_threshold');
            $table->string('batch_number')->nullable()->after('expiry_date');
            
            // Additional tracking
            $table->integer('reorder_point')->default(10)->after('batch_number'); // Auto-suggest reorder
            $table->integer('max_stock_level')->nullable()->after('reorder_point');
            
            // Indexes
            $table->index('expiry_date');
            $table->index('cost_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['expiry_date']);
            $table->dropIndex(['cost_price']);
            $table->dropColumn([
                'cost_price',
                'last_purchase_price',
                'expiry_date',
                'batch_number',
                'reorder_point',
                'max_stock_level'
            ]);
        });
    }
};
