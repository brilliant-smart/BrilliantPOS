<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Create expenses table for tracking shop operational expenses.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique(); // EXP-2026-0001
            $table->string('title'); // e.g., "Generator Fuel Purchase"
            $table->text('description')->nullable(); // Detailed notes
            $table->decimal('amount', 15, 2); // Expense amount
            $table->enum('payment_method', [
                'cash',
                'bank_transfer', 
                'pos_terminal',
                'personal_payment',
                'shop_account',
                'other'
            ])->default('cash');
            $table->foreignId('category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users'); // Who recorded it
            $table->date('expense_date'); // When expense occurred
            $table->string('vendor')->nullable(); // Who was paid (e.g., "BEDC Electricity")
            $table->string('receipt_number')->nullable(); // Receipt/invoice reference
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->text('notes')->nullable(); // Internal notes
            $table->timestamps();
            $table->softDeletes(); // Soft delete for audit trail
            
            // Indexes for performance
            $table->index('expense_number');
            $table->index('expense_date');
            $table->index('payment_method');
            $table->index('category_id');
            $table->index('recorded_by');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
