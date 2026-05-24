<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        // Drop the broken composite unique constraints.
        // MySQL treats NULL as unique in composite indexes, meaning two rows with
        // the same slug and NULL deleted_at are both allowed — defeating the purpose.
        // Uniqueness is enforced at the application level via
        // Rule::unique('products')->whereNull('deleted_at') in ProductController.

        Schema::table('products', function (Blueprint $table) {
            // Check and drop composite slug unique if it exists
            $slugIndex = DB::selectOne(
                "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'products' AND INDEX_NAME = 'products_slug_unique' GROUP BY INDEX_NAME",
                [config('database.connections.mysql.database')]
            );

            if ($slugIndex) {
                $table->dropUnique('products_slug_unique');
            }

            // Check and drop composite sku unique if it exists
            $skuIndex = DB::selectOne(
                "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'products' AND INDEX_NAME = 'products_sku_unique' GROUP BY INDEX_NAME",
                [config('database.connections.mysql.database')]
            );

            if ($skuIndex) {
                $table->dropUnique('products_sku_unique');
            }
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        // Restore composite unique constraints (broken for NULL deleted_at, but reverse migration)
        Schema::table('products', function (Blueprint $table) {
            $table->unique(['slug', 'deleted_at'], 'products_slug_unique');
            $table->unique(['sku', 'deleted_at'], 'products_sku_unique');
        });
    }
};