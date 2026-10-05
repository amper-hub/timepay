<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetOtpErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_a_code_without_revealing_unknown_accounts(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for this email, a reset code has been sent.');

        Mail::assertSent(OtpMail::class, fn (OtpMail $mail): bool => preg_match('/^\d{6}$/', $mail->otp) === 1);

        $this->postJson('/api/forgot-password', ['email' => 'unknown@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for this email, a reset code has been sent.');
    }

    public function test_invalid_and_expired_codes_return_safe_messages(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        $emailHash = hash('sha256', strtolower($user->email));
        DB::table('password_reset_otps')->where('email_hash', $emailHash)->update(['token' => '123456']);
        $this->postJson('/api/verify-reset-otp', [
            'email' => $user->email,
            'otp' => '000000',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The reset code is invalid or expired. Request a new code and try again.')
            ->assertDontSee('SQLSTATE');

        DB::table('password_reset_otps')->where('email_hash', $emailHash)->update([
            'expires_at' => now()->subMinute(),
        ]);

        $token = DB::table('password_reset_otps')->where('email_hash', $emailHash)->value('token');
        $this->postJson('/api/verify-reset-otp', [
            'email' => $user->email,
            'otp' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The reset code is invalid or expired. Request a new code and try again.');
    }

    public function test_valid_code_resets_the_password_and_is_consumed(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        $emailHash = hash('sha256', strtolower($user->email));
        $token = DB::table('password_reset_otps')->where('email_hash', $emailHash)->value('token');

        $this->postJson('/api/verify-reset-otp', [
            'email' => $user->email,
            'otp' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Your password has been reset. You can now sign in with the new password.');

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_otps', ['email_hash' => $emailHash]);
    }
}
