<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
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

        if ($request->filled('action_category')) {
            $category = $request->action_category;
            $query->where(function ($q) use ($category) {
                switch ($category) {
                    case 'create':
                        $q->where('action', 'like', '%.create');
                        break;
                    case 'update':
                        $q->where('action', 'like', '%.update')
                          ->orWhere('action', 'like', '%.toggle_status');
                        break;
                    case 'delete':
                        $q->where('action', 'like', '%.delete')
                          ->orWhere('action', 'like', '%.deactivate')
                          ->orWhere('action', 'like', '%.mark_expired');
                        break;
                    case 'auth':
                        $q->where('action', 'like', 'auth.%');
                        break;
                    case 'security':
                        $q->where('action', 'security_settings.update')
                          ->orWhere('action', 'like', 'ip_whitelist.%')
                          ->orWhere('action', '2fa.%')
                          ->orWhere('action', 'user.password_change');
                        break;
                    case 'backup':
                        $q->where('action', 'like', 'backup.%');
                        break;
                    case 'stock':
                        $q->where('action', 'like', 'stock.%');
                        break;
                    case 'void':
                        $q->where('action', 'like', '%.void')
                          ->orWhere('action', 'like', '%.cancel')
                          ->orWhere('action', 'like', '%.reject');
                        break;
                    case 'approve':
                        $q->where('action', 'like', '%.approve');
                        break;
                    case 'payment':
                        $q->where('action', 'sale.payment')
                          ->orWhere('action', 'purchase_order.payment');
                        break;
                }
            });
        }

        if ($request->filled('model_type')) {
            $modelMap = [
                'Product' => 'App\Models\Product',
                'Sale' => 'App\Models\Sale',
                'User' => 'App\Models\User',
                'PurchaseOrder' => 'App\Models\PurchaseOrder',
                'Supplier' => 'App\Models\Supplier',
                'Expense' => 'App\Models\Expense',
                'ExpenseCategory' => 'App\Models\ExpenseCategory',
                'BatchTracking' => 'App\Models\BatchTracking',
                'Setting' => 'App\Models\Setting',
                'SecuritySetting' => 'SecuritySetting',
                'IpWhitelist' => 'IpWhitelist',
                'Backup' => 'Backup',
            ];
            $modelValue = $request->model_type;
            $query->where('model_type', $modelMap[$modelValue] ?? $modelValue);
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

        return AuditLogResource::collection($logs);
    }

    /**
     * Get audit log details
     */
    public function show(AuditLog $auditLog)
    {
        return new AuditLogResource($auditLog->load('user'));
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
                ->get()
                ->map(function ($item) {
                    $label = $item->model_type;
                    if ($label) {
                        $short = class_basename($label);
                        $label = preg_replace('/(?=[A-Z])/', ' ', $short);
                    } else {
                        $label = 'System';
                    }
                    return ['model_type' => $item->model_type, 'label' => $label, 'count' => $item->count];
                }),
            'by_user' => (clone $query)->with('user:id,name,email,role')
                ->selectRaw('user_id, COUNT(*) as count')
                ->groupBy('user_id')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'recent_activities' => (clone $query)
                ->select(['id', 'user_id', 'action', 'model_type', 'model_id', 'description', 'ip_address', 'created_at'])
                ->with('user:id,name,email,role')
                ->latest()
                ->limit(20)
                ->get(),
        ];

        return response()->json($stats);
    }
}
