<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ProductBatch;
use App\Services\BatchTrackingService;
use App\Traits\ConvertsDateToMonthEnd;
use Illuminate\Http\Request;

class BatchTrackingController extends Controller
{
    use ConvertsDateToMonthEnd;
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

        AuditLog::log('batch.create', $batch, null, $batch->toArray(), "Batch {$batch->batch_number} created");

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

        AuditLog::log('batch.update', $batch, null, $batch->toArray(), "Batch {$batch->batch_number} updated");

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

        AuditLog::log('batch.mark_expired', $batch, null, null, "Batch {$batch->batch_number} marked as expired");

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
}