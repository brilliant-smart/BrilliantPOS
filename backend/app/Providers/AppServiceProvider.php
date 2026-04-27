<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Policies\PurchaseOrderPolicy;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        // Resolve Product by ID (numeric) or slug (string)
        Route::bind('product', function (string $value) {
            // If numeric, find by ID (for admin routes)
            if (is_numeric($value)) {
                return Product::findOrFail($value);
            }
            
            // Otherwise, find by slug (for public routes)
            return Product::where('slug', $value)->where('is_active', true)->firstOrFail();
        });
    }
}
