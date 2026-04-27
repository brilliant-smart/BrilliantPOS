<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckIPWhitelist
{
    public function handle(Request $request, Closure $next)
    {
        $settings = DB::table('security_settings')->first();
        
        if (!$settings || !$settings->ip_whitelist_enabled) {
            return $next($request);
        }

        $clientIP = $request->ip();
        
        $isWhitelisted = DB::table('ip_whitelist')
            ->where('ip_address', $clientIP)
            ->where('is_active', true)
            ->exists();

        if (!$isWhitelisted) {
            return response()->json([
                'message' => 'Access denied. Your IP address is not whitelisted.',
            ], 403);
        }

        return $next($request);
    }
}
