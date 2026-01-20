<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\Auth\OtpService;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PasswordResetService
{
    protected $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Step 1: Request Password Reset (Link via Email)
     */
    public function requestReset(string $email)
    {
        $user = User::where('email', $email)->first();

        // Security: Don't reveal user existence
        if (!$user) {
            return;
        }

        // 1. Generate Token
        $token = Str::random(60);

        // 2. Store Token (Hashed)
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'email' => $email,
                'token' => Hash::make($token),
                'created_at' => Carbon::now()
            ]
        );

        // 3. Construct Link
        // Assuming Frontend runs on localhost:5173 during dev, or a configured domain in prod
        $frontendUrl = env('WEBPROMOTION_URL', 'lumapos-web.sunnflower.site'); 
        $link = "{$frontendUrl}/reset-password?token={$token}&email=" . urlencode($email);

        // 4. Send Email
        if (app()->environment('production')) {
             try {
                \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\ResetPasswordMail($link));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to send Reset Password email: " . $e->getMessage());
                throw $e;
            }
        } else {
             // Local: Log the link
            \Illuminate\Support\Facades\Log::info(" [PASSWORD RESET] Link for {$email}: {$link}");
        }
    }

    /**
     * Step 2: Reset Password (Using Token from Link)
     */
    public function resetPassword(string $email, string $token, string $newPassword)
    {
        // 1. Validate Token
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$record || !Hash::check($token, $record->token)) {
            throw ValidationException::withMessages([
                'token' => ['Invalid or expired reset token.']
            ]);
        }

        // 2. Check Token Expiry (e.g., 60 minutes)
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            throw ValidationException::withMessages([
                'token' => ['Reset token has expired.']
            ]);
        }

        // 3. Update User Password
        $user = User::where('email', $email)->first();
        if ($user) {
            $user->password = Hash::make($newPassword);
            $user->save();
        }

        // 4. Invalidate Token
        DB::table('password_reset_tokens')->where('email', $email)->delete();
    }
}
