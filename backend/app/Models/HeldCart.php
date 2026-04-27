<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeldCart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'items',
        'discount_percentage',
        'discount_amount',
        'reference',
        'notes',
        'held_at',
        'recalled_at',
    ];

    protected $casts = [
        'items' => 'array',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'held_at' => 'datetime',
        'recalled_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate unique reference for held cart
     */
    public static function generateReference(): string
    {
        $date = now()->format('Ymd');
        $count = self::whereDate('created_at', today())->count() + 1;
        
        return sprintf('HOLD-%s-%03d', $date, $count);
    }

    /**
     * Scope for active held carts (not recalled)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('recalled_at');
    }

    /**
     * Scope for current user's held carts
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
