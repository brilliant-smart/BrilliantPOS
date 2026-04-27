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
        Schema::table('products', function (Blueprint $table) {
            // Check if columns don't exist before adding
            if (!Schema::hasColumn('products', 'serial_number')) {
                $table->string('serial_number')->nullable()->after('barcode');
            }
            if (!Schema::hasColumn('products', 'track_expiry')) {
                $table->boolean('track_expiry')->default(false)->after('barcode');
            }
            if (!Schema::hasColumn('products', 'track_batch')) {
                $table->boolean('track_batch')->default(false)->after('track_expiry');
            }
            if (!Schema::hasColumn('products', 'track_serial')) {
                $table->boolean('track_serial')->default(false)->after('track_batch');
            }
            
            // Auto-reorder settings
            if (!Schema::hasColumn('products', 'auto_reorder_enabled')) {
                $table->boolean('auto_reorder_enabled')->default(false)->after('reorder_point');
            }
            if (!Schema::hasColumn('products', 'auto_reorder_quantity')) {
                $table->integer('auto_reorder_quantity')->nullable()->after('auto_reorder_enabled');
            }
            
            // Warehouse/location within department
            if (!Schema::hasColumn('products', 'warehouse_location')) {
                $table->string('warehouse_location')->nullable()->after('department_id');
            }
            if (!Schema::hasColumn('products', 'shelf_position')) {
                $table->string('shelf_position')->nullable()->after('warehouse_location');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Only drop columns that were added by this migration
            $columnsToCheck = [
                'serial_number',
                'track_expiry',
                'track_batch',
                'track_serial',
                'auto_reorder_enabled',
                'auto_reorder_quantity',
                'warehouse_location',
                'shelf_position',
            ];
            
            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
