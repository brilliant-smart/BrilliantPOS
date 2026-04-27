<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'method',
        'amount',
        'reference',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Payment method labels
     */
    public static function methodLabels(): array
    {
        return [
            'cash' => 'Cash',
            'card' => 'Card',
            'pos' => 'POS Terminal',
            'credit' => 'Credit',
            'bank_transfer' => 'Bank Transfer',
        ];
    }

    /**
     * Get formatted payment method
     */
    public function getMethodLabelAttribute(): string
    {
        return self::methodLabels()[$this->method] ?? $this->method;
    }
}
