<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'created_by',
        'approved_by',
        'received_by',
        'status',
        'order_date',
        'expected_delivery_date',
        'actual_delivery_date',
        'subtotal',
        'vat_amount',
        'discount_amount',
        'shipping_cost',
        'total_amount',
        'payment_status',
        'payment_method',
        'payment_due_date',
        'payment_date',
        'amount_paid',
        'notes',
        'rejection_reason',
        'approved_at',
        'received_at',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'payment_due_date' => 'date',
        'payment_date' => 'date',
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Accessors & Computed Properties
     */
    public function getAmountDueAttribute(): float
    {
        return $this->total_amount - $this->amount_paid;
    }

    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->items()->count() > 0 && 
               $this->items()->whereColumn('quantity_received', '<', 'quantity_ordered')->count() === 0;
    }

    public function getIsPartiallyReceivedAttribute(): bool
    {
        return $this->items()->where('quantity_received', '>', 0)->count() > 0 &&
               !$this->is_fully_received;
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', 'unpaid');
    }

    /**
     * Generate PO Number
     */
    public static function generatePoNumber(): string
    {
        return DB::transaction(function () {
            $year = date('Y');
            $lastNumber = self::whereYear('created_at', $year)
                ->lockForUpdate()
                ->selectRaw("CAST(SUBSTRING(po_number, -4) AS UNSIGNED) as num")
                ->orderByDesc('num')
                ->value('num') ?? 0;

            return 'PO-' . $year . '-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        });
    }
}
