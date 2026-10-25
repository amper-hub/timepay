<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\LeaveRequest;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployerPortalTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $employer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create(['name' => 'Employer Portal Co', 'pay_metric' => 'hourly', 'currency' => 'PHP']);
        $this->employer = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYER,
            'status' => 'active',
        ]);
    }

    public function test_dashboard_uses_company_scoped_attendance_leave_and_payroll_data(): void
    {
        $employee = $this->employee('Visible Employee');
        $inactiveEmployee = $this->employee('Inactive Employee', 'inactive');
        $otherCompany = Company::query()->create(['name' => 'Other Portal Co', 'pay_metric' => 'hourly', 'currency' => 'PHP']);
        $otherEmployee = $this->employee('Other Company Employee', 'active', $otherCompany);

        $this->attendance($employee, 'verified', now());
        $this->attendance($inactiveEmployee, 'rejected', now());
        $this->attendance($otherEmployee, 'verified', now());
        $this->leave($employee, 'pending');
        $this->leave($otherEmployee, 'pending');

        $period = PayrollPeriod::query()->create([
            'company_id' => $this->company->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'frequency' => 'monthly',
            'status' => 'approved',
            'gross_total' => '2000.00',
            'deduction_total' => '100.00',
            'net_total' => '1900.00',
            'calculated_at' => now(),
            'created_by' => $this->employer->id,
        ]);

        $this->actingAs($this->employer)
            ->get(route('employer.dashboard'))
            ->assertOk()
            ->assertSee('Active employees')
            ->assertSee('2')
            ->assertSee('Verified attendance today')
            ->assertSee('Pending leave requests')
            ->assertSee('Latest calculated payroll')
            ->assertSee($this->company->formatMoney('1900.00'))
            ->assertSee('section=details&amp;period_id='.$period->id, false)
            ->assertDontSee('Other Company Employee')
            ->assertDontSee('Average Hourly Rate')
            ->assertDontSee('Pending Payroll');
    }

    public function test_employee_directory_search_is_limited_to_the_employers_company(): void
    {
        $this->employee('Alice Example');
        $otherCompany = Company::query()->create(['name' => 'Other Portal Co', 'pay_metric' => 'hourly', 'currency' => 'PHP']);
        $this->employee('Alice Other Tenant', 'active', $otherCompany);

        $this->actingAs($this->employer)
            ->get(route('employer.employees.index', ['search' => 'Alice']))
            ->assertOk()
            ->assertSee('Employee directory')
            ->assertSee('Alice Example')
            ->assertDontSee('Alice Other Tenant')
            ->assertSee('Add Employee');
    }

    public function test_employee_table_renders_role_status_and_actions_under_the_matching_headers(): void
    {
        $active = $this->employee('Table Active Employee');
        $inactive = $this->employee('Table Inactive Employee', 'inactive');

        $html = $this->actingAs($this->employer)
            ->get(route('employer.employees.index'))
            ->assertOk()
            ->getContent();

        $activeRowStart = strpos($html, '<tr class="hover:bg-slate-50">');
        $activeRowEnd = strpos($html, '</tr>', $activeRowStart);
        $this->assertNotFalse($activeRowStart);
        $this->assertNotFalse($activeRowEnd);
        $activeRow = substr($html, $activeRowStart, $activeRowEnd - $activeRowStart);
        $this->assertSame(4, substr_count($activeRow, '<td '));
        $activeRowText = preg_replace('/\s+/', ' ', strip_tags($activeRow));
        $rolePosition = strpos($activeRow, '>Employee</td>');
        $statusPosition = strpos($activeRow, 'border-emerald-200');
        $actionsPosition = strpos($activeRow, 'Deactivate Employee');
        $this->assertNotFalse($rolePosition);
        $this->assertNotFalse($statusPosition);
        $this->assertNotFalse($actionsPosition);
        $this->assertTrue($rolePosition < $statusPosition && $statusPosition < $actionsPosition);
        $this->assertStringContainsString($active->name, $activeRowText);

        $inactiveRowStart = strpos($html, '<tr class="hover:bg-slate-50">', $activeRowEnd);
        $inactiveRowEnd = strpos($html, '</tr>', $inactiveRowStart);
        $inactiveRow = substr($html, $inactiveRowStart, $inactiveRowEnd - $inactiveRowStart);
        $this->assertSame(4, substr_count($inactiveRow, '<td '));
        $inactiveRowText = preg_replace('/\s+/', ' ', strip_tags($inactiveRow));
        $this->assertStringContainsString('Deactivated', $inactiveRowText);
        $this->assertStringNotContainsString('Deactivate Employee', $inactiveRowText);
    }

    public function test_leave_page_only_renders_decision_controls_for_pending_requests(): void
    {
        $employee = $this->employee('Leave Employee');
        $pending = $this->leave($employee, 'pending');
        $approved = $this->leave($employee, 'approved', 'Approved after discussion.');
        $rejected = $this->leave($employee, 'rejected', 'Dates overlap a scheduled closure.');

        $this->actingAs($this->employer)
            ->get(route('employer.leaves.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('data-leave-row="'.$pending->id.'"', false)
            ->assertSee('name="status" value="approved"', false)
            ->assertSee('name="status" value="rejected"', false);

        $this->get(route('employer.leaves.index', ['status' => 'approved']))
            ->assertOk()
            ->assertSee('data-leave-row="'.$approved->id.'"', false)
            ->assertSee('Approved after discussion.')
            ->assertSee('Decision recorded: Approved')
            ->assertDontSee('name="status" value="approved"', false)
            ->assertDontSee('name="status" value="rejected"', false);

        $this->get(route('employer.leaves.index', ['status' => 'rejected']))
            ->assertOk()
            ->assertSee('data-leave-row="'.$rejected->id.'"', false)
            ->assertSee('Dates overlap a scheduled closure.')
            ->assertSee('Decision recorded: Rejected')
            ->assertDontSee('name="status" value="approved"', false)
            ->assertDontSee('name="status" value="rejected"', false);
    }

    public function test_leave_decision_returns_confirmed_state_and_refreshes_to_read_only_view(): void
    {
        $employee = $this->employee('Decision Employee');
        $leave = $this->leave($employee, 'pending');

        $this->actingAs($this->employer)
            ->patchJson(route('employer.leaves.update', $leave), [
                'status' => 'approved',
                'admin_notes' => 'Coverage was confirmed with the team.',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $leave->id)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.admin_notes', 'Coverage was confirmed with the team.');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leave->id,
            'status' => 'approved',
            'admin_notes' => 'Coverage was confirmed with the team.',
        ]);

        $this->get(route('employer.leaves.index', ['status' => 'approved']))
            ->assertOk()
            ->assertSee('Coverage was confirmed with the team.')
            ->assertDontSee('name="status" value="approved"', false)
            ->assertDontSee('name="status" value="rejected"', false);
    }

    public function test_stale_leave_decision_cannot_overwrite_a_completed_request(): void
    {
        $employee = $this->employee('Concurrent Decision Employee');
        $leave = $this->leave($employee, 'pending');

        $this->actingAs($this->employer)
            ->patchJson(route('employer.leaves.update', $leave), ['status' => 'approved'])
            ->assertOk();

        $this->patchJson(route('employer.leaves.update', $leave), ['status' => 'rejected'])
            ->assertStatus(409)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'approved']);
    }

    public function test_employer_cannot_decide_another_companys_leave_request(): void
    {
        $otherCompany = Company::query()->create(['name' => 'Other Portal Co', 'pay_metric' => 'hourly', 'currency' => 'PHP']);
        $otherEmployee = $this->employee('Private Leave Employee', 'active', $otherCompany);
        $leave = $this->leave($otherEmployee, 'pending', null, $otherCompany);

        $this->actingAs($this->employer)
            ->patchJson(route('employer.leaves.update', $leave), ['status' => 'approved'])
            ->assertNotFound();

        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'pending']);
    }

    public function test_company_employee_cannot_use_employer_leave_decision_actions(): void
    {
        $employee = $this->employee('Request Owner');
        $leave = $this->leave($employee, 'pending');
        $anotherEmployee = $this->employee('Unauthorized Reviewer');

        $this->actingAs($anotherEmployee)
            ->patchJson(route('employer.leaves.update', $leave), ['status' => 'approved'])
            ->assertForbidden();

        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'pending']);
    }

    public function test_attendance_filters_and_employee_choices_are_company_scoped(): void
    {
        $employee = $this->employee('Filtered Employee');
        $otherEmployeeInCompany = $this->employee('Other In Company');
        $otherCompany = Company::query()->create(['name' => 'Other Portal Co', 'pay_metric' => 'hourly', 'currency' => 'PHP']);
        $foreignEmployee = $this->employee('Foreign Employee', 'active', $otherCompany);

        $this->attendance($employee, 'verified', now()->startOfDay()->addHours(9));
        $this->attendance($otherEmployeeInCompany, 'rejected', now()->subDays(3));
        $this->attendance($foreignEmployee, 'verified', now()->startOfDay()->addHours(9));

        $this->actingAs($this->employer)
            ->get(route('employer.attendance', [
                'employee_id' => $employee->id,
                'status' => 'verified',
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Filtered Employee')
            ->assertSee('Showing 1 of 1 events.')
            ->assertDontSee('Foreign Employee');
        $this->get(route('employer.attendance', ['employee_id' => $foreignEmployee->id]))
            ->assertSessionHasErrors('employee_id');
    }

    public function test_business_settings_and_geofence_render_inside_the_employer_workspace(): void
    {
        $this->actingAs($this->employer)
            ->get(route('employer.settings.index'))
            ->assertOk()
            ->assertSee('Business profile')
            ->assertSee('Work schedule')
            ->assertSee('Monthly leave allowance')
            ->assertSee('Payroll currency')
            ->assertSee('Attendance and location rules');

        $this->get(route('employer.geofence'))
            ->assertOk()
            ->assertSee('Attendance and location rules')
            ->assertSee('Worksite boundary')
            ->assertSee('geofence-map');
    }

    private function employee(string $name, string $status = 'active', ?Company $company = null): User
    {
        return User::factory()->create([
            'company_id' => ($company ?? $this->company)->id,
            'name' => $name,
            'email' => str($name)->slug('-').'@example.test',
            'role' => 'employee',
            'status' => $status,
        ]);
    }

    private function leave(User $employee, string $status, ?string $notes = null, ?Company $company = null): LeaveRequest
    {
        return LeaveRequest::query()->create([
            'company_id' => ($company ?? $employee->company)->id,
            'user_id' => $employee->id,
            'leave_type' => 'Vacation',
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
            'reason' => 'Personal time away.',
            'status' => $status,
            'admin_notes' => $notes,
        ]);
    }

    private function attendance(User $employee, string $status, mixed $timestamp): AttendanceLog
    {
        return AttendanceLog::query()->create([
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'timestamp' => $timestamp,
            'type' => 'clock_in',
            'latitude' => '14.59950000',
            'longitude' => '120.98420000',
            'distance_meters' => '5.00',
            'status' => $status,
        ]);
    }
}
