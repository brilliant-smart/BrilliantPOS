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
        // Add controlled substance tracking to products
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'is_controlled_substance')) {
                $table->boolean('is_controlled_substance')->default(false)->after('track_serial');
            }
            if (!Schema::hasColumn('products', 'controlled_substance_schedule')) {
                $table->enum('controlled_substance_schedule', ['I', 'II', 'III', 'IV', 'V'])->nullable()->after('is_controlled_substance');
            }
            if (!Schema::hasColumn('products', 'requires_prescription')) {
                $table->boolean('requires_prescription')->default(false)->after('controlled_substance_schedule');
            }
        });

        // Track controlled substance transactions
        Schema::create('controlled_substance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->enum('transaction_type', ['received', 'dispensed', 'destroyed', 'returned', 'transfer']);
            $table->integer('quantity');
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->foreignId('prescription_id')->nullable()->constrained()->onDelete('set null');
            $table->string('patient_name')->nullable();
            $table->string('patient_id_type')->nullable(); // Driver's license, passport, etc.
            $table->string('patient_id_number')->nullable();
            $table->foreignId('dispensed_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('transaction_date')->useCurrent();
            $table->timestamps();
            
            $table->index(['product_id', 'transaction_date']);
            $table->index('transaction_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('controlled_substance_logs');
        
        Schema::table('products', function (Blueprint $table) {
            $columns = ['is_controlled_substance', 'controlled_substance_schedule', 'requires_prescription'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
