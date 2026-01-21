<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Register new tenant and user (Self-Service Registration)
     */
    public function register(Request $request, \App\Services\TenantOnboardingService $onboardingService): JsonResponse
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'otp' => 'required|numeric',
        ]);

        // Verify OTP
        // Verify OTP using Service
        $otpService = app(\App\Services\Auth\OtpService::class); // Resolve manually or inject
        try {
            $isValid = $otpService->verify($request->email, $request->otp, 'register');
            if (!$isValid) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired OTP code'
                ], 400);
            }
        } catch (\Exception $e) {
             return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }

        // Cache::forget($key); // Handled by Service (marked confirmed)



        try {
            $result = $onboardingService->registerTenant($request->all());
            
            $user = $result['user'];
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => [
                    'tenant' => $result['tenant'],
                    'user' => $user,
                    'token' => $token,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login user and check subscription
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account is deactivated'
            ], 403);
        }

        // Check Tenant Status
        if ($user->tenant && !$user->tenant->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant account is suspended. Please contact support.'
            ], 403);
        }

        // Check Subscription (Optional - Don't block login, just return status)
        // We allow login even if expired so they can renew via Dashboard.
        // Frontend will handle the restriction to POS features.
        
        // if ($user->tenant) {
        //     $subscription = $user->tenant->activeSubscription;
        //     ...
        // }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Audit Log: Login
        $userAgent = $request->userAgent();
        $platform = stripos($userAgent, 'Mobile') !== false ? 'mobile' : 'web'; // Simple detection
        if (stripos($userAgent, 'Postman') !== false) $platform = 'api_tool';

        \App\Models\AuditLog::create([
            'model_type' => User::class,
            'model_id' => $user->id,
            'event' => 'auth.login',
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent,
            'client_platform' => $platform,
            'tenant_id' => $user->tenant_id,
            'new_values' => ['login_at' => now()]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user->load(['tenant', 'outlet', 'roles.permissions']),
                'subscription' => $user->tenant ? $user->tenant->latestSubscription : null,
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    /**
     * Get User Profile (with subscription)
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load(['tenant', 'outlet', 'roles.permissions']);
        // Append role name specifically if needed by frontend
        $user->role = $user->getRoleNames()->first(); 
        
        $subscription = $user->tenant ? $user->tenant->activeSubscription : null;

        return response()->json([
            'success' => true,
            'message' => 'Profile retrieved successfully',
            'data' => [
                'user' => $user,
                'subscription' => $user->tenant ? $user->tenant->latestSubscription : null,
            ]
        ]);
    }

    /**
     * Forgot Password - Send Reset Link
     */
    public function forgotPassword(Request $request, \App\Services\Auth\PasswordResetService $resetService)
    {
        $request->validate(['email' => 'required|email']);
        
        $resetService->requestReset($request->email);

        return response()->json([
            'success' => true,
            'message' => 'Reset link has been sent to your email.'
        ]);
    }

    /**
     * Reset Password (using Token from Link)
     */
    public function resetPassword(Request $request, \App\Services\Auth\PasswordResetService $resetService)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed'
        ]);

        try {
            $resetService->resetPassword($request->email, $request->token, $request->password);

            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully. You can now login.'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
             return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
             return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $user
        ]);
    }

    /**
     * Update Password
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password saat ini salah'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah'
        ]);
    }
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }
}

