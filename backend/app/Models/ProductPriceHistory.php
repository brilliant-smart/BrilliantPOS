<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductPriceHistory extends Model
{
    use HasFactory;

    protected $table = 'product_price_history';

    protected $fillable = [
        'product_id',
        'old_price',
        'new_price',
        'price_change',
        'percentage_change',
        'change_type',
        'supplier_name',
        'reference_number',
        'changed_at',
        'changed_by',
        'notes',
    ];

    protected $casts = [
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
        'price_change' => 'decimal:2',
        'percentage_change' => 'decimal:2',
        'changed_at' => 'datetime',
    ];

    /**
     * Get the product that owns the price history
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who made the change
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
