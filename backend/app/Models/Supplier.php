<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'contact_person',
        'email',
        'phone',
        'phone_alt',
        'address',
        'city',
        'state',
        'country',
        'payment_terms',
        'custom_payment_days',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'custom_payment_days' => 'integer',
    ];

    /**
     * Get purchase orders for this supplier
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * Get active purchase orders
     */
    public function activePurchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class)
            ->whereIn('status', ['pending', 'approved', 'ordered', 'partially_received']);
    }

    /**
     * Scope to get active suppliers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
