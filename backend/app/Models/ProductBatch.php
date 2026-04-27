<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductBatch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'quantity_received',
        'quantity_remaining',
        'cost_price',
        'selling_price',
        'purchase_order_id',
        'supplier_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'manufacturing_date' => 'date',
        'expiry_date' => 'date',
        'quantity_received' => 'integer',
        'quantity_remaining' => 'integer',
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    /**
     * Get the product that owns the batch
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the purchase order
     */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get the supplier
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Check if batch is expired
     */
    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /**
     * Check if batch is expiring soon (within 30 days)
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expiry_date && 
               $this->expiry_date->isFuture() && 
               $this->expiry_date->diffInDays(now()) <= $days;
    }

    /**
     * Get batches expiring soon
     */
    public static function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('status', 'active')
                    ->where('quantity_remaining', '>', 0)
                    ->whereNotNull('expiry_date')
                    ->whereDate('expiry_date', '>', now())
                    ->whereDate('expiry_date', '<=', now()->addDays($days))
                    ->orderBy('expiry_date', 'asc');
    }

    /**
     * Get expired batches
     */
    public static function scopeExpired($query)
    {
        return $query->where('quantity_remaining', '>', 0)
                    ->whereNotNull('expiry_date')
                    ->whereDate('expiry_date', '<', now())
                    ->orderBy('expiry_date', 'asc');
    }

    /**
     * Get active batches ordered by FEFO (First Expired, First Out)
     */
    public static function scopeFEFO($query)
    {
        return $query->where('status', 'active')
                    ->where('quantity_remaining', '>', 0)
                    ->orderBy('expiry_date', 'asc');
    }
}
