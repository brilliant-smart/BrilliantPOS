<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CacheResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, $ttl = 3600)
    {
        // Only cache GET requests
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        // Generate cache key
        $key = 'response:' . md5($request->fullUrl() . serialize($request->query()));

        // Check if cached
        if (config('performance.cache.enabled') && Cache::has($key)) {
            $cached = Cache::get($key);
            return response()->json($cached['data'], $cached['status'])
                ->withHeaders($cached['headers'] ?? []);
        }

        // Get response
        $response = $next($request);

        // Cache successful responses
        if ($response->status() === 200 && config('performance.cache.enabled')) {
            Cache::put($key, [
                'data' => json_decode($response->content(), true),
                'status' => $response->status(),
                'headers' => $response->headers->all(),
            ], (int) $ttl);
        }

        return $response;
    }
}
