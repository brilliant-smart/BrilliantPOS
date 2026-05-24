<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite can't drop columns with indexes; create table only
            Schema::create('product_barcodes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->onDelete('cascade');
                $table->string('barcode', 100);
                $table->timestamps();

                $table->unique('barcode');
                $table->index('product_id');
            });
            return;
        }
        // Step 1: Create the product_barcodes table
        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('barcode', 100);
            $table->timestamps();

            $table->unique('barcode');
            $table->index('product_id');
        });

        // Step 2: Migrate existing barcode data from products to product_barcodes
        DB::table('products')
            ->whereNotNull('barcode')
            ->where('barcode', '!=', '')
            ->orderBy('id')
            ->chunk(100, function ($products) {
                foreach ($products as $product) {
                    DB::table('product_barcodes')->insert([
                        'product_id' => $product->id,
                        'barcode' => $product->barcode,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        // Step 3: Drop barcode column from products
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_barcode_unique');
            $table->dropColumn('barcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::dropIfExists('product_barcodes');
            return;
        }
        // Re-add barcode column to products
        Schema::table('products', function (Blueprint $table) {
            $table->string('barcode')->nullable()->after('sku');
            $table->unique(['barcode', 'deleted_at'], 'products_barcode_unique');
        });

        // Migrate data back from product_barcodes to products
        DB::table('product_barcodes')
            ->orderBy('id')
            ->chunk(100, function ($barcodes) {
                foreach ($barcodes as $barcodeRecord) {
                    DB::table('products')
                        ->where('id', $barcodeRecord->product_id)
                        ->update(['barcode' => $barcodeRecord->barcode]);
                }
            });

        // Drop product_barcodes table
        Schema::dropIfExists('product_barcodes');
    }
};