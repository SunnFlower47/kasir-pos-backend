<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantSuspended
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // If user is logged in
        if ($user) {
            // Check if user belongs to a tenant
            if ($user->tenant) {
                // Check if tenant is suspended
                if (!$user->tenant->is_active) {
                    // Force logout token
                    // $user->currentAccessToken()->delete(); 
                    // optional: we can revoke token, or just block request.
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Your tenant account is suspended. Please contact support.'
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
