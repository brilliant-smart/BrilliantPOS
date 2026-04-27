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
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('prescription_number')->unique();
            $table->string('patient_name');
            $table->string('patient_phone')->nullable();
            $table->text('patient_address')->nullable();
            $table->date('patient_dob')->nullable();
            $table->string('doctor_name');
            $table->string('doctor_license')->nullable();
            $table->string('hospital')->nullable();
            $table->date('prescription_date');
            $table->date('expiry_date')->nullable();
            $table->enum('status', ['pending', 'dispensed', 'partially_dispensed', 'expired', 'cancelled'])->default('pending');
            $table->foreignId('dispensed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('dispensed_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('image_url')->nullable(); // Scanned prescription
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('prescription_number');
            $table->index('patient_name');
            $table->index('status');
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->integer('prescribed_quantity');
            $table->integer('dispensed_quantity')->default(0);
            $table->string('dosage')->nullable();
            $table->string('frequency')->nullable(); // e.g., "2 times daily"
            $table->integer('duration_days')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_controlled_substance')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
    }
};
