<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\SaleService;
use App\Traits\EscapesLikeWildcards;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    use EscapesLikeWildcards;

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
        $query = Sale::with(['cashier:id,name'])->withCount('items');

        // Exclude voided sales by default (can be included via ?include_voided=1)
        if (!$request->boolean('include_voided')) {
            $query->where('status', '!=', 'voided');
        }

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
            $search = $this->escapeLike($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('sale_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $sales = $query->latest('created_at')
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

        // Derive payment_status from amount_paid vs total to prevent client-side spoofing
        if (isset($validated['items'])) {
            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $lineTotal = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
                $lineTotal -= $lineTotal * (($item['discount_percent'] ?? 0) / 100);
                $subtotal += $lineTotal;
            }
            $total = $subtotal - ($validated['discount_amount'] ?? 0);
            $amountPaid = (float) ($validated['amount_paid'] ?? $total);
            $saleType = $validated['sale_type'] ?? 'cash';

            // For credit sales, override amount_paid and payment_status
            if ($saleType === 'credit') {
                $validated['payment_status'] = $amountPaid > 0 ? 'partially_paid' : 'unpaid';
            } elseif ($amountPaid >= $total) {
                $validated['payment_status'] = 'paid';
            } elseif ($amountPaid > 0) {
                $validated['payment_status'] = 'partially_paid';
            } else {
                $validated['payment_status'] = 'unpaid';
            }
        }

        try {
            $sale = $this->saleService->createSale($validated, $request->user());

            AuditLog::log('sale.create', $sale, null, $sale->toArray(), "Sale {$sale->sale_number} created");

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
        $sale->load(['items.product', 'items.unitType', 'cashier', 'payments']);

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
            'method' => 'nullable|string|in:cash,pos,bank_transfer',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $updatedSale = $this->saleService->recordPayment(
                $sale,
                $validated['amount'],
                $request->user(),
                $validated['method'] ?? null,
                $validated['reference'] ?? null,
                $validated['notes'] ?? null
            );

            AuditLog::log('sale.payment', $sale, null, ['amount_paid' => $validated['amount']], "Payment of ₦{$validated['amount']} recorded for {$sale->sale_number}");

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
     * Update contact name for a credit sale
     * PATCH /api/sales/{sale}/contact
     */
    public function updateContact(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'contact_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
        ]);

        $sale->update($validated);

        return response()->json([
            'message' => 'Contact updated',
            'contact_name' => $sale->contact_name,
            'customer_phone' => $sale->customer_phone,
        ]);
    }

    /**
     * Get credit summary for outstanding credit tracking
     * GET /api/sales/credit-summary
     */
    public function creditSummary(Request $request)
    {
        $thresholdDays = Setting::get('credit_overdue_threshold_days', 7);

        // Use SQL aggregation for summary stats (no PHP loop needed)
        $baseQuery = Sale::whereIn('payment_status', ['unpaid', 'partially_paid']);

        $totalOutstanding = round((float) $baseQuery->clone()
            ->selectRaw('COALESCE(SUM(total_amount - amount_paid), 0) as total')
            ->value('total'), 2);

        $overdueData = $baseQuery->clone()
            ->selectRaw('COUNT(*) as count, ROUND(COALESCE(SUM(total_amount - amount_paid), 0), 2) as total')
            ->whereRaw('DATEDIFF(?, sale_date) >= ?', [now(), $thresholdDays])
            ->first();

        $pendingData = $baseQuery->clone()
            ->selectRaw('COUNT(*) as count, ROUND(COALESCE(SUM(total_amount - amount_paid), 0), 2) as total')
            ->whereRaw('DATEDIFF(?, sale_date) < ?', [now(), $thresholdDays])
            ->first();

        // Paginated credit items with eager-loaded cashier
        $credits = $baseQuery->clone()
            ->with(['cashier:id,name'])
            ->orderBy('sale_date', 'asc')
            ->paginate($request->input('per_page', 50))
            ->through(function ($sale) use ($thresholdDays) {
                $balance = round((float) $sale->total_amount - (float) $sale->amount_paid, 2);
                $daysOutstanding = max(0, (int) now()->diffInDays($sale->sale_date));

                return [
                    'id' => $sale->id,
                    'sale_number' => $sale->sale_number,
                    'sale_date' => $sale->sale_date->toIso8601String(),
                    'customer_name' => $sale->customer_name ?: 'Walk-in',
                    'customer_phone' => $sale->customer_phone,
                    'contact_name' => $sale->contact_name,
                    'total_amount' => round((float) $sale->total_amount, 2),
                    'amount_paid' => round((float) $sale->amount_paid, 2),
                    'balance' => $balance,
                    'days_outstanding' => $daysOutstanding,
                    'status' => $daysOutstanding >= $thresholdDays ? 'overdue' : 'pending',
                    'cashier_name' => $sale->cashier?->name,
                ];
            });

        return response()->json([
            'total_outstanding' => $totalOutstanding,
            'overdue_count' => (int) $overdueData->count,
            'overdue_amount' => (float) $overdueData->total,
            'pending_count' => (int) $pendingData->count,
            'pending_amount' => (float) $pendingData->total,
            'credits' => $credits,
        ]);
    }

    /**
     * Get overdue credit count for sidebar badge
     * GET /api/sales/overdue-count
     */
    public function overdueCount(Request $request)
    {
        $thresholdDays = Setting::get('credit_overdue_threshold_days', 7);

        $count = Sale::whereIn('payment_status', ['unpaid', 'partially_paid'])
            ->whereRaw("DATEDIFF(?, sale_date) >= ?", [now(), $thresholdDays])
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Get sales summary/statistics
     * GET /api/sales/summary
     */
    public function summary(Request $request)
    {
        $query = Sale::where('status', '!=', 'voided');

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
                ->where('status', '!=', 'voided')
                ->sum('amount_due'),
            'sales_by_type' => Sale::query()
                ->selectRaw('sale_type, COUNT(*) as count, SUM(total_amount) as total')
                ->whereBetween('sale_date', [$startDate, $endDate])
                ->where('status', '!=', 'voided')
                ->groupBy('sale_type')
                ->get(),
            'sales_by_payment_status' => Sale::query()
                ->selectRaw('payment_status, COUNT(*) as count, SUM(total_amount) as total')
                ->whereBetween('sale_date', [$startDate, $endDate])
                ->where('status', '!=', 'voided')
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

        $query = Sale::where('status', '!=', 'voided');

        // Date range
        $query->whereBetween('sale_date', [$startDate, $endDate]);

        // Use SQL aggregation for summary metrics instead of loading all records
        $summaryData = (clone $query)->selectRaw('
            COUNT(*) as total_sales,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(SUM(gross_profit), 0) as total_profit,
            COALESCE(SUM(cost_of_goods_sold), 0) as total_cogs,
            COALESCE(AVG(total_amount), 0) as average_sale_value,
            COALESCE(AVG(gross_profit), 0) as average_profit
        ')->first();

        // Payment method breakdown via SQL
        $paymentMethodBreakdown = (clone $query)
            ->selectRaw('sale_type as method, COUNT(*) as count, SUM(total_amount) as total, SUM(gross_profit) as profit')
            ->groupBy('sale_type')
            ->get()
            ->map(fn($row) => [
                'method' => $row->method,
                'count' => $row->count,
                'total' => (float) $row->total,
                'profit' => (float) $row->profit,
            ]);

        // Hourly distribution via SQL
        $hourlyDistribution = (clone $query)
            ->selectRaw('HOUR(sale_date) as hour, COUNT(*) as count, SUM(total_amount) as total')
            ->groupByRaw('HOUR(sale_date)')
            ->orderBy('hour')
            ->get()
            ->map(fn($row) => [
                'hour' => $row->hour,
                'count' => $row->count,
                'total' => (float) $row->total,
            ]);

        // Top selling products via SQL (limited)
        $topProducts = (clone $query)
            ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->selectRaw('products.id, products.name, SUM(sale_items.quantity) as total_quantity, SUM(sale_items.line_total) as total_revenue, SUM(sale_items.quantity * sale_items.unit_cost) as total_cost')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get();

        $totalRevenue = (float) ($summaryData->total_revenue ?? 0);
        $totalProfit = (float) ($summaryData->total_profit ?? 0);

        $summary = [
            'total_sales' => (int) ($summaryData->total_sales ?? 0),
            'total_orders' => (int) ($summaryData->total_sales ?? 0),
            'total_revenue' => $totalRevenue,
            'total_profit' => $totalProfit,
            'total_cogs' => (float) ($summaryData->total_cogs ?? 0),
            'average_sale_value' => (float) ($summaryData->average_sale_value ?? 0),
            'average_profit' => (float) ($summaryData->average_profit ?? 0),
            'profit_margin' => $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0,
        ];

        // Daily trend via SQL aggregation
        $dailyTrend = [];
        if (\Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate)) > 1) {
            $dailyTrend = (clone $query)
                ->selectRaw('DATE(sale_date) as date, COUNT(*) as count, SUM(total_amount) as revenue, SUM(gross_profit) as profit, SUM(cost_of_goods_sold) as cogs')
                ->groupByRaw('DATE(sale_date)')
                ->orderBy('date')
                ->get()
                ->map(fn($row) => [
                    'date' => $row->date,
                    'count' => $row->count,
                    'revenue' => (float) $row->revenue,
                    'profit' => (float) $row->profit,
                    'cogs' => (float) $row->cogs,
                ]);
        }

        // Map topProducts to expected format
        $topProductsFormatted = $topProducts->map(fn($item) => [
            'product_id' => $item->id,
            'product_name' => $item->name,
            'quantity_sold' => (int) $item->total_quantity,
            'revenue' => (float) $item->total_revenue,
            'cost' => (float) $item->total_cost,
            'profit' => (float) ($item->total_revenue - $item->total_cost),
        ]);

        return response()->json([
            'summary' => $summary,
            'payment_method_breakdown' => $paymentMethodBreakdown,
            'daily_trend' => $dailyTrend,
            'top_products' => $topProductsFormatted,
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

        // Exclude voided sales by default unless explicitly requested
        if (!$request->boolean('include_voided')) {
            $query->where('status', '!=', 'voided');
        }

        if ($request->filled('start_date')) {
            $query->whereDate('sale_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('sale_date', '<=', $request->end_date);
        }

        $sales = $query->latest('sale_date')->get();

        $format = $request->get('format', 'csv');

        // Calculate summary for PDF using SQL aggregation instead of collection
        $summaryQuery = Sale::where('status', '!=', 'voided');
        if ($request->filled('start_date')) {
            $summaryQuery->whereDate('sale_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $summaryQuery->whereDate('sale_date', '<=', $request->end_date);
        }
        $summaryData = $summaryQuery->selectRaw('COUNT(*) as total_sales, COALESCE(SUM(total_amount), 0) as total_revenue, COALESCE(SUM(gross_profit), 0) as total_profit, COALESCE(SUM(cost_of_goods_sold), 0) as total_cogs')->first();

        $summary = [
            'total_sales' => (int) $summaryData->total_sales,
            'total_revenue' => (float) $summaryData->total_revenue,
            'total_profit' => (float) $summaryData->total_profit,
            'total_cogs' => (float) $summaryData->total_cogs,
            'profit_margin' => $summaryData->total_revenue > 0
                ? ($summaryData->total_profit / $summaryData->total_revenue) * 100
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

        return DB::transaction(function () use ($sale, $user) {
            // Load items within transaction
            $sale->load('items');

            // Restore stock for each item with row locks
            // Use conversion_factor to restore correct base-unit quantity
            foreach ($sale->items as $item) {
                $product = Product::lockForUpdate()->find($item->product_id);
                if ($product) {
                    $conversionFactor = $item->conversion_factor ?? 1;
                    $quantityInBaseUnits = $item->quantity * $conversionFactor;

                    $previousStock = $product->stock_quantity;
                    $product->increment('stock_quantity', $quantityInBaseUnits);
                    $product->refresh();

                    StockMovement::create([
                        'product_id' => $product->id,
                        'user_id' => $user->id,
                        'type' => 'return',
                        'quantity' => $quantityInBaseUnits,
                        'previous_stock' => $previousStock,
                        'new_stock' => $product->stock_quantity,
                        'reference_type' => 'sale_delete',
                        'reference_id' => $sale->id,
                        'notes' => "Stock restored from deleted sale {$sale->sale_number}",
                    ]);
                }
            }

            $sale->delete();

            AuditLog::log('sale.delete', $sale, null, null, "Sale {$sale->sale_number} deleted");

            return response()->json([
                'message' => 'Sale deleted successfully',
            ]);
        });
    }
}