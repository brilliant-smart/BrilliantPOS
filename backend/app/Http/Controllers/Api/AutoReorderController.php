<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutoReorderLog;
use App\Services\AutoReorderService;
use Illuminate\Http\Request;

class AutoReorderController extends Controller
{
    protected $autoReorderService;

    public function __construct(AutoReorderService $autoReorderService)
    {
        $this->autoReorderService = $autoReorderService;
    }

    /**
     * Get reorder suggestions
     */
    public function suggestions(Request $request)
    {
        $suggestions = $this->autoReorderService->getReorderSuggestions();

        return response()->json([
            'suggestions' => $suggestions,
            'total' => $suggestions->count(),
        ]);
    }

    /**
     * Trigger manual reorder check
     */
    public function triggerCheck()
    {
        $results = $this->autoReorderService->checkAndTriggerReorders();

        return response()->json([
            'message' => 'Reorder check completed',
            'results' => $results,
        ]);
    }

    /**
     * Get auto-reorder logs
     */
    public function logs(Request $request)
    {
        $query = AutoReorderLog::with(['product', 'suggestedSupplier', 'purchaseOrder', 'triggeredBy']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('action_taken')) {
            $query->where('action_taken', $request->action_taken);
        }

        $logs = $query->latest('triggered_at')->paginate($request->input('per_page', 15));

        return response()->json($logs);
    }

    /**
     * Get auto-reorder statistics
     */
    public function statistics(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $stats = $this->autoReorderService->getStatistics($startDate, $endDate);

        return response()->json($stats);
    }
}
