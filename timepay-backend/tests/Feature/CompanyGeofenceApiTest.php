<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyGeofenceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_gets_current_geofence_for_their_own_company(): void
    {
        $company = Company::query()->create([
            'name' => 'Employee Company',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'geofence_radius_meters' => 225,
            'pay_metric' => 'hourly',
        ]);
        $otherCompany = Company::query()->create([
            'name' => 'Other Company',
            'latitude' => 35.0,
            'longitude' => 139.0,
            'geofence_radius_meters' => 500,
            'pay_metric' => 'hourly',
        ]);
        $employee = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_EMPLOYEE,
        ]);

        Sanctum::actingAs($employee);

        // Simulate an employer changing saved settings after the employee logged in.
        $company->update([
            'latitude' => 14.6000,
            'longitude' => 120.9850,
            'geofence_radius_meters' => 300,
        ]);

        $this->getJson('/api/employee/company-geofence?company_id='.$otherCompany->id)
            ->assertOk()
            ->assertExactJson([
                'latitude' => '14.60000000',
                'longitude' => '120.98500000',
                'geofence_radius_meters' => 300,
            ]);
    }

    public function test_company_geofence_requires_authentication(): void
    {
        $this->getJson('/api/employee/company-geofence')->assertUnauthorized();
    }
}
