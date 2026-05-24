<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBarcode extends Model
{
    protected $fillable = [
        'product_id',
        'product_unit_type_id',
        'barcode',
    ];

    protected $casts = [
        'product_id' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unitType()
    {
        return $this->belongsTo(ProductUnitType::class, 'product_unit_type_id');
    }
}