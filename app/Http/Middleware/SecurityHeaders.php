<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip OPTIONS (handled by HandleCors)
        if ($request->getMethod() === 'OPTIONS') {
            return $next($request);
        }

        $response = $next($request);

        // IMPORTANT: Do NOT overwrite CORS headers
        // Only add security headers that don't conflict with CORS
        // CORS headers are already set by HandleCors middleware

        // Add security headers (these don't conflict with CORS)
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Strict Transport Security (HSTS) - only for HTTPS
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Content Security Policy (CSP) - adjust as needed
        // Note: CSP might interfere with CORS, so we leave it commented out
        // $response->headers->set('Content-Security-Policy', "default-src 'self'");

        return $response;
    }
}

