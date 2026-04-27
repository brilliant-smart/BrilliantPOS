<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoReorderLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'current_stock',
        'reorder_point',
        'suggested_quantity',
        'suggested_supplier_id',
        'action_taken',
        'purchase_order_id',
        'triggered_by',
        'triggered_at',
        'notes',
    ];

    protected $casts = [
        'current_stock' => 'integer',
        'reorder_point' => 'integer',
        'suggested_quantity' => 'integer',
        'triggered_at' => 'datetime',
    ];

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the suggested supplier
     */
    public function suggestedSupplier()
    {
        return $this->belongsTo(Supplier::class, 'suggested_supplier_id');
    }

    /**
     * Get the created purchase order
     */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get the user who triggered (if manual)
     */
    public function triggeredBy()
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /**
     * Check if automatic trigger
     */
    public function isAutomatic(): bool
    {
        return is_null($this->triggered_by);
    }
}
