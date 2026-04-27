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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2); // Selling price (VAT inclusive)
            $table->decimal('unit_cost', 12, 2); // Cost price (for profit calculation)
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('line_total', 15, 2); // quantity * unit_price * (1 - discount)
            $table->decimal('line_cost', 15, 2); // quantity * unit_cost
            $table->decimal('line_profit', 15, 2); // line_total - line_cost
            
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('sale_id');
            $table->index('product_id');
            $table->index(['sale_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
