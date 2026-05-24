<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Traits\EscapesLikeWildcards;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ExpenseController extends Controller
{
    use EscapesLikeWildcards;
    /**
     * Display a listing of expenses with filtering and pagination.
     */
    public function index(Request $request)
    {
        $query = Expense::with(['category', 'recorder']);

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $this->escapeLike($request->search);
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('expense_number', 'like', "%{$search}%")
                  ->orWhere('vendor', 'like', "%{$search}%");
            });
        }

        // Date range filter
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        // Category filter
        if ($request->has('category_id') && $request->category_id) {
            $query->category($request->category_id);
        }

        // Payment method filter
        if ($request->has('payment_method') && $request->payment_method) {
            $query->paymentMethod($request->payment_method);
        }

        // Sort by expense_date descending (most recent first)
        $query->orderBy('expense_date', 'desc')->orderBy('created_at', 'desc');

        // Paginate
        $perPage = $request->get('per_page', 15);
        $expenses = $query->paginate($perPage);

        return response()->json($expenses);
    }

    /**
     * Store a newly created expense.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'payment_method' => 'required|in:cash,bank_transfer,pos_terminal,personal_payment,shop_account,other',
            'category_id' => 'nullable|exists:expense_categories,id',
            'expense_date' => 'required|date|before_or_equal:today',
            'vendor' => 'nullable|string|max:255',
            'receipt_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $expense = Expense::create([
            'title' => $request->title,
            'description' => $request->description,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'category_id' => $request->category_id,
            'recorded_by' => $request->user()->id,
            'expense_date' => $request->expense_date,
            'vendor' => $request->vendor,
            'receipt_number' => $request->receipt_number,
            'notes' => $request->notes,
        ]);

        $expense->load(['category', 'recorder']);

        AuditLog::log('expense.create', $expense, null, $expense->toArray(), "Expense {$expense->expense_number} created");

        return response()->json([
            'message' => 'Expense recorded successfully',
            'expense' => $expense
        ], 201);
    }

    /**
     * Display the specified expense.
     */
    public function show(Expense $expense)
    {
        $expense->load(['category', 'recorder']);
        return response()->json($expense);
    }

    /**
     * Update the specified expense.
     */
    public function update(Request $request, Expense $expense)
    {
        // Only owner/manager or the creator can update
        $user = $request->user();
        if (!in_array($user->role, ['owner', 'manager']) && $expense->recorded_by !== $user->id) {
            return response()->json(['message' => 'You can only update your own expenses'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'sometimes|required|numeric|min:0.01|max:999999999.99',
            'payment_method' => 'sometimes|required|in:cash,bank_transfer,pos_terminal,personal_payment,shop_account,other',
            'category_id' => 'nullable|exists:expense_categories,id',
            'expense_date' => 'sometimes|required|date|before_or_equal:today',
            'vendor' => 'nullable|string|max:255',
            'receipt_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $expense->update($request->only([
            'title',
            'description',
            'amount',
            'payment_method',
            'category_id',
            'expense_date',
            'vendor',
            'receipt_number',
            'notes',
        ]));

        AuditLog::log('expense.update', $expense, null, $expense->toArray(), "Expense {$expense->expense_number} updated");

        $expense->load(['category', 'recorder']);

        return response()->json([
            'message' => 'Expense updated successfully',
            'expense' => $expense
        ]);
    }

    /**
     * Remove the specified expense (soft delete).
     */
    public function destroy(Expense $expense)
    {
        // Only owner/manager or the creator can delete
        $user = request()->user();
        if (!in_array($user->role, ['owner', 'manager']) && $expense->recorded_by !== $user->id) {
            return response()->json(['message' => 'You can only delete your own expenses'], 403);
        }

        AuditLog::log('expense.delete', $expense, null, null, "Expense {$expense->expense_number} deleted");

        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted successfully'
        ]);
    }

    /**
     * Get expense analytics and summary.
     */
    public function analytics(Request $request)
    {
        $period = $request->get('period', 'this_month');
        $startDate = null;
        $endDate = null;

        // Calculate date range based on period
        switch ($period) {
            case 'today':
                $startDate = Carbon::today();
                $endDate = Carbon::today();
                break;
            case 'yesterday':
                $startDate = Carbon::yesterday();
                $endDate = Carbon::yesterday();
                break;
            case 'this_week':
                $startDate = Carbon::now()->startOfWeek();
                $endDate = Carbon::now()->endOfWeek();
                break;
            case 'last_week':
                $startDate = Carbon::now()->subWeek()->startOfWeek();
                $endDate = Carbon::now()->subWeek()->endOfWeek();
                break;
            case 'this_month':
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
                break;
            case 'last_month':
                $startDate = Carbon::now()->subMonth()->startOfMonth();
                $endDate = Carbon::now()->subMonth()->endOfMonth();
                break;
            case 'custom':
                $startDate = $request->get('start_date', Carbon::now()->startOfMonth());
                $endDate = $request->get('end_date', Carbon::now()->endOfMonth());
                break;
            default:
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
        }

        // Get expenses for the period — use SQL aggregation for summary
        $summaryData = Expense::dateRange($startDate, $endDate)
            ->selectRaw('COUNT(*) as total_expenses, COALESCE(SUM(amount), 0) as total_amount, COALESCE(AVG(amount), 0) as average_expense')
            ->first();

        $summary = [
            'total_expenses' => (int) $summaryData->total_expenses,
            'total_amount' => (float) $summaryData->total_amount,
            'average_expense' => (float) $summaryData->average_expense,
        ];

        // Payment method breakdown via SQL
        $paymentBreakdown = Expense::dateRange($startDate, $endDate)
            ->selectRaw('payment_method as method, COUNT(*) as count, SUM(amount) as amount')
            ->groupBy('payment_method')
            ->get()
            ->map(fn($row) => [
                'method' => $row->method,
                'count' => $row->count,
                'amount' => (float) $row->amount,
            ]);

        // Category breakdown via SQL with join
        $categoryBreakdown = Expense::dateRange($startDate, $endDate)
            ->join('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
            ->selectRaw('expense_categories.id as category_id, expense_categories.name as category_name, COALESCE(expense_categories.color, \'#6B7280\') as category_color, COUNT(*) as count, SUM(expenses.amount) as amount')
            ->groupBy('expense_categories.id', 'expense_categories.name', 'expense_categories.color')
            ->get()
            ->map(fn($row) => [
                'category_id' => $row->category_id,
                'category_name' => $row->category_name,
                'category_color' => $row->category_color,
                'count' => $row->count,
                'amount' => (float) $row->amount,
            ]);

        // Top vendors via SQL
        $topVendors = Expense::dateRange($startDate, $endDate)
            ->whereNotNull('vendor')
            ->where('vendor', '!=', '')
            ->selectRaw('vendor, COUNT(*) as count, SUM(amount) as amount')
            ->groupBy('vendor')
            ->orderByDesc('amount')
            ->limit(5)
            ->get()
            ->map(fn($row) => [
                'vendor' => $row->vendor,
                'count' => $row->count,
                'amount' => (float) $row->amount,
            ]);

        // Daily trend via SQL
        $dailyTrend = [];
        if (Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) > 1) {
            $dailyTrend = Expense::dateRange($startDate, $endDate)
                ->selectRaw('DATE(expense_date) as date, COUNT(*) as count, SUM(amount) as amount')
                ->groupByRaw('DATE(expense_date)')
                ->orderBy('date')
                ->get()
                ->map(fn($row) => [
                    'date' => $row->date,
                    'count' => $row->count,
                    'amount' => (float) $row->amount,
                ]);
        }

        return response()->json([
            'summary' => $summary,
            'payment_breakdown' => $paymentBreakdown,
            'category_breakdown' => $categoryBreakdown,
            'top_vendors' => $topVendors,
            'daily_trend' => $dailyTrend,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
                'type' => $period,
            ],
        ]);
    }
}