<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class PayrollMigrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('PAYROLL_LEGACY_RATE_EFFECTIVE_DATES');
        unset($_ENV['PAYROLL_LEGACY_RATE_EFFECTIVE_DATES'], $_SERVER['PAYROLL_LEGACY_RATE_EFFECTIVE_DATES']);

        $this->company = Company::query()->create([
            'name' => 'Migration Safety Co',
            'pay_metric' => 'hourly',
            'currency' => 'PHP',
        ]);
    }

    public function test_legacy_employee_with_one_positive_rate_and_verified_effective_date_is_unambiguous(): void
    {
        $employee = $this->employee('42.00', '0.00');
        $this->setEffectiveDateMap([(string) $employee->id => '2024-01-15']);

        $this->runLegacyRatePreflight();
        $this->assertTrue(true);
    }

    public function test_legacy_employee_without_verified_effective_date_is_rejected(): void
    {
        $this->employee('42.00', '0.00');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing or invalid verified effective date');
        $this->runLegacyRatePreflight();
    }

    public function test_legacy_employee_with_invalid_effective_date_is_rejected(): void
    {
        $employee = $this->employee('42.00', '0.00');
        $this->setEffectiveDateMap([(string) $employee->id => '2026-02-30']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing or invalid verified effective date');
        $this->runLegacyRatePreflight();
    }

    public function test_legacy_employee_with_no_positive_rate_is_rejected(): void
    {
        $this->employee('0.00', '0.00');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('compensation basis/rate is missing or ambiguous');
        $this->runLegacyRatePreflight();
    }

    public function test_legacy_employee_with_both_positive_rates_is_rejected_as_ambiguous(): void
    {
        $this->employee('42.00', '900.00');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('compensation basis/rate is missing or ambiguous');
        $this->runLegacyRatePreflight();
    }

    private function setEffectiveDateMap(array $dates): void
    {
        $encoded = json_encode($dates, JSON_THROW_ON_ERROR);
        putenv('PAYROLL_LEGACY_RATE_EFFECTIVE_DATES='.$encoded);
        $_ENV['PAYROLL_LEGACY_RATE_EFFECTIVE_DATES'] = $encoded;
        $_SERVER['PAYROLL_LEGACY_RATE_EFFECTIVE_DATES'] = $encoded;
    }

    private function employee(string $hourlyRate, string $dailyRate): User
    {
        return User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYEE,
            'hourly_rate' => $hourlyRate,
            'daily_rate' => $dailyRate,
        ]);
    }

    private function runLegacyRatePreflight(): void
    {
        $migrationPath = database_path('migrations/2026_10_16_000000_create_payroll_foundation_tables.php');
        $migration = require $migrationPath;
        $method = new ReflectionMethod($migration, 'assertLegacyCompensationIsUnambiguous');
        $method->setAccessible(true);
        $method->invoke($migration);
    }
}
