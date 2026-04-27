<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBarcode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use AuthorizesRequests;

    /** Admin: List all products (including inactive) */
    public function adminIndex(Request $request)
    {
        $query = Product::with('barcodes');

        // Filter by search query (name, SKU, barcode)
        if ($request->filled('search')) {
            $search = $request->search;
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

    /** Public: Search product by barcode */
    public function searchByBarcode(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string'
        ]);

        $product = Product::whereHas('barcodes', function ($query) use ($request) {
                $query->where('barcode', $request->barcode);
            })
            ->where('is_active', true)
            ->first();

        if (!$product) {
            return response()->json([
                'message' => 'Product not found with barcode: ' . $request->barcode
            ], 404);
        }

        return response()->json($product->load('barcodes'));
    }

    /** Protected: Create product */
    public function store(Request $request)
    {
        // Convert month/year expiry date (YYYY-MM) to last day of month (YYYY-MM-DD)
        if ($request->filled('expiry_date')) {
            $request->merge([
                'expiry_date' => $this->convertToLastDayOfMonth($request->expiry_date)
            ]);
        }

        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'slug'                => ['required', 'string', 'max:255', Rule::unique('products')->whereNull('deleted_at')],
            'sku'                 => ['nullable', 'string', 'max:100', Rule::unique('products')->whereNull('deleted_at')],
            'barcodes'            => ['nullable', 'array'],
            'barcodes.*'          => ['nullable', 'string', 'max:100'],
            'description'         => ['nullable', 'string'],
            'price'               => ['required', 'numeric', 'min:0'],
            'stock_quantity'      => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'image'               => ['nullable', 'image', 'max:2048'],
            'is_active'           => ['boolean'],
            'is_featured'         => ['boolean'],
            'track_batch'         => ['boolean'],
            'track_expiry'        => ['boolean'],
            'batch_number'        => ['nullable', 'string', 'max:255'],
            'manufacturing_date'  => ['nullable', 'date', 'before_or_equal:today'],
            'expiry_date'         => ['nullable', 'date', 'after_or_equal:today'],
        ]);

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

        // Handle barcodes
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
                $product->barcodes()->create(['barcode' => trim($barcode)]);
            }
        }

        return response()->json($product->load('barcodes'), 201);
    }

    /** Protected: Update product */
    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        // Convert month/year expiry date (YYYY-MM) to last day of month (YYYY-MM-DD)
        if ($request->filled('expiry_date')) {
            $request->merge([
                'expiry_date' => $this->convertToLastDayOfMonth($request->expiry_date)
            ]);
        }

        $validated = $request->validate([
            'name'                => ['sometimes', 'string', 'max:255'],
            'slug'                => ['sometimes', 'string', 'max:255', Rule::unique('products')->whereNull('deleted_at')->ignore($product->id)],
            'sku'                 => ['sometimes', 'string', 'max:100', Rule::unique('products')->whereNull('deleted_at')->ignore($product->id)],
            'barcodes'            => ['nullable', 'array'],
            'barcodes.*'          => ['nullable', 'string', 'max:100'],
            'description'         => ['nullable', 'string'],
            'price'               => ['sometimes', 'numeric', 'min:0'],
            'stock_quantity'      => ['sometimes', 'integer', 'min:0'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'image'               => ['nullable', 'image', 'max:2048'],
            'is_active'           => ['boolean'],
            'is_featured'         => ['boolean'],
            'track_batch'         => ['boolean'],
            'track_expiry'        => ['boolean'],
            'batch_number'        => ['nullable', 'string', 'max:255'],
            'manufacturing_date'  => ['nullable', 'date', 'before_or_equal:today'],
            'expiry_date'         => ['nullable', 'date', 'after_or_equal:today'],
        ]);

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

        $product->update($validated);

        // Handle barcodes: replace all barcodes for this product
        if ($request->has('_has_barcodes')) {
            $product->barcodes()->delete();

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
                $product->barcodes()->create(['barcode' => trim($barcode)]);
            }
        }

        return response()->json($product->load('barcodes'));
    }

    /** Protected: Delete product */
    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        if ($product->image_url) {
            Storage::disk('public')->delete($product->image_url);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }

    /**
     * Convert month/year format (YYYY-MM) to last day of month (YYYY-MM-DD)
     * Example: "2026-03" becomes "2026-03-31"
     * Full dates are passed through unchanged
     */
    private function convertToLastDayOfMonth($date)
    {
        if (empty($date)) {
            return $date;
        }

        // If already a full date (YYYY-MM-DD), return as is
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        // If month/year format (YYYY-MM), convert to last day of month
        if (preg_match('/^\d{4}-\d{2}$/', $date)) {
            try {
                $dateObj = \DateTime::createFromFormat('Y-m', $date);
                if ($dateObj) {
                    // Get last day of the month
                    return $dateObj->format('Y-m-t');
                }
            } catch (\Exception $e) {
                // If parsing fails, return original
                return $date;
            }
        }

        // Return original if format not recognized
        return $date;
    }
}