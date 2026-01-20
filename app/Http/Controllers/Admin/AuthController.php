<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::guard('web')->attempt($credentials)) {
            $user = Auth::guard('web')->user();
            \Illuminate\Support\Facades\Log::info('Admin Login Attempt: Success', ['email' => $credentials['email'], 'role' => $user->getRoleNames()]);
            
            // Strictly allow only System Admin
            if (!$user->hasRole('System Admin', 'sanctum')) {
                \Illuminate\Support\Facades\Log::warning('Admin Login Blocked: Invalid Role', ['email' => $credentials['email']]);
                Auth::guard('web')->logout();
                return back()->withErrors([
                    'email' => 'You do not have permission to access the admin panel.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();
            
            \App\Models\AuditLog::createLog('App\Models\User', $user->id, 'login');

            return redirect()->intended(route('admin.dashboard'));
        }

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
