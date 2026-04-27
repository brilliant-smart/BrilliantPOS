<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class SaleController extends Controller
{
    protected $saleService;

    public function __construct(SaleService $saleService)
    {
        $this->saleService = $saleService;
    }

    /**
     * Display a listing of sales
     * GET /api/sales
     */
    public function index(Request $request)
    {
        $query = Sale::with(['cashier']);

        // Sale type filter
        if ($request->filled('sale_type')) {
            $query->where('sale_type', $request->sale_type);
        }

        // Payment status filter
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Cashier filter
        if ($request->filled('cashier_id')) {
            $query->where('cashier_id', $request->cashier_id);
        }

        // Date range
        if ($request->filled('start_date')) {
            $query->whereDate('sale_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('sale_date', '<=', $request->end_date);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sale_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $sales = $query->latest('sale_date')
            ->paginate($request->input('per_page', 15));

        return response()->json($sales);
    }

    /**
     * Store a new sale
     * POST /api/sales
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sale_type' => 'nullable|in:cash,credit,online,pos',
            'payment_status' => 'nullable|in:unpaid,partially_paid,paid',
            'sale_date' => 'nullable|date',
            'discount_amount' => 'nullable|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.notes' => 'nullable|string',
        ]);

        try {
            $sale = $this->saleService->createSale($validated, $request->user());

            return response()->json([
                'message' => 'Sale created successfully',
                'sale' => $sale,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create sale',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified sale
     * GET /api/sales/{sale}
     */
    public function show(Sale $sale)
    {
        $sale->load(['items.product', 'cashier']);

        return response()->json($sale);
    }

    /**
     * Record payment for credit sale
     * POST /api/sales/{sale}/payment
     */
    public function recordPayment(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $updatedSale = $this->saleService->recordPayment(
                $sale,
                $validated['amount'],
                $request->user()
            );

            return response()->json([
                'message' => 'Payment recorded successfully',
                'sale' => $updatedSale,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to record payment',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get sales summary/statistics
     * GET /api/sales/summary
     */
    public function summary(Request $request)
    {
        $query = Sale::query();

        // Date range (default to today)
        $startDate = $request->input('start_date', now()->startOfDay());
        $endDate = $request->input('end_date', now()->endOfDay());

        $query->whereBetween('sale_date', [$startDate, $endDate]);

        $summary = [
            'total_sales' => $query->count(),
            'total_revenue' => $query->sum('total_amount'),
            'total_cost' => $query->sum('cost_of_goods_sold'),
            'total_profit' => $query->sum('gross_profit'),
            'average_sale_value' => $query->avg('total_amount'),
            'total_outstanding' => Sale::where('payment_status', '!=', 'paid')
                ->sum('amount_due'),
            'sales_by_type' => Sale::query()
                ->selectRaw('sale_type, COUNT(*) as count, SUM(total_amount) as total')
                ->whereBetween('sale_date', [$startDate, $endDate])
                ->groupBy('sale_type')
                ->get(),
            'sales_by_payment_status' => Sale::query()
                ->selectRaw('payment_status, COUNT(*) as count, SUM(total_amount) as total')
                ->whereBetween('sale_date', [$startDate, $endDate])
                ->groupBy('payment_status')
                ->get(),
        ];

        return response()->json($summary);
    }

    /**
     * Get comprehensive sales analytics
     * GET /api/sales/analytics
     */
    public function analytics(Request $request)
    {
        $period = $request->get('period', 'this_month');
        $startDate = null;
        $endDate = null;

        // Calculate date range based on period
        switch ($period) {
            case 'today':
                $startDate = now()->startOfDay();
                $endDate = now()->endOfDay();
                break;
            case 'yesterday':
                $startDate = now()->subDay()->startOfDay();
                $endDate = now()->subDay()->endOfDay();
                break;
            case 'this_week':
                $startDate = now()->startOfWeek();
                $endDate = now()->endOfWeek();
                break;
            case 'this_month':
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
                break;
            case 'custom':
                $startDate = $request->get('start_date', now()->startOfMonth());
                $endDate = $request->get('end_date', now()->endOfMonth());
                break;
            default:
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
        }

        $query = Sale::query();

        // Date range
        $query->whereBetween('sale_date', [$startDate, $endDate]);

        // Get all sales for the period
        $sales = $query->get();

        // Calculate summary metrics
        $summary = [
            'total_sales' => $sales->count(),
            'total_orders' => $sales->count(),
            'total_revenue' => $sales->sum('total_amount'),
            'total_profit' => $sales->sum('gross_profit'),
            'total_cogs' => $sales->sum('cost_of_goods_sold'),
            'average_sale_value' => $sales->count() > 0 ? $sales->avg('total_amount') : 0,
            'average_profit' => $sales->count() > 0 ? $sales->avg('gross_profit') : 0,
            'profit_margin' => $sales->sum('total_amount') > 0
                ? ($sales->sum('gross_profit') / $sales->sum('total_amount')) * 100
                : 0,
        ];

        // Sales by payment method
        $paymentMethodBreakdown = $sales->groupBy('sale_type')->map(function ($items, $method) {
            return [
                'method' => $method,
                'count' => $items->count(),
                'revenue' => $items->sum('total_amount'),
                'profit' => $items->sum('gross_profit'),
            ];
        })->values();

        // Daily trend
        $dailyTrend = [];
        if (\Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate)) > 1) {
            $dailyTrend = $sales->groupBy(function ($sale) {
                return $sale->sale_date->format('Y-m-d');
            })->map(function ($items, $date) {
                return [
                    'date' => $date,
                    'count' => $items->count(),
                    'revenue' => $items->sum('total_amount'),
                    'profit' => $items->sum('gross_profit'),
                    'cogs' => $items->sum('cost_of_goods_sold'),
                ];
            })->values();
        }

        // Top selling products (from sale items)
        $topProducts = \DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->select(
                'products.id',
                'products.name',
                \DB::raw('SUM(sale_items.quantity) as total_quantity'),
                \DB::raw('SUM(sale_items.line_total) as total_revenue'),
                \DB::raw('SUM(sale_items.quantity * sale_items.unit_cost) as total_cost')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->id,
                    'product_name' => $item->name,
                    'quantity_sold' => $item->total_quantity,
                    'revenue' => $item->total_revenue,
                    'cost' => $item->total_cost,
                    'profit' => $item->total_revenue - $item->total_cost,
                ];
            });

        return response()->json([
            'summary' => $summary,
            'payment_method_breakdown' => $paymentMethodBreakdown,
            'daily_trend' => $dailyTrend,
            'top_products' => $topProducts,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
                'type' => $period,
            ],
        ]);
    }

    /**
     * Export sales as CSV or PDF
     * GET /api/sales/export
     */
    public function exportCsv(Request $request)
    {
        $query = Sale::with(['cashier', 'items.product']);

        if ($request->filled('start_date')) {
            $query->whereDate('sale_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('sale_date', '<=', $request->end_date);
        }

        $sales = $query->latest('sale_date')->get();

        $format = $request->get('format', 'csv');

        // Calculate summary for PDF
        $summary = [
            'total_sales' => $sales->count(),
            'total_revenue' => $sales->sum('total_amount'),
            'total_profit' => $sales->sum('gross_profit'),
            'total_cogs' => $sales->sum('cost_of_goods_sold'),
            'profit_margin' => $sales->sum('total_amount') > 0
                ? ($sales->sum('gross_profit') / $sales->sum('total_amount')) * 100
                : 0,
        ];

        // Get period info
        $period = [
            'start' => $request->get('start_date', now()->startOfMonth()),
            'end' => $request->get('end_date', now()->endOfMonth()),
        ];

        // PDF Export
        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.sales-report', compact('sales', 'summary', 'period'));
            $pdf->setPaper('a4', 'portrait');
            $filename = 'sales-report-' . date('Y-m-d') . '.pdf';
            return $pdf->download($filename);
        }

        // CSV Export
        $filename = 'sales-export-' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($sales) {
            $file = fopen('php://output', 'w');

            // Header
            fputcsv($file, [
                'Sale Number',
                'Date',
                'Cashier',
                'Type',
                'Customer',
                'Total Amount',
                'COGS',
                'Gross Profit',
                'Profit Margin %',
                'Payment Status',
                'Amount Paid',
                'Amount Due'
            ]);

            // Data
            foreach ($sales as $sale) {
                $profitMargin = $sale->total_amount > 0
                    ? ($sale->gross_profit / $sale->total_amount) * 100
                    : 0;

                fputcsv($file, [
                    $sale->sale_number,
                    $sale->sale_date->format('Y-m-d H:i'),
                    $sale->cashier ? $sale->cashier->name : 'N/A',
                    $sale->sale_type,
                    $sale->customer_name ?? 'Walk-in Customer',
                    number_format($sale->total_amount, 2),
                    number_format($sale->cost_of_goods_sold ?? 0, 2),
                    number_format($sale->gross_profit ?? 0, 2),
                    number_format($profitMargin, 2),
                    $sale->payment_status,
                    number_format($sale->amount_paid ?? 0, 2),
                    number_format($sale->amount_due ?? 0, 2),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Print receipt (standard format)
     * WEB ROUTE: GET /admin/sales/{sale}/receipt
     * Authentication handled by auth.token middleware
     */
    public function printReceipt(Request $request, Sale $sale)
    {
        // Load relationships and return printable receipt view
        $sale->load(['items.product', 'user', 'cashier']);

        return view('pdf.sale-receipt', compact('sale'));
    }

    /**
     * Print thermal receipt (80mm POS printer optimized)
     * WEB ROUTE: GET /admin/receipts/{sale}
     * Authentication handled by auth.token middleware
     */
    public function thermalReceipt(Request $request, Sale $sale)
    {
        // Load relationships for thermal receipt
        $sale->load(['items.product', 'user', 'cashier']);

        return view('admin.receipts.thermal', compact('sale'));
    }

    /**
     * Delete sale (soft delete) - Only for today's sales
     * DELETE /api/sales/{sale}
     */
    public function destroy(Sale $sale)
    {
        // Only owner can delete
        $user = request()->user();
        if (!in_array($user->role, ['owner'])) {
            return response()->json(['message' => 'Only owner can delete sales'], 403);
        }

        // Only allow deletion of today's sales
        if (!$sale->sale_date->isToday()) {
            return response()->json([
                'message' => 'Can only delete sales from today',
            ], 422);
        }

        $sale->delete();

        return response()->json([
            'message' => 'Sale deleted successfully',
        ]);
    }
}