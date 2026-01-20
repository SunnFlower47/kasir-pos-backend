<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');
        $clientType = $request->header('X-Client-Type');
        $isProduction = app()->environment('production');

        // Allow same-origin requests (Admin Panel)
        $host = $request->getSchemeAndHttpHost();
        if ($origin === $host || empty($origin)) {
            if ($request->is('admin*') || $request->is('login')) {
                return $next($request);
            }
        }

        // Allowed origins for web clients
        $allowedWebOrigins = [
            'https://kasir-pos.sunnflower.site',
            'https://luma-pos.sunnflower.site',
            'https://lumapos-web.sunnflower.site',
        ];

        // Add development origins only in non-production
        if (!$isProduction) {
            $allowedWebOrigins = array_merge($allowedWebOrigins, [
                'http://localhost:4174',
                'http://localhost:4173',
                'http://127.0.0.1:4173',
                'http://localhost:8081',
                'http://127.0.0.1:8081',
                'http://localhost:5174',
                'http://localhost:5173',
                config('app.url'),
                'http://kasir-pos-system.test',
            ]);
        }
        
        // Also allow APP_URL in production if needed, or handle same-origin logic
        if ($isProduction) {
             $allowedWebOrigins[] = config('app.url');
        }

        // Check client types
        $isMobileApp = $clientType === 'mobile' && !$origin;
        $isElectronApp = $clientType === 'electron';

        // Electron app origin validation
        $isElectronOriginAllowed = false;
        if ($isElectronApp && $origin) {
            $allowedElectronOrigins = [
                'http://127.0.0.1',
                'http://localhost',
                'file://',
            ];
            foreach ($allowedElectronOrigins as $allowedOrigin) {
                if (strpos($origin, $allowedOrigin) === 0) {
                    $isElectronOriginAllowed = true;
                    break;
                }
            }
        }

        // For security: In production, require X-Client-Type header if no origin
        if ($isProduction && !$origin) {
            if (!in_array($clientType, ['mobile', 'electron'])) {
                // Reject requests without origin and without valid client type in production
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Missing origin or invalid client type',
                ], 403);
            }
        }

        // Check if web origin is allowed (case-insensitive)
        $isWebAllowed = false;
        if ($origin) {
            $originLower = strtolower(trim($origin));
            foreach ($allowedWebOrigins as $allowedWebOrigin) {
                if ($originLower === strtolower(trim($allowedWebOrigin))) {
                    $isWebAllowed = true;
                    break;
                }
            }
        }

        // In development, also allow requests without origin/headers (for Expo dev client)
        $isDevClient = !$isProduction && !$origin && (!$clientType || $clientType === 'mobile');

        // Allow if mobile app OR electron app OR web origin is allowed OR dev client
        $isAllowed = $isMobileApp || ($isElectronApp && ($isElectronOriginAllowed || !$origin)) || $isWebAllowed || $isDevClient;

        // Determine allowed origin
        if ($isMobileApp || $isDevClient) {
            $allowedOrigin = '*'; // Mobile apps and dev client don't have origin
        } elseif ($isElectronApp && ($isElectronOriginAllowed || !$origin)) {
            // Electron app: use origin if valid, or null (CORS not critical for Electron)
            $allowedOrigin = ($origin && $isElectronOriginAllowed) ? $origin : null;
        } elseif ($isWebAllowed) {
            $allowedOrigin = $origin;
        } else {
            $allowedOrigin = null;
        }

        // Handle OPTIONS (preflight)
        // Preflight requests don't need authentication, just origin validation
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 200);

            // CRITICAL: For preflight, we must return CORS headers if origin matches whitelist
            // Check origin against whitelist directly (case-insensitive comparison)
            $originMatches = false;
            if ($origin) {
                $originLower = strtolower(trim($origin));
                foreach ($allowedWebOrigins as $allowedWebOrigin) {
                    if ($originLower === strtolower(trim($allowedWebOrigin))) {
                        $originMatches = true;
                        break;
                    }
                }
            }

            // For preflight: return CORS headers if origin is in whitelist
            // Priority: 1. Web origin match, 2. $allowedOrigin (mobile/electron), 3. Dev client
            if ($originMatches && $origin) {
                // Web origin is in whitelist - allow preflight
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Client-Type, X-Client-Version, Accept, Origin');
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                $response->headers->set('Access-Control-Max-Age', '86400');
                return $response;
            }

            if ($allowedOrigin) {
                // Mobile app or Electron app
                $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Client-Type, X-Client-Version, Accept, Origin');
                $response->headers->set('Access-Control-Allow-Credentials', ($isMobileApp || $isElectronApp) ? 'false' : 'true');
                $response->headers->set('Access-Control-Max-Age', '86400');
                return $response;
            }

            if ($isDevClient) {
                // Development client (Expo)
                $response->headers->set('Access-Control-Allow-Origin', '*');
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Client-Type, X-Client-Version, Accept, Origin');
                $response->headers->set('Access-Control-Max-Age', '86400');
                return $response;
            }

            // If none match, return response without CORS headers (will be blocked by browser)
            return $response;
        }

        // CRITICAL SECURITY: Block unauthorized requests BEFORE processing
        // Don't process the request if it's not allowed
        if (!$isAllowed) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: CORS policy violation or invalid client type',
            ], 403);
        }

        // Handle actual request - only if allowed
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Catch any API exceptions so we can still add CORS headers
            if ($request->is('api/*') || $request->expectsJson()) {
                $response = \App\Exceptions\ApiExceptionHandler::handle($e, $request);
                if (!$response) {
                    throw $e;
                }
            } else {
                throw $e;
            }
        }

        // Set CORS headers for allowed requests
        // CRITICAL: Always set headers if web origin is in whitelist
        if ($isWebAllowed && $origin) {
            // Web origin is allowed - set CORS headers
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');
        } elseif ($allowedOrigin) {
            // Mobile app or Electron app
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            if (!$isMobileApp && !$isElectronApp) {
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }
            $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');
        } elseif ($isDevClient) {
            // Development client (Expo)
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');
        }

        return $response;
    }
}

