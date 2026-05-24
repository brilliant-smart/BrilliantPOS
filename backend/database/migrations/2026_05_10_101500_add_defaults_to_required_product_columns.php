<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add default values to NOT NULL columns that lack them
        // This prevents insert errors when the frontend omits optional fields

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_price', 15, 2)->default(0)->change();
            $table->decimal('price', 15, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_price', 15, 2)->default(null)->change();
            $table->decimal('price', 15, 2)->default(null)->change();
        });
    }
};