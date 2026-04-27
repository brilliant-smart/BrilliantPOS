<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers
     * GET /api/suppliers
     */
    public function index(Request $request)
    {
        $query = Supplier::query();

        // Filter active/inactive
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $suppliers = $request->boolean('paginate', true)
            ? $query->latest()->paginate($request->input('per_page', 15))
            : $query->latest()->get();

        return response()->json($suppliers);
    }

    /**
     * Store a newly created supplier
     * POST /api/suppliers
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255|unique:suppliers,code',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'phone_alt' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'payment_terms' => 'nullable|in:cash,bank_transfer,cheque,card,credit,credit_7,credit_14,credit_30,credit_60,custom',
            'custom_payment_days' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        // Auto-generate supplier code if not provided
        if (empty($validated['code'])) {
            $validated['code'] = $this->generateSupplierCode();
        }

        $supplier = Supplier::create($validated);

        return response()->json([
            'message' => 'Supplier created successfully',
            'supplier' => $supplier,
        ], 201);
    }

    /**
     * Generate next supplier code
     */
    private function generateSupplierCode(): string
    {
        $year = date('Y');
        $lastSupplier = Supplier::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastSupplier && preg_match('/SUP-' . $year . '-(\d+)/', $lastSupplier->code, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return 'SUP-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Display the specified supplier
     * GET /api/suppliers/{supplier}
     */
    public function show(Supplier $supplier)
    {
        $supplier->load(['purchaseOrders' => function ($query) {
            $query->latest()->limit(10);
        }]);

        return response()->json($supplier);
    }

    /**
     * Update the specified supplier
     * PUT /api/suppliers/{supplier}
     */
    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:255|unique:suppliers,code,' . $supplier->id,
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'phone_alt' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'payment_terms' => 'nullable|in:cash,bank_transfer,cheque,card,credit,credit_7,credit_14,credit_30,credit_60,custom',
            'custom_payment_days' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $supplier->update($validated);

        return response()->json([
            'message' => 'Supplier updated successfully',
            'supplier' => $supplier,
        ]);
    }

    /**
     * Remove the specified supplier (soft delete)
     * DELETE /api/suppliers/{supplier}
     */
    public function destroy(Supplier $supplier)
    {
        // Check if supplier has any purchase orders
        if ($supplier->purchaseOrders()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete supplier with existing purchase orders. Please deactivate instead.',
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'message' => 'Supplier deleted successfully',
        ]);
    }

    /**
     * Toggle supplier active status
     * POST /api/suppliers/{supplier}/toggle-status
     */
    public function toggleStatus(Supplier $supplier)
    {
        $supplier->update([
            'is_active' => !$supplier->is_active,
        ]);

        return response()->json([
            'message' => 'Supplier status updated successfully',
            'supplier' => $supplier,
        ]);
    }
}
