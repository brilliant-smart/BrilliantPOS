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
        Schema::table('sale_items', function (Blueprint $table) {
            // Check if columns don't exist before adding
            if (!Schema::hasColumn('sale_items', 'unit_cost')) {
                $table->decimal('unit_cost', 12, 2)->default(0)->after('unit_price');
            }
            
            if (!Schema::hasColumn('sale_items', 'line_cost')) {
                $table->decimal('line_cost', 15, 2)->default(0)->after('unit_cost');
            }
            
            if (!Schema::hasColumn('sale_items', 'line_profit')) {
                $table->decimal('line_profit', 15, 2)->default(0)->after('line_cost');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_items', 'unit_cost')) {
                $table->dropColumn('unit_cost');
            }
            if (Schema::hasColumn('sale_items', 'line_cost')) {
                $table->dropColumn('line_cost');
            }
            if (Schema::hasColumn('sale_items', 'line_profit')) {
                $table->dropColumn('line_profit');
            }
        });
    }
};
