<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnitType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Traits\ConvertsDateToMonthEnd;
use App\Traits\EscapesLikeWildcards;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use AuthorizesRequests, ConvertsDateToMonthEnd, EscapesLikeWildcards;

    /** Admin: List all products (including inactive) */
    public function adminIndex(Request $request)
    {
        $query = Product::with(['barcodes', 'unitTypes']);

        // Filter by search query (name, SKU, barcode)
        if ($request->filled('search')) {
            $search = $this->escapeLike($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%")
                  ->orWhereHas('barcodes', function ($bq) use ($search) {
                      $bq->where('barcode', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active === 'true' || $request->is_active === '1');
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'newest');
        switch ($sortBy) {
            case 'oldest':
                $query->oldest();
                break;
            case 'price-low':
                $query->orderBy('price', 'asc');
                break;
            case 'price-high':
                $query->orderBy('price', 'desc');
                break;
            case 'name-asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name-desc':
                $query->orderBy('name', 'desc');
                break;
            case 'newest':
            default:
                $query->latest();
                break;
        }

        // Support custom limit for admin panel (without pagination)
        if ($request->filled('limit')) {
            $limit = min((int) $request->limit, 1000); // Max 1000 items
            return response()->json(
                $query->limit($limit)->get()
            );
        }

        return response()->json(
            $query->paginate(50)
        );
    }

    /** Get a single product */
    public function show(Product $product)
    {
        return response()->json($product->load(['barcodes', 'unitTypes']));
    }

    /** Cashier-accessible: Search products (active only, no cost/profit) */
    public function search(Request $request)
    {
        $query = Product::where('is_active', true)
            ->select(['id', 'name', 'sku', 'price', 'stock_quantity', 'image_url', 'low_stock_threshold', 'is_active', 'track_batch', 'track_expiry', 'expiry_date', 'batch_number']);

        if ($request->filled('search')) {
            $search = $this->escapeLike($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%")
                  ->orWhereHas('barcodes', function ($bq) use ($search) {
                      $bq->where('barcode', 'LIKE', "%{$search}%");
                  });
            });
        }

        $limit = min((int) $request->get('limit', 10), 50);

        return response()->json(
            $query->with(['barcodes', 'unitTypes'])->latest()->limit($limit)->get()
        );
    }

    /** Public: Search product by barcode (cashier-accessible, limited fields) */
    public function searchByBarcode(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string'
        ]);

        $barcodeRecord = ProductBarcode::where('barcode', $request->barcode)
            ->with('unitType')
            ->first();

        if (!$barcodeRecord) {
            return response()->json([
                'message' => 'Product not found with barcode: ' . $request->barcode
            ], 404);
        }

        // Select only cashier-safe fields — exclude cost/profit data
        $product = Product::where('id', $barcodeRecord->product_id)
            ->where('is_active', true)
            ->select(['id', 'name', 'sku', 'price', 'stock_quantity', 'image_url', 'low_stock_threshold', 'is_active', 'unit_type', 'track_batch', 'track_expiry', 'expiry_date', 'batch_number'])
            ->with(['barcodes:product_id,barcode,product_unit_type_id', 'unitTypes:id,product_id,name,conversion_factor,selling_price'])
            ->first();

        if (!$product) {
            return response()->json([
                'message' => 'Product not found or inactive'
            ], 404);
        }

        $matchedUnitType = null;
        if ($barcodeRecord->product_unit_type_id) {
            $matchedUnitType = $barcodeRecord->unitType;
        }

        return response()->json([
            'product' => $product,
            'matched_unit_type' => $matchedUnitType,
        ]);
    }

    /** Protected: Create product */
    public function store(StoreProductRequest $request)
    {
        // Convert month/year expiry date (YYYY-MM) to last day of month (YYYY-MM-DD)
        if ($request->filled('expiry_date')) {
            $request->merge([
                'expiry_date' => $this->convertToLastDayOfMonth($request->expiry_date)
            ]);
        }

        $validated = $request->validated();

        // Role check: only owner or manager can create products
        if (!in_array($request->user()->role, ['owner', 'manager'])) {
            return response()->json(['message' => 'Unauthorized to create products'], 403);
        }

        if ($request->hasFile('image')) {
            // For shared hosting where symbolic links don't work well
            // Store directly in public directory in production
            if (app()->environment('production')) {
                $image = $request->file('image');
                $filename = time() . '_' . $image->getClientOriginalName();
                $image->move(public_path('storage/products'), $filename);
                $validated['image_url'] = 'products/' . $filename;
            } else {
                // Use standard storage for local development
                $validated['image_url'] = $request->file('image')
                    ->store('products', 'public');
            }
        }

        $product = Product::create($validated);

        // Handle unit types
        if ($request->has('_has_unit_types') && is_array($request->unit_types)) {
            foreach ($request->unit_types as $unitTypeData) {
                $unitType = $product->unitTypes()->create([
                    'name' => $unitTypeData['name'],
                    'short_name' => $unitTypeData['short_name'] ?? substr($unitTypeData['name'], 0, 3),
                    'conversion_factor' => $unitTypeData['conversion_factor'] ?? 1,
                    'selling_price' => $unitTypeData['selling_price'] ?? $product->price,
                    'is_base' => $unitTypeData['is_base'] ?? false,
                    'sort_order' => $unitTypeData['sort_order'] ?? 0,
                ]);

                // Handle barcodes for this unit type
                if (!empty($unitTypeData['barcodes'])) {
                    $unitBarcodes = array_filter($unitTypeData['barcodes'], fn($b) => !empty(trim($b)));
                    foreach ($unitBarcodes as $barcode) {
                        $barcode = trim($barcode);
                        $existing = ProductBarcode::where('barcode', $barcode)
                            ->whereHas('product', function ($q) {
                                $q->whereNull('deleted_at');
                            })
                            ->first();
                        if ($existing) {
                            continue; // Skip duplicates silently for unit type barcodes
                        }
                        $product->barcodes()->create([
                            'barcode' => $barcode,
                            'product_unit_type_id' => $unitType->id,
                        ]);
                    }
                }
            }
        }

        // Handle legacy barcodes (not linked to unit types)
        if ($request->has('_has_barcodes')) {
            $barcodes = array_filter($request->barcodes ?? [], fn($b) => !empty(trim($b)));

            // Check for duplicates across other active products before inserting
            foreach ($barcodes as $barcode) {
                $existing = ProductBarcode::where('barcode', trim($barcode))
                    ->whereHas('product', function ($q) {
                        $q->whereNull('deleted_at');
                    })
                    ->first();
                if ($existing) {
                    return response()->json([
                        'message' => "Barcode '{$barcode}' is already assigned to another product",
                        'errors' => ['barcodes' => ["Barcode '{$barcode}' is already assigned to another product"]],
                    ], 422);
                }
            }

            foreach ($barcodes as $barcode) {
                $product->barcodes()->create([
                    'barcode' => trim($barcode),
                    'product_unit_type_id' => null,
                ]);
            }
        }

        AuditLog::log('product.create', $product, null, $product->toArray(), "Product {$product->name} created");

        return response()->json($product->load(['barcodes', 'unitTypes']), 201);
    }

    /** Protected: Update product */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $this->authorize('update', $product);

        // Convert month/year expiry date (YYYY-MM) to last day of month (YYYY-MM-DD)
        if ($request->filled('expiry_date')) {
            $request->merge([
                'expiry_date' => $this->convertToLastDayOfMonth($request->expiry_date)
            ]);
        }

        $validated = $request->validated();

        if ($request->hasFile('image')) {
            // Delete old image
            if ($product->image_url) {
                if (app()->environment('production')) {
                    $oldImagePath = public_path('storage/' . $product->image_url);
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                } else {
                    Storage::disk('public')->delete($product->image_url);
                }
            }

            // Store new image
            if (app()->environment('production')) {
                $image = $request->file('image');
                $filename = time() . '_' . $image->getClientOriginalName();
                $image->move(public_path('storage/products'), $filename);
                $validated['image_url'] = 'products/' . $filename;
            } else {
                $validated['image_url'] = $request->file('image')
                    ->store('products', 'public');
            }
        }

        $oldValues = $product->toArray();
        $product->update($validated);

        AuditLog::log('product.update', $product, $oldValues, $product->toArray(), "Product {$product->name} updated");

        // Sync base unit type selling price with product price
        $baseUnitType = $product->unitTypes()->where('is_base', true)->first();
        if ($baseUnitType && $product->isDirty('price')) {
            $baseUnitType->update(['selling_price' => $product->price]);
        }

        // Handle unit types: sync
        if ($request->has('_has_unit_types')) {
            if ($request->has('unit_types_clear')) {
                // Delete all non-base unit types and their barcodes
                $nonBaseTypes = $product->unitTypes()->where('is_base', false)->get();
                foreach ($nonBaseTypes as $nonBaseType) {
                    ProductBarcode::where('product_unit_type_id', $nonBaseType->id)->delete();
                    $nonBaseType->delete();
                }
            } elseif (is_array($request->unit_types)) {
                $existingIds = [];
                foreach ($request->unit_types as $unitTypeData) {
                    if (!empty($unitTypeData['id'])) {
                        $unitType = ProductUnitType::where('id', $unitTypeData['id'])
                            ->where('product_id', $product->id)
                            ->first();
                        if ($unitType) {
                            $unitType->update([
                                'name' => $unitTypeData['name'] ?? $unitType->name,
                                'short_name' => $unitTypeData['short_name'] ?? $unitType->short_name,
                                'conversion_factor' => $unitTypeData['conversion_factor'] ?? $unitType->conversion_factor,
                                'selling_price' => $unitTypeData['selling_price'] ?? $unitType->selling_price,
                                'is_base' => $unitTypeData['is_base'] ?? $unitType->is_base,
                                'sort_order' => $unitTypeData['sort_order'] ?? $unitType->sort_order,
                            ]);
                            $existingIds[] = $unitType->id;
                        }
                    } else {
                        $unitType = $product->unitTypes()->create([
                            'name' => $unitTypeData['name'],
                            'short_name' => $unitTypeData['short_name'] ?? substr($unitTypeData['name'], 0, 3),
                            'conversion_factor' => $unitTypeData['conversion_factor'] ?? 1,
                            'selling_price' => $unitTypeData['selling_price'] ?? $product->price,
                            'is_base' => $unitTypeData['is_base'] ?? false,
                            'sort_order' => $unitTypeData['sort_order'] ?? 0,
                        ]);
                        $existingIds[] = $unitType->id;
                    }

                    // Handle barcodes for this unit type
                    if (isset($unitType)) {
                        // Always remove old barcodes for this unit type first
                        ProductBarcode::where('product_unit_type_id', $unitType->id)->delete();

                        if (!empty($unitTypeData['barcodes'])) {
                            $unitBarcodes = array_filter($unitTypeData['barcodes'], fn($b) => !empty(trim($b)));
                            foreach ($unitBarcodes as $barcode) {
                                $barcode = trim($barcode);
                                $existing = ProductBarcode::where('barcode', $barcode)
                                    ->where('product_id', '!=', $product->id)
                                    ->whereHas('product', function ($q) {
                                        $q->whereNull('deleted_at');
                                    })
                                    ->first();
                                if (!$existing) {
                                    $product->barcodes()->create([
                                        'barcode' => $barcode,
                                        'product_unit_type_id' => $unitType->id,
                                    ]);
                                }
                            }
                        }
                    }
                }
                // Delete unit types not in the request (except base unit type)
                $product->unitTypes()
                    ->where('is_base', false)
                    ->whereNotIn('id', $existingIds)
                    ->delete();
            }
        }

        // Handle legacy barcodes: replace all barcodes not linked to unit types
        if ($request->has('_has_barcodes')) {
            // Delete all unlinked barcodes (legacy barcodes)
            ProductBarcode::where('product_id', $product->id)
                ->whereNull('product_unit_type_id')
                ->delete();

            $barcodes = array_filter($request->barcodes ?? [], fn($b) => !empty(trim($b)));

            // Check for duplicates across other active products before inserting
            foreach ($barcodes as $barcode) {
                $existing = ProductBarcode::where('barcode', trim($barcode))
                    ->where('product_id', '!=', $product->id)
                    ->whereHas('product', function ($q) {
                        $q->whereNull('deleted_at');
                    })
                    ->first();
                if ($existing) {
                    return response()->json([
                        'message' => "Barcode '{$barcode}' is already assigned to another product",
                        'errors' => ['barcodes' => ["Barcode '{$barcode}' is already assigned to another product"]],
                    ], 422);
                }
            }

            foreach ($barcodes as $barcode) {
                $product->barcodes()->create([
                    'barcode' => trim($barcode),
                    'product_unit_type_id' => null,
                ]);
            }
        }

        return response()->json($product->load(['barcodes', 'unitTypes']));
    }

    /** Protected: Delete product */
    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        if ($product->image_url) {
            Storage::disk('public')->delete($product->image_url);
        }

        AuditLog::log('product.delete', $product, null, null, "Product {$product->name} deleted");

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }
}