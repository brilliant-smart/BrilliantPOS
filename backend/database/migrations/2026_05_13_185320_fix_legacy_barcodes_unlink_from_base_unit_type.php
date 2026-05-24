<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Unlink legacy barcodes from base unit types.
     * Barcodes entered in the top-level barcode field should have
     * product_unit_type_id = NULL, not linked to a base unit type.
     * This migration finds barcodes linked to base unit types that
     * aren't also in the unit_types JSON form data, and unlinks them.
     */
    public function up(): void
    {
        // Get all base unit type IDs
        $baseUnitTypeIds = DB::table('product_unit_types')
            ->where('is_base', true)
            ->pluck('id')
            ->toArray();

        if (!empty($baseUnitTypeIds)) {
            // Set product_unit_type_id to NULL for all barcodes
            // that are linked to a base unit type — these are legacy barcodes
            // that should be unlinked
            DB::table('product_barcodes')
                ->whereIn('product_unit_type_id', $baseUnitTypeIds)
                ->update(['product_unit_type_id' => null]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No safe way to reverse — the original linkage data is lost.
        // Legacy barcodes will remain unlinked, which is the correct behavior.
    }
};