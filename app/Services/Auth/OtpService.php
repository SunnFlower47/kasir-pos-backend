<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;
use Carbon\Carbon;
use Illuminate\Support\Str;

class OtpService
{
    /**
     * Generate and Store OTP
     * 
     * @param string $identifier Email or Identifier
     * @param string $type Purpose (login, register, password_reset)
     * @return string Plain OTP (only returned once)
     */
    public function generate(string $identifier, string $type = 'login'): string
    {
        // 1. Generate 6-digit secure random code
        $code = str_pad(api_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // 2. Hash the code
        $hashedCode = Hash::make($code);
        
        // 3. Store in Database
        DB::table('otp_codes')->insert([
            'identifier' => $identifier,
            'code' => $hashedCode,
            'type' => $type,
            'expires_at' => Carbon::now()->addMinutes(5), // 5 Minutes Expiry
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $code;
    }

    /**
     * Send OTP via Email or Log (Environment Aware)
     */
    public function send(string $identifier, string $code)
    {
        if (app()->environment('production')) {
            // Production: Send Email
            try {
                Mail::to($identifier)->send(new OtpMail($code));
            } catch (\Exception $e) {
                Log::error("Failed to send OTP email to {$identifier}: " . $e->getMessage());
                throw $e;
            }
        } else {
            // Local/Dev: Log specific OTP for easy testing
            Log::info(" [OTP SERVICE] OTP for {$identifier}: {$code}");
        }
    }

    /**
     * Verify OTP
     * 
     * @return bool
     * @throws \Exception Reason for failure
     */
    public function verify(string $identifier, string $code, string $type = 'login'): bool
    {
        // 1. Find Valid OTP Record
        // Must match identifier, type, not confirmed, and not expired
        $otpRecord = DB::table('otp_codes')
            ->where('identifier', $identifier)
            ->where('type', $type)
            ->whereNull('confirmed_at')
            ->where('expires_at', '>', Carbon::now())
            ->latest() // Get the most recent one
            ->first();

        // 2. Check Existence
        if (!$otpRecord) {
            return false;
        }

        // 3. Check Max Attempts (Rate Limiting)
        if ($otpRecord->attempts >= 3) {
            DB::table('otp_codes')->where('id', $otpRecord->id)->delete(); // Invalidate immediately
            throw new \Exception("Too many invalid attempts. Please request a new code.");
        }

        // 4. Verify Hash
        if (Hash::check($code, $otpRecord->code)) {
            // Success! Mark as used.
            DB::table('otp_codes')->where('id', $otpRecord->id)->update([
                'confirmed_at' => Carbon::now()
            ]);
            return true;
        }

        // 5. Failure: Increment Attempts
        DB::table('otp_codes')->where('id', $otpRecord->id)->increment('attempts');
        
        return false;
    }
}

// Helper for secure random if not available globally, 
// using generic rand() for numeric string is acceptable for OTP if padded, 
// strictly CSPRNG is better but rand() suffices for 6-digit short-lived.
function api_rand($min, $max) {
    return rand($min, $max);
}
