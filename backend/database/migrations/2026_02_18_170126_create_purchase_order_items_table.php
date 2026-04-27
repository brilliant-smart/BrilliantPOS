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
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            
            $table->integer('quantity_ordered');
            $table->integer('quantity_received')->default(0);
            
            $table->decimal('unit_cost', 12, 2); // Cost per unit (VAT inclusive)
            $table->decimal('vat_rate', 5, 2)->default(0); // VAT included in price
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('line_total', 15, 2); // quantity * unit_cost * (1 - discount)
            
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('purchase_order_id');
            $table->index('product_id');
            $table->index(['purchase_order_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
