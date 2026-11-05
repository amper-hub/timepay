<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register_without_sending_verification_email(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('employer.dashboard', absolute: false));
        Notification::assertNothingSent();
    }

    public function test_employers_can_register_without_sending_verification_email(): void
    {
        Notification::fake();

        $response = $this->post('/register-employer', [
            'company_name' => 'Demo Company',
            'employer_name' => 'Demo Employer',
            'email' => 'demo-employer@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('employer.dashboard', absolute: false));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'demo-employer@example.com',
            'role' => 'EMPLOYER',
        ]);
        Notification::assertNothingSent();
    }
}
