<?php

namespace Tests\Feature;

use App\Exports\DtrSpreadsheetText;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class DtrReportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $employee;

    private User $employer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'TimePay DTR Test Company',
            'latitude' => null,
            'longitude' => null,
            'geofence_radius_meters' => 100,
            'work_start_time' => '08:00:00',
            'work_end_time' => '17:00:00',
            'pay_metric' => 'hourly',
        ]);
        $this->employee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYEE,
            'name' => 'DTR Employee',
        ]);
        $this->employer = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYER,
        ]);
    }

    public function test_employee_can_read_only_their_own_dtr_without_a_client_supplied_employee_id(): void
    {
        $otherEmployee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYEE,
        ]);
        $this->log($this->employee, '2026-10-10 08:00:00', 'clock_in');
        $this->log($this->employee, '2026-10-10 16:00:00', 'clock_out');
        $this->log($otherEmployee, '2026-10-10 08:00:00', 'clock_in');
        $this->log($otherEmployee, '2026-10-10 16:00:00', 'clock_out');

        Sanctum::actingAs($this->employee);
        $this->getJson('/api/employee/dtr?start_date=2026-10-01&end_date=2026-10-31&employee_id='.$otherEmployee->id)
            ->assertOk()
            ->assertJsonPath('data.employee.id', $this->employee->id)
            ->assertJsonPath('data.total_hours', 8)
            ->assertJsonCount(1, 'data.records');
    }

    public function test_employer_can_preview_their_employee_but_not_another_company_employee(): void
    {
        $otherCompany = Company::query()->create(['name' => 'Other Company']);
        $otherEmployee = User::factory()->create([
            'company_id' => $otherCompany->id,
            'role' => User::ROLE_EMPLOYEE,
        ]);

        $this->actingAs($this->employer)
            ->getJson("/employer/attendance/dtr/{$this->employee->id}?start_date=2026-10-01&end_date=2026-10-31")
            ->assertOk()
            ->assertJsonPath('data.employee.id', $this->employee->id);

        $this->actingAs($this->employer)
            ->getJson("/employer/attendance/dtr/{$otherEmployee->id}?start_date=2026-10-01&end_date=2026-10-31")
            ->assertNotFound();
    }

    public function test_attendance_log_contains_employee_and_all_employee_dtr_actions(): void
    {
        $this->log($this->employee, '2026-10-10 08:00:00', 'clock_in');

        $this->actingAs($this->employer)
            ->get('/employer/attendance')
            ->assertOk()
            ->assertSee('Download DTR')
            ->assertSee('Export All Employees')
            ->assertSee('Preview records');
    }

    public function test_employee_cannot_use_employer_dtr_routes(): void
    {
        $this->actingAs($this->employee)
            ->getJson("/employer/attendance/dtr/{$this->employee->id}?start_date=2026-10-01&end_date=2026-10-31")
            ->assertForbidden();
    }

    public function test_overnight_hours_incomplete_and_rejected_punches_are_reported_safely(): void
    {
        $this->log($this->employee, '2026-10-01 22:00:00', 'clock_in');
        $this->log($this->employee, '2026-10-01 22:00:00', 'clock_in');
        $this->log($this->employee, '2026-10-02 06:00:00', 'clock_out');
        $this->log($this->employee, '2026-10-02 06:00:00', 'clock_out');
        $this->log($this->employee, '2026-10-03 08:15:00', 'clock_in');
        $this->log($this->employee, '2026-10-04 08:00:00', 'clock_in', 'rejected');

        Sanctum::actingAs($this->employee);
        $response = $this->getJson('/api/employee/dtr?start_date=2026-10-01&end_date=2026-10-04');

        $response->assertOk()
            ->assertJsonPath('data.total_hours', 8)
            ->assertJsonPath('data.records.0.date', '2026-10-01')
            ->assertJsonPath('data.records.0.clock_in', '10:00 PM')
            ->assertJsonPath('data.records.0.clock_out', '6:00 AM')
            ->assertJsonPath('data.records.0.net_hours', 8)
            ->assertJsonPath('data.records.0.status', 'Completed')
            ->assertJsonPath('data.records.0.break_duration_minutes', null)
            ->assertJsonPath('data.records.1.status', 'Incomplete')
            ->assertJsonPath('data.records.1.net_hours', null)
            ->assertJsonPath('data.records.2.status', 'Invalidated')
            ->assertJsonPath('data.records.2.net_hours', null);
    }

    public function test_invalid_and_oversized_date_ranges_are_rejected(): void
    {
        Sanctum::actingAs($this->employee);

        $this->getJson('/api/employee/dtr?start_date=2026-10-15&end_date=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');

        $this->getJson('/api/employee/dtr?start_date=2025-01-01&end_date=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');
    }

    public function test_empty_period_returns_a_valid_empty_report(): void
    {
        Sanctum::actingAs($this->employee);

        $this->getJson('/api/employee/dtr?start_date=2026-10-01&end_date=2026-10-31')
            ->assertOk()
            ->assertJsonPath('data.total_hours', 0)
            ->assertJsonCount(0, 'data.records');
    }

    public function test_pdf_and_excel_exports_return_the_expected_file_formats(): void
    {
        $this->log($this->employee, '2026-10-10 08:00:00', 'clock_in');
        $this->log($this->employee, '2026-10-10 16:00:00', 'clock_out');

        Sanctum::actingAs($this->employee);
        $pdf = $this->get('/api/employee/dtr/pdf?start_date=2026-10-01&end_date=2026-10-31');
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));

        $this->actingAs($this->employer);
        $xlsx = $this->get("/employer/attendance/dtr/{$this->employee->id}/xlsx?start_date=2026-10-01&end_date=2026-10-31");
        $xlsx->assertOk();
        $this->assertStringStartsWith('PK', $xlsx->streamedContent());
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $xlsx->headers->get('Content-Type')
        );
    }

    public function test_employer_can_export_one_consolidated_workbook_for_company_employees(): void
    {
        $this->log($this->employee, '2026-10-10 08:00:00', 'clock_in');
        $this->log($this->employee, '2026-10-10 16:00:00', 'clock_out');

        $this->actingAs($this->employer);
        $xlsx = $this->get('/employer/attendance/dtr/export-all.xlsx?start_date=2026-10-01&end_date=2026-10-31');

        $xlsx->assertOk();
        $contents = $xlsx->streamedContent();
        $this->assertStringStartsWith('PK', $contents);
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $xlsx->headers->get('Content-Type'));

        $temporaryFile = tempnam(sys_get_temp_dir(), 'timepay-dtr-');
        $this->assertNotFalse($temporaryFile);

        try {
            file_put_contents($temporaryFile, $contents);
            $workbook = IOFactory::load($temporaryFile);
            $this->assertSame(['DTR Summary', 'Attendance'], $workbook->getSheetNames());
            $this->assertSame('DTR Employee', $workbook->getSheet(0)->getCell('B8')->getValue());
            $this->assertSame(8, (int) $workbook->getSheet(0)->getCell('C8')->getValue());
        } finally {
            unlink($temporaryFile);
        }
    }

    public function test_consolidated_export_rejects_periods_over_93_days(): void
    {
        $this->actingAs($this->employer)
            ->getJson('/employer/attendance/dtr/export-all.xlsx?start_date=2026-01-01&end_date=2026-04-04')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Consolidated exports are limited to 93 days at a time.');
    }

    public function test_employee_and_company_text_is_safe_for_spreadsheet_cells(): void
    {
        $this->assertSame("'=1+1", DtrSpreadsheetText::safe('=1+1'));
        $this->assertSame("'  @SUM(A1:A2)", DtrSpreadsheetText::safe('  @SUM(A1:A2)'));
        $this->assertSame('DTR Employee', DtrSpreadsheetText::safe('DTR Employee'));
    }

    private function log(User $employee, string $timestamp, string $type, string $status = 'verified'): AttendanceLog
    {
        return AttendanceLog::query()->create([
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'timestamp' => $timestamp,
            'type' => $type,
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'distance_meters' => 0,
            'status' => $status,
            'is_suspicious' => $status === 'flagged',
        ]);
    }
}
