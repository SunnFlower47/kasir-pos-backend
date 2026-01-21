<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ValidateRecaptcha
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $version  'v2' or 'v3' (default v3)
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $version = 'v3')
    {
        // 1. Check if ReCAPTCHA is enabled in config
        if (!config('services.recaptcha.enabled', false)) {
            return $next($request);
        }

        // 2. Local environment bypass (Double strict check)
        if (app()->environment('local', 'testing') && config('services.recaptcha.enabled') !== true) {
             return $next($request);
        }

        $token = $request->input('recaptcha_token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'ReCAPTCHA token missing.'
            ], 422);
        }

        // 3. Verify with Google
        $secret = $version === 'v3' 
            ? config('services.recaptcha.v3_secret_key') 
            : config('services.recaptcha.v2_secret_key');
            
        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $request->ip(),
        ]);

        $body = $response->json();

        // 4. Handle V3 logic (Score based)
        if ($version === 'v3') {
            $threshold = config('services.recaptcha.threshold', 0.5);
            
            if (!($body['success'] ?? false) || ($body['score'] ?? 0) < $threshold) {
                Log::warning('ReCAPTCHA V3 Failed', ['ip' => $request->ip(), 'score' => $body['score'] ?? 0, 'errors' => $body['error-codes'] ?? []]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Security check failed. Please refresh and try again.',
                    'action_required' => 'recaptcha_v2_challenge' // Signal frontend to show fallback
                ], 403);
            }
        }
        
        // 5. Handle V2 logic (Checkbox)
        else {
             if (!($body['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please complete the CAPTCHA.'
                ], 422);
            }
        }

        return $next($request);
    }
}
