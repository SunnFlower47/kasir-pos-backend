<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // use \Illuminate\Foundation\Auth\ThrottlesLogins; // Removed: Not available in this version

    public function showLogin()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'g-recaptcha-response' => ['required'],
        ]);

        $throttleKey = \Illuminate\Support\Str::lower($request->input('email')) . '|' . $request->ip();

        // 1. Throttling Check
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey);
            event(new \Illuminate\Auth\Events\Lockout($request));
            
            return back()->withErrors([
                'email' => trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
            ])->onlyInput('email');
        }

        // 2. ReCAPTCHA Verification
        $recaptchaResponse = \Illuminate\Support\Facades\Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('services.recaptcha.v2_secret_key'),
            'response' => $request->input('g-recaptcha-response'),
            'remoteip' => $request->ip(),
        ]);

        if (!$recaptchaResponse->json('success')) {
            // Check if it's a localhost/dev environment issue or legitimate failure
            // If strictly needed, we can log the error code: $recaptchaResponse->json('error-codes')
            \Illuminate\Support\Facades\Log::warning('Admin Recaptcha Failed', ['email' => $credentials['email'], 'errors' => $recaptchaResponse->json('error-codes')]);
            
            // Only increment attempts if we consider bad captcha as a failed attempt? Usually yes to prevent spam.
            \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 60); // 60 seconds decay
            
            return back()->withErrors(['g-recaptcha-response' => 'ReCAPTCHA verification failed. Please try again.'])->onlyInput('email');
        }

        // 3. Attempt Login
        if (Auth::guard('web')->attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            $user = Auth::guard('web')->user();
            \Illuminate\Support\Facades\Log::info('Admin Login Attempt: Success', ['email' => $credentials['email'], 'role' => $user->getRoleNames()]);
            
            // Strictly allow only System Admin
            if (!$user->hasRole('System Admin', 'sanctum') && !$user->hasRole('System Admin', 'web')) {
                 if (!$user->hasRole('System Admin')) {
                    \Illuminate\Support\Facades\Log::warning('Admin Login Blocked: Invalid Role', ['email' => $credentials['email']]);
                    Auth::guard('web')->logout();
                    \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 60);
                    return back()->withErrors([
                        'email' => 'You do not have permission to access the admin panel.',
                    ])->onlyInput('email');
                 }
            }

            $request->session()->regenerate();
            \Illuminate\Support\Facades\RateLimiter::clear($throttleKey);
            
            \App\Models\AuditLog::createLog('App\Models\User', $user->id, 'login');

            return redirect()->intended(route('admin.dashboard'));
        }

        \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 60);
        \Illuminate\Support\Facades\Log::warning('Admin Login Failed: Invalid Credentials', ['email' => $credentials['email']]);
        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
