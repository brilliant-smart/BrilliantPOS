<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_unit_type_id',
        'quantity_ordered',
        'quantity_received',
        'unit_type',
        'conversion_factor',
        'unit_cost',
        'vat_rate',
        'discount_percent',
        'line_total',
        'notes',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
    ];

    protected $casts = [
        'product_unit_type_id' => 'integer',
        'quantity_ordered' => 'integer',
        'quantity_received' => 'integer',
        'conversion_factor' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'line_total' => 'decimal:2',
        'manufacturing_date' => 'date',
        'expiry_date' => 'date',
    ];

    /**
     * Relationships
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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
    public function getQuantityPendingAttribute(): int
    {
        return $this->quantity_ordered - $this->quantity_received;
    }

    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->quantity_received >= $this->quantity_ordered;
    }
}
