<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\PayrollAudit;
use App\Models\PayrollAttendanceAllocation;
use App\Models\PayrollCompensationHistory;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\PayrollProfile;
use App\Models\User;
use App\Services\PayrollCalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayrollWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $employer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 12:00:00');
        $this->company = Company::query()->create(['name' => 'Payroll Test Co', 'pay_metric' => 'hourly', 'currency' => 'PHP']);
        $this->employer = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYER,
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_monthly_calculation_uses_centavo_arithmetic_and_official_rule_snapshots(): void
    {
        $employee = $this->employee('Monthly Employee');
        $profile = $this->profile($employee, [
            'salary_type' => 'monthly',
            'pay_frequency' => 'monthly',
            'sss_enabled' => true,
            'philhealth_enabled' => true,
            'pagibig_enabled' => true,
            'withholding_tax_enabled' => true,
        ]);
        $this->rate($employee, 'monthly', '30000.00');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');

        $calculated = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id);
        $entry = $calculated->entries->sole();

        $this->assertSame('30000.00', $entry->gross_pay);
        $this->assertSame('2450.00', $this->sumDeductionsBeforeTax($entry));
        $this->assertSame('1007.55', $entry->lines->firstWhere('code', 'bir_withholding_tax')->amount);
        $this->assertSame('3457.55', $entry->employee_deductions);
        $this->assertSame('3980.00', $entry->employer_contributions);
        $this->assertSame('26542.45', $entry->net_pay);
        $this->assertSame('33980.00', $entry->employer_cost);
        $this->assertSame('2025-01-01', data_get($entry->calculation_snapshot, 'statutory_rules.sss.effective_from'));
        $this->assertSame('2025-01-01', data_get($entry->calculation_snapshot, 'statutory_rules.philhealth.effective_from'));
        $this->assertSame('2024-02-01', data_get($entry->calculation_snapshot, 'statutory_rules.pagibig.effective_from'));
        $this->assertSame('2026-10-31', data_get($entry->calculation_snapshot, 'period_end'));
        $this->assertSame($period->id, $entry->payroll_period_id);
    }

    public function test_thirteenth_month_uses_approved_basic_pay_and_centavo_rounding_before_review(): void
    {
        $employee = $this->employee('Annual Pay Employee');
        $this->profile($employee, ['pay_frequency' => 'monthly', 'rank_and_file' => true]);
        $approvedPeriod = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly', 'approved');
        $approvedEntry = $this->entry($employee, $approvedPeriod, '1000.06');
        $approvedEntry->lines()->create([
            'category' => 'earning', 'code' => 'basic_pay', 'label' => 'Basic pay', 'amount' => '1000.06', 'taxable' => true,
        ]);
        $approvedEntry->lines()->create([
            'category' => 'earning', 'code' => 'allowance', 'label' => 'Allowance', 'amount' => '500.00', 'taxable' => true,
        ]);

        $period = $this->period($this->company, '2026-01-01', '2026-12-31', 'yearly');
        $calculated = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id);
        $entry = $calculated->entries->sole();

        $this->assertSame('83.34', $entry->gross_pay);
        $this->assertSame('0.00', $entry->employee_deductions);
        $this->assertSame('1000.06', data_get($entry->calculation_snapshot, 'basic_salary_in_approved_runs'));
        $this->assertSame('2026-01-01', $calculated->period_start->toDateString());
        $this->assertContains('13th_month_uses_approved_basic_pay_snapshots', $entry->review_flags);
        $this->assertContains('calendar_year_not_completed', $entry->review_flags);

        Sanctum::actingAs($this->employer);
        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve")->assertUnprocessable();
        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve", [
            'review_note' => 'Verified annual basic pay and centavo rounding.',
        ])->assertUnprocessable()->assertJsonValidationErrors('payroll');
    }

    public function test_inactive_employee_thirteenth_month_is_an_audited_final_pay_exception_without_reactivation(): void
    {
        $employee = $this->employee('Separated Rank And File Employee');
        $this->profile($employee, ['pay_frequency' => 'monthly', 'rank_and_file' => true]);
        $approvedPeriod = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly', 'approved');
        $approvedEntry = $this->entry($employee, $approvedPeriod, '12000.00');
        $approvedEntry->lines()->create([
            'category' => 'earning', 'code' => 'basic_pay', 'label' => 'Basic pay', 'amount' => '12000.00', 'taxable' => true,
        ]);
        $employee->update(['status' => 'inactive']);
        $period = $this->period($this->company, '2026-01-01', '2026-12-31', 'yearly');
        $calculated = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id);
        $this->assertContains('inactive_employee_13th_month_requires_review', $calculated->entries->sole()->review_flags);
        Sanctum::actingAs($this->employer);

        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve")->assertUnprocessable()->assertJsonValidationErrors('review_note');
        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve", [
            'review_note' => 'Reviewed the separated employee’s final 13th-month calculation.',
        ])->assertUnprocessable()->assertJsonValidationErrors('payroll');

        $this->assertSame('inactive', $employee->fresh()->status);
        $this->assertDatabaseMissing('payroll_audits', ['entity_id' => $period->id, 'action' => 'approved']);
    }

    public function test_reviewable_warning_requires_an_audited_note_before_approval(): void
    {
        $employee = $this->employee('Monthly Employee');
        $this->profile($employee, ['salary_type' => 'monthly', 'pay_frequency' => 'monthly']);
        $this->rate($employee, 'monthly', '30000.00');
        $period = $this->period($this->company, '2026-10-01', '2026-10-30', 'monthly');
        $calculated = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id);
        $this->assertContains('monthly_salary_prorated_by_calendar_day', $calculated->entries->sole()->review_flags);
        Sanctum::actingAs($this->employer);

        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve")->assertUnprocessable();
        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve", [
            'review_note' => 'Reviewed the partial-month salary proration against the period dates.',
        ])->assertUnprocessable()->assertJsonValidationErrors('payroll');

        $this->assertDatabaseMissing('payroll_audits', ['entity_id' => $period->id, 'action' => 'approved']);
        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve")->assertUnprocessable()->assertJsonValidationErrors('payroll');
    }

    public function test_inactive_employees_are_excluded_from_new_ordinary_payroll_calculations(): void
    {
        $active = $this->employee('Active Payroll Employee');
        $inactive = $this->employee('Inactive Payroll Employee');
        foreach ([$active, $inactive] as $employee) {
            $this->profile($employee, ['salary_type' => 'monthly', 'pay_frequency' => 'monthly']);
            $this->rate($employee, 'monthly', '30000.00');
        }
        $inactive->update(['status' => 'inactive']);
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');

        $calculated = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id);

        $this->assertSame([$active->id], $calculated->entries->pluck('user_id')->all());
        $this->assertDatabaseMissing('payroll_entries', ['payroll_period_id' => $period->id, 'user_id' => $inactive->id]);
    }

    public function test_missing_compensation_rate_blocks_approval_even_with_a_review_note(): void
    {
        $employee = $this->employee('Rate Gap Employee');
        $this->profile($employee, ['salary_type' => 'hourly', 'pay_frequency' => 'monthly']);
        $this->attendance($employee, '2026-10-12 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-12 17:00:00', 'clock_out');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');
        app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id);
        Sanctum::actingAs($this->employer);

        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve", [
            'review_note' => 'Looked at the employee record before approval.',
        ])->assertUnprocessable()->assertJsonValidationErrors('payroll');
        $this->assertDatabaseHas('payroll_periods', ['id' => $period->id, 'status' => 'pending_review']);
    }

    public function test_effective_hourly_rate_covers_hourly_attendance(): void
    {
        $employee = $this->employee('Hourly Effective Rate Employee');
        $this->profile($employee, ['salary_type' => 'hourly', 'pay_frequency' => 'weekly']);
        $this->rate($employee, 'hourly', '50.00', '2026-10-25');
        $this->attendance($employee, '2026-10-27 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-27 10:00:00', 'clock_out');
        $this->attendance($employee, '2026-10-28 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-28 10:00:00', 'clock_out');
        $period = $this->period($this->company, '2026-10-26', '2026-11-01', 'weekly');

        $entry = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id)->entries->sole();

        $this->assertSame('200.00', $entry->gross_pay);
        $this->assertNotContains('missing_effective_pay_rate', $entry->review_flags);
        $this->assertNotContains('missing_effective_hourly_rate', $entry->review_flags);
    }

    public function test_effective_daily_rate_covers_daily_payable_dates(): void
    {
        $employee = $this->employee('Daily Effective Rate Employee');
        $this->profile($employee, ['salary_type' => 'daily', 'pay_frequency' => 'weekly']);
        $this->rate($employee, 'daily', '400.00', '2026-10-25');
        $this->attendance($employee, '2026-10-27 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-27 10:00:00', 'clock_out');
        $this->attendance($employee, '2026-10-28 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-28 10:00:00', 'clock_out');
        $period = $this->period($this->company, '2026-10-26', '2026-11-01', 'weekly');

        $entry = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id)->entries->sole();

        $this->assertSame('800.00', $entry->gross_pay);
        $this->assertNotContains('missing_effective_pay_rate', $entry->review_flags);
    }

    public function test_hourly_to_daily_transition_uses_each_effective_basis_in_one_period(): void
    {
        $employee = $this->employee('Transition Effective Rate Employee');
        $this->profile($employee, ['salary_type' => 'daily', 'pay_frequency' => 'weekly']);
        $hourly = $this->rate($employee, 'hourly', '50.00', '2026-10-25');
        $hourly->update(['effective_to' => '2026-10-27']);
        $daily = $this->rate($employee, 'daily', '400.00', '2026-10-28');
        $this->attendance($employee, '2026-10-27 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-27 10:00:00', 'clock_out');
        $this->attendance($employee, '2026-10-28 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-28 10:00:00', 'clock_out');
        $period = $this->period($this->company, '2026-10-26', '2026-11-01', 'weekly');

        $entry = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id)->entries->sole();

        $this->assertSame('100.00', $entry->lines->firstWhere('label', 'Verified regular hours')?->amount);
        $this->assertSame('400.00', $entry->lines->firstWhere('label', 'Verified payable days')?->amount);
        $this->assertSame('500.00', $entry->gross_pay);
        $this->assertNotContains('missing_effective_pay_rate', $entry->review_flags);
        $this->assertSame('2026-10-27', $hourly->fresh()->effective_to->toDateString());
        $this->assertSame('2026-10-28', $daily->fresh()->effective_from->toDateString());
        $this->assertNull($daily->fresh()->effective_to);
    }

    public function test_genuine_compensation_gap_remains_critical_and_blocks_approval(): void
    {
        $employee = $this->employee('Compensation Gap Employee');
        $this->profile($employee, ['salary_type' => 'daily', 'pay_frequency' => 'weekly']);
        $this->rate($employee, 'daily', '400.00', '2026-10-28');
        $this->attendance($employee, '2026-10-27 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-27 10:00:00', 'clock_out');
        $this->attendance($employee, '2026-10-28 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-28 10:00:00', 'clock_out');
        $period = $this->period($this->company, '2026-10-26', '2026-11-01', 'weekly');
        $entry = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id)->entries->sole();

        $this->assertSame('400.00', $entry->gross_pay);
        $this->assertContains('missing_effective_pay_rate', $entry->review_flags);
        $this->assertContains('missing_effective_pay_rate', \App\Support\PayrollReviewFlags::critical());
        Sanctum::actingAs($this->employer);
        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve", [
            'review_note' => 'Reviewed the remaining daily rate and attendance.',
        ])->assertUnprocessable()->assertJsonValidationErrors('payroll');
    }

    public function test_recalculation_updates_gross_and_flags_after_effective_basis_change(): void
    {
        $employee = $this->employee('Recalculated Effective Rate Employee');
        $profile = $this->profile($employee, ['salary_type' => 'hourly', 'pay_frequency' => 'weekly']);
        $hourly = $this->rate($employee, 'hourly', '50.00', '2026-10-25');
        $this->attendance($employee, '2026-10-27 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-27 10:00:00', 'clock_out');
        $this->attendance($employee, '2026-10-28 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-28 10:00:00', 'clock_out');
        $period = $this->period($this->company, '2026-10-26', '2026-11-01', 'weekly');
        $initial = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id)->entries->sole();
        $this->assertSame('200.00', $initial->gross_pay);

        $hourly->update(['effective_to' => '2026-10-27']);
        $this->rate($employee, 'daily', '400.00', '2026-10-28');
        $profile->update(['salary_type' => 'daily']);
        $recalculated = app(PayrollCalculationService::class)->calculate($period->fresh(), (int) $this->employer->id)->entries->sole();

        $this->assertSame('500.00', $recalculated->gross_pay);
        $this->assertNotContains('missing_effective_pay_rate', $recalculated->review_flags);
        $this->assertSame('daily', data_get($recalculated->calculation_snapshot, 'salary_type'));
        $this->assertSame('daily', data_get($recalculated->calculation_snapshot, 'payroll_profile_snapshot.salary_type'));
    }

    public function test_attendance_marked_paid_after_calculation_blocks_approval(): void
    {
        $employee = $this->employee('Paid Attendance Employee');
        $this->profile($employee, ['salary_type' => 'hourly', 'pay_frequency' => 'monthly']);
        $this->rate($employee, 'hourly', '100.00');
        $clockIn = $this->attendance($employee, '2026-10-12 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-12 17:00:00', 'clock_out');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');
        app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id);
        $clockIn->update(['is_paid' => true]);
        Sanctum::actingAs($this->employer);

        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve", [
            'review_note' => 'Reviewed the register and attendance again.',
        ])->assertUnprocessable()->assertJsonValidationErrors('payroll');
    }

    public function test_attendance_duration_is_clipped_to_inclusive_payroll_date_boundaries(): void
    {
        $employee = $this->employee('Boundary Shift Employee');
        $this->profile($employee, ['salary_type' => 'hourly', 'pay_frequency' => 'monthly']);
        $this->rate($employee, 'hourly', '60.00');
        $this->attendance($employee, '2026-09-30 22:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-01 02:00:00', 'clock_out');
        $this->attendance($employee, '2026-10-31 22:00:00', 'clock_in');
        $this->attendance($employee, '2026-11-01 02:00:00', 'clock_out');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');

        $entry = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id)->entries->sole();

        $this->assertSame(240, $entry->worked_minutes);
        $this->assertSame('240.00', $entry->gross_pay);
        $this->assertSame(2, $entry->attendanceLogs()->count());
    }

    public function test_duplicate_attendance_is_reviewed_and_blocks_approval(): void
    {
        $employee = $this->employee('Duplicate Punch Employee');
        $this->profile($employee, ['salary_type' => 'hourly', 'pay_frequency' => 'monthly']);
        $this->rate($employee, 'hourly', '60.00');
        $this->attendance($employee, '2026-10-12 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-12 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-12 12:00:00', 'clock_out');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');

        $entry = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id)->entries->sole();
        $this->assertContains('duplicate_attendance_requires_review', $entry->review_flags);
        Sanctum::actingAs($this->employer);
        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve", [
            'review_note' => 'Reviewed duplicate attendance source punches.',
        ])->assertUnprocessable()->assertJsonValidationErrors('payroll');
    }

    public function test_missing_clock_out_and_long_shift_flags_block_approval_even_with_review_note(): void
    {
        $employee = $this->employee('Incomplete Attendance Employee');
        $this->profile($employee, ['salary_type' => 'hourly', 'pay_frequency' => 'monthly', 'standard_hours_per_day' => 8]);
        $this->rate($employee, 'hourly', '60.00');
        $this->attendance($employee, '2026-10-12 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-13 18:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-14 04:00:00', 'clock_out');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');

        $entry = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id)->entries->sole();
        $this->assertContains('incomplete_attendance_requires_review', $entry->review_flags);
        $this->assertContains('long_shift_overtime_requires_approval', $entry->review_flags);
        Sanctum::actingAs($this->employer);
        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve", [
            'review_note' => 'Incomplete attendance and overtime reviewed.',
        ])->assertUnprocessable()->assertJsonValidationErrors('payroll');
    }

    public function test_flagged_or_rejected_attendance_blocks_approval(): void
    {
        $employee = $this->employee('Unverified Punch Employee');
        $this->profile($employee, ['salary_type' => 'hourly', 'pay_frequency' => 'monthly']);
        $this->rate($employee, 'hourly', '60.00');
        $this->attendance($employee, '2026-10-12 08:00:00', 'clock_in')->update(['status' => 'flagged', 'is_suspicious' => true]);
        $this->attendance($employee, '2026-10-12 12:00:00', 'clock_out');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');

        $entry = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id)->entries->sole();
        $this->assertContains('invalid_attendance_requires_review', $entry->review_flags);
        Sanctum::actingAs($this->employer);
        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve", [
            'review_note' => 'Reviewed the flagged source attendance punch.',
        ])->assertUnprocessable()->assertJsonValidationErrors('payroll');
    }

    public function test_employer_period_api_reports_the_same_accumulated_minutes_as_payroll_entry(): void
    {
        $employee = $this->employee('API Hours Employee');
        $this->profile($employee, ['salary_type' => 'hourly', 'pay_frequency' => 'monthly']);
        $this->rate($employee, 'hourly', '60.00');
        $this->attendance($employee, '2026-10-12 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-12 12:30:00', 'clock_out');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');
        $entry = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id)->entries->sole();

        Sanctum::actingAs($this->employer);
        $this->getJson("/api/employer/payroll/periods/{$period->id}")
            ->assertOk()
            ->assertJsonPath('data.entries.0.worked_minutes', $entry->worked_minutes)
            ->assertJsonPath('data.entries.0.attendance_allocations.0.elapsed_seconds', 16200);
        $this->assertSame(16200, $entry->attendanceAllocations()->sole()->elapsed_seconds);
        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve")
            ->assertOk()->assertJsonPath('data.net_pay', $period->fresh()->net_total);
        $this->getJson("/api/employer/payroll/periods/{$period->id}")
            ->assertOk()->assertJsonPath('data.entries.0.remaining_balance', $entry->net_pay);
        $this->postJson("/api/employer/payroll/entries/{$entry->id}/payments", [
            'amount' => $entry->net_pay, 'payment_date' => '2026-10-20', 'method' => 'manual_bank_deposit',
            'reference_number' => 'SYNTHETIC-RECON-1',
        ])->assertOk();
        Sanctum::actingAs($employee);
        $this->getJson('/api/payroll/payslips')->assertOk()
            ->assertJsonPath('data.0.gross_pay', $entry->gross_pay)
            ->assertJsonPath('data.0.net_pay', $entry->net_pay)
            ->assertJsonPath('data.0.regular_hours', 4.5)
            ->assertJsonPath('data.0.amount_paid', $entry->net_pay)
            ->assertJsonPath('data.0.payment_status', 'paid');
    }

    public function test_recalculated_snapshot_approves_after_equivalent_json_representation_changes(): void
    {
        $employee = $this->employee('Snapshot Canonicalization Employee');
        $this->profile($employee, ['salary_type' => 'hourly', 'pay_frequency' => 'monthly']);
        $this->rate($employee, 'hourly', '60.00');
        $this->attendance($employee, '2026-10-12 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-12 09:00:00', 'clock_out');
        $this->attendance($employee, '2026-10-12 10:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-12 10:30:00', 'clock_out');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');

        app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id);
        $entry = app(PayrollCalculationService::class)->calculate($period->fresh(), (int) $this->employer->id)->entries->sole();
        $snapshot = $entry->calculation_snapshot;

        // MySQL JSON may reorder object keys; arrays of allocations are sets
        // keyed by allocation_key for approval, even if their list order changes.
        $snapshot['hourly_daily_monthly_rates'] = array_map(
            fn (array $rate): array => array_reverse([...$rate, 'rate' => '60.0'], true),
            $snapshot['hourly_daily_monthly_rates'],
        );
        $snapshot['attendance_allocations'] = array_reverse(array_map(function (array $allocation): array {
            foreach (['original_clock_in_at', 'original_clock_out_at', 'segment_start_at', 'segment_end_at'] as $field) {
                $allocation[$field] = preg_replace('/\.0{1,6}$/', '', $allocation[$field]);
            }

            return array_reverse($allocation, true);
        }, $snapshot['attendance_allocations']));
        $entry->forceFill(['calculation_snapshot' => $snapshot])->save();

        Sanctum::actingAs($this->employer);
        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_attendance_timestamp_changed_after_calculation_blocks_approval(): void
    {
        $employee = $this->employee('Changed Attendance Snapshot Employee');
        $this->profile($employee, ['salary_type' => 'hourly', 'pay_frequency' => 'monthly']);
        $this->rate($employee, 'hourly', '60.00');
        $clockIn = $this->attendance($employee, '2026-10-12 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-12 09:00:00', 'clock_out');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');
        app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id);
        $clockIn->update(['timestamp' => '2026-10-12 08:01:00']);

        Sanctum::actingAs($this->employer);
        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve")
            ->assertUnprocessable()->assertJsonValidationErrors('payroll');
    }

    public function test_attendance_source_cannot_be_linked_to_an_incompatible_payroll_entry(): void
    {
        $employee = $this->employee('Unique Attendance Source Employee');
        $this->profile($employee, ['salary_type' => 'hourly', 'pay_frequency' => 'monthly']);
        $this->rate($employee, 'hourly', '60.00');
        $clockIn = $this->attendance($employee, '2026-10-12 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-12 09:00:00', 'clock_out');
        $firstPeriod = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');
        $firstEntry = app(PayrollCalculationService::class)->calculate($firstPeriod, (int) $this->employer->id)->entries->sole();
        $otherPeriod = $this->period($this->company, '2026-11-01', '2026-11-30', 'monthly');
        $otherEntry = $this->entry($employee, $otherPeriod, '0.00');

        $inserted = DB::table('payroll_entry_attendance_logs')->insertOrIgnore([
            'payroll_entry_id' => $otherEntry->id,
            'attendance_log_id' => $clockIn->id,
        ]);

        $this->assertSame(0, $inserted);
        $this->assertDatabaseHas('payroll_entry_attendance_logs', [
            'payroll_entry_id' => $firstEntry->id,
            'attendance_log_id' => $clockIn->id,
        ]);
        $this->assertDatabaseMissing('payroll_entry_attendance_logs', [
            'payroll_entry_id' => $otherEntry->id,
            'attendance_log_id' => $clockIn->id,
        ]);
    }

    public function test_adjacent_periods_split_boundary_shift_without_losing_or_double_counting_minutes(): void
    {
        $employee = $this->employee('Adjacent Period Employee');
        $this->profile($employee, ['salary_type' => 'hourly', 'pay_frequency' => 'monthly']);
        $this->rate($employee, 'hourly', '60.00');
        $this->attendance($employee, '2026-10-31 23:00:00', 'clock_in');
        $this->attendance($employee, '2026-11-01 01:00:00', 'clock_out');
        $october = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');
        $november = $this->period($this->company, '2026-11-01', '2026-11-30', 'monthly');

        $octEntry = app(PayrollCalculationService::class)->calculate($october, (int) $this->employer->id)->entries->sole();
        $novEntry = app(PayrollCalculationService::class)->calculate($november, (int) $this->employer->id)->entries->sole();

        $this->assertSame(60, $octEntry->worked_minutes);
        $this->assertSame(60, $novEntry->worked_minutes);
        $this->assertSame(120, $octEntry->worked_minutes + $novEntry->worked_minutes);
        $this->assertSame(1, $octEntry->attendanceLogs()->count());
        $this->assertSame(1, $novEntry->attendanceLogs()->count());
        $allocations = PayrollAttendanceAllocation::query()->whereIn('payroll_entry_id', [$octEntry->id, $novEntry->id])
            ->orderBy('segment_start_at')->get();
        $this->assertCount(2, $allocations);
        $this->assertSame(7200, $allocations->sum('elapsed_seconds'));
        $this->assertSame('2026-10-31 23:00:00', $allocations[0]->segment_start_at->toDateTimeString());
        $this->assertSame('2026-11-01 00:00:00', $allocations[0]->segment_end_at->toDateTimeString());
        $this->assertSame('2026-10-31 23:00:00', $allocations[0]->original_clock_in_at->toDateTimeString());
        $this->assertSame('2026-11-01 01:00:00', $allocations[0]->original_clock_out_at->toDateTimeString());
        $this->assertSame('2026-11-01 00:00:00', $allocations[1]->segment_start_at->toDateTimeString());
        $this->assertSame('2026-11-01 01:00:00', $allocations[1]->segment_end_at->toDateTimeString());
        $this->assertNotSame($allocations[0]->allocation_key, $allocations[1]->allocation_key);
    }

    public function test_exact_period_end_timestamp_is_excluded_from_prior_period_and_included_in_next(): void
    {
        $employee = $this->employee('Exact Boundary Employee');
        $this->profile($employee, ['salary_type' => 'hourly', 'pay_frequency' => 'monthly']);
        $this->rate($employee, 'hourly', '60.00');
        $this->attendance($employee, '2026-10-31 23:59:00', 'clock_in');
        $this->attendance($employee, '2026-11-01 00:00:00', 'clock_out');
        $october = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');
        $november = $this->period($this->company, '2026-11-01', '2026-11-30', 'monthly');

        $octEntry = app(PayrollCalculationService::class)->calculate($october, (int) $this->employer->id)->entries->sole();
        $novEntry = app(PayrollCalculationService::class)->calculate($november, (int) $this->employer->id)->entries->sole();

        $this->assertSame(1, $octEntry->worked_minutes);
        $this->assertSame(0, $novEntry->worked_minutes);
        $this->assertSame(60, $octEntry->attendanceAllocations()->sole()->elapsed_seconds);
        $this->assertSame(0, $novEntry->attendanceAllocations()->count());
    }

    public function test_shift_starting_exactly_at_midnight_is_allocated_only_to_the_new_period(): void
    {
        $employee = $this->employee('Midnight Start Employee');
        $this->profile($employee);
        $this->rate($employee, 'hourly', '60.00');
        $this->attendance($employee, '2026-11-01 00:00:00', 'clock_in');
        $this->attendance($employee, '2026-11-01 01:00:00', 'clock_out');
        $october = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');
        $november = $this->period($this->company, '2026-11-01', '2026-11-30', 'monthly');

        $octEntry = app(PayrollCalculationService::class)->calculate($october, (int) $this->employer->id)->entries->sole();
        $novEntry = app(PayrollCalculationService::class)->calculate($november, (int) $this->employer->id)->entries->sole();

        $this->assertSame(0, $octEntry->worked_minutes);
        $this->assertSame(60, $novEntry->worked_minutes);
        $this->assertSame(3600, $novEntry->attendanceAllocations()->sole()->elapsed_seconds);
        $this->assertSame('Asia/Manila', config('app.timezone'));
    }

    public function test_draft_recalculation_replaces_its_own_allocation_without_duplicating_it(): void
    {
        $employee = $this->employee('Allocation Retry Employee');
        $this->profile($employee);
        $this->rate($employee, 'hourly', '60.00');
        $this->attendance($employee, '2026-10-12 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-12 09:00:00', 'clock_out');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');

        $first = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id)->entries->sole();
        $firstKey = $first->calculation_snapshot['attendance_allocations'][0]['allocation_key'];
        $second = app(PayrollCalculationService::class)->calculate($period->fresh(), (int) $this->employer->id)->entries->sole();

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($firstKey, $second->calculation_snapshot['attendance_allocations'][0]['allocation_key']);
        $this->assertSame(1, PayrollAttendanceAllocation::query()->where('payroll_period_id', $period->id)->count());
        $this->assertSame(60, $second->worked_minutes);
        $duplicateRow = $second->attendanceAllocations()->sole()->getAttributes();
        unset($duplicateRow['id'], $duplicateRow['created_at'], $duplicateRow['updated_at']);
        $this->assertSame(0, DB::table('payroll_attendance_allocations')->insertOrIgnore($duplicateRow));
        $this->assertSame(1, PayrollAttendanceAllocation::query()->where('payroll_period_id', $period->id)->count());
    }

    public function test_recalculation_preserves_attendance_when_same_period_allocation_rows_outlive_the_old_entry(): void
    {
        $employee = $this->employee('Stale Allocation Recalculation Employee');
        $this->profile($employee, ['salary_type' => 'daily', 'pay_frequency' => 'weekly']);
        $hourly = $this->rate($employee, 'hourly', '50.00', '2026-10-25');
        $hourly->update(['effective_to' => '2026-10-27']);
        $this->rate($employee, 'daily', '400.00', '2026-10-28');
        $this->attendance($employee, '2026-10-27 14:31:03', 'clock_in');
        $this->attendance($employee, '2026-10-27 14:39:04', 'clock_out');
        $this->attendance($employee, '2026-10-28 10:46:28', 'clock_in');
        $this->attendance($employee, '2026-10-28 10:47:58', 'clock_out');
        $period = $this->period($this->company, '2026-10-26', '2026-11-01', 'weekly');

        $firstEntry = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id)->entries->sole();
        $this->assertSame(10, $firstEntry->worked_minutes);
        $this->assertSame(2, $firstEntry->payable_days);

        // Reproduce the legacy MyISAM orphan: entry deletion cannot cascade
        // these four pivot rows, so the next sync would hit the unique
        // attendance_log_id key even though the entry is already gone.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                $this->assertSame(4, DB::table('payroll_entry_attendance_logs')
                    ->where('payroll_entry_id', $firstEntry->id)
                    ->update(['payroll_entry_id' => 999999999]));
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        // Emulate allocation rows left behind when the MyISAM table cannot cascade
        // them as the migration's foreign-key declaration expects.
        $otherPeriod = $this->period($this->company, '2026-10-05', '2026-10-11', 'weekly');
        $otherEntry = $this->entry($employee, $otherPeriod, '0.00');
        DB::table('payroll_attendance_allocations')->where('payroll_entry_id', $firstEntry->id)
            ->update(['payroll_entry_id' => $otherEntry->id]);

        $recalculated = app(PayrollCalculationService::class)->calculate($period->fresh(), (int) $this->employer->id)->entries->sole();

        $this->assertSame($firstEntry->worked_minutes, $recalculated->worked_minutes);
        $this->assertSame($firstEntry->payable_days, $recalculated->payable_days);
        $this->assertSame($firstEntry->gross_pay, $recalculated->gross_pay);
        $this->assertNotContains('attendance_allocation_overlap_requires_review', $recalculated->review_flags);
        $rebuiltAllocations = DB::table('payroll_attendance_allocations')
            ->where('company_id', $this->company->id)
            ->where('payroll_period_id', $period->id)
            ->where('payroll_entry_id', $recalculated->id)
            ->orderBy('clock_in_log_id')->get(['elapsed_seconds']);
        $this->assertSame([481, 90], $rebuiltAllocations->pluck('elapsed_seconds')->map(fn ($seconds): int => (int) $seconds)->all());
        $rebuiltAttendanceLinks = DB::table('payroll_entry_attendance_logs')->where('payroll_entry_id', $recalculated->id)
            ->orderBy('attendance_log_id')->pluck('attendance_log_id')->map(fn ($id): int => (int) $id)->all();
        $this->assertSame($employee->attendanceLogs()->orderBy('attendance_logs.id')->pluck('attendance_logs.id')->map(fn ($id): int => (int) $id)->all(), $rebuiltAttendanceLinks);
        $this->assertSame(0, DB::table('payroll_entry_attendance_logs as links')
            ->leftJoin('payroll_entries as entries', 'entries.id', '=', 'links.payroll_entry_id')
            ->whereNull('entries.id')->whereIn('links.attendance_log_id', $rebuiltAttendanceLinks)->count());
    }

    public function test_recalculation_allocation_cleanup_is_scoped_to_its_company(): void
    {
        $employee = $this->employee('Scoped Allocation Cleanup Employee');
        $this->profile($employee, ['salary_type' => 'hourly', 'pay_frequency' => 'weekly']);
        $this->rate($employee, 'hourly', '50.00');
        $this->attendance($employee, '2026-10-27 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-27 09:00:00', 'clock_out');
        $period = $this->period($this->company, '2026-10-26', '2026-11-01', 'weekly');
        app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id);

        $otherCompany = Company::query()->create(['name' => 'Other Allocation Company', 'pay_metric' => 'hourly', 'currency' => 'PHP']);
        $otherEmployee = $this->employee('Other Company Allocation Employee', $otherCompany);
        $this->profile($otherEmployee, ['salary_type' => 'hourly', 'pay_frequency' => 'weekly']);
        $this->rate($otherEmployee, 'hourly', '60.00');
        $otherClockIn = $this->attendance($otherEmployee, '2026-10-27 08:00:00', 'clock_in');
        $otherClockOut = $this->attendance($otherEmployee, '2026-10-27 09:00:00', 'clock_out');
        $otherPeriod = $this->period($otherCompany, '2026-10-26', '2026-11-01', 'weekly');
        $otherEntry = app(PayrollCalculationService::class)->calculate($otherPeriod, (int) $this->employer->id)->entries->sole();
        $otherAllocation = $otherEntry->attendanceAllocations()->sole();

        // Simulate a stale row with this period id but a different tenant id.
        $otherAllocation->update(['payroll_period_id' => $period->id]);
        $recalculated = app(PayrollCalculationService::class)->calculate($period->fresh(), (int) $this->employer->id)->entries->sole();

        $this->assertSame(60, $recalculated->worked_minutes);
        $this->assertDatabaseHas('payroll_attendance_allocations', [
            'id' => $otherAllocation->id,
            'company_id' => $otherCompany->id,
            'payroll_period_id' => $period->id,
        ]);
        $this->assertDatabaseHas('payroll_attendance_allocations', [
            'company_id' => $this->company->id,
            'payroll_period_id' => $period->id,
            'payroll_entry_id' => $recalculated->id,
        ]);
        $this->assertDatabaseHas('payroll_entry_attendance_logs', [
            'payroll_entry_id' => $otherEntry->id,
            'attendance_log_id' => $otherClockIn->id,
        ]);
        $this->assertDatabaseHas('payroll_entry_attendance_logs', [
            'payroll_entry_id' => $otherEntry->id,
            'attendance_log_id' => $otherClockOut->id,
        ]);
    }

    public function test_november_can_be_generated_before_october_without_changing_segment_allocations(): void
    {
        $employee = $this->employee('November First Order Employee');
        $this->profile($employee);
        $this->rate($employee, 'hourly', '60.00');
        $this->attendance($employee, '2026-10-31 23:00:00', 'clock_in');
        $this->attendance($employee, '2026-11-01 01:00:00', 'clock_out');
        $october = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');
        $november = $this->period($this->company, '2026-11-01', '2026-11-30', 'monthly');

        $novEntry = app(PayrollCalculationService::class)->calculate($november, (int) $this->employer->id)->entries->sole();
        $octEntry = app(PayrollCalculationService::class)->calculate($october, (int) $this->employer->id)->entries->sole();

        $this->assertSame(60, $novEntry->worked_minutes);
        $this->assertSame(60, $octEntry->worked_minutes);
        $this->assertSame(120, PayrollAttendanceAllocation::query()
            ->where('user_id', $employee->id)->sum('elapsed_seconds') / 60);
    }

    public function test_paid_prior_period_punch_does_not_block_the_remaining_unallocated_segment(): void
    {
        $employee = $this->employee('Previously Paid Boundary Employee');
        $this->profile($employee);
        $this->rate($employee, 'hourly', '60.00');
        $clockIn = $this->attendance($employee, '2026-10-31 23:00:00', 'clock_in');
        $this->attendance($employee, '2026-11-01 01:00:00', 'clock_out');
        $october = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');
        $november = $this->period($this->company, '2026-11-01', '2026-11-30', 'monthly');
        $octEntry = app(PayrollCalculationService::class)->calculate($october, (int) $this->employer->id)->entries->sole();

        Sanctum::actingAs($this->employer);
        $this->postJson("/api/employer/payroll/periods/{$october->id}/approve")->assertOk();
        $this->postJson("/api/employer/payroll/entries/{$octEntry->id}/payments", [
            'amount' => '60.00', 'payment_date' => '2026-10-31', 'method' => 'manual_cash', 'reference_number' => 'CROSS-OCT-1',
        ])->assertOk();
        $this->assertTrue($clockIn->fresh()->is_paid);

        $novEntry = app(PayrollCalculationService::class)->calculate($november, (int) $this->employer->id)->entries->sole();
        $this->assertSame(60, $novEntry->worked_minutes);
        $this->assertNotContains('attendance_allocation_history_requires_review', $novEntry->review_flags);
        $this->assertSame(3600, $novEntry->attendanceAllocations()->sole()->elapsed_seconds);
        Sanctum::actingAs($this->employer);
        $this->postJson("/api/employer/payroll/periods/{$november->id}/approve")->assertOk();
        $this->postJson("/api/employer/payroll/entries/{$novEntry->id}/payments", [
            'amount' => '60.00', 'payment_date' => '2026-11-30', 'method' => 'manual_cash', 'reference_number' => 'CROSS-NOV-1',
        ])->assertOk();

        $this->getJson("/api/employer/payroll/periods/{$november->id}")
            ->assertOk()->assertJsonPath('data.entries.0.worked_minutes', 60)
            ->assertJsonPath('data.entries.0.net_pay', '60.00')
            ->assertJsonPath('data.entries.0.amount_paid', '60.00')
            ->assertJsonPath('data.entries.0.remaining_balance', '0.00');
        Sanctum::actingAs($employee);
        $this->getJson('/api/payroll/payslips')->assertOk()
            ->assertJsonPath('data.0.gross_pay', '60.00')
            ->assertJsonPath('data.0.net_pay', '60.00')
            ->assertJsonPath('data.0.amount_paid', '60.00')
            ->assertJsonPath('data.0.payment_status', 'paid');
        $this->assertSame(7200, (int) PayrollAttendanceAllocation::query()
            ->where('clock_in_log_id', $clockIn->id)->sum('elapsed_seconds'));
    }

    public function test_finalized_payroll_cannot_be_recalculated_or_its_allocations_rewritten(): void
    {
        $employee = $this->employee('Final Allocation Employee');
        $this->profile($employee);
        $this->rate($employee, 'hourly', '60.00');
        $this->attendance($employee, '2026-10-12 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-12 09:00:00', 'clock_out');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');
        $entry = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id)->entries->sole();
        $allocationId = $entry->attendanceAllocations()->sole()->id;
        Sanctum::actingAs($this->employer);
        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve")->assertOk();

        try {
            app(PayrollCalculationService::class)->calculate($period->fresh(), (int) $this->employer->id);
            $this->fail('A finalized period must not be recalculated.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertDatabaseHas('payroll_attendance_allocations', ['id' => $allocationId, 'payroll_entry_id' => $entry->id]);
        $this->assertSame('approved', $period->fresh()->status);
    }

    public function test_compensation_changed_after_calculation_requires_recalculation(): void
    {
        $employee = $this->employee('Changed Rate Employee');
        $this->profile($employee, ['salary_type' => 'monthly', 'pay_frequency' => 'monthly']);
        $rate = $this->rate($employee, 'monthly', '30000.00');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');
        app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id);
        $rate->update(['rate' => '35000.00']);
        Sanctum::actingAs($this->employer);

        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve")
            ->assertUnprocessable()->assertJsonValidationErrors('payroll');
    }

    public function test_zero_pay_is_approvable_when_hourly_employee_has_no_payable_work(): void
    {
        $employee = $this->employee('No Work Employee');
        $this->profile($employee, ['salary_type' => 'hourly', 'pay_frequency' => 'monthly', 'sss_enabled' => false, 'philhealth_enabled' => false, 'pagibig_enabled' => false, 'withholding_tax_enabled' => false]);
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');
        $calculated = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id);
        $this->assertSame('0.00', $calculated->entries->sole()->gross_pay);
        $this->assertSame([], $calculated->entries->sole()->review_flags);
        Sanctum::actingAs($this->employer);

        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_deductions_exceeding_gross_block_approval(): void
    {
        $employee = $this->employee('Excess Deduction Employee');
        $this->profile($employee, ['salary_type' => 'monthly', 'pay_frequency' => 'monthly']);
        $this->rate($employee, 'monthly', '1000.00');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');
        $calculated = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id);
        $entry = $calculated->entries->sole();
        $entry->lines()->create([
            'category' => 'employee_deduction', 'code' => 'test_deduction', 'label' => 'Test deduction', 'amount' => '1500.00', 'taxable' => false,
        ]);
        $entry->update(['employee_deductions' => '1500.00', 'net_pay' => '-500.00', 'review_flags' => ['negative_net_pay_requires_review']]);
        $period->update(['deduction_total' => '1500.00', 'net_total' => '-500.00']);
        Sanctum::actingAs($this->employer);

        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve", [
            'review_note' => 'The deductions and pay amounts were reviewed.',
        ])->assertUnprocessable()->assertJsonValidationErrors('payroll');
    }

    public function test_statutory_contribution_thresholds_use_separate_employee_and_employer_shares(): void
    {
        $cases = [
            ['Boundary 5250', '5250.00', '275.00', '560.00', '250.00', '105.00'],
            ['Boundary 15000', '15000.00', '750.00', '1530.00', '375.00', '200.00'],
            ['Boundary 100000', '100000.00', '1750.00', '3530.00', '2500.00', '200.00'],
        ];
        foreach ($cases as [$name, $salary, $sssEmployee, $sssEmployerAndEc, $philhealthEmployee, $pagibigEmployee]) {
            $employee = $this->employee($name);
            $this->profile($employee, [
                'salary_type' => 'monthly', 'sss_enabled' => true, 'philhealth_enabled' => true, 'pagibig_enabled' => true,
            ]);
            $this->rate($employee, 'monthly', $salary);
        }
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');
        $calculated = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id);

        foreach ($cases as [$name, , $sssEmployee, $sssEmployerAndEc, $philhealthEmployee, $pagibigEmployee]) {
            $entry = $calculated->entries->first(fn (PayrollEntry $entry): bool => $entry->employee->name === $name);
            $this->assertSame($sssEmployee, $entry->lines->firstWhere('code', 'sss_employee')->amount);
            $this->assertSame($sssEmployerAndEc, number_format($entry->lines->whereIn('code', ['sss_employer', 'sss_ec'])->sum(fn ($line): float => (float) $line->amount), 2, '.', ''));
            $this->assertSame($philhealthEmployee, $entry->lines->firstWhere('code', 'philhealth_employee')->amount);
            $this->assertSame($pagibigEmployee, $entry->lines->firstWhere('code', 'pagibig_employee')->amount);
        }
        $this->assertContains('unsupported_payroll_rule_requires_review', $calculated->entries->first()->review_flags);
        $this->assertSame('2025-01-01', data_get($calculated->entries->first()->calculation_snapshot, 'statutory_rules.sss.effective_from'));
    }

    public function test_bir_monthly_table_boundary_values_are_applied_to_centavo_precision(): void
    {
        foreach ([['Tax threshold', '20833.00', '0.00'], ['Tax above threshold', '20834.00', '0.15']] as [$name, $salary, $tax]) {
            $employee = $this->employee($name);
            $this->profile($employee, ['salary_type' => 'monthly', 'withholding_tax_enabled' => true]);
            $this->rate($employee, 'monthly', $salary);
        }
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');
        $calculated = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id);

        foreach ([['Tax threshold', '0.00'], ['Tax above threshold', '0.15']] as [$name, $expectedTax]) {
            $entry = $calculated->entries->first(fn (PayrollEntry $entry): bool => $entry->employee->name === $name);
            $this->assertSame($expectedTax, $entry->lines->firstWhere('code', 'bir_withholding_tax')?->amount ?? '0.00');
        }
    }

    public function test_withholding_tax_enabled_blocks_approval_without_employee_tax_classification(): void
    {
        $employee = $this->employee('Unclassified Tax Employee');
        $this->profile($employee, ['salary_type' => 'monthly', 'withholding_tax_enabled' => true]);
        $this->rate($employee, 'monthly', '30000.00');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');

        $entry = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id)->entries->sole();
        $this->assertContains('missing_statutory_input_requires_review', $entry->review_flags);
        Sanctum::actingAs($this->employer);
        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve", [
            'review_note' => 'Reviewed missing tax classification data.',
        ])->assertUnprocessable()->assertJsonValidationErrors('payroll');
    }

    public function test_web_payroll_actions_return_to_the_period_the_employer_just_created(): void
    {
        $this->actingAs($this->employer);
        $response = $this->post('/employer/payroll/periods', [
            'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'frequency' => 'monthly',
        ]);

        $response->assertRedirect();
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Employee register');
    }

    public function test_payroll_page_shows_the_simple_summary_and_first_run_empty_state(): void
    {
        $this->employee('Setup check employee');

        $this->actingAs($this->employer)
            ->get(route('employer.payroll'))
            ->assertOk()
            ->assertSee('Manage payroll periods, review calculations, and track manual payments.')
            ->assertSee('Payroll overview')
            ->assertSee('Eligible Employees')
            ->assertSee('Gross payroll')
            ->assertSee('Total deductions')
            ->assertSee('Net payroll')
            ->assertDontSee('Employer payroll cost');

        $this->get(route('employer.payroll', ['section' => 'periods']))
            ->assertOk()
            ->assertSee('No payroll periods yet. Create your first payroll period to get started.');

        $this->get(route('employer.payroll', ['section' => 'settings']))
            ->assertRedirect(route('employer.employees.index'));
        $this->get(route('employer.employees.show', User::query()->where('name', 'Setup check employee')->firstOrFail()))
            ->assertOk()
            ->assertSee('1. Pay profile')
            ->assertSee('2. Eligibility and contributions')
            ->assertSee('3. Recurring earnings and deductions');
    }

    public function test_payroll_page_uses_calculated_period_values_and_shows_review_warnings(): void
    {
        $employee = $this->employee('Review Employee');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly', 'pending_review');
        $period->update([
            'gross_total' => '125.50',
            'deduction_total' => '10.00',
            'employer_contribution_total' => '12.00',
            'net_total' => '115.50',
            'calculated_at' => now(),
        ]);
        $entry = $this->entry($employee, $period, '115.50');
        $entry->update(['review_flags' => ['employee_status_requires_review', 'attendance_allocation_overlap_requires_review']]);

        $this->actingAs($this->employer)
            ->get(route('employer.payroll', ['period_id' => $period->id]))
            ->assertOk()
            ->assertSee($this->company->formatMoney('125.50'))
            ->assertSee($this->company->formatMoney('10.00'))
            ->assertSee($this->company->formatMoney('115.50'))
            ->assertSee('Review flags before approval')
            ->assertSee('This work interval overlaps an existing payroll allocation. Review the prior allocation and ensure the same attendance time is not paid twice before recalculating.')
            ->assertSee('Employee is inactive. Ordinary payroll approval is blocked; review the employee’s outstanding pay separately.')
            ->assertSee('Review note')
            ->assertSee('Approval is blocked until critical issues are corrected and the period is recalculated.')
            ->assertSee('View payment history and balances');
    }

    public function test_draft_payroll_shows_company_scoped_employee_setup_warnings(): void
    {
        $employee = $this->employee('Missing Profile Employee');
        $otherCompany = Company::query()->create(['name' => 'Other Co', 'pay_metric' => 'hourly', 'currency' => 'PHP']);
        $this->employee('Other Company Employee', $otherCompany);
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');

        $this->actingAs($this->employer)
            ->get(route('employer.payroll', ['period_id' => $period->id]))
            ->assertOk()
            ->assertSee('Check employee setup before calculating')
            ->assertSee('Missing Profile Employee')
            ->assertSee('Missing pay profile.')
            ->assertDontSee('Other Company Employee');
    }

    public function test_web_period_creation_rejects_an_end_date_before_the_start_date(): void
    {
        $this->actingAs($this->employer)
            ->from(route('employer.payroll'))
            ->post('/employer/payroll/periods', [
                'period_start' => '2026-10-20',
                'period_end' => '2026-10-19',
                'frequency' => 'monthly',
            ])
            ->assertRedirect(route('employer.payroll'))
            ->assertSessionHasErrors('period_end');

        $this->assertDatabaseCount('payroll_periods', 0);
    }

    public function test_web_pay_profile_form_updates_effective_dated_compensation(): void
    {
        $employee = $this->employee('Profile update employee');

        $this->actingAs($this->employer)
            ->patch('/employer/payroll/profiles?section=settings', [
                'user_id' => $employee->id,
                'salary_type' => 'daily',
                'rate' => '800.00',
                'pay_frequency' => 'weekly',
                'effective_from' => '2026-10-16',
            ])
            ->assertRedirect(route('employer.employees.show', $employee));

        $this->assertDatabaseHas('payroll_profiles', [
            'user_id' => $employee->id,
            'salary_type' => 'daily',
            'pay_frequency' => 'weekly',
        ]);
        $rate = PayrollCompensationHistory::query()->where('user_id', $employee->id)->sole();
        $this->assertSame('daily', $rate->basis);
        $this->assertSame('800.00', $rate->rate);
        $this->assertSame('2026-10-16', $rate->effective_from->toDateString());
    }

    public function test_web_recurring_deduction_requires_authorization_and_records_the_item(): void
    {
        $employee = $this->employee('Recurring item employee');
        $this->actingAs($this->employer)
            ->from(route('employer.employees.show', $employee))
            ->post(route('employer.payroll.profile-items.store', ['return_to' => 'employee_profile', 'employee_id' => $employee->id]), [
                'user_id' => $employee->id,
                'category' => 'deduction',
                'code' => 'equipment_recovery',
                'label' => 'Equipment recovery',
                'amount' => '50.00',
                'effective_from' => '2026-10-15',
            ])
            ->assertRedirect(route('employer.employees.show', $employee))
            ->assertSessionHasErrors('authorization_reference');

        $this->assertDatabaseCount('payroll_profile_items', 0);

        $this->from(route('employer.employees.show', $employee))
            ->post(route('employer.payroll.profile-items.store', ['return_to' => 'employee_profile', 'employee_id' => $employee->id]), [
                'user_id' => $employee->id,
                'category' => 'deduction',
                'code' => 'equipment_recovery',
                'label' => 'Equipment recovery',
                'amount' => '50.00',
                'authorization_reference' => 'SIGNED-AUTH-100',
                'effective_from' => '2026-10-15',
            ])
            ->assertRedirect(route('employer.employees.show', $employee));

        $this->assertDatabaseHas('payroll_profile_items', [
            'user_id' => $employee->id,
            'category' => 'deduction',
            'code' => 'equipment_recovery',
            'authorization_reference' => 'SIGNED-AUTH-100',
            'taxable' => false,
        ]);
    }

    public function test_payroll_period_status_filter_shows_only_matching_periods(): void
    {
        $approved = $this->period($this->company, '2026-08-01', '2026-08-31', 'monthly', 'approved');
        $draft = $this->period($this->company, '2026-09-01', '2026-09-30', 'monthly', 'draft');

        $this->actingAs($this->employer)
            ->get(route('employer.payroll', ['section' => 'periods', 'period_status' => 'approved']))
            ->assertOk()
            ->assertSee($approved->period_start->format('M d, Y'))
            ->assertDontSee($draft->period_start->format('M d, Y'));
    }

    public function test_payroll_periods_cannot_overlap_active_runs_or_use_a_wrong_period_length(): void
    {
        Sanctum::actingAs($this->employer);
        $payload = ['period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'frequency' => 'monthly'];

        $created = $this->postJson('/api/employer/payroll/periods', $payload)->assertOk();
        $this->assertSame('draft', $created->json('data.status'));
        $this->postJson('/api/employer/payroll/periods', [
            'period_start' => '2026-10-15', 'period_end' => '2026-11-14', 'frequency' => 'monthly',
        ])->assertUnprocessable();
        $this->postJson('/api/employer/payroll/periods', [
            'period_start' => '2026-10-01', 'period_end' => '2026-10-08', 'frequency' => 'weekly',
        ])->assertUnprocessable();
    }

    public function test_weekly_payroll_period_uses_seven_inclusive_calendar_dates(): void
    {
        $this->actingAs($this->employer);
        $validPayload = [
            'period_start' => '2026-10-26',
            'period_end' => '2026-11-01',
            'frequency' => 'weekly',
        ];

        $this->post(route('employer.payroll.periods.store', ['section' => 'create']), $validPayload)
            ->assertRedirect();
        $createdPeriod = PayrollPeriod::query()->sole();
        $this->assertSame('2026-10-26', $createdPeriod->period_start->toDateString());
        $this->assertSame('2026-11-01', $createdPeriod->period_end->toDateString());
        $this->assertSame('weekly', $createdPeriod->frequency);
        $this->assertSame('draft', $createdPeriod->status);

        $this->from(route('employer.payroll', ['section' => 'create']))
            ->post(route('employer.payroll.periods.store'), [
                'period_start' => '2026-10-26',
                'period_end' => '2026-10-31',
                'frequency' => 'weekly',
            ])
            ->assertRedirect(route('employer.payroll', ['section' => 'create']))
            ->assertSessionHasErrors('period_end');

        $this->from(route('employer.payroll', ['section' => 'create']))
            ->post(route('employer.payroll.periods.store'), [
                'period_start' => '2026-11-02',
                'period_end' => '2026-11-09',
                'frequency' => 'weekly',
            ])
            ->assertRedirect(route('employer.payroll', ['section' => 'create']))
            ->assertSessionHasErrors('period_end');

        $this->assertDatabaseCount('payroll_periods', 1);
    }

    public function test_partial_payments_are_idempotently_scoped_and_cannot_exceed_the_balance(): void
    {
        $employee = $this->employee('Payment Employee');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly', 'approved');
        $entry = $this->entry($employee, $period, '100.00');
        Sanctum::actingAs($this->employer);
        $payload = ['amount' => '40.00', 'payment_date' => '2026-10-15', 'method' => 'manual_bank_deposit', 'reference_number' => 'PAY-100'];

        $this->postJson("/api/employer/payroll/entries/{$entry->id}/payments", $payload)
            ->assertOk()->assertJsonPath('message', 'Payment recorded; TimePay does not initiate a transfer.');
        $this->assertSame('partially_paid', $period->fresh()->status);
        $this->assertSame('40.00', $entry->fresh()->amount_paid);

        $this->postJson("/api/employer/payroll/entries/{$entry->id}/payments", [
            ...$payload,
        ])->assertUnprocessable();
        $this->postJson("/api/employer/payroll/entries/{$entry->id}/payments", [
            ...$payload,
            'amount' => '60.01',
            'reference_number' => 'PAY-101',
        ])->assertUnprocessable();
        $this->postJson("/api/employer/payroll/entries/{$entry->id}/payments", [
            ...$payload,
            'amount' => '60.00',
            'reference_number' => 'PAY-102',
        ])->assertOk();
        $this->assertSame('paid', $period->fresh()->status);
        $this->assertSame('100.00', $entry->fresh()->amount_paid);
        $this->assertDatabaseCount('payroll_payments', 2);
    }

    public function test_employee_payslips_use_frozen_identity_and_reject_cross_company_access(): void
    {
        $employee = $this->employee('Original Employee Name');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly', 'approved');
        $entry = $this->entry($employee, $period, '125.50', [
            'employee_snapshot' => ['id' => $employee->id, 'name' => 'Original Employee Name'],
            'company_snapshot' => ['name' => 'Payroll Test Co', 'currency' => 'PHP'],
        ]);
        $entry->lines()->create([
            'category' => 'earning', 'code' => 'basic_pay', 'label' => 'Basic pay', 'amount' => '125.50', 'taxable' => true,
        ]);
        $employee->update(['name' => 'Changed Current Name']);
        $this->company->update(['name' => 'Changed Current Company']);

        Sanctum::actingAs($employee);
        $this->getJson('/api/payroll/payslips')->assertOk()
            ->assertJsonPath('data.0.employee_name', 'Original Employee Name')
            ->assertJsonPath('data.0.company_name', 'Payroll Test Co')
            ->assertJsonPath('data.0.currency', 'PHP')
            ->assertJsonPath('data.0.gross_pay', '125.50')
            ->assertJsonPath('data.0.earnings.0.amount', '125.50')
            ->assertJsonPath('data.0.payment_status', 'unpaid');
        $this->getJson('/api/employee/payroll/ytd?as_of=2026-10-31')->assertOk()
            ->assertJsonPath('data.gross_pay', '125.50')
            ->assertJsonPath('data.taxable_compensation', '125.50')
            ->assertJsonPath('data.formatted_taxable_compensation', '₱ 125.50');

        $otherCompany = Company::query()->create(['name' => 'Other Co', 'pay_metric' => 'hourly', 'currency' => 'PHP']);
        $otherEmployee = $this->employee('Other Employee', $otherCompany);
        Sanctum::actingAs($otherEmployee);
        $this->getJson("/api/payroll/payslip/entry-{$entry->id}/download")->assertNotFound();

        $otherEmployer = User::factory()->create(['company_id' => $otherCompany->id, 'role' => User::ROLE_EMPLOYER]);
        Sanctum::actingAs($otherEmployer);
        $this->getJson("/api/employer/payroll/periods/{$period->id}")->assertNotFound();
        $this->postJson("/api/employer/payroll/entries/{$entry->id}/payments", [
            'amount' => '1.00', 'payment_date' => '2026-10-15', 'method' => 'manual_cash',
        ])->assertNotFound();
    }

    public function test_payroll_report_register_exports_and_employee_pdf_use_finalized_entries(): void
    {
        $employee = $this->employee('Report Employee');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly', 'approved');
        $entry = $this->entry($employee, $period, '125.50');
        $entry->lines()->create([
            'category' => 'earning', 'code' => 'basic_pay', 'label' => 'Basic pay', 'amount' => '125.50', 'taxable' => true,
        ]);

        Sanctum::actingAs($this->employer);
        $this->getJson('/api/employer/payroll/reports?view=monthly&from=2026-10-01&to=2026-10-31&employee_id='.$employee->id)
            ->assertOk()->assertJsonPath('data.0.gross_pay', '125.50')->assertJsonPath('data.0.entries.0.remaining_balance', '125.50');
        $this->get("/api/employer/payroll/periods/{$period->id}/register.pdf")
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get("/api/employer/payroll/periods/{$period->id}/export.xlsx")
            ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        Sanctum::actingAs($employee);
        $this->get('/api/payroll/payslip/entry-'.$entry->id.'/download')
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_new_employee_creation_creates_a_payroll_profile_and_rate_history(): void
    {
        Sanctum::actingAs($this->employer);

        $response = $this->postJson('/employer/employees', [
            'name' => 'New Payroll Hire',
            'email' => 'new-hire@example.test',
            'salary_type' => 'daily',
            'daily_rate' => '900.00',
            'pay_frequency' => 'weekly',
        ])->assertCreated();

        $employeeId = $response->json('user.id');
        $this->assertDatabaseHas('payroll_profiles', ['user_id' => $employeeId, 'salary_type' => 'daily', 'pay_frequency' => 'weekly']);
        $this->assertDatabaseHas('payroll_compensation_histories', ['user_id' => $employeeId, 'basis' => 'daily', 'rate' => '900.00']);
    }

    public function test_employer_can_update_statutory_eligibility_without_touching_compensation(): void
    {
        $employee = $this->employee('Eligibility Employee');
        $profile = $this->profile($employee, [
            'sss_enabled' => true,
            'philhealth_enabled' => true,
            'pagibig_enabled' => true,
            'withholding_tax_enabled' => true,
        ]);

        $this->actingAs($this->employer)->patch('/employer/payroll/profiles?section=settings', [
            'user_id' => $employee->id,
            'sss_enabled' => '0',
            'philhealth_enabled' => null,
            'pagibig_enabled' => '0',
            'withholding_tax_enabled' => '0',
        ])->assertRedirect(route('employer.employees.show', $employee));

        $profile->refresh();
        $this->assertFalse($profile->sss_enabled);
        $this->assertTrue($profile->philhealth_enabled);
        $this->assertFalse($profile->pagibig_enabled);
        $this->assertFalse($profile->withholding_tax_enabled);
        $this->assertDatabaseCount('payroll_compensation_histories', 0);
    }

    private function employee(string $name, ?Company $company = null): User
    {
        $company ??= $this->company;
        return User::factory()->create([
            'company_id' => $company->id,
            'name' => $name,
            'role' => User::ROLE_EMPLOYEE,
            'status' => 'active',
            'hourly_rate' => '20.00',
        ]);
    }

    private function profile(User $employee, array $overrides = []): PayrollProfile
    {
        return PayrollProfile::query()->create(array_merge([
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'salary_type' => 'hourly',
            'pay_frequency' => 'monthly',
            'payroll_eligible' => true,
            'overtime_eligible' => true,
            'rank_and_file' => true,
            'sss_enabled' => false,
            'philhealth_enabled' => false,
            'pagibig_enabled' => false,
            'withholding_tax_enabled' => false,
            'standard_hours_per_day' => 8,
            'standard_work_days_per_month' => 22,
        ], $overrides));
    }

    private function rate(User $employee, string $basis, string $rate, string $effectiveFrom = '2026-01-01'): PayrollCompensationHistory
    {
        return PayrollCompensationHistory::query()->create([
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'basis' => $basis,
            'rate' => $rate,
            'effective_from' => $effectiveFrom,
        ]);
    }

    private function period(Company $company, string $start, string $end, string $frequency, string $status = 'draft'): PayrollPeriod
    {
        return PayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_start' => $start,
            'period_end' => $end,
            'frequency' => $frequency,
            'status' => $status,
            'created_by' => $this->employer->id,
        ]);
    }

    private function entry(User $employee, PayrollPeriod $period, string $netPay, array $snapshot = []): PayrollEntry
    {
        return PayrollEntry::query()->create([
            'company_id' => $period->company_id,
            'payroll_period_id' => $period->id,
            'user_id' => $employee->id,
            'gross_pay' => $netPay,
            'employee_deductions' => '0.00',
            'employer_contributions' => '0.00',
            'net_pay' => $netPay,
            'employer_cost' => $netPay,
            'amount_paid' => '0.00',
            'calculation_snapshot' => $snapshot + [
                'employee_snapshot' => ['id' => $employee->id, 'name' => $employee->name],
                'company_snapshot' => ['name' => $this->company->name, 'currency' => $this->company->currency],
            ],
            'review_flags' => [],
        ]);
    }

    private function attendance(User $employee, string $timestamp, string $type): AttendanceLog
    {
        return AttendanceLog::query()->create([
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'timestamp' => $timestamp,
            'type' => $type,
            'latitude' => '14.59950000',
            'longitude' => '120.98420000',
            'distance_meters' => '1.00',
            'status' => 'verified',
            'is_paid' => false,
        ]);
    }

    private function sumDeductionsBeforeTax(PayrollEntry $entry): string
    {
        $statutory = $entry->lines->where('category', 'employee_deduction')->where('code', '!=', 'bir_withholding_tax')
            ->sum(fn ($line) => (int) round((float) $line->amount * 100));

        return number_format($statutory / 100, 2, '.', '');
    }
}
