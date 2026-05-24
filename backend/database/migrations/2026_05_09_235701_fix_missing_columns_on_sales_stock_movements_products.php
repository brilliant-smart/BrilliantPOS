<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'discount_percentage')) {
                $table->decimal('discount_percentage', 5, 2)->default(0)->after('discount_amount');
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_movements', 'reference_type')) {
                $table->string('reference_type')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('stock_movements', 'reference_id')) {
                $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            }
        });

        // Add index for reference lookup if columns exist but index doesn't
        if (Schema::hasColumn('stock_movements', 'reference_type')) {
            try {
                Schema::table('stock_movements', function (Blueprint $table) {
                    $table->index(['reference_type', 'reference_id'], 'stock_movements_reference_index');
                });
            } catch (\Exception $e) {
                // Index may already exist
            }
        }

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'manufacturing_date')) {
                $table->date('manufacturing_date')->nullable()->after('track_expiry');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'manufacturing_date')) {
                $table->dropColumn('manufacturing_date');
            }
        });

        if (Schema::hasColumn('stock_movements', 'reference_type')) {
            try {
                Schema::table('stock_movements', function (Blueprint $table) {
                    $table->dropIndex('stock_movements_reference_index');
                });
            } catch (\Exception $e) {
                // Index may not exist
            }

            Schema::table('stock_movements', function (Blueprint $table) {
                $table->dropColumn(['reference_type', 'reference_id']);
            });
        }

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'discount_percentage')) {
                $table->dropColumn('discount_percentage');
            }
        });
    }
};