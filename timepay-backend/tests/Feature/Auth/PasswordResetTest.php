<?php

namespace Tests\Feature\Auth;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_screens_can_be_rendered(): void
    {
        $this->get('/forgot-password')->assertOk();
        $this->get('/reset-password')->assertOk();
    }

    public function test_reset_code_can_be_requested_for_an_existing_account(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect(route('password.reset'));

        Mail::assertSent(OtpMail::class, fn (OtpMail $mail): bool => preg_match('/^\d{6}$/', $mail->otp) === 1);
        $this->assertDatabaseHas('password_reset_otps', [
            'email_hash' => hash('sha256', strtolower($user->email)),
        ]);
    }

    public function test_password_can_be_reset_with_a_valid_code(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);
        $emailHash = hash('sha256', strtolower($user->email));
        $otp = DB::table('password_reset_otps')->where('email_hash', $emailHash)->value('token');

        $this->post('/reset-password', [
            'email' => $user->email,
            'otp' => $otp,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Password123!', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_otps', ['email_hash' => $emailHash]);
    }
}
