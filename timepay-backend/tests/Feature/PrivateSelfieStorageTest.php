<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\User;
use App\Services\FacePlusPlusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class PrivateSelfieStorageTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Storage::fake('public');

        $this->company = Company::query()->create([
            'name' => 'Private Storage Test Company',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'geofence_radius_meters' => 150,
            'pay_metric' => 'hourly',
        ]);

        $this->employee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYEE,
        ]);
    }

    public function test_private_disk_uses_the_persistent_storage_root(): void
    {
        $this->assertSame(storage_path('app/private'), config('filesystems.disks.private.root'));
    }

    public function test_first_face_enrollment_keeps_the_selfies_path_on_the_private_disk(): void
    {
        $faceService = Mockery::mock(FacePlusPlusService::class);
        $faceService->shouldReceive('verifyHeadTurnSequence')->once()->andReturn(true);
        $faceService->shouldReceive('compare')->once()->andReturn([
            'confidence' => 99.0,
            'threshold' => 80.0,
            'matched' => true,
        ]);
        $this->app->instance(FacePlusPlusService::class, $faceService);

        Sanctum::actingAs($this->employee);

        $response = $this->post('/api/attendance/clock-in', [
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'liveness_mode' => 'head_turn',
            'selfie' => UploadedFile::fake()->image('enrollment.jpg'),
            'liveness_open' => UploadedFile::fake()->image('open.jpg'),
            'liveness_closed' => UploadedFile::fake()->image('closed.jpg'),
            'liveness_reopened' => UploadedFile::fake()->image('reopened.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('face_verification.enrolled', true);

        $path = $this->employee->fresh()->baseline_photo_path;
        $this->assertIsString($path);
        $this->assertStringStartsWith('selfies/', $path);
        Storage::disk('private')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_selfie_retrieval_requires_ownership_or_same_company_authorization(): void
    {
        $path = 'selfies/attendance.jpg';
        $log = $this->attendanceLog($path);
        Storage::disk('private')->put($path, 'private-selfie-bytes');

        Sanctum::actingAs($this->employee);
        $this->get('/api/attendance/'.$log->id.'/photo')
            ->assertOk()
            ->assertStreamedContent('private-selfie-bytes')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $sameCompanyEmployer = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYER,
        ]);
        Sanctum::actingAs($sameCompanyEmployer);
        $this->get('/api/attendance/'.$log->id.'/photo')->assertOk();

        $otherCompany = Company::query()->create([
            'name' => 'Other Company',
            'pay_metric' => 'hourly',
        ]);
        $otherCompanyEmployer = User::factory()->create([
            'company_id' => $otherCompany->id,
            'role' => User::ROLE_EMPLOYER,
        ]);
        Sanctum::actingAs($otherCompanyEmployer);
        $this->get('/api/attendance/'.$log->id.'/photo')->assertForbidden();

        $otherEmployee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYEE,
        ]);
        Sanctum::actingAs($otherEmployee);
        $this->get('/api/attendance/'.$log->id.'/photo')->assertForbidden();
    }

    public function test_legacy_storage_prefixed_selfie_paths_resolve_inside_the_private_disk(): void
    {
        $path = 'selfies/legacy-attendance.jpg';
        $log = $this->attendanceLog('storage/'.$path);
        Storage::disk('private')->put($path, 'legacy-private-selfie');

        Sanctum::actingAs($this->employee);
        $this->get('/api/attendance/'.$log->id.'/photo')
            ->assertOk()
            ->assertStreamedContent('legacy-private-selfie');
    }

    public function test_missing_selfie_returns_not_found(): void
    {
        $log = $this->attendanceLog('selfies/missing.jpg');

        Sanctum::actingAs($this->employee);
        $this->get('/api/attendance/'.$log->id.'/photo')->assertNotFound();
    }

    private function attendanceLog(string $photoPath): AttendanceLog
    {
        return AttendanceLog::query()->create([
            'user_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'timestamp' => now(),
            'type' => 'clock_in',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'distance_meters' => 0,
            'photo_path' => $photoPath,
            'status' => 'verified',
            'is_suspicious' => false,
        ]);
    }
}
