<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\PasswordResetOtpDeliveryFailed;
use App\Http\Controllers\Controller;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetOtpController extends Controller
{
    public function __construct(private readonly PasswordResetOtpService $otpService)
    {
    }

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = Str::lower(trim($validated['email']));

        try {
            $this->otpService->sendOtp($email);
        } catch (PasswordResetOtpDeliveryFailed) {
            return back()->withInput()->withErrors([
                'email' => 'We could not send the reset code. Please try again shortly.',
            ]);
        }

        return redirect()->route('password.reset')
            ->with('password_reset_email', $email)
            ->with('status', 'If an account exists for this email, a reset code has been sent.');
    }

    public function showResetForm(): View
    {
        return view('auth.reset-password');
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = $this->otpService->resetPassword(
            $validated['email'],
            $validated['otp'],
            $validated['password'],
        );

        if (! $user) {
            return back()
                ->withInput($request->only('email', 'otp'))
                ->withErrors(['otp' => 'The reset code is invalid or expired. Request a new code and try again.']);
        }

        return redirect()->route('login')->with('status', 'Your password has been reset. You can now sign in.');
    }
}
