<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'expense_number',
        'title',
        'description',
        'amount',
        'payment_method',
        'category_id',
        'recorded_by',
        'expense_date',
        'vendor',
        'receipt_number',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot the model and attach event listeners.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate expense number on creation
        static::creating(function ($expense) {
            if (empty($expense->expense_number)) {
                $expense->expense_number = static::generateExpenseNumber();
            }
        });
    }

    /**
     * Generate a unique expense number.
     *
     * Format: EXP-YYYYMMDD-####
     */
    public static function generateExpenseNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "EXP-{$date}-";
        
        // Get the last expense number for today
        $lastExpense = static::where('expense_number', 'like', "{$prefix}%")
            ->orderBy('expense_number', 'desc')
            ->first();
        
        if ($lastExpense) {
            $lastNumber = (int) substr($lastExpense->expense_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get the category that owns the expense.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    /**
     * Get the user who recorded the expense.
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to filter by payment method.
     */
    public function scopePaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }
}
