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

    public function test_employee_profile_shows_only_the_selected_employees_saved_payroll_settings(): void
    {
        $employee = $this->employee('Payroll Profile One');
        $other = $this->employee('Payroll Profile Two');
        \App\Models\PayrollProfile::query()->create([
            'company_id' => $this->company->id, 'user_id' => $employee->id,
            'salary_type' => 'daily', 'pay_frequency' => 'weekly', 'payroll_eligible' => true,
            'overtime_eligible' => false, 'rank_and_file' => true, 'sss_enabled' => false,
            'philhealth_enabled' => false, 'pagibig_enabled' => false, 'withholding_tax_enabled' => false,
            'standard_hours_per_day' => 7, 'standard_work_days_per_month' => 20,
        ]);
        \App\Models\PayrollProfile::query()->create([
            'company_id' => $this->company->id, 'user_id' => $other->id,
            'salary_type' => 'hourly', 'pay_frequency' => 'monthly', 'payroll_eligible' => false,
            'overtime_eligible' => true, 'rank_and_file' => false, 'sss_enabled' => true,
            'philhealth_enabled' => true, 'pagibig_enabled' => true, 'withholding_tax_enabled' => true,
            'standard_hours_per_day' => 8, 'standard_work_days_per_month' => 22,
        ]);
        \App\Models\PayrollCompensationHistory::query()->create([
            'company_id' => $this->company->id, 'user_id' => $employee->id, 'basis' => 'daily',
            'rate' => '850.00', 'effective_from' => now()->subDays(4)->toDateString(), 'changed_by' => $this->employer->id,
        ]);
        \App\Models\PayrollCompensationHistory::query()->create([
            'company_id' => $this->company->id, 'user_id' => $other->id, 'basis' => 'hourly',
            'rate' => '125.00', 'effective_from' => now()->subDays(4)->toDateString(), 'changed_by' => $this->employer->id,
        ]);
        \App\Models\PayrollProfileItem::query()->create([
            'company_id' => $this->company->id, 'user_id' => $employee->id, 'category' => 'earning',
            'code' => 'meal_allowance', 'label' => 'Meal allowance', 'amount' => '300.00', 'taxable' => true,
            'effective_from' => '2026-01-01', 'effective_to' => '2026-12-31', 'created_by' => $this->employer->id,
        ]);

        $this->actingAs($this->employer)
            ->get(route('employer.employees.show', $employee))
            ->assertOk()
            ->assertSee('Payroll Profile One')
            ->assertSee('value="850.00"', false)
            ->assertSee('value="7"', false)
            ->assertSee('value="20"', false)
            ->assertSee('value="weekly" selected', false)
            ->assertSee('meal_allowance')
            ->assertSee('Meal allowance')
            ->assertSee('Jan 01, 2026', false)
            ->assertDontSee('Payroll Profile Two')
            ->assertDontSee('125.00');
    }

    public function test_employee_profile_settings_save_and_reopen_without_changing_another_employee(): void
    {
        $employee = $this->employee('Payroll Settings Target');
        $other = $this->employee('Payroll Settings Neighbor');
        \App\Models\PayrollProfile::query()->create([
            'company_id' => $this->company->id, 'user_id' => $employee->id,
            'salary_type' => 'hourly', 'pay_frequency' => 'monthly', 'payroll_eligible' => true,
            'overtime_eligible' => false, 'rank_and_file' => true, 'sss_enabled' => false,
            'philhealth_enabled' => false, 'pagibig_enabled' => false, 'withholding_tax_enabled' => false,
            'standard_hours_per_day' => 8, 'standard_work_days_per_month' => 22,
        ]);
        \App\Models\PayrollProfile::query()->create([
            'company_id' => $this->company->id, 'user_id' => $other->id,
            'salary_type' => 'hourly', 'pay_frequency' => 'monthly', 'payroll_eligible' => true,
            'overtime_eligible' => true, 'rank_and_file' => true, 'sss_enabled' => false,
            'philhealth_enabled' => false, 'pagibig_enabled' => false, 'withholding_tax_enabled' => false,
            'standard_hours_per_day' => 8, 'standard_work_days_per_month' => 22,
        ]);
        $effectiveFrom = now()->toDateString();

        $this->actingAs($this->employer)
            ->patch(route('employer.payroll.profiles.update', ['return_to' => 'employee_profile', 'employee_id' => $employee->id]), [
                'user_id' => $employee->id, 'salary_type' => 'daily', 'rate' => '900.00',
                'pay_frequency' => 'weekly', 'effective_from' => $effectiveFrom,
                'standard_hours_per_day' => 7, 'standard_work_days_per_month' => 20,
                'payment_method' => 'manual_bank_deposit',
            ])
            ->assertRedirect(route('employer.employees.show', $employee));

        $this->assertDatabaseHas('payroll_profiles', [
            'user_id' => $employee->id, 'salary_type' => 'daily', 'pay_frequency' => 'weekly',
            'standard_hours_per_day' => 7, 'standard_work_days_per_month' => 20,
        ]);
        $this->assertDatabaseHas('payroll_profiles', [
            'user_id' => $other->id, 'salary_type' => 'hourly', 'pay_frequency' => 'monthly',
            'standard_hours_per_day' => 8,
        ]);
        $savedRate = \App\Models\PayrollCompensationHistory::query()->where('user_id', $employee->id)->sole();
        $this->assertSame('daily', $savedRate->basis);
        $this->assertSame('900.00', $savedRate->rate);
        $this->assertSame($effectiveFrom, $savedRate->effective_from->toDateString());

        $this->post(route('employer.payroll.profile-items.store', ['return_to' => 'employee_profile', 'employee_id' => $employee->id]), [
            'user_id' => $employee->id, 'category' => 'earning', 'code' => 'meal_allowance',
            'label' => 'Meal allowance', 'amount' => '350.00', 'taxable' => '1',
            'effective_from' => '2026-11-01', 'effective_to' => '2026-12-31',
        ])->assertRedirect(route('employer.employees.show', $employee));

        $savedItem = \App\Models\PayrollProfileItem::query()->where('user_id', $employee->id)->sole();
        $this->assertSame('350.00', $savedItem->amount);
        $this->assertSame('2026-11-01', $savedItem->effective_from->toDateString());
        $this->assertSame('2026-12-31', $savedItem->effective_to->toDateString());
        $this->assertTrue($savedItem->taxable);
        $this->get(route('employer.employees.show', $employee))
            ->assertOk()->assertSee('value="900.00"', false)->assertSee('Meal allowance')
            ->assertSee('Nov 01, 2026', false)->assertSee('Dec 31, 2026', false);
    }

    public function test_employee_profile_route_rejects_an_employee_from_another_company(): void
    {
        $otherCompany = Company::query()->create(['name' => 'Other Profile Co', 'pay_metric' => 'hourly', 'currency' => 'PHP']);
        $foreignEmployee = $this->employee('Foreign Profile Employee', 'active', $otherCompany);

        $this->actingAs($this->employer)
            ->get(route('employer.employees.show', $foreignEmployee))
            ->assertNotFound();
    }

    public function test_employee_directory_shows_scoped_attendance_and_hides_draft_payroll_amounts(): void
    {
        $employee = $this->employee('Summary Employee');
        $employee->forceFill(['baseline_photo_path' => 'selfies/baseline.jpg'])->save();
        $period = PayrollPeriod::query()->create([
            'company_id' => $this->company->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'frequency' => 'monthly',
            'status' => 'draft',
            'created_by' => $this->employer->id,
        ]);
        $this->attendance($employee, 'verified', now()->startOfMonth()->addHours(9));
        $this->attendance($employee, 'flagged', now()->startOfMonth()->addHours(10));
        \App\Models\PayrollEntry::query()->create([
            'company_id' => $this->company->id,
            'payroll_period_id' => $period->id,
            'user_id' => $employee->id,
            'gross_pay' => '1200.00',
            'employee_deductions' => '100.00',
            'employer_contributions' => '80.00',
            'net_pay' => '1100.00',
            'employer_cost' => '1280.00',
            'calculation_snapshot' => [],
            'review_flags' => ['missing_statutory_input_requires_review'],
        ]);

        $this->actingAs($this->employer)
            ->get(route('employer.employees.index', ['search' => (string) $employee->id]))
            ->assertOk()
            ->assertSee('Employee ID #'.$employee->id)
            ->assertSee('2 recorded clock-ins')
            ->assertSee('Hours: needs review')
            ->assertSee('Not final')
            ->assertDontSee('Gross: <strong>PHP 1,200.00</strong>', false)
            ->assertSee('Reset baseline photo');
    }

    public function test_cross_period_attendance_hides_even_an_approved_payroll_summary(): void
    {
        $employee = $this->employee('Boundary Summary Employee');
        $period = PayrollPeriod::query()->create([
            'company_id' => $this->company->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'frequency' => 'monthly',
            'status' => 'approved',
            'created_by' => $this->employer->id,
        ]);
        $this->attendance($employee, 'verified', '2026-09-30 23:00:00');
        AttendanceLog::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'timestamp' => '2026-10-01 01:00:00',
            'type' => 'clock_out',
            'latitude' => '14.59950000',
            'longitude' => '120.98420000',
            'distance_meters' => '5.00',
            'status' => 'verified',
        ]);
        \App\Models\PayrollEntry::query()->create([
            'company_id' => $this->company->id,
            'payroll_period_id' => $period->id,
            'user_id' => $employee->id,
            'gross_pay' => '500.00',
            'employee_deductions' => '0.00',
            'employer_contributions' => '0.00',
            'net_pay' => '500.00',
            'employer_cost' => '500.00',
            'calculation_snapshot' => [],
            'review_flags' => [],
        ]);

        $this->actingAs($this->employer)
            ->get(route('employer.employees.index', ['period_id' => $period->id]))
            ->assertOk()
            ->assertSee('cross-period shift')
            ->assertDontSee('Gross: <strong>PHP 500.00</strong>', false)
            ->assertDontSee('Gross PHP 500.00');
    }

    public function test_employee_table_renders_status_attendance_payroll_and_actions(): void
    {
        $active = $this->employee('Table Active Employee');
        $inactive = $this->employee('Table Inactive Employee', 'inactive');

        $html = $this->actingAs($this->employer)
            ->get(route('employer.employees.index'))
            ->assertOk()
            ->getContent();

        $activeRowStart = strpos($html, '<tr class="align-top hover:bg-slate-50">');
        $activeRowEnd = strpos($html, '</tr>', $activeRowStart);
        $this->assertNotFalse($activeRowStart);
        $this->assertNotFalse($activeRowEnd);
        $activeRow = substr($html, $activeRowStart, $activeRowEnd - $activeRowStart);
        $this->assertSame(7, substr_count($activeRow, '<td '));
        $activeRowText = preg_replace('/\s+/', ' ', strip_tags($activeRow));
        $rolePosition = strpos($activeRow, 'Employee ID #'.$active->id);
        $statusPosition = strpos($activeRow, 'border-emerald-200');
        $actionsPosition = strpos($activeRow, 'Deactivate Employee</button>');
        $this->assertNotFalse($rolePosition);
        $this->assertNotFalse($statusPosition);
        $this->assertNotFalse($actionsPosition);
        $this->assertTrue($rolePosition < $statusPosition && $statusPosition < $actionsPosition);
        $this->assertStringContainsString($active->name, $activeRowText);

        $inactiveRowStart = strpos($html, '<tr class="align-top hover:bg-slate-50">', $activeRowEnd);
        $inactiveRowEnd = strpos($html, '</tr>', $inactiveRowStart);
        $inactiveRow = substr($html, $inactiveRowStart, $inactiveRowEnd - $inactiveRowStart);
        $this->assertSame(7, substr_count($inactiveRow, '<td '));
        $inactiveRowText = preg_replace('/\s+/', ' ', strip_tags($inactiveRow));
        $this->assertStringContainsString('Inactive', $inactiveRowText);
        $this->assertStringNotContainsString('Deactivate Employee</button>', $inactiveRow);
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
