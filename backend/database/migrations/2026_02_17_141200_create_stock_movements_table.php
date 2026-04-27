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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->enum('type', ['purchase', 'sale', 'adjustment', 'damage', 'return', 'initial']);
            $table->integer('quantity'); // Positive for additions, negative for deductions
            $table->integer('previous_stock');
            $table->integer('new_stock');
            $table->text('notes')->nullable();
            $table->decimal('unit_cost', 10, 2)->nullable(); // For purchase tracking
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index(['product_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
