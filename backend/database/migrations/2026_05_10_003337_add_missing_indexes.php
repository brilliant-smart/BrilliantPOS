<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index('user_id', 'stock_movements_user_id_index');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index('approved_by', 'purchase_orders_approved_by_index');
            $table->index('received_by', 'purchase_orders_received_by_index');
        });

        Schema::table('product_batches', function (Blueprint $table) {
            $table->index('supplier_id', 'product_batches_supplier_id_index');
            $table->index('purchase_order_id', 'product_batches_purchase_order_id_index');
        });

        Schema::table('product_price_history', function (Blueprint $table) {
            $table->index('changed_by', 'product_price_history_changed_by_index');
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->index('user_id', 'notification_logs_user_id_index');
        });

        Schema::table('auto_reorder_logs', function (Blueprint $table) {
            $table->index('suggested_supplier_id', 'auto_reorder_logs_suggested_supplier_id_index');
            $table->index('purchase_order_id', 'auto_reorder_logs_purchase_order_id_index');
            $table->index('triggered_by', 'auto_reorder_logs_triggered_by_index');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_user_id_index');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex('purchase_orders_approved_by_index');
            $table->dropIndex('purchase_orders_received_by_index');
        });

        Schema::table('product_batches', function (Blueprint $table) {
            $table->dropIndex('product_batches_supplier_id_index');
            $table->dropIndex('product_batches_purchase_order_id_index');
        });

        Schema::table('product_price_history', function (Blueprint $table) {
            $table->dropIndex('product_price_history_changed_by_index');
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex('notification_logs_user_id_index');
        });

        Schema::table('auto_reorder_logs', function (Blueprint $table) {
            $table->dropIndex('auto_reorder_logs_suggested_supplier_id_index');
            $table->dropIndex('auto_reorder_logs_purchase_order_id_index');
            $table->dropIndex('auto_reorder_logs_triggered_by_index');
        });
    }
};