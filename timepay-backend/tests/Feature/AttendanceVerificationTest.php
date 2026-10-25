<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\User;
use App\Services\FacePlusPlusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Filesystem\FilesystemAdapter;
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

    public function test_attendance_requires_all_server_checked_head_turn_samples(): void
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

    public function test_repeating_an_event_id_returns_the_original_log_without_creating_a_duplicate(): void
    {
        Storage::disk('private')->put('selfies/reference.jpg', 'baseline');
        $this->employee->forceFill(['baseline_photo_path' => 'selfies/reference.jpg'])->save();
        $faceService = Mockery::mock(FacePlusPlusService::class);
        $faceService->shouldReceive('verifyBlinkSequence')->once()->andReturn(true);
        $faceService->shouldReceive('compare')->twice()->andReturn([
            'confidence' => 95.0, 'threshold' => 80.0, 'matched' => true,
        ]);
        $faceService->shouldReceive('analyzeSelfieProof')->once()->andReturn([
            'anomaly' => false, 'reason' => null, 'signals' => [],
        ]);
        $this->app->instance(FacePlusPlusService::class, $faceService);
        $eventId = '10000000-0000-4000-8000-000000000001';

        $first = $this->post('/api/attendance/clock-in', [
            ...$this->validPunchPayload(),
            'liveness_mode' => 'blink',
            'event_id' => $eventId,
            'captured_at' => now()->subMinute()->toISOString(),
            'occurred_at' => now()->subMinute()->toISOString(),
        ], ['Accept' => 'application/json'])->assertOk();

        $second = $this->post('/api/attendance/clock-in', [
            ...$this->validPunchPayload(),
            'liveness_mode' => 'blink',
            'event_id' => $eventId,
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('idempotent_replay', true);

        $this->assertSame(
            $first->json('attendance_log.id'),
            $second->json('attendance_log.id')
        );
        $this->assertDatabaseCount('attendance_logs', 1);
        $this->assertDatabaseHas('attendance_logs', ['event_id' => $eventId]);
        $this->assertDatabaseCount('attendance_event_responses', 1);
    }

    public function test_event_id_cannot_be_replayed_by_another_employee(): void
    {
        $other = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYEE,
        ]);
        \App\Models\AttendanceEventResponse::query()->create([
            'user_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'event_id' => '10000000-0000-4000-8000-000000000002',
            'response' => ['success' => true, 'attendance_log' => ['id' => 5]],
        ]);
        Sanctum::actingAs($other);

        $this->post('/api/attendance/clock-in', [
            ...$this->validPunchPayload(),
            'event_id' => '10000000-0000-4000-8000-000000000002',
        ], ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonPath('error', 'attendance_event_conflict');

        $this->assertDatabaseCount('attendance_logs', 0);
        $this->assertDatabaseCount('attendance_event_responses', 1);
    }

    public function test_failed_server_liveness_never_creates_an_attendance_log(): void
    {
        $faceService = Mockery::mock(FacePlusPlusService::class);
        $faceService->shouldReceive('verifyHeadTurnSequence')->once()->andReturn(false);
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

    public function test_blink_mode_uses_server_checked_eye_states_and_keeps_identity_checks(): void
    {
        Storage::disk('private')->put('selfies/reference.jpg', 'baseline');
        $this->employee->forceFill(['baseline_photo_path' => 'selfies/reference.jpg'])->save();

        $faceService = Mockery::mock(FacePlusPlusService::class);
        $faceService->shouldReceive('verifyBlinkSequence')->once()->andReturn(true);
        $faceService->shouldReceive('verifyHeadTurnSequence')->never();
        $faceService->shouldReceive('compare')->twice()->andReturn([
            'confidence' => 95.0, 'threshold' => 80.0, 'matched' => true,
        ]);
        $faceService->shouldReceive('analyzeSelfieProof')->once()->andReturn([
            'anomaly' => false, 'reason' => null, 'signals' => [],
        ]);
        $this->app->instance(FacePlusPlusService::class, $faceService);

        $response = $this->post('/api/attendance/clock-in', [
            ...$this->validPunchPayload(),
            'liveness_mode' => 'blink',
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('attendance_log.status', 'verified')
            ->assertJsonPath('face_verification.matched', true);
        $this->assertDatabaseCount('attendance_logs', 1);
    }

    public function test_first_time_enrollment_is_idempotent_and_does_not_record_attendance(): void
    {
        $faceService = Mockery::mock(FacePlusPlusService::class);
        $faceService->shouldReceive('verifyBlinkSequence')->once()->andReturn(true);
        $faceService->shouldReceive('compare')->once()->andReturn([
            'confidence' => 95.0, 'threshold' => 80.0, 'matched' => true,
        ]);
        $faceService->shouldNotReceive('analyzeSelfieProof');
        $this->app->instance(FacePlusPlusService::class, $faceService);
        $eventId = '10000000-0000-4000-8000-000000000003';
        $payload = [
            ...$this->validPunchPayload(),
            'liveness_mode' => 'blink',
            'event_id' => $eventId,
        ];

        $first = $this->post('/api/attendance/clock-in', $payload, [
            'Accept' => 'application/json',
        ])->assertOk()
            ->assertJsonPath('face_verification.enrolled', true)
            ->assertJsonPath('attendance_log', null);

        $replay = $this->post('/api/attendance/clock-in', $payload, [
            'Accept' => 'application/json',
        ])->assertOk()
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('face_verification.enrolled', true)
            ->assertJsonPath('attendance_log', null);

        $this->assertSame($first->json('message'), $replay->json('message'));
        $this->assertNotNull($this->employee->fresh()->baseline_photo_path);
        $this->assertDatabaseCount('attendance_logs', 0);
        $this->assertDatabaseCount('attendance_event_responses', 1);
    }

    public function test_reset_invalidates_stale_enrollment_replay_and_next_punch_uses_new_baseline(): void
    {
        $comparePaths = [];
        $faceService = Mockery::mock(FacePlusPlusService::class);
        $faceService->shouldReceive('verifyBlinkSequence')->times(3)->andReturn(true);
        $faceService->shouldReceive('compare')->times(4)->andReturnUsing(
            static function (string $referencePath, string $selfiePath) use (&$comparePaths): array {
                $comparePaths[] = $referencePath;

                return ['confidence' => 95.0, 'threshold' => 80.0, 'matched' => true];
            }
        );
        $faceService->shouldReceive('analyzeSelfieProof')->once()->andReturn([
            'anomaly' => false, 'reason' => null, 'signals' => [],
        ]);
        $this->app->instance(FacePlusPlusService::class, $faceService);

        $eventId = '10000000-0000-4000-8000-000000000010';
        $this->post('/api/attendance/clock-in', [
            ...$this->validPunchPayload(),
            'liveness_mode' => 'blink',
            'event_id' => $eventId,
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('face_verification.enrolled', true)
            ->assertJsonPath('attendance_log', null);
        $firstEnrolledPath = $this->employee->fresh()->baseline_photo_path;
        $this->assertNotNull($firstEnrolledPath);

        // Simulate an enrollment response that the client did not receive: its event ID remains pending.
        $this->postJson('/api/profile/reset-face')->assertOk();
        $this->assertDatabaseMissing('attendance_event_responses', ['event_id' => $eventId]);
        $this->assertNull($this->employee->fresh()->baseline_photo_path);
        Storage::disk('private')->assertMissing($firstEnrolledPath);

        $this->post('/api/attendance/clock-in', [
            ...$this->validPunchPayload(),
            'liveness_mode' => 'blink',
            'event_id' => $eventId,
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('face_verification.enrolled', true)
            ->assertJsonPath('attendance_log', null)
            ->assertJsonMissingPath('idempotent_replay');
        $newBaselinePath = $this->employee->fresh()->baseline_photo_path;
        $this->assertNotNull($newBaselinePath);
        $this->assertNotSame($firstEnrolledPath, $newBaselinePath);
        Storage::disk('private')->assertExists($newBaselinePath);

        $punch = $this->post('/api/attendance/clock-in', [
            ...$this->validPunchPayload(),
            'liveness_mode' => 'blink',
            'event_id' => '10000000-0000-4000-8000-000000000011',
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('attendance_log.status', 'verified')
            ->assertJsonPath('face_verification.matched', true);

        $this->assertSame(
            Storage::disk('private')->path($newBaselinePath),
            $comparePaths[3]
        );
        $this->assertSame($newBaselinePath, $this->employee->fresh()->baseline_photo_path);
        $this->assertDatabaseCount('attendance_logs', 1);
        $this->assertDatabaseCount('attendance_event_responses', 2);
        $this->assertNotNull($punch->json('attendance_log.id'));
    }

    public function test_failed_enrollment_does_not_persist_or_report_a_baseline(): void
    {
        $faceService = Mockery::mock(FacePlusPlusService::class);
        $faceService->shouldReceive('verifyBlinkSequence')->once()->andReturn(true);
        $faceService->shouldReceive('compare')->once()->andReturn([
            'confidence' => 20.0, 'threshold' => 80.0, 'matched' => false,
        ]);
        $this->app->instance(FacePlusPlusService::class, $faceService);

        $this->post('/api/attendance/clock-in', [
            ...$this->validPunchPayload(),
            'liveness_mode' => 'blink',
        ], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonPath('error', 'face_capture_mismatch')
            ->assertJsonMissingPath('face_verification.enrolled');

        $this->assertNull($this->employee->fresh()->baseline_photo_path);
        $this->assertDatabaseCount('attendance_logs', 0);
        $this->assertSame([], Storage::disk('private')->allFiles('selfies'));
    }

    public function test_private_photo_storage_failure_does_not_claim_enrollment(): void
    {
        $faceService = Mockery::mock(FacePlusPlusService::class);
        $faceService->shouldReceive('verifyBlinkSequence')->once()->andReturn(true);
        $faceService->shouldReceive('compare')->once()->andReturn([
            'confidence' => 95.0, 'threshold' => 80.0, 'matched' => true,
        ]);
        $this->app->instance(FacePlusPlusService::class, $faceService);

        $privateDisk = Mockery::mock(FilesystemAdapter::class);
        $privateDisk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->twice()->with('private')->andReturn($privateDisk);

        $this->post('/api/attendance/clock-in', [
            ...$this->validPunchPayload(),
            'liveness_mode' => 'blink',
        ], ['Accept' => 'application/json'])->assertStatus(500)
            ->assertJsonPath('error', 'selfie_storage_failed')
            ->assertJsonMissingPath('face_verification.enrolled');

        $this->assertNull($this->employee->fresh()->baseline_photo_path);
        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_ordinary_clock_in_does_not_overwrite_an_existing_valid_baseline(): void
    {
        $baselinePath = 'selfies/established-reference.jpg';
        Storage::disk('private')->put($baselinePath, 'established-baseline');
        $this->employee->forceFill(['baseline_photo_path' => $baselinePath])->save();

        $faceService = Mockery::mock(FacePlusPlusService::class);
        $faceService->shouldReceive('verifyBlinkSequence')->once()->andReturn(true);
        $faceService->shouldReceive('compare')->twice()->andReturn([
            'confidence' => 95.0, 'threshold' => 80.0, 'matched' => true,
        ]);
        $faceService->shouldReceive('analyzeSelfieProof')->once()->andReturn([
            'anomaly' => false, 'reason' => null, 'signals' => [],
        ]);
        $this->app->instance(FacePlusPlusService::class, $faceService);

        $this->post('/api/attendance/clock-in', [
            ...$this->validPunchPayload(),
            'liveness_mode' => 'blink',
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('attendance_log.status', 'verified')
            ->assertJsonPath('face_verification.matched', true);

        $this->assertSame($baselinePath, $this->employee->fresh()->baseline_photo_path);
        Storage::disk('private')->assertExists($baselinePath);
        $this->assertDatabaseCount('attendance_logs', 1);
    }

    public function test_blink_mode_rejection_never_creates_an_attendance_log(): void
    {
        $faceService = Mockery::mock(FacePlusPlusService::class);
        $faceService->shouldReceive('verifyBlinkSequence')->once()->andReturn(false);
        $faceService->shouldNotReceive('compare');
        $this->app->instance(FacePlusPlusService::class, $faceService);

        $this->post('/api/attendance/clock-in', [
            ...$this->validPunchPayload(),
            'liveness_mode' => 'blink',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'face_liveness_failed');

        $this->assertDatabaseCount('attendance_logs', 0);
        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_unknown_liveness_mode_is_rejected(): void
    {
        $this->post('/api/attendance/clock-in', [
            ...$this->validPunchPayload(),
            'liveness_mode' => 'client_verified',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['liveness_mode']);

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_face_identity_mismatch_is_flagged_and_never_marked_verified(): void
    {
        Storage::disk('private')->put('selfies/reference.jpg', 'baseline');
        $this->employee->forceFill(['baseline_photo_path' => 'selfies/reference.jpg'])->save();

        $faceService = Mockery::mock(FacePlusPlusService::class);
        $faceService->shouldReceive('verifyHeadTurnSequence')->once()->andReturn(true);
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
        $faceService->shouldReceive('verifyHeadTurnSequence')
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
        $faceService->shouldReceive('verifyHeadTurnSequence')->once()->andReturn(true);
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

    public function test_attendance_history_preserves_flagged_and_rejected_verification_states(): void
    {
        $log = AttendanceLog::query()->create([
            'user_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'timestamp' => now()->setTime(8, 45),
            'type' => 'clock_in',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'distance_meters' => 0,
            'status' => 'flagged',
            'is_suspicious' => true,
            'suspicion_reason' => 'Face++ Identity Mismatch',
        ]);

        $this->getJson('/api/attendance/history')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'on_time')
            ->assertJsonPath('data.0.verification_status', 'flagged');

        $log->update(['status' => 'rejected']);

        $this->getJson('/api/attendance/history')
            ->assertOk()
            ->assertJsonPath('data.0.verification_status', 'rejected');
    }

    /** @return array<string, mixed> */
    private function validPunchPayload(): array
    {
        return [
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'liveness_mode' => 'head_turn',
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'liveness_open' => UploadedFile::fake()->image('open.jpg'),
            'liveness_closed' => UploadedFile::fake()->image('closed.jpg'),
            'liveness_reopened' => UploadedFile::fake()->image('reopened.jpg'),
        ];
    }
}
