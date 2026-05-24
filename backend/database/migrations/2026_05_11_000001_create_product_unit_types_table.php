<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_unit_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('short_name', 10);
            $table->decimal('conversion_factor', 10, 2)->default(1);
            $table->decimal('selling_price', 15, 2);
            $table->boolean('is_base')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'short_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_unit_types');
    }
};