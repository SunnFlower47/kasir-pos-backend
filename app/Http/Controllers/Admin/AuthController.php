<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use \Illuminate\Foundation\Auth\ThrottlesLogins;

    protected $maxAttempts = 3;
    protected $decayMinutes = 1;

    public function showLogin()
    {
        return view('admin.auth.login');
    }

    public function username()
    {
        return 'email';
    }

    public function login(Request $request)
    {
        // 1. Throttling Check
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            return $this->sendLockoutResponse($request);
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'g-recaptcha-response' => ['required'],
        ]);

        // 2. ReCAPTCHA Verification
        $recaptchaResponse = \Illuminate\Support\Facades\Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('services.recaptcha.v2_secret_key'),
            'response' => $request->input('g-recaptcha-response'),
            'remoteip' => $request->ip(),
        ]);

        if (!$recaptchaResponse->json('success')) {
            $this->incrementLoginAttempts($request);
            return back()->withErrors(['g-recaptcha-response' => 'ReCAPTCHA verification failed. Please try again.'])->onlyInput('email');
        }

        // 3. Helper for incrementing attempts on failure
        if (Auth::guard('web')->attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            $user = Auth::guard('web')->user();
            \Illuminate\Support\Facades\Log::info('Admin Login Attempt: Success', ['email' => $credentials['email'], 'role' => $user->getRoleNames()]);
            
            // Strictly allow only System Admin
            if (!$user->hasRole('System Admin', 'sanctum') && !$user->hasRole('System Admin', 'web')) { // Check both guards if needed or just role name
                 // Standard Spatie role check: $user->hasRole('System Admin') checks default guard. 
                 // If role was assigned via API (Sanctum), it might be on 'sanctum' guard? 
                 // Usually roles are persisted in DB. simple hasRole('System Admin') is enough if guard_name matches or is 'web'.
                 // Let's stick to simple hasRole since web login uses web guard.
                 if (!$user->hasRole('System Admin')) {
                    \Illuminate\Support\Facades\Log::warning('Admin Login Blocked: Invalid Role', ['email' => $credentials['email']]);
                    Auth::guard('web')->logout();
                    $this->incrementLoginAttempts($request);
                    return back()->withErrors([
                        'email' => 'You do not have permission to access the admin panel.',
                    ])->onlyInput('email');
                 }
            }

            $request->session()->regenerate();
            $this->clearLoginAttempts($request);
            
            \App\Models\AuditLog::createLog('App\Models\User', $user->id, 'login');

            return redirect()->intended(route('admin.dashboard'));
        }

        $this->incrementLoginAttempts($request);
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
