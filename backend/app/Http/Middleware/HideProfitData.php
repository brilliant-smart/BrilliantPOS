<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class HideProfitData
{
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
        
        if ($content && $response->headers->get('content-type') === 'application/json') {
            $data = json_decode($content, true);
            
            if ($data) {
                $data = $this->filterProfitData($data);
                $response->setContent(json_encode($data));
            }
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
        
        // Fields to remove for non-owner/manager users
        $sensitiveFields = [
            'cost_price',
            'last_purchase_price',
            'profit',
            'profit_margin',
            'profit_percentage',
            'total_profit',
            'unit_cost',
            'line_profit',
            'gross_profit',
            'net_profit',
            'amount_paid',
            'balance',
        ];
        
        // Remove sensitive fields
        foreach ($sensitiveFields as $field) {
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
}
