<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductPriceHistory;
use Illuminate\Http\Request;

class PriceHistoryController extends Controller
{
    /**
     * Get all price history records
     * GET /api/price-history
     */
    public function index(Request $request)
    {
        $query = ProductPriceHistory::with('product:id,name,sku');

        // Date range filter
        if ($request->filled('start_date')) {
            $query->whereDate('changed_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('changed_at', '<=', $request->end_date);
        }

        // Change type filter
        if ($request->filled('change_type')) {
            $query->where('change_type', $request->change_type);
        }

        // Product filter
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Trend filter
        if ($request->filled('trend')) {
            if ($request->trend === 'increase') {
                $query->where('price_change', '>', 0);
            } elseif ($request->trend === 'decrease') {
                $query->where('price_change', '<', 0);
            }
        }

        $priceHistory = $query->latest('changed_at')->get();

        // Format response
        $formattedHistory = $priceHistory->map(function ($record) {
            return [
                'id' => $record->id,
                'product_id' => $record->product_id,
                'product_name' => $record->product->name ?? 'Unknown Product',
                'product_sku' => $record->product->sku ?? 'N/A',
                'old_price' => $record->old_price,
                'new_price' => $record->new_price,
                'price_change' => $record->price_change,
                'percentage_change' => $record->percentage_change,
                'change_type' => $record->change_type,
                'supplier_name' => $record->supplier_name,
                'reference_number' => $record->reference_number,
                'changed_at' => $record->changed_at,
                'notes' => $record->notes,
            ];
        });

        return response()->json([
            'data' => $formattedHistory,
        ]);
    }

    /**
     * Get price history for a specific product
     * GET /api/products/{productId}/price-history
     */
    public function productHistory($productId)
    {
        $history = ProductPriceHistory::where('product_id', $productId)
            ->with('product:id,name,sku')
            ->latest('changed_at')
            ->get();

        return response()->json([
            'data' => $history,
        ]);
    }
}
