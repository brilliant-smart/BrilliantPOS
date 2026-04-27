<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Drop stock_transfers table (inter-department only)
        Schema::dropIfExists('stock_transfers');

        // Remove department_id FK and column from each table
        $tables = ['users', 'products', 'sales', 'purchase_orders', 'expenses'];
        foreach ($tables as $tableName) {
            if (Schema::hasColumn($tableName, 'department_id')) {
                Schema::table($tableName, function ($table) use ($tableName) {
                    try {
                        $table->dropForeign("{$tableName}_department_id_foreign");
                    } catch (\Exception $e) {}
                });
                Schema::table($tableName, function ($table) {
                    $table->dropColumn('department_id');
                });
            }
        }

        // Drop departments table
        Schema::dropIfExists('departments');

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        // This migration is not reversible
    }
};