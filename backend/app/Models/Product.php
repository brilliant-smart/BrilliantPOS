<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
class Product extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'name',
        'slug',
        'sku',
        'description',
        'price',
        'cost_price',
        'last_purchase_price',
        'stock_quantity',
        'unit_type',
        'low_stock_threshold',
        'image_url',
        'is_active',
        'is_featured',
        'expiry_date',
        'manufacturing_date',
        'batch_number',
        'serial_number',
        'reorder_point',
        'max_stock_level',
        'track_expiry',
        'track_batch',
        'track_serial',
        'auto_reorder_enabled',
        'auto_reorder_quantity',
        'warehouse_location',
        'shelf_position',
    ];

    /**
     * Get the barcodes for the product.
     */
    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    /**
     * Get the stock movements for the product.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Get the batches for the product.
     */
    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    /**
     * Get active batches ordered by FEFO (First Expired, First Out)
     */
    public function activeBatches(): HasMany
    {
        return $this->hasMany(ProductBatch::class)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->orderBy('expiry_date', 'asc');
    }

    /**
     * Get auto reorder logs for this product
     */
    public function autoReorderLogs(): HasMany
    {
        return $this->hasMany(AutoReorderLog::class);
    }

    /**
     * Get price history for this product
     */
    public function priceHistory(): HasMany
    {
        return $this->hasMany(ProductPriceHistory::class);
    }

    public function unitTypes(): HasMany
    {
        return $this->hasMany(ProductUnitType::class)->orderBy('sort_order')->orderBy('conversion_factor');
    }

    public function baseUnitType()
    {
        return $this->hasOne(ProductUnitType::class)->where('is_base', true);
    }

    /**
     * Get latest price from last purchase order
     */
    public function getLastPurchasePriceInfoAttribute()
    {
        $lastHistory = $this->priceHistory()
            ->where('change_type', 'purchase')
            ->latest('changed_at')
            ->first();

        if (!$lastHistory) {
            return null;
        }

        return [
            'price' => $lastHistory->new_price,
            'date' => $lastHistory->changed_at,
            'supplier' => $lastHistory->supplier_name,
            'reference' => $lastHistory->reference_number,
        ];
    }

    protected $appends = ['image_full_url', 'stock_status'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'track_expiry' => 'boolean',
            'track_batch' => 'boolean',
            'track_serial' => 'boolean',
            'auto_reorder_enabled' => 'boolean',
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'last_purchase_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'reorder_point' => 'integer',
            'max_stock_level' => 'integer',
            'auto_reorder_quantity' => 'integer',
            'expiry_date' => 'date',
            'manufacturing_date' => 'date',
        ];
    }

    public function getImageFullUrlAttribute(): ?string
    {
        if (!$this->image_url) {
            return null;
        }

        // For production, images are stored directly in public/storage
        // For local development, they use the storage link
        if (app()->environment('production')) {
            return asset('storage/' . $this->image_url);
        } else {
            return asset('storage/' . $this->image_url);
        }
    }

    /**
     * Get the stock status of the product.
     */
    public function getStockStatusAttribute(): string
    {
        if ($this->stock_quantity <= 0) {
            return 'out_of_stock';
        } elseif ($this->stock_quantity <= $this->low_stock_threshold) {
            return 'low_stock';
        }
        return 'in_stock';
    }

    /**
     * Check if product is in stock.
     */
    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    /**
     * Check if product has low stock.
     */
    public function hasLowStock(): bool
    {
        return $this->stock_quantity > 0 && $this->stock_quantity <= $this->low_stock_threshold;
    }

    /**
     * Check if product is out of stock.
     */
    public function isOutOfStock(): bool
    {
        return $this->stock_quantity <= 0;
    }

    /**
     * Get profit margin (percentage)
     */
    public function getProfitMarginAttribute(): float
    {
        if ($this->cost_price <= 0 || $this->price <= 0) {
            return 0;
        }

        return (($this->price - $this->cost_price) / $this->price) * 100;
    }

    /**
     * Get profit per unit
     */
    public function getUnitProfitAttribute(): float
    {
        return $this->price - $this->cost_price;
    }

    /**
     * Check if product needs reordering
     */
    public function getNeedsReorderAttribute(): bool
    {
        return $this->stock_quantity <= $this->reorder_point;
    }

    /**
     * Check if product is expiring soon (within 30 days)
     */
    public function getIsExpiringSoonAttribute(): bool
    {
        if (!$this->expiry_date) {
            return false;
        }

        return $this->expiry_date->diffInDays(now()) <= 30 && $this->expiry_date->isFuture();
    }

    /**
     * Check if product is expired
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expiry_date) {
            return false;
        }

        return $this->expiry_date->isPast();
    }

    /**
     * Get total stock value (cost)
     */
    public function getStockValueAttribute(): float
    {
        return $this->stock_quantity * $this->cost_price;
    }

    protected static function booted()
    {
        static::creating(function ($product) {
            if (empty($product->slug)) {
                $slug = \Illuminate\Support\Str::slug($product->name);
                $originalSlug = $slug;
                $count = 1;
                while (static::where('slug', $slug)->whereNull('deleted_at')->exists()) {
                    $slug = $originalSlug . '-' . $count;
                    $count++;
                }
                $product->slug = $slug;
            }

            // Enforce uniqueness excluding soft-deletes (DB constraint can't do this with NULL)
            if ($product->sku && static::where('sku', $product->sku)->whereNull('deleted_at')->exists()) {
                throw new \Illuminate\Database\QueryException('', [], new \Exception('SKU already exists among active products'));
            }
        });

        static::updating(function ($product) {
            if ($product->isDirty('slug') && static::where('slug', $product->slug)->whereNull('deleted_at')->where('id', '!=', $product->id)->exists()) {
                throw new \Illuminate\Database\QueryException('', [], new \Exception('Slug already exists among active products'));
            }

            if ($product->isDirty('sku') && $product->sku && static::where('sku', $product->sku)->whereNull('deleted_at')->where('id', '!=', $product->id)->exists()) {
                throw new \Illuminate\Database\QueryException('', [], new \Exception('SKU already exists among active products'));
            }
        });

        static::created(function ($product) {
            if (!$product->unitTypes()->where('is_base', true)->exists()) {
                $product->unitTypes()->create([
                    'name' => 'Piece',
                    'short_name' => 'pc',
                    'conversion_factor' => 1,
                    'selling_price' => $product->price ?? 0,
                    'is_base' => true,
                    'sort_order' => 0,
                ]);
            }
        });

        static::deleting(function ($product) {
            if ($product->image_url) {
                if (app()->environment('production')) {
                    // Delete from public/storage directory
                    $imagePath = public_path('storage/' . $product->image_url);
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                } else {
                    // Delete from storage using Laravel's Storage facade
                    Storage::disk('public')->delete($product->image_url);
                }
            }
        });
    }
}
