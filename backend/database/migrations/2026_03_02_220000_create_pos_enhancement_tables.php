<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * POS Enhancement: Held Carts, Payments, Sale Status
     */
    public function up(): void
    {
        // Held Carts Table - for Hold & Recall functionality
        Schema::create('held_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->json('items'); // Cart items as JSON
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('reference', 50)->unique(); // e.g., HOLD-20260302-001
            $table->text('notes')->nullable();
            $table->timestamp('held_at');
            $table->timestamp('recalled_at')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('held_at');
            $table->index('reference');
        });

        // Payments Table - for split payment support
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->onDelete('cascade');
            $table->enum('method', ['cash', 'card', 'pos', 'credit', 'bank_transfer'])->default('cash');
            $table->decimal('amount', 10, 2);
            $table->string('reference', 100)->nullable(); // Card/POS transaction reference
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('sale_id');
            $table->index('method');
        });

        // Update sales table for void functionality and status
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('status', ['completed', 'voided', 'on_hold'])->default('completed')->after('payment_status');
            $table->foreignId('voided_by')->nullable()->constrained('users')->onDelete('set null')->after('status');
            $table->text('void_reason')->nullable()->after('voided_by');
            $table->timestamp('voided_at')->nullable()->after('void_reason');
            
            $table->index('status');
            $table->index('voided_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropColumn(['status', 'voided_by', 'void_reason', 'voided_at']);
        });

        Schema::dropIfExists('payments');
        Schema::dropIfExists('held_carts');
    }
};
