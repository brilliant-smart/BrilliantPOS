<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ExpenseController extends Controller
{
    /**
     * Display a listing of expenses with filtering and pagination.
     */
    public function index(Request $request)
    {
        $query = Expense::with(['category', 'recorder']);

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
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

        // Get expenses for the period
        $expenses = Expense::dateRange($startDate, $endDate)->get();

        // Calculate summary
        $summary = [
            'total_expenses' => $expenses->count(),
            'total_amount' => $expenses->sum('amount'),
            'average_expense' => $expenses->count() > 0 ? $expenses->avg('amount') : 0,
        ];

        // Payment method breakdown
        $paymentBreakdown = $expenses->groupBy('payment_method')->map(function ($items, $method) {
            return [
                'method' => $method,
                'count' => $items->count(),
                'amount' => $items->sum('amount'),
            ];
        })->values();

        // Category breakdown
        $categoryBreakdown = $expenses->groupBy('category_id')->map(function ($items) {
            $category = $items->first()->category;
            return [
                'category_id' => $category ? $category->id : null,
                'category_name' => $category ? $category->name : 'Uncategorized',
                'category_color' => $category ? $category->color : '#6B7280',
                'count' => $items->count(),
                'amount' => $items->sum('amount'),
            ];
        })->values();

        // Top vendors
        $topVendors = $expenses->filter(function ($expense) {
            return !empty($expense->vendor);
        })->groupBy('vendor')->map(function ($items, $vendor) {
            return [
                'vendor' => $vendor,
                'count' => $items->count(),
                'amount' => $items->sum('amount'),
            ];
        })->sortByDesc('amount')->take(5)->values();

        // Daily trend (if period is longer than a day)
        $dailyTrend = [];
        if (Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) > 1) {
            $dailyTrend = $expenses->groupBy(function ($expense) {
                return $expense->expense_date->format('Y-m-d');
            })->map(function ($items, $date) {
                return [
                    'date' => $date,
                    'count' => $items->count(),
                    'amount' => $items->sum('amount'),
                ];
            })->values();
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