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
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'bank_transfer', 'cheque', 'card', 'credit'])->default('credit')->after('payment_status');
            $table->date('payment_due_date')->nullable()->after('payment_method');
            $table->date('payment_date')->nullable()->after('payment_due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_due_date', 'payment_date']);
        });
    }
};
