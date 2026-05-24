<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class ReceiptController extends Controller
{
    /**
     * Generate a short-lived receipt token
     * POST /api/sales/{sale}/receipt-token
     */
    public function generateToken(Sale $sale)
    {
        $token = Str::random(32);

        Cache::put("receipt_token:{$token}", $sale->id, now()->addMinutes(5));

        return response()->json([
            'token' => $token,
        ]);
    }

    /**
     * Download receipt using a short-lived token
     * GET /receipt/{token}
     */
    public function download(string $token)
    {
        $saleId = Cache::get("receipt_token:{$token}");

        if (!$saleId) {
            abort(404, 'Receipt link expired or invalid');
        }

        // Consume the token (single use)
        Cache::forget("receipt_token:{$token}");

        $sale = Sale::with(['items.product', 'user', 'cashier'])->findOrFail($saleId);

        return view('admin.receipts.thermal', compact('sale'));
    }
}