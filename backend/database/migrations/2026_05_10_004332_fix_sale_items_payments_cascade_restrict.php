<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change sale_items.sale_id from CASCADE to RESTRICT — soft-deletes make
        // RESTRICT safer since accidental hard-deletion is blocked at the DB level.
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('restrict');
        });

        // Change payments.sale_id from CASCADE to RESTRICT
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade');
        });
    }
};