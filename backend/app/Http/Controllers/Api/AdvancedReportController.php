<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdvancedReportingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AdvancedReportController extends Controller
{
    protected $reportingService;

    public function __construct(AdvancedReportingService $reportingService)
    {
        $this->reportingService = $reportingService;
    }

    /**
     * Get inventory aging report
     */
    public function inventoryAging(Request $request)
    {
        $report = $this->reportingService->inventoryAgingReport();

        if ($request->input('format') === 'csv') {
            return $this->exportToCsv($report['products'], 'inventory-aging-report.csv', [
                'Product Name' => 'product.name',
                'SKU' => 'product.sku',
                'Stock Quantity' => 'product.stock_quantity',
                'Days in Stock' => 'days_in_stock',
                'Aging Category' => 'aging_category',
                'Stock Value' => 'stock_value',
            ]);
        }

        return response()->json($report);
    }

    /**
     * Get supplier performance analytics
     */
    public function supplierPerformance(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        
        $report = $this->reportingService->supplierPerformanceAnalytics($startDate, $endDate);

        if ($request->input('format') === 'csv') {
            return $this->exportToCsv($report, 'supplier-performance-report.csv', [
                'Supplier Name' => 'supplier.name',
                'Total Orders' => 'total_orders',
                'Completed Orders' => 'completed_orders',
                'Total Amount' => 'total_amount',
                'Average Order Value' => 'average_order_value',
                'On Time Deliveries' => 'on_time_deliveries',
                'Late Deliveries' => 'late_deliveries',
                'On Time %' => 'on_time_percentage',
                'Quality Score' => 'quality_score',
            ]);
        }

        return response()->json($report);
    }

    /**
     * Get ABC analysis
     */
    public function abcAnalysis(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        
        $report = $this->reportingService->abcAnalysis($startDate, $endDate);

        if ($request->input('format') === 'csv') {
            return $this->exportToCsv($report['products'], 'abc-analysis-report.csv', [
                'Product Name' => 'product.name',
                'SKU' => 'product.sku',
                'Category' => 'category',
                'Revenue' => 'revenue',
                'Quantity Sold' => 'quantity_sold',
                'Revenue %' => 'revenue_percentage',
                'Cumulative %' => 'cumulative_percentage',
            ]);
        }

        return response()->json($report);
    }

    /**
     * Get dead stock report
     */
    public function deadStock(Request $request)
    {
        $days = $request->input('days', 90);

        $report = $this->reportingService->deadStockReport($days);

        if ($request->input('format') === 'csv') {
            return $this->exportToCsv($report['products'], 'dead-stock-report.csv', [
                'Product Name' => 'product.name',
                'SKU' => 'product.sku',
                'Stock Quantity' => 'stock_quantity',
                'Stock Value' => 'stock_value',
                'Days Since Last Sale' => 'days_since_last_sale',
            ]);
        }

        return response()->json($report);
    }

    /**
     * Get stockout report
     */
    public function stockout(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        
        $report = $this->reportingService->stockoutReport($startDate, $endDate);

        if ($request->input('format') === 'csv') {
            return $this->exportToCsv($report['stockouts'], 'stockout-report.csv', [
                'Product Name' => 'product.name',
                'SKU' => 'product.sku',
                'Current Stock' => 'current_stock',
                'Stock Status' => 'stock_status',
                'Reorder Point' => 'reorder_point',
                'Sales During Period' => 'sales_during_period',
                'Estimated Lost Revenue' => 'estimated_lost_revenue',
            ]);
        }

        return response()->json($report);
    }

    /**
     * Get sales forecast
     */
    public function salesForecast(Request $request)
    {
        $productId = $request->input('product_id');
        $forecastDays = $request->input('forecast_days', 30);
        
        $report = $this->reportingService->salesForecast($productId, $forecastDays);

        return response()->json($report);
    }

    /**
     * Export data to CSV
     */
    private function exportToCsv($data, $filename, $columnMapping)
    {
        $csvData = [];
        
        // Add headers
        $csvData[] = array_keys($columnMapping);

        // Add data rows
        foreach ($data as $item) {
            $row = [];
            foreach ($columnMapping as $path) {
                $value = $this->getNestedValue($item, $path);
                $row[] = $value;
            }
            $csvData[] = $row;
        }

        // Create CSV content
        $output = fopen('php://temp', 'r+');
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Get nested value from array/object using dot notation
     */
    private function getNestedValue($item, $path)
    {
        $keys = explode('.', $path);
        $value = $item;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } elseif (is_object($value) && isset($value->$key)) {
                $value = $value->$key;
            } else {
                return '';
            }
        }

        return $value;
    }
}
