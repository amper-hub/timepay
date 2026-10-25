<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SuperAdminRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_registration_assigns_the_employer_role_by_default(): void
    {
        $response = $this->post('/register', [
            'name' => 'New Employer',
            'email' => 'employer@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect('/employer/dashboard');
        $this->assertDatabaseHas('users', [
            'email' => 'employer@example.com',
            'role' => 'EMPLOYER',
        ]);
    }

    public function test_super_admins_can_access_the_super_admin_dashboard_and_employers_cannot(): void
    {
        $superAdmin = User::factory()->create([
            'email' => 'super@example.com',
            'role' => 'SUPER_ADMIN',
        ]);

        $employer = User::factory()->create([
            'email' => 'boss@example.com',
            'role' => 'EMPLOYER',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('super-admin.dashboard'))
            ->assertOk()
            ->assertSee('Admins')
            ->assertSee(route('super-admin.admins.index'));

        $this->actingAs($employer)
            ->get(route('super-admin.dashboard'))
            ->assertForbidden();
    }

    
    public function test_root_redirects_super_admins_to_the_super_admin_dashboard(): void
    {
        $superAdmin = User::factory()->create([
            'email' => 'root-super@example.com',
            'role' => 'SUPER_ADMIN',
        ]);

        $this->actingAs($superAdmin)
            ->get('/')
            ->assertRedirect('/super-admin/dashboard');
    }

    public function test_guest_authentication_and_business_registration_pages_render_with_their_existing_contracts(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('toggle-password', false)
            ->assertSee(route('password.request'))
            ->assertSee(route('employer.register'));

        $this->get(route('employer.register'))
            ->assertOk()
            ->assertSee('name="company_name"', false)
            ->assertSee('name="employer_name"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee(route('employer.register.store'));

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Employee accounts are created by an employer.');
    }

    public function test_business_registration_keeps_its_company_and_verification_flow(): void
    {
        Event::fake();

        $response = $this->post(route('employer.register.store'), [
            'company_name' => 'Northwind Studio',
            'employer_name' => 'Taylor Owner',
            'email' => 'taylor@northwind.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('employer.dashboard'));
        $this->assertDatabaseHas('companies', ['name' => 'Northwind Studio']);
        $this->assertDatabaseHas('users', [
            'email' => 'taylor@northwind.test',
            'name' => 'Taylor Owner',
            'role' => User::ROLE_EMPLOYER,
            'status' => 'active',
        ]);
        Event::assertDispatched(Registered::class, fn (Registered $event) => $event->user->email === 'taylor@northwind.test');
    }

    public function test_business_registration_keeps_input_validation_and_does_not_create_partial_records(): void
    {
        $this->from(route('employer.register'))
            ->post(route('employer.register.store'), [
                'employer_name' => 'Taylor Owner',
                'email' => 'invalid-email',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertRedirect(route('employer.register'))
            ->assertSessionHasErrors(['company_name', 'email', 'password']);

        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_employer_login_keeps_its_existing_destination(): void
    {
        User::factory()->create([
            'email' => 'employer-login@example.com',
            'role' => User::ROLE_EMPLOYER,
        ]);

        $this->post(route('login'), [
            'email' => 'employer-login@example.com',
            'password' => 'password',
        ])->assertRedirect('/employer/dashboard');
    }

    public function test_super_admin_login_keeps_its_existing_destination(): void
    {
        User::factory()->create([
            'email' => 'super-login@example.com',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->post(route('login'), [
            'email' => 'super-login@example.com',
            'password' => 'password',
        ])->assertRedirect('/super-admin/dashboard');
    }

    public function test_employee_mobile_login_returns_the_employee_session_contract(): void
    {
        $employee = User::factory()->create([
            'email' => 'employee-mobile@example.test',
            'role' => User::ROLE_EMPLOYEE,
        ]);

        $this->postJson(route('api.auth.login'), [
            'email' => 'employee-mobile@example.test',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('role', User::ROLE_EMPLOYEE)
            ->assertJsonPath('user.id', $employee->id)
            ->assertJsonPath('user.email', 'employee-mobile@example.test')
            ->assertJsonPath('company.id', $employee->company_id);
    }

    public function test_invalid_web_login_uses_the_generic_credential_error(): void
    {
        $this->from(route('login'))
            ->post(route('login'), [
                'email' => 'unknown@example.com',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'The email or password you entered is incorrect.']);
    }

    public function test_password_reset_email_verification_and_confirmation_pages_render(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Forgot your password?');

        $this->get(route('password.reset'))
            ->assertOk()
            ->assertSee('Enter your code');

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('verify your email address');

        $this->actingAs($user)
            ->get(route('password.confirm'))
            ->assertOk()
            ->assertSee('confirm your password');
    }

    public function test_super_admin_lists_filters_and_details_render_for_existing_records(): void
    {
        $superAdmin = User::factory()->create([
            'email' => 'ui-super@example.test',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
        $employer = User::factory()->create([
            'name' => 'Preview Employer',
            'email' => 'preview-owner@example.test',
            'role' => User::ROLE_EMPLOYER,
        ]);
        $admin = User::factory()->create([
            'name' => 'Preview Admin User',
            'email' => 'preview-admin-user@example.test',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('super-admin.employers.index', ['search' => 'Preview Employer']))
            ->assertOk()
            ->assertSee('Preview Employer')
            ->assertSee('Factory Company');

        $this->get(route('super-admin.admins.index', ['search' => 'Preview Admin User']))
            ->assertOk()
            ->assertSee('Preview Admin User');

        $this->get(route('super-admin.employers.show', $employer))->assertOk();
        $this->get(route('super-admin.employers.edit', $employer))->assertOk();
        $this->get(route('super-admin.admins.show', $admin))->assertOk();
        $this->get(route('super-admin.admins.edit', $admin))->assertOk();
        $this->get(route('super-admin.leaves.index'))->assertOk();
        $this->get(route('super-admin.reports.index'))->assertOk();
        $this->get(route('super-admin.platform.index'))->assertOk();
    }
}
