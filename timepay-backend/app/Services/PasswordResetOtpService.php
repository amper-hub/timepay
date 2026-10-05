<?php

namespace App\Services;

use App\Exceptions\PasswordResetOtpDeliveryFailed;
use App\Mail\OtpMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class PasswordResetOtpService
{
    private const OTP_TTL_MINUTES = 15;

    private const OTP_RESEND_SECONDS = 60;

    private const OTP_MAX_ATTEMPTS = 5;

    /** Send a code when an account exists, while keeping the response generic. */
    public function sendOtp(string $email): void
    {
        $email = Str::lower(trim($email));
        $emailHash = hash('sha256', $email);

        DB::table('password_reset_otps')
            ->where('expires_at', '<=', now())
            ->delete();

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user) {
            return;
        }

        $existingOtp = DB::table('password_reset_otps')
            ->where('email_hash', $emailHash)
            ->first();

        if ($existingOtp && Carbon::parse($existingOtp->last_sent_at)->gt(now()->subSeconds(self::OTP_RESEND_SECONDS))) {
            return;
        }

        $otp = (string) random_int(100000, 999999);
        $now = now();

        try {
            Mail::to($user->email)->send(new OtpMail($otp));
        } catch (Throwable $exception) {
            report($exception);

            throw new PasswordResetOtpDeliveryFailed($exception);
        }

        DB::table('password_reset_otps')->updateOrInsert(
            ['email_hash' => $emailHash],
            [
                'email' => $email,
                'token' => $otp,
                'attempts' => 0,
                'expires_at' => $now->copy()->addMinutes(self::OTP_TTL_MINUTES),
                'last_sent_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    /** Reset a password only when the matching, unexpired code is valid. */
    public function resetPassword(string $email, string $otp, string $password): ?User
    {
        $email = Str::lower(trim($email));
        $emailHash = hash('sha256', $email);

        $user = DB::transaction(function () use ($email, $emailHash, $otp, $password): ?User {
            $otpRow = DB::table('password_reset_otps')
                ->where('email_hash', $emailHash)
                ->lockForUpdate()
                ->first();

            if (! $otpRow) {
                return null;
            }

            if (Carbon::parse($otpRow->expires_at)->isPast() || $otpRow->attempts >= self::OTP_MAX_ATTEMPTS) {
                DB::table('password_reset_otps')->where('email_hash', $emailHash)->delete();

                return null;
            }

            if (! hash_equals((string) $otpRow->token, $otp)) {
                DB::table('password_reset_otps')
                    ->where('email_hash', $emailHash)
                    ->increment('attempts');

                return null;
            }

            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->first();

            if (! $user) {
                DB::table('password_reset_otps')->where('email_hash', $emailHash)->delete();

                return null;
            }

            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            $user->tokens()->delete();
            DB::table('password_reset_otps')->where('email_hash', $emailHash)->delete();

            return $user;
        });

        if ($user) {
            event(new PasswordReset($user));
        }

        return $user;
    }
}
