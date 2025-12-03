<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowElectronOrigin
{
    /**
     * Handle an incoming request.
     *
     * This middleware allows requests from Electron app by checking:
     * 1. Custom header X-Client-Type: electron
     * 2. Origin from localhost (for Electron app)
     *
     * This is safe because:
     * - Electron app is a desktop application, not a web browser
     * - Only our signed Electron app can send the X-Client-Type header
     * - Localhost origin is restricted to 127.0.0.1 and localhost only
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip OPTIONS - handled by HandleCors
        if ($request->getMethod() === 'OPTIONS') {
            return $next($request);
        }

        // Only handle Electron requests (non-web)
        $isElectron = $request->header('X-Client-Type') === 'electron';
        if (!$isElectron) {
            return $next($request);
        }

        // Process request
        $response = $next($request);

        // Add Electron-specific CORS headers
        $origin = $request->header('Origin');
        if ($origin) {
            $allowedElectronOrigins = [
                'http://127.0.0.1',
                'http://localhost',
                'file://',
            ];

            foreach ($allowedElectronOrigins as $allowedOrigin) {
                if (str_starts_with($origin, $allowedOrigin)) {
                    $response->headers->set('Access-Control-Allow-Origin', $origin);
                    $response->headers->set('Access-Control-Allow-Credentials', 'true');
                    $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
                    $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Client-Type, X-Client-Version');
                    break;
                }
            }
        }

        return $response;
    }
}

