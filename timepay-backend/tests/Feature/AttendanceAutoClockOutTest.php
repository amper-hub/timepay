<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\PayrollProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceAutoClockOutTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_non_overtime_employee_including_a_legacy_early_punch_is_closed_at_shift_end(): void
    {
        config([
            'app.timezone' => 'UTC',
            'timepay.attendance_timezone' => 'Asia/Manila',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-11-06 09:03:00', 'UTC'));

        $company = Company::query()->create([
            'name' => 'Auto Clock Out Test Company',
            'pay_metric' => 'hourly',
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
            'working_days' => ['Friday'],
        ]);
        $employee = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_EMPLOYEE,
        ]);
        PayrollProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'salary_type' => 'hourly',
            'pay_frequency' => 'monthly',
            'overtime_eligible' => false,
        ]);
        AttendanceLog::query()->create([
            'user_id' => $employee->id,
            'company_id' => $company->id,
            'timestamp' => Carbon::parse('2026-11-06 00:47:00', 'UTC'),
            'type' => 'clock_in',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'distance_meters' => 10,
            'status' => 'verified',
            'is_suspicious' => false,
        ]);

        $this->artisan('attendance:auto-clock-out')->assertSuccessful();

        $this->assertDatabaseCount('attendance_logs', 2);
        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $employee->id,
            'type' => 'clock_out',
            'timestamp' => '2026-11-06 09:00:00',
            'source' => 'system',
            'status' => 'verified',
            'is_suspicious' => false,
        ]);

        // Re-running the scheduled command must not create another clock-out.
        $this->artisan('attendance:auto-clock-out')->assertSuccessful();
        $this->assertDatabaseCount('attendance_logs', 2);
    }

    public function test_new_payroll_profiles_default_to_overtime_disabled(): void
    {
        $company = Company::query()->create([
            'name' => 'Default Eligibility Test Company',
            'pay_metric' => 'hourly',
        ]);
        $employee = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_EMPLOYEE,
        ]);

        $profile = PayrollProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'salary_type' => 'hourly',
            'pay_frequency' => 'monthly',
        ]);

        $this->assertFalse($profile->fresh()->overtime_eligible);
    }

    public function test_overtime_eligible_employee_is_not_automatically_clocked_out(): void
    {
        config([
            'app.timezone' => 'UTC',
            'timepay.attendance_timezone' => 'Asia/Manila',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-11-06 09:03:00', 'UTC'));

        $company = Company::query()->create([
            'name' => 'Overtime Test Company',
            'pay_metric' => 'hourly',
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
            'working_days' => ['Friday'],
        ]);
        $employee = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_EMPLOYEE,
        ]);
        PayrollProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'salary_type' => 'hourly',
            'pay_frequency' => 'monthly',
            'overtime_eligible' => true,
        ]);
        AttendanceLog::query()->create([
            'user_id' => $employee->id,
            'company_id' => $company->id,
            'timestamp' => Carbon::parse('2026-11-06 01:00:00', 'UTC'),
            'type' => 'clock_in',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'distance_meters' => 10,
            'status' => 'verified',
            'is_suspicious' => false,
        ]);

        $this->artisan('attendance:auto-clock-out')->assertSuccessful();

        $this->assertDatabaseCount('attendance_logs', 1);
        $this->assertDatabaseMissing('attendance_logs', [
            'user_id' => $employee->id,
            'type' => 'clock_out',
            'source' => 'system',
        ]);
    }
}
