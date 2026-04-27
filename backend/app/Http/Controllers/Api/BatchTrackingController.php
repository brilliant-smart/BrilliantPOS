<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductBatch;
use App\Services\BatchTrackingService;
use Illuminate\Http\Request;

class BatchTrackingController extends Controller
{
    protected $batchService;

    public function __construct(BatchTrackingService $batchService)
    {
        $this->batchService = $batchService;
    }

    /**
     * Get all batches
     */
    public function index(Request $request)
    {
        $query = ProductBatch::with(['product', 'supplier', 'purchaseOrder']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by product
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter expiring soon
        if ($request->boolean('expiring_soon')) {
            $query->expiringSoon($request->input('days', 30));
        }

        // Filter expired
        if ($request->boolean('expired')) {
            $query->expired();
        }

        $batches = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json($batches);
    }

    /**
     * Store a new batch
     */
    public function store(Request $request)
    {
        // Convert month/year expiry date to last day of month
        if ($request->filled('expiry_date')) {
            $request->merge([
                'expiry_date' => $this->convertToLastDayOfMonth($request->expiry_date)
            ]);
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'batch_number' => 'required|string|unique:product_batches,batch_number',
            'manufacturing_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:today',
            'quantity_received' => 'required|integer|min:1',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'notes' => 'nullable|string',
        ]);

        $validated['quantity_remaining'] = $validated['quantity_received'];
        $validated['status'] = 'active';

        $batch = $this->batchService->createBatch($validated);

        return response()->json([
            'message' => 'Batch created successfully',
            'batch' => $batch->load(['product', 'supplier']),
        ], 201);
    }

    /**
     * Get a specific batch
     */
    public function show(ProductBatch $batch)
    {
        return response()->json($batch->load(['product', 'supplier', 'purchaseOrder']));
    }

    /**
     * Update a batch
     */
    public function update(Request $request, ProductBatch $batch)
    {
        $validated = $request->validate([
            'manufacturing_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'status' => 'nullable|in:active,expired,recalled,damaged',
            'notes' => 'nullable|string',
        ]);

        $batch->update($validated);

        return response()->json([
            'message' => 'Batch updated successfully',
            'batch' => $batch,
        ]);
    }

    /**
     * Get expiring batches
     */
    public function expiring(Request $request)
    {
        $days = $request->input('days', 30);
        $batches = $this->batchService->getExpiringBatches($days);

        return response()->json([
            'days' => $days,
            'batches' => $batches,
            'total' => $batches->count(),
        ]);
    }

    /**
     * Get expired batches
     */
    public function expired()
    {
        $batches = $this->batchService->getExpiredBatches();

        return response()->json([
            'batches' => $batches,
            'total' => $batches->count(),
        ]);
    }

    /**
     * Mark batch as expired
     */
    public function markExpired(ProductBatch $batch)
    {
        if ($batch->status === 'expired') {
            return response()->json([
                'message' => 'Batch is already marked as expired',
            ], 422);
        }

        $this->batchService->markBatchAsExpired($batch);

        return response()->json([
            'message' => 'Batch marked as expired successfully',
        ]);
    }

    /**
     * Get batch inventory report
     */
    public function inventoryReport(Request $request)
    {
        $report = $this->batchService->getBatchInventoryReport();

        return response()->json($report);
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