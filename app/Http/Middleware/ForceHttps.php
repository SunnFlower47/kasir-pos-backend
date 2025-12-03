<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for OPTIONS (handled by HandleCors) and Electron
        if ($request->getMethod() === 'OPTIONS') {
            return $next($request);
        }
        
        $isElectron = $request->header('X-Client-Type') === 'electron';

        // Force HTTPS in production environment (except Electron app)
        if (app()->environment('production') && !$request->secure() && !$isElectron) {
            return redirect()->secure($request->getRequestUri());
        }

        return $next($request);
    }
}

