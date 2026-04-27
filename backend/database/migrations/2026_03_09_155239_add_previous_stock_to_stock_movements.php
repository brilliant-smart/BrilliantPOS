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
        Schema::table('stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_movements', 'previous_stock')) {
                $table->integer('previous_stock')->default(0)->after('quantity');
            }
            if (!Schema::hasColumn('stock_movements', 'new_stock')) {
                $table->integer('new_stock')->default(0)->after('previous_stock');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'previous_stock')) {
                $table->dropColumn('previous_stock');
            }
            if (Schema::hasColumn('stock_movements', 'new_stock')) {
                $table->dropColumn('new_stock');
            }
        });
    }
};
