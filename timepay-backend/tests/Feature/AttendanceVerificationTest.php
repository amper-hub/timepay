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
use RuntimeException;
use Tests\TestCase;

class AttendanceVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->company = Company::query()->create([
            'name' => 'TimePay Test Company',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'geofence_radius_meters' => 150,
            'pay_metric' => 'hourly',
        ]);

        $this->employee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYEE,
        ]);

        Sanctum::actingAs($this->employee);
    }

    public function test_attendance_requires_all_server_checked_blink_samples(): void
    {
        $payload = [
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'liveness' => true,
        ];

        foreach (['/api/attendance/clock-in', '/api/attendance/check-in'] as $endpoint) {
            $this->post($endpoint, $payload, ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'liveness_open',
                    'liveness_closed',
                    'liveness_reopened',
                ]);
        }

        $this->assertDatabaseCount('attendance_logs', 0);
        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_failed_server_liveness_never_creates_an_attendance_log(): void
    {
        $faceService = Mockery::mock(FacePlusPlusService::class);
        $faceService->shouldReceive('verifyBlinkSequence')->once()->andReturn(false);
        $faceService->shouldNotReceive('compare');
        $this->app->instance(FacePlusPlusService::class, $faceService);

        $response = $this->post('/api/attendance/clock-in', $this->validPunchPayload(), [
            'Accept' => 'application/json',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error', 'face_liveness_failed');
        $this->assertDatabaseCount('attendance_logs', 0);
        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_face_identity_mismatch_is_flagged_and_never_marked_verified(): void
    {
        Storage::disk('private')->put('selfies/reference.jpg', 'baseline');
        $this->employee->forceFill(['baseline_photo_path' => 'selfies/reference.jpg'])->save();

        $faceService = Mockery::mock(FacePlusPlusService::class);
        $faceService->shouldReceive('verifyBlinkSequence')->once()->andReturn(true);
        $faceService->shouldReceive('compare')->twice()->andReturn(
            ['confidence' => 95.0, 'threshold' => 80.0, 'matched' => true],
            ['confidence' => 34.0, 'threshold' => 80.0, 'matched' => false]
        );
        $faceService->shouldReceive('analyzeSelfieProof')->never();
        $this->app->instance(FacePlusPlusService::class, $faceService);

        $response = $this->post('/api/attendance/clock-in', $this->validPunchPayload(), [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('attendance_log.status', 'flagged')
            ->assertJsonPath('face_verification.matched', false);
        $this->assertDatabaseCount('attendance_logs', 1);
        $this->assertDatabaseMissing('attendance_logs', [
            'user_id' => $this->employee->id,
            'status' => 'verified',
        ]);
    }

    public function test_provider_failure_does_not_create_a_successful_clock_in(): void
    {
        $faceService = Mockery::mock(FacePlusPlusService::class);
        $faceService->shouldReceive('verifyBlinkSequence')
            ->once()
            ->andThrow(new RuntimeException('Face++ detection is temporarily unavailable.'));
        $faceService->shouldNotReceive('compare');
        $this->app->instance(FacePlusPlusService::class, $faceService);

        $response = $this->post('/api/attendance/clock-in', $this->validPunchPayload(), [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('error', 'face_verification_unavailable');
        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_identity_provider_failure_deletes_the_uncommitted_private_selfie(): void
    {
        Storage::disk('private')->put('selfies/reference.jpg', 'baseline');
        $this->employee->forceFill(['baseline_photo_path' => 'selfies/reference.jpg'])->save();

        $faceService = Mockery::mock(FacePlusPlusService::class);
        $faceService->shouldReceive('verifyBlinkSequence')->once()->andReturn(true);
        $faceService->shouldReceive('compare')->twice()->andReturnUsing(
            static fn (): array => ['confidence' => 95.0, 'threshold' => 80.0, 'matched' => true],
            static fn () => throw new RuntimeException('Face++ comparison is temporarily unavailable.')
        );
        $this->app->instance(FacePlusPlusService::class, $faceService);

        $this->post('/api/attendance/clock-in', $this->validPunchPayload(), [
            'Accept' => 'application/json',
        ])->assertStatus(503);

        $this->assertDatabaseCount('attendance_logs', 0);
        $this->assertSame(['selfies/reference.jpg'], Storage::disk('private')->allFiles('selfies'));
    }

    public function test_attendance_status_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/attendance/status')->assertUnauthorized();
    }

    public function test_attendance_photos_are_available_only_to_authorized_company_users(): void
    {
        $otherEmployee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYEE,
        ]);
        $log = AttendanceLog::query()->create([
            'user_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'timestamp' => now(),
            'type' => 'clock_in',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'distance_meters' => 0,
            'photo_path' => 'selfies/attendance.jpg',
            'status' => 'verified',
            'is_suspicious' => false,
        ]);
        Storage::disk('private')->put($log->photo_path, 'private-photo');

        Sanctum::actingAs($otherEmployee);
        $this->get("/api/attendance/{$log->id}/photo")->assertForbidden();

        Sanctum::actingAs($this->employee);
        $this->get("/api/attendance/{$log->id}/photo")
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    /** @return array<string, mixed> */
    private function validPunchPayload(): array
    {
        return [
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'liveness_open' => UploadedFile::fake()->image('open.jpg'),
            'liveness_closed' => UploadedFile::fake()->image('closed.jpg'),
            'liveness_reopened' => UploadedFile::fake()->image('reopened.jpg'),
        ];
    }
}
