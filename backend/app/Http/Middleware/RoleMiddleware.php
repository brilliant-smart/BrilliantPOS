<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @param  string  ...$roles  Allowed roles (supports comma or pipe separated)
     * @return Response
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Authentication required.',
            ], 401);
        }

        // Deactivated accounts must not access any protected route
        if (method_exists($user, 'getAttribute') && $user->is_active === false) {
            return response()->json([
                'message' => 'Your account has been deactivated.',
            ], 403);
        }

        // Normalize: "role:owner,manager" arrives as ['owner,manager']
        // "role:owner|manager" arrives as ['owner', 'manager']
        $allowedRoles = collect($roles)
            ->flatMap(fn(string $r) => array_map('trim', explode(',', $r)))
            ->unique()
            ->values()
            ->all();

        if (!in_array($user->role, $allowedRoles, true)) {
            return response()->json([
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}