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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique(); // SALE-2026-0001
            $table->foreignId('department_id')->constrained()->onDelete('restrict');
            $table->foreignId('cashier_id')->constrained('users')->onDelete('restrict'); // Who made the sale
            
            $table->enum('sale_type', ['cash', 'credit', 'online', 'pos'])->default('cash');
            $table->enum('payment_status', ['unpaid', 'partially_paid', 'paid'])->default('paid');
            
            $table->decimal('subtotal', 15, 2);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('amount_due', 15, 2)->default(0);
            
            $table->decimal('cost_of_goods_sold', 15, 2)->default(0); // Total COGS for this sale
            $table->decimal('gross_profit', 15, 2)->default(0); // total_amount - COGS
            
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamp('sale_date');
            $table->softDeletes();
            $table->timestamps();
            
            // Indexes
            $table->index('sale_number');
            $table->index('sale_type');
            $table->index('payment_status');
            $table->index('sale_date');
            $table->index(['department_id', 'sale_date']);
            $table->index(['cashier_id', 'sale_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
