<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class SuperAdminRoleTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

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
            ->assertOk();

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
}

