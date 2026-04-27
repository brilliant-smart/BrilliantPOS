<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Track automatic reorder suggestions and actions
     */
    public function up(): void
    {
        Schema::create('auto_reorder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('current_stock');
            $table->integer('reorder_point');
            $table->integer('suggested_quantity');
            $table->foreignId('suggested_supplier_id')->nullable()->constrained('suppliers')->onDelete('set null');
            $table->enum('action_taken', ['po_created', 'notification_sent', 'ignored', 'manual_override'])->default('notification_sent');
            $table->foreignId('purchase_order_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->onDelete('set null'); // null if automatic
            $table->timestamp('triggered_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['product_id', 'triggered_at']);
            $table->index('action_taken');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_reorder_logs');
    }
};
