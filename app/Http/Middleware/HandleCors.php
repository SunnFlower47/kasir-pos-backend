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

        // Allowed origins
        $allowed = [
            'https://kasir-pos.sunnflower.site',
            'http://localhost:4173',
            'http://127.0.0.1:4173',
        ];

        $isAllowed = $origin && in_array($origin, $allowed);

        // Handle OPTIONS (preflight)
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 200);

            if ($isAllowed) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Client-Type, X-Client-Version, Accept, Origin');
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                $response->headers->set('Access-Control-Max-Age', '86400');
            }

            return $response;
        }

        // Handle actual request
        $response = $next($request);

        if ($isAllowed) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}

