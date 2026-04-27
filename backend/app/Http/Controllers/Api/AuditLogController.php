<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Get audit logs
     */
    public function index(Request $request)
    {
        $query = AuditLog::with('user');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->end_date);
        }

        if ($request->filled('ip_address')) {
            $query->where('ip_address', $request->ip_address);
        }

        $logs = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json($logs);
    }

    /**
     * Get audit log details
     */
    public function show(AuditLog $auditLog)
    {
        return response()->json($auditLog->load('user'));
    }

    /**
     * Get audit summary/statistics
     */
    public function statistics(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = AuditLog::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $stats = [
            'total_actions' => $query->count(),
            'today_actions' => (clone $query)->whereDate('created_at', today())->count(),
            'week_actions' => (clone $query)->where('created_at', '>=', now()->startOfWeek())->count(),
            'active_users' => (clone $query)->distinct('user_id')->count('user_id'),
            'by_action' => (clone $query)->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->get(),
            'by_model' => (clone $query)->selectRaw('model_type, COUNT(*) as count')
                ->groupBy('model_type')
                ->get(),
            'by_user' => (clone $query)->with('user')
                ->selectRaw('user_id, COUNT(*) as count')
                ->groupBy('user_id')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'recent_activities' => (clone $query)->with('user')
                ->latest()
                ->limit(20)
                ->get(),
        ];

        return response()->json($stats);
    }
}
