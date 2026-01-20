<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $user = Auth::user();
        
        // System Admin bypasses subscription check
        if ($user->hasRole('System Admin')) {
            return $next($request);
        }

        if (!$user->tenant) {
            return response()->json([
                'success' => false,
                'message' => 'User does not belong to any tenant'
            ], 403);
        }

        $subscription = $user->tenant->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found',
                'code' => 'SUBSCRIPTION_REQUIRED'
            ], 403);
        }

        if ($subscription->end_date && $subscription->end_date->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription has expired',
                'code' => 'SUBSCRIPTION_EXPIRED'
            ], 403);
        }

        return $next($request);
    }
}
