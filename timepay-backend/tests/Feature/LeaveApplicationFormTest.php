<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveApplicationFormTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $employee;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Application Form Company',
            'pay_metric' => 'hourly',
            'currency' => 'PHP',
        ]);
        $this->employee = User::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Leave Form Employee',
            'role' => User::ROLE_EMPLOYEE,
        ]);
        $this->manager = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYER,
        ]);
    }

    public function test_employee_web_page_and_form_use_saved_request_data(): void
    {
        $leave = $this->makeLeave();

        $this->actingAs($this->employee)
            ->get(route('employee.leaves.index'))
            ->assertOk()
            ->assertSee('My leave applications')
            ->assertSee('View application form');

        $this->get(route('leave-applications.show', $leave))
            ->assertOk()
            ->assertSee('EMPLOYEE LEAVE APPLICATION FORM')
            ->assertSee('Leave Form Employee')
            ->assertSee('Vacation')
            ->assertSee('Personal family matter.')
            ->assertSee('Pending')
            ->assertSee('Manager’s Remarks')
            ->assertSee('Applicant’s Signature')
            ->assertSee('Manager / Authorized Signature');
    }

    public function test_employee_can_download_a4_pdf_with_a_safe_filename(): void
    {
        $leave = $this->makeLeave();

        $response = $this->actingAs($this->employee)
            ->get(route('leave-applications.download', $leave));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=Leave-Application-'.$leave->id.'.pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_employee_cannot_open_another_employees_form(): void
    {
        $otherEmployee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYEE,
        ]);
        $leave = $this->makeLeave($otherEmployee);

        $this->actingAs($this->employee)
            ->get(route('leave-applications.show', $leave))
            ->assertNotFound();
    }

    public function test_manager_can_open_company_form_but_not_another_company_form(): void
    {
        $leave = $this->makeLeave();

        $this->actingAs($this->manager)
            ->get(route('leave-applications.show', $leave))
            ->assertOk()
            ->assertSee('Manager remarks saved for review.');

        $otherCompany = Company::query()->create([
            'name' => 'Other Form Company',
            'pay_metric' => 'hourly',
            'currency' => 'PHP',
        ]);
        $otherEmployee = User::factory()->create([
            'company_id' => $otherCompany->id,
            'role' => User::ROLE_EMPLOYEE,
        ]);
        $otherLeave = $this->makeLeave($otherEmployee, $otherCompany);

        $this->get(route('leave-applications.show', $otherLeave))->assertNotFound();
    }

    public function test_current_pending_approved_and_rejected_statuses_are_rendered(): void
    {
        foreach (['pending', 'approved', 'rejected'] as $status) {
            $leave = $this->makeLeave(status: $status);

            $this->actingAs($this->employee)
                ->get(route('leave-applications.show', $leave))
                ->assertOk()
                ->assertSee(ucfirst($status));
        }
    }

    public function test_mobile_pdf_endpoint_uses_sanctum_and_checks_ownership(): void
    {
        $leave = $this->makeLeave();
        Sanctum::actingAs($this->employee);

        $this->get('/api/employee/leaves/'.$leave->id.'/application.pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=Leave-Application-'.$leave->id.'.pdf');

        $otherEmployee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYEE,
        ]);
        $otherLeave = $this->makeLeave($otherEmployee);
        Sanctum::actingAs($this->employee);

        $this->get('/api/employee/leaves/'.$otherLeave->id.'/application.pdf')->assertNotFound();
    }

    public function test_nonexistent_and_unauthenticated_requests_are_rejected(): void
    {
        $this->get('/leave-applications/999999')->assertRedirect(route('login'));

        $this->actingAs($this->employee)
            ->get('/leave-applications/999999')
            ->assertNotFound();
    }

    private function makeLeave(?User $employee = null, ?Company $company = null, string $status = 'pending'): LeaveRequest
    {
        $owner = $employee ?? $this->employee;
        $ownerCompany = $company ?? $owner->company;

        return LeaveRequest::query()->create([
            'company_id' => $ownerCompany->id,
            'user_id' => $owner->id,
            'leave_type' => 'Vacation',
            'start_date' => '2026-12-03',
            'end_date' => '2026-12-04',
            'reason' => 'Personal family matter.',
            'status' => $status,
            'admin_notes' => 'Manager remarks saved for review.',
        ]);
    }
}
