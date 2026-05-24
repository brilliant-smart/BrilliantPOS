<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnitType;
use Illuminate\Console\Command;

class MigrateProductUnitTypes extends Command
{
    protected $signature = 'migrate:product-unit-types';
    protected $description = 'Create base unit types for existing products and link barcodes to them';

    public function handle(): int
    {
        $products = Product::all();
        $created = 0;
        $linked = 0;

        foreach ($products as $product) {
            $baseUnitType = $product->unitTypes()->where('is_base', true)->first();

            if (!$baseUnitType) {
                $baseUnitType = ProductUnitType::create([
                    'product_id' => $product->id,
                    'name' => 'Piece',
                    'short_name' => 'pc',
                    'conversion_factor' => 1,
                    'selling_price' => $product->price ?? 0,
                    'is_base' => true,
                    'sort_order' => 0,
                ]);
                $created++;
            }

            $linkedCount = ProductBarcode::where('product_id', $product->id)
                ->whereNull('product_unit_type_id')
                ->update(['product_unit_type_id' => $baseUnitType->id]);
            $linked += $linkedCount;
        }

        $this->info("Created {$created} base unit types.");
        $this->info("Linked {$linked} barcodes to their base unit types.");

        return self::SUCCESS;
    }
}