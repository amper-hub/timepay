<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\PayrollAudit;
use App\Models\PayrollCompensationHistory;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\PayrollProfile;
use App\Models\User;
use App\Services\PayrollCalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_partial_period_recurring_items_are_flagged_for_manual_review(): void
    {
        $employee = $this->employee('Synthetic Recurring Item Employee');
        $this->profile($employee, ['salary_type' => 'monthly', 'pay_frequency' => 'monthly']);
        $this->rate($employee, 'monthly', '30000.00');
        \\App\\Models\\PayrollProfileItem::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'category' => 'earning',
            'code' => 'temporary_allowance',
            'label' => 'Temporary allowance',
            'amount' => '500.00',
            'taxable' => true,
            'effective_from' => '2026-10-15',
            'effective_to' => '2026-10-31',
            'created_by' => $this->employer->id,
        ]);
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');

        $calculated = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id);
        $entry = $calculated->entries->sole();

        $this->assertSame('30500.00', $entry->gross_pay);
        $this->assertContains('recurring_profile_item_partial_period_requires_review', $entry->review_flags);
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
        ])->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_calculation_flags_require_an_audited_review_note_before_approval(): void
    {
        $employee = $this->employee('Hourly Employee');
        $this->profile($employee, ['pay_frequency' => 'monthly']);
        $this->rate($employee, 'hourly', '20.00');
        $this->attendance($employee, '2026-10-02 08:00:00', 'clock_in');
        $this->attendance($employee, '2026-10-02 18:00:00', 'clock_out');
        $period = $this->period($this->company, '2026-10-01', '2026-10-31', 'monthly');

        $employee->update(['status' => 'inactive']);
        $calculated = app(PayrollCalculationService::class)->calculate($period, (int) $this->employer->id);
        $this->assertContains('employee_status_requires_review', $calculated->entries->sole()->review_flags);
        $this->assertSame('inactive', data_get($calculated->entries->sole()->calculation_snapshot, 'employee_status_snapshot'));
        Sanctum::actingAs($this->employer);

        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve")->assertUnprocessable();
        $this->postJson("/api/employer/payroll/periods/{$period->id}/approve", [
            'review_note' => 'Checked the long shift and inactive status against approved records.',
        ])->assertOk()->assertJsonPath('data.status', 'approved');

        $audit = PayrollAudit::query()->where('entity_type', 'payroll_period')->where('entity_id', $period->id)
            ->where('action', 'approved')->sole();
        $this->assertSame('Checked the long shift and inactive status against approved records.', data_get($audit->after_data, 'review_note'));
    }

    public function test_web_payroll_actions_return_to_the_period_the_employer_just_created(): void
    {
        $this->actingAs($this->employer);
        $this->post('/employer/payroll/periods', [
            'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'frequency' => 'monthly',
        ])->assertRedirect(route('employer.payroll'));

        $this->get(route('employer.payroll'))->assertOk()->assertSee('Pay register');
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

        $this->actingAs($this->employer)->patch('/employer/payroll/profiles', [
            'user_id' => $employee->id,
            'sss_enabled' => '0',
            'philhealth_enabled' => null,
            'pagibig_enabled' => '0',
            'withholding_tax_enabled' => '0',
        ])->assertRedirect(route('employer.payroll'));

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
