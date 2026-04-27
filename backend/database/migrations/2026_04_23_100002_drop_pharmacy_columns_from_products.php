<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $columns = ['is_controlled_substance', 'controlled_substance_schedule', 'requires_prescription'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_controlled_substance')->default(false)->after('is_featured');
            $table->string('controlled_substance_schedule')->nullable()->after('is_controlled_substance');
            $table->boolean('requires_prescription')->default(false)->after('controlled_substance_schedule');
        });
    }
};