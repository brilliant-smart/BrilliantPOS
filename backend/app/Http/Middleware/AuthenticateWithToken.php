<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Custom authentication middleware for web routes
 * Supports both session and token authentication
 * Used for printable documents (receipts, PDFs)
 */
class AuthenticateWithToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Try to authenticate via token (query parameter or header)
        $token = $request->input('token') ?? $request->bearerToken();
        
        if ($token) {
            $accessToken = PersonalAccessToken::findToken($token);
            
            if ($accessToken) {
                // Set the authenticated user
                $request->setUserResolver(fn () => $accessToken->tokenable);
                return $next($request);
            }
        }
        
        // Try session authentication (if configured)
        if ($request->user()) {
            return $next($request);
        }
        
        // Return 401 error page for unauthenticated requests
        return response()->view('errors.401', [
            'message' => 'Authentication required. Please log in and try again.'
        ], 401);
    }
}
