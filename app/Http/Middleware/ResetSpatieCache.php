<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Spatie\Permission\PermissionRegistrar;

class ResetSpatieCache
{
    /**
     * Handle an incoming request.
     * Force clear Spatie cache to ensure Tenant Global Scope logic is respected.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (\Illuminate\Support\Facades\Auth::check()) {
            $user = \Illuminate\Support\Facades\Auth::user();
            $tenantId = $user->tenant_id ?? 'global';
            
            // 1. Construct dynamic cache key based on Tenant
            $newCacheKey = 'spatie.permission.cache.tenant_' . $tenantId;
            
            // 2. Override Config
            config(['permission.cache.key' => $newCacheKey]);

            // 3. Force Re-initialization of PermissionRegistrar
            // This ensures Spatie class re-reads the config and uses the new key
            app()->forgetInstance(\Spatie\Permission\PermissionRegistrar::class);
            
            // Note: We do NOT call forgetCachedPermissions().
            // We want to KEEP the cache, just separate it per tenant.
        }

        return $next($request);
    }
}
