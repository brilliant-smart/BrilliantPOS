<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        // 1. Drop orphan department_id FK and column from inventory_cycle_counts
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::table('inventory_cycle_counts', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 2. Drop redundant reorder_level column from products (reorder_point is the canonical column)
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('reorder_level');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        Schema::table('products', function (Blueprint $table) {
            $table->integer('reorder_level')->default(10);
        });

        Schema::table('inventory_cycle_counts', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained()->onDelete('set null');
        });
    }
};