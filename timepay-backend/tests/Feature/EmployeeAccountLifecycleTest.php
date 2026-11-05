<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\LeaveRequest;
use App\Models\PayrollAudit;
use App\Models\PayrollCompensationHistory;
use App\Models\PayrollProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeAccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $employer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::query()->create(['name' => 'Lifecycle Co', 'pay_metric' => 'hourly', 'currency' => 'PHP']);
        $this->employer = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYER,
            'status' => 'active',
        ]);
    }

    public function test_employee_creation_temporary_password_authenticates_and_enters_required_password_change_flow(): void
    {
        $response = $this->actingAs($this->employer)->postJson(route('employer.employees.store'), [
            'name' => 'New Employee',
            'email' => 'new.employee@example.test',
            'hourly_rate' => '20.00',
        ])->assertCreated();

        $employeeId = $response->json('user.id');
        $temporaryPassword = $response->json('temporary_password');
        $employee = User::query()->findOrFail($employeeId);

        $this->assertSame($this->company->id, $employee->company_id);
        $this->assertSame(strtolower(User::ROLE_EMPLOYEE), strtolower($employee->role));
        $this->assertTrue(Hash::check($temporaryPassword, $employee->password));
        $this->assertTrue($employee->requires_password_change);
        $this->assertArrayNotHasKey('password', $response->json('user'));

        $this->postJson('/api/auth/login', ['email' => $employee->email, 'password' => $temporaryPassword])
            ->assertForbidden()
            ->assertJsonPath('error', 'password_change_required')
            ->assertJsonPath('user_id', $employee->id);

        $this->postJson('/api/auth/login', ['email' => $employee->email, 'password' => 'definitely-wrong'])
            ->assertUnprocessable();

        $this->postJson('/api/auth/update-temporary-password', [
            'user_id' => $employee->id,
            'current_password' => $temporaryPassword,
            'new_password' => 'A-new-test-password-2026',
            'new_password_confirmation' => 'A-new-test-password-2026',
        ])->assertOk()->assertJsonPath('user.id', $employee->id);

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'requires_password_change' => false]);
        $this->postJson('/api/auth/login', [
            'email' => $employee->email,
            'password' => 'A-new-test-password-2026',
        ])->assertOk()->assertJsonPath('user.id', $employee->id);
    }

    public function test_employee_creation_rejects_email_already_used_by_another_company(): void
    {
        $otherCompany = Company::query()->create(['name' => 'Other Co', 'pay_metric' => 'hourly', 'currency' => 'PHP']);
        User::factory()->create(['company_id' => $otherCompany->id, 'role' => User::ROLE_EMPLOYEE, 'email' => 'shared@example.test']);

        $this->actingAs($this->employer)
            ->postJson(route('employer.employees.store'), [
                'name' => 'Duplicate Email',
                'email' => 'shared@example.test',
                'hourly_rate' => '20.00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_mobile_login_selects_correct_legacy_duplicate_email_by_password_and_rejects_inactive_accounts(): void
    {
        $otherCompany = Company::query()->create(['name' => 'Legacy Duplicate Co', 'pay_metric' => 'hourly', 'currency' => 'PHP']);
        User::factory()->create([
            'company_id' => $otherCompany->id,
            'role' => User::ROLE_EMPLOYEE,
            'email' => 'shared@example.test',
            'password' => 'another-password',
        ]);
        $employee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYEE,
            'email' => 'shared@example.test',
            'password' => 'correct-password',
            'status' => 'active',
        ]);

        $this->postJson('/api/auth/login', ['email' => 'SHARED@example.test', 'password' => 'correct-password'])
            ->assertOk()
            ->assertJsonPath('user.id', $employee->id);

        $employee->update(['status' => 'inactive']);
        $this->postJson('/api/auth/login', ['email' => 'shared@example.test', 'password' => 'correct-password'])
            ->assertForbidden()
            ->assertJsonPath('error', 'account_unavailable');
    }

    public function test_deactivation_preserves_history_is_scoped_and_repeated_request_is_safe(): void
    {
        $employee = $this->employee('Keep History');
        $attendance = AttendanceLog::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'timestamp' => now(),
            'type' => 'clock_in',
            'latitude' => '14.59950000',
            'longitude' => '120.98420000',
            'distance_meters' => '5.00',
            'status' => 'verified',
        ]);
        $leave = LeaveRequest::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'leave_type' => 'Vacation',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'reason' => 'Family time',
            'status' => 'pending',
        ]);
        $profile = PayrollProfile::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'salary_type' => 'hourly',
            'pay_frequency' => 'monthly',
        ]);
        $compensation = PayrollCompensationHistory::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'basis' => 'hourly',
            'rate' => '20.00',
            'effective_from' => now()->subMonth()->toDateString(),
            'changed_by' => $this->employer->id,
        ]);

        $this->actingAs($this->employer)
            ->get(route('employer.employees.index'))
            ->assertOk()
            ->assertSee('Deactivate Employee')
            ->assertSee($employee->name)
            ->assertSee(route('employer.employees.deactivate', $employee), false);

        $route = route('employer.employees.deactivate', $employee);
        $this->actingAs($this->employer)->patch($route)->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('attendance_logs', ['id' => $attendance->id, 'user_id' => $employee->id]);
        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'user_id' => $employee->id]);
        $this->assertDatabaseHas('payroll_profiles', ['id' => $profile->id, 'user_id' => $employee->id]);
        $this->assertDatabaseHas('payroll_compensation_histories', ['id' => $compensation->id, 'user_id' => $employee->id]);
        $this->assertDatabaseHas('payroll_audits', [
            'entity_id' => $employee->id,
            'action' => 'employee_account_deactivated',
            'actor_id' => $this->employer->id,
        ]);

        $this->patch($route)->assertRedirect();
        $this->assertSame(1, PayrollAudit::query()->where('entity_id', $employee->id)->count());
    }

    public function test_employers_cannot_deactivate_other_company_users_and_unauthenticated_requests_are_rejected(): void
    {
        $otherCompany = Company::query()->create(['name' => 'Other Co', 'pay_metric' => 'hourly', 'currency' => 'PHP']);
        $employee = User::factory()->create(['company_id' => $otherCompany->id, 'role' => User::ROLE_EMPLOYEE]);
        $route = route('employer.employees.deactivate', $employee);

        $this->patch($route)->assertRedirect(route('login'));
        $this->actingAs($this->employer)->patch($route)->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'status' => 'active']);
    }

    private function employee(string $name): User
    {
        return User::factory()->create([
            'company_id' => $this->company->id,
            'name' => $name,
            'email' => str($name)->slug('-').'@example.test',
            'role' => User::ROLE_EMPLOYEE,
            'status' => 'active',
        ]);
    }
}
