<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryCycleCountItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cycle_count_id',
        'product_id',
        'batch_number',
        'expected_quantity',
        'actual_quantity',
        'unit_cost',
        'notes',
    ];

    protected $casts = [
        'expected_quantity' => 'integer',
        'actual_quantity' => 'integer',
        'variance' => 'integer',
        'unit_cost' => 'decimal:2',
        'variance_value' => 'decimal:2',
    ];

    /**
     * Get the cycle count
     */
    public function cycleCount()
    {
        return $this->belongsTo(InventoryCycleCount::class, 'cycle_count_id');
    }

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Check if item has variance
     */
    public function hasVariance(): bool
    {
        return $this->actual_quantity !== $this->expected_quantity;
    }

    /**
     * Get variance percentage
     */
    public function getVariancePercentageAttribute(): float
    {
        if ($this->expected_quantity == 0) {
            return 0;
        }
        return (($this->actual_quantity - $this->expected_quantity) / $this->expected_quantity) * 100;
    }
}
