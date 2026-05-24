<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HideProfitData
{
    /**
     * Fields to remove for non-owner/manager users.
     */
    private const SENSITIVE_FIELDS = [
        'cost_price',
        'last_purchase_price',
        'profit',
        'profit_margin',
        'profit_percentage',
        'total_profit',
        'unit_cost',
        'line_profit',
        'line_cost',
        'gross_profit',
        'net_profit',
        'cost_of_goods_sold',
        'discount_percentage',
        'discount_amount',
        'amount_due',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $user = $request->user();

        // Owner and manager can see everything
        if (!$user || in_array($user->role, ['owner', 'manager'])) {
            return $response;
        }

        // For cashier and other roles, filter out cost/profit data
        $content = $response->getContent();

        $contentType = $response->headers->get('content-type') ?? '';

        if ($content && str_starts_with($contentType, 'application/json')) {
            $data = json_decode($content, true);

            if ($data) {
                $data = $this->filterProfitData($data);
                $response->setContent(json_encode($data));
            }
        } elseif ($content && str_starts_with($contentType, 'text/csv')) {
            // Filter CSV responses line by line
            $response->setContent($this->filterCsvProfitData($content));
        }

        return $response;
    }

    /**
     * Recursively filter profit/cost data from response
     */
    private function filterProfitData($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        // Remove sensitive fields
        foreach (self::SENSITIVE_FIELDS as $field) {
            unset($data[$field]);
        }

        // Recursively filter nested arrays and objects
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->filterProfitData($value);
            }
        }

        return $data;
    }

    /**
     * Filter profit/cost columns from CSV content
     */
    private function filterCsvProfitData(string $csv): string
    {
        $lines = str_getcsv($csv, "\n");
        if (empty($lines)) {
            return $csv;
        }

        $sensitiveFieldsLower = array_map('strtolower', self::SENSITIVE_FIELDS);

        // Parse header to find sensitive column indices
        $headers = str_getcsv(array_shift($lines));
        $removeIndices = [];
        foreach ($headers as $i => $header) {
            if (in_array(strtolower(trim($header)), $sensitiveFieldsLower)) {
                $removeIndices[] = $i;
            }
        }

        if (empty($removeIndices)) {
            return $csv;
        }

        // Rebuild header
        $newHeaders = array_values(array_filter($headers, fn($_, $i) => !in_array($i, $removeIndices), ARRAY_FILTER_USE_BOTH));

        // Rebuild data rows
        $filteredLines = [implode(',', $newHeaders)];
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $row = str_getcsv($line);
            $newRow = array_values(array_filter($row, fn($_, $i) => !in_array($i, $removeIndices), ARRAY_FILTER_USE_BOTH));
            $filteredLines[] = implode(',', $newRow);
        }

        return implode("\n", $filteredLines);
    }
}