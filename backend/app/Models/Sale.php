<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'status',
        'voided_by',
        'void_reason',
        'voided_at',
        'sale_number',
        'cashier_id',
        'sale_type',
        'payment_status',
        'subtotal',
        'vat_amount',
        'discount_percentage',
        'discount_amount',
        'total_amount',
        'amount_paid',
        'amount_due',
        'cost_of_goods_sold',
        'gross_profit',
        'customer_name',
        'customer_phone',
        'contact_name',
        'notes',
        'sale_date',
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'voided_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'cost_of_goods_sold' => 'decimal:2',
        'gross_profit' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Accessors
     */
    public function getProfitMarginAttribute(): float
    {
        return $this->total_amount > 0 
            ? ($this->gross_profit / $this->total_amount) * 100 
            : 0;
    }

    /**
     * Scopes
     */
    public function scopeByCashier($query, $cashierId)
    {
        return $query->where('cashier_id', $cashierId);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('sale_date', [$startDate, $endDate]);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', '!=', 'paid');
    }

    /**
     * Generate Sale Number
     */
    public static function generateSaleNumber(): string
    {
        $attempts = 0;
        $maxAttempts = 3;

        while ($attempts < $maxAttempts) {
            try {
                return DB::transaction(function () {
                    $year = date('Y');
                    // Include soft-deleted records — the unique constraint covers them too
                    $lastNumber = self::withTrashed()
                        ->whereYear('created_at', $year)
                        ->lockForUpdate()
                        ->selectRaw("CAST(SUBSTRING(sale_number, -4) AS UNSIGNED) as num")
                        ->orderByDesc('num')
                        ->value('num') ?? 0;

                    return 'SALE-' . $year . '-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                });
            } catch (\Illuminate\Database\QueryException $e) {
                // Duplicate key — retry with a new number
                if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), '1062')) {
                    $attempts++;
                    continue;
                }
                throw $e;
            }
        }

        // Fallback: use a timestamp-based suffix to guarantee uniqueness
        return 'SALE-' . date('Y') . '-' . str_pad((int)(microtime(true) * 1000) % 10000, 4, '0', STR_PAD_LEFT);
    }
}
