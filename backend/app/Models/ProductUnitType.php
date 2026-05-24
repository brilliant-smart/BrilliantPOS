<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductUnitType extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'short_name',
        'conversion_factor',
        'selling_price',
        'is_base',
        'sort_order',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'is_base' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class, 'product_unit_type_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'product_unit_type_id');
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'product_unit_type_id');
    }

    public function getBaseUnitQuantity(int $quantity): float
    {
        return $quantity * (float) $this->conversion_factor;
    }

    public function getPerPiecePrice(): float
    {
        if ((float) $this->conversion_factor <= 0) {
            return (float) $this->selling_price;
        }
        return (float) $this->selling_price / (float) $this->conversion_factor;
    }
}