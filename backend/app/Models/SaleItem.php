<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'product_unit_type_id',
        'quantity',
        'unit_type',
        'conversion_factor',
        'unit_price',
        'unit_cost',
        'discount_percent',
        'line_total',
        'line_cost',
        'line_profit',
        'notes',
    ];

    protected $casts = [
        'product_unit_type_id' => 'integer',
        'quantity' => 'integer',
        'conversion_factor' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'line_total' => 'decimal:2',
        'line_cost' => 'decimal:2',
        'line_profit' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(ProductUnitType::class, 'product_unit_type_id');
    }

    /**
     * Accessors
     */
    public function getProfitMarginAttribute(): float
    {
        return $this->line_total > 0 
            ? ($this->line_profit / $this->line_total) * 100 
            : 0;
    }
}
