<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FinancialReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    protected $financialReportService;

    public function __construct(FinancialReportService $financialReportService)
    {
        $this->financialReportService = $financialReportService;
    }

    /**
     * Get comprehensive financial overview
     * GET /api/reports/financial-overview
     */
    public function financialOverview(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $overview = $this->financialReportService->getFinancialOverview(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        return response()->json($overview);
    }

    /**
     * Get cashier performance report
     * GET /api/reports/cashier-performance
     */
    public function cashierPerformance(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $report = $this->financialReportService->getCashierPerformance(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        return response()->json($report);
    }

    /**
     * Get top selling products
     * GET /api/reports/top-selling-products
     */
    public function topSellingProducts(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $products = $this->financialReportService->getTopSellingProducts(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
            $validated['limit'] ?? 20
        );

        return response()->json($products);
    }

    /**
     * Detect stock variances (potential theft/loss)
     * GET /api/reports/stock-variances
     */
    public function stockVariances(Request $request)
    {
        $variances = $this->financialReportService->detectStockVariances();

        return response()->json([
            'variances' => $variances,
            'total_variances' => count($variances),
            'high_severity_count' => collect($variances)->where('severity', 'high')->count(),
            'total_variance_value' => round(collect($variances)->sum('variance_value'), 2),
        ]);
    }

    /**
     * Get reorder report
     * GET /api/reports/reorder
     */
    public function reorderReport(Request $request)
    {
        $products = $this->financialReportService->getReorderReport();

        return response()->json([
            'products' => $products,
            'total_products_to_reorder' => count($products),
            'total_estimated_cost' => round(collect($products)->sum('estimated_cost'), 2),
        ]);
    }

    /**
     * Get expiring products
     * GET /api/reports/expiring-products
     */
    public function expiringProducts(Request $request)
    {
        $validated = $request->validate([
            'days_ahead' => 'nullable|integer|min:1|max:365',
        ]);

        $products = $this->financialReportService->getExpiringProducts(
            $validated['days_ahead'] ?? 30
        );

        return response()->json([
            'products' => $products,
            'total_expiring_products' => count($products),
            'critical_count' => collect($products)->where('urgency', 'critical')->count(),
            'total_stock_value_at_risk' => round(collect($products)->sum('stock_value'), 2),
        ]);
    }
}