<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Track periodic inventory cycle counts for accuracy
     */
    public function up(): void
    {
        Schema::create('inventory_cycle_counts', function (Blueprint $table) {
            $table->id();
            $table->string('count_number')->unique();
            $table->foreignId('department_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('counted_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->enum('status', ['in_progress', 'completed', 'verified', 'cancelled'])->default('in_progress');
            $table->date('count_date');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('count_number');
            $table->index('status');
        });

        Schema::create('inventory_cycle_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_count_id')->constrained('inventory_cycle_counts')->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('batch_number')->nullable();
            $table->integer('expected_quantity');
            $table->integer('actual_quantity');
            $table->integer('variance')->storedAs('actual_quantity - expected_quantity');
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('variance_value', 15, 2)->storedAs('(actual_quantity - expected_quantity) * unit_cost');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['cycle_count_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_cycle_count_items');
        Schema::dropIfExists('inventory_cycle_counts');
    }
};
