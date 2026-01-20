<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

    protected $otpService;

    public function __construct(\App\Services\Auth\OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Send OTP to email
     */
    public function send(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'type' => 'nullable|string|in:login,register,password_reset'
        ]);

        $email = $request->email;
        $type = $request->type ?? 'register'; // Default to register if not specified

        try {
            // Generate (Hash & Store)
            $code = $this->otpService->generate($email, $type);
            
            // Send (Mail or Log)
            $this->otpService->send($email, $code);

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully to ' . $email,
            ]);

        } catch (\Exception $e) {
            Log::error('OTP Send Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP. ' . (app()->environment('local') ? $e->getMessage() : ''),
            ], 500);
        }
    }

    /**
     * Verify OTP
     */
    public function verify(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|numeric',
            'type' => 'nullable|string'
        ]);

        $type = $request->type ?? 'register';

        try {
            $isValid = $this->otpService->verify($request->email, $request->otp, $type);

            if (!$isValid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired OTP'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}

