<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exceptions\PasswordResetOtpDeliveryFailed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use App\Services\PasswordResetOtpService;

class MobilePasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResetOtpService $otpService)
    {
    }

    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = Str::lower(trim($validated['email']));
        try {
            $this->otpService->sendOtp($email);
        } catch (PasswordResetOtpDeliveryFailed) {
            return response()->json([
                'message' => 'We could not send the reset code. Please try again shortly.',
            ], 503);
        }

        return response()->json([
            'message' => 'If an account exists for this email, a reset code has been sent.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        // Keep compatibility with versions of the mobile app that used the old field names.
        $request->merge([
            'password' => $request->input('password', $request->input('new_password')),
            'password_confirmation' => $request->input('password_confirmation', $request->input('confirm_password')),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', PasswordRule::defaults()],
            'password_confirmation' => ['required', 'same:password'],
        ]);

        $user = $this->otpService->resetPassword(
            $validated['email'],
            $validated['otp'],
            $validated['password'],
        );

        if (! $user) {
            return response()->json([
                'message' => 'The reset code is invalid or expired. Request a new code and try again.',
            ], 422);
        }

        return response()->json([
            'message' => 'Your password has been reset. You can now sign in with the new password.',
        ]);
    }
}
