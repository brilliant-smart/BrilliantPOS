<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryAnalyticsService;
use Illuminate\Http\Request;

class InventoryAnalyticsController extends Controller
{
    protected InventoryAnalyticsService $analyticsService;

    public function __construct(InventoryAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get dashboard statistics
     * GET /api/inventory/analytics/dashboard
     */
    public function dashboard(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $stats = $this->analyticsService->getDashboardStats(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
            $request->user()
        );

        return response()->json($stats);
    }

    /**
     * Get movement report
     * GET /api/inventory/analytics/movements
     */
    public function movements(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'type' => 'nullable|in:purchase,sale,adjustment,damage,return',
            'product_id' => 'nullable|exists:products,id',
            'user_id' => 'nullable|exists:users,id',
            'limit' => 'nullable|integer|min:1|max:500',
        ]);

        $report = $this->analyticsService->getMovementReport($validated, $request->user());

        return response()->json($report);
    }

    /**
     * Get turnover rate analysis
     * GET /api/inventory/analytics/turnover
     */
    public function turnover(Request $request)
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $turnover = $this->analyticsService->getTurnoverRate($validated['days'] ?? 30, $request->user());

        return response()->json($turnover);
    }

    /**
     * Export report to CSV
     * GET /api/inventory/analytics/export
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:dashboard,movements,turnover',
            'format' => 'required|in:csv',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $type = $validated['type'];
        $format = $validated['format'];

        if ($format === 'csv') {
            return $this->exportToCSV($type, $validated);
        }

        return response()->json([
            'message' => 'Format not supported yet',
        ], 400);
    }

    /**
     * Export data to CSV
     */
    private function exportToCSV(string $type, array $filters)
    {
        $filename = "inventory_{$type}_" . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($type, $filters) {
            $file = fopen('php://output', 'w');

            if ($type === 'movements') {
                // Export movements
                fputcsv($file, ['Date', 'Product', 'SKU', 'Type', 'Quantity', 'Previous', 'New', 'User', 'Notes']);
                
                $report = $this->analyticsService->getMovementReport($filters, request()->user());
                foreach ($report['movements'] as $movement) {
                    fputcsv($file, [
                        $movement['created_at'],
                        $movement['product_name'],
                        $movement['sku'],
                        $movement['type'],
                        $movement['quantity'],
                        $movement['previous_quantity'],
                        $movement['new_quantity'],
                        $movement['user_name'],
                        $movement['notes'],
                    ]);
                }
            } elseif ($type === 'turnover') {
                // Export turnover
                fputcsv($file, ['Product', 'SKU', 'Units Sold', 'Current Stock', 'Turnover Rate', 'Days of Stock']);
                
                $turnover = $this->analyticsService->getTurnoverRate($filters['days'] ?? 30, request()->user());
                foreach ($turnover['products'] as $product) {
                    fputcsv($file, [
                        $product['product_name'],
                        $product['sku'] ?? 'N/A',
                        $product['units_sold'],
                        $product['current_stock'],
                        $product['turnover_rate'],
                        $product['days_of_stock'],
                    ]);
                }
            } elseif ($type === 'dashboard') {
                // Export dashboard summary
                $stats = $this->analyticsService->getDashboardStats(
                    $filters['start_date'] ?? null,
                    $filters['end_date'] ?? null,
                    request()->user()
                );
                
                fputcsv($file, ['Inventory Dashboard Summary']);
                fputcsv($file, ['Generated', date('Y-m-d H:i:s')]);
                fputcsv($file, []);
                
                fputcsv($file, ['Overview']);
                fputcsv($file, ['Total Products', $stats['overview']['total_products']]);
                fputcsv($file, ['Total Stock Units', $stats['overview']['total_stock_units']]);
                fputcsv($file, ['Total Stock Value', $stats['overview']['total_stock_value']]);
                fputcsv($file, []);
                
                fputcsv($file, ['Stock Status']);
                fputcsv($file, ['In Stock', $stats['stock_status']['in_stock']]);
                fputcsv($file, ['Low Stock', $stats['stock_status']['low_stock']]);
                fputcsv($file, ['Out of Stock', $stats['stock_status']['out_of_stock']]);
                fputcsv($file, []);

                fputcsv($file, ['Inventory Value']);
                fputcsv($file, ['Total Products', $stats['inventory_value']['product_count']]);
                fputcsv($file, ['Total Units', $stats['inventory_value']['total_units']]);
                fputcsv($file, ['Total Value', $stats['inventory_value']['total_value']]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
