<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryCycleCount extends Model
{
    use HasFactory;

    protected $fillable = [
        'count_number',
        'counted_by',
        'verified_by',
        'status',
        'count_date',
        'started_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'count_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user who counted
     */
    public function countedBy()
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    /**
     * Get the user who verified
     */
    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get the count items
     */
    public function items()
    {
        return $this->hasMany(InventoryCycleCountItem::class, 'cycle_count_id');
    }

    /**
     * Generate next count number
     */
    public static function generateCountNumber(): string
    {
        $year = date('Y');
        $lastCount = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastCount && preg_match('/CC-' . $year . '-(\d+)/', $lastCount->count_number, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return 'CC-' . $year . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get total variance value
     */
    public function getTotalVarianceValueAttribute()
    {
        return $this->items->sum('variance_value');
    }

    /**
     * Get total items with variance
     */
    public function getItemsWithVarianceCountAttribute()
    {
        return $this->items->where('variance', '!=', 0)->count();
    }
}
