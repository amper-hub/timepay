<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceLog;
use App\Services\FacePlusPlusService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    /**
     * Earth's radius in meters for Haversine formula.
     */
    private const EARTH_RADIUS_METERS = 6371000;

    /**
     * Maximum allowed distance from the company job site for attendance punches.
     */
    private const GEOFENCE_RADIUS_METERS = 100;

    public function __construct(
        private readonly FacePlusPlusService $facePlusPlusService
    ) {
    }

    /**
     * Handle clock-in request with geofencing verification.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $user = $request->user();
        $user->load('company');

        $company = $user->company;

        // Calculate distance using Haversine formula
        $distance = $this->calculateDistance(
            $validated['latitude'],
            $validated['longitude'],
            (float) $company->latitude,
            (float) $company->longitude
        );

        $distanceInMeters = (int) round($distance);

        // Check if user is within the geofence
        if ($distanceInMeters <= self::GEOFENCE_RADIUS_METERS) {
            // Create attendance record for today
            $today = Carbon::today();

            $attendanceRecord = AttendanceRecord::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'company_id' => $user->company_id,
                    'date' => $today,
                ],
                [
                    'time_in' => now()->toTimeString(),
                    'latitude_in' => $validated['latitude'],
                    'longitude_in' => $validated['longitude'],
                    'status' => 'present',
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Check-in successful.',
                'attendance_record' => [
                    'id' => $attendanceRecord->id,
                    'user_id' => $attendanceRecord->user_id,
                    'date' => $attendanceRecord->date,
                    'time_in' => $attendanceRecord->time_in,
                    'latitude_in' => $attendanceRecord->latitude_in,
                    'longitude_in' => $attendanceRecord->longitude_in,
                    'status' => $attendanceRecord->status,
                ],
                'distance_meters' => $distanceInMeters,
                'geofence_radius_meters' => $company->geofence_radius_meters,
            ], 200);
        }

        // User is outside the geofence
        $distanceOutside = $distanceInMeters - $company->geofence_radius_meters;

        return response()->json([
            'success' => false,
            'message' => 'Check-in failed. You are outside the office geofence.',
            'distance_from_office_meters' => $distanceOutside,
            'your_coordinates' => [
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
            ],
            'office_coordinates' => [
                'latitude' => $company->latitude,
                'longitude' => $company->longitude,
            ],
            'geofence_radius_meters' => $company->geofence_radius_meters,
        ], 403);
    }

    /**
     * Handle attendance punch request with biometric face capture and GPS geofencing.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function punch(Request $request): JsonResponse
    {
        return $this->store($request);
    }

    /**
     * Store an attendance punch with state validation, geofencing, and Face++ comparison.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:clock_in,clock_out',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'selfie' => 'required_without:photo|image|mimes:jpeg,jpg,png|max:5120',
            'photo' => 'required_without:selfie|image|mimes:jpeg,jpg,png|max:5120',
        ]);

        $user = $request->user();
        $user->load('company');
        $company = $user->company;

        if ($company->latitude !== null && $company->longitude !== null) {
            $distanceInMeters = (int) round($this->calculateDistance(
                $validated['latitude'],
                $validated['longitude'],
                (float) $company->latitude,
                (float) $company->longitude
            ));

            if ($distanceInMeters > self::GEOFENCE_RADIUS_METERS) {
                return response()->json([
                    'success' => false,
                    'message' => "Punch rejected. You are outside the designated job site boundary (Distance: {$distanceInMeters} meters away).",
                ], 422);
            }
        }

        $latestPunchToday = AttendanceLog::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('timestamp', today())
            ->latest('timestamp')
            ->first();

        $expectedType = $latestPunchToday?->type === 'clock_in' ? 'clock_out' : 'clock_in';

        if ($validated['type'] !== $expectedType) {
            throw ValidationException::withMessages([
                'type' => [
                    $expectedType === 'clock_out'
                        ? 'You are already clocked in. Please clock out before clocking in again.'
                        : 'You are already clocked out. Please clock in before clocking out.',
                ],
            ]);
        }

        $distanceInMeters = $company->latitude !== null && $company->longitude !== null
            ? (float) round($this->calculateDistance(
                $validated['latitude'],
                $validated['longitude'],
                (float) $company->latitude,
                (float) $company->longitude
            ), 2)
            : null;
        $isWithinGeofence = $distanceInMeters === null || $distanceInMeters <= self::GEOFENCE_RADIUS_METERS;

        $file = $request->file('selfie') ?? $request->file('photo');
        $filename = 'selfie_' . $user->id . '_' . now()->format('YmdHis') . '_' . str()->random(8) . '.' . $file->extension();
        $photoPath = $file->storeAs('selfies', $filename, 'public');
        $selfieAbsolutePath = Storage::disk('public')->path($photoPath);

        $isFirstTimeEnrollment = $user->baseline_photo_path === null;

        if ($isFirstTimeEnrollment) {
            $user->baseline_photo_path = $photoPath;
            $user->save();

            $status = 'verified';
            $faceMatched = null;
        } else {
            $baselineAbsolutePath = $this->resolveBaselinePhotoPath($user->baseline_photo_path);
            $faceMatched = $this->facePlusPlusService->compare($baselineAbsolutePath, $selfieAbsolutePath);
            $status = $faceMatched ? 'verified' : 'flagged';
        }

        $attendanceLog = AttendanceLog::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'timestamp' => now(),
            'type' => $validated['type'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'distance_meters' => $distanceInMeters,
            'photo_path' => $photoPath,
            'status' => $status,
        ]);

        if ($isFirstTimeEnrollment) {
            return response()->json([
                'success' => true,
                'message' => 'First-time facial enrollment successful! Clock-in recorded.',
                'attendance_log' => [
                    'id' => $attendanceLog->id,
                    'user_id' => $attendanceLog->user_id,
                    'timestamp' => $attendanceLog->timestamp,
                    'type' => $attendanceLog->type,
                    'status' => $attendanceLog->status,
                    'distance_meters' => $attendanceLog->distance_meters,
                    'photo_path' => asset('storage/' . $attendanceLog->photo_path),
                ],
                'face_verification' => [
                    'enrolled' => true,
                    'baseline_photo_configured' => true,
                    'matched' => null,
                ],
                'geofence_info' => [
                    'within_geofence' => $isWithinGeofence,
                    'user_coordinates' => [
                        'latitude' => $validated['latitude'],
                        'longitude' => $validated['longitude'],
                    ],
                    'office_coordinates' => [
                        'latitude' => $company->latitude,
                        'longitude' => $company->longitude,
                    ],
                    'geofence_radius_meters' => self::GEOFENCE_RADIUS_METERS,
                    'distance_from_office_meters' => $distanceInMeters,
                ],
                'current_state' => $attendanceLog->type === 'clock_in' ? 'clocked_in' : 'clocked_out',
                'next_expected_punch' => $attendanceLog->type === 'clock_in' ? 'clock_out' : 'clock_in',
            ], 200);
        }

        $responseMessage = $status === 'verified'
            ? ucfirst(str_replace('_', ' ', $validated['type'])) . ' successful. Face match and geofence checks passed.'
            : ucfirst(str_replace('_', ' ', $validated['type'])) . ' recorded as flagged. Face verification did not meet the confidence threshold.';

        return response()->json([
            'success' => $status === 'verified',
            'message' => $responseMessage,
            'attendance_log' => [
                'id' => $attendanceLog->id,
                'user_id' => $attendanceLog->user_id,
                'timestamp' => $attendanceLog->timestamp,
                'type' => $attendanceLog->type,
                'status' => $attendanceLog->status,
                'distance_meters' => $attendanceLog->distance_meters,
                'photo_path' => $attendanceLog->photo_path ? asset('storage/' . $attendanceLog->photo_path) : null,
            ],
            'face_verification' => [
                'enrolled' => false,
                'baseline_photo_configured' => true,
                'matched' => $faceMatched,
            ],
            'geofence_info' => [
                'within_geofence' => $isWithinGeofence,
                'user_coordinates' => [
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                ],
                'office_coordinates' => [
                    'latitude' => $company->latitude,
                    'longitude' => $company->longitude,
                ],
                'geofence_radius_meters' => self::GEOFENCE_RADIUS_METERS,
                'distance_from_office_meters' => $distanceInMeters,
            ],
            'current_state' => $attendanceLog->type === 'clock_in' ? 'clocked_in' : 'clocked_out',
            'next_expected_punch' => $attendanceLog->type === 'clock_in' ? 'clock_out' : 'clock_in',
        ], 200);
    }

    /**
     * Return the employee's current attendance state for today.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        $latestPunchToday = AttendanceLog::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('timestamp', today())
            ->latest('timestamp')
            ->first();

        $currentState = $latestPunchToday?->type === 'clock_in'
            ? 'clocked_in'
            : 'clocked_out';

        return response()->json([
            'current_state' => $currentState,
            'last_punch' => $latestPunchToday ? [
                'id' => $latestPunchToday->id,
                'type' => $latestPunchToday->type,
                'status' => $latestPunchToday->status,
                'timestamp' => $latestPunchToday->timestamp,
            ] : null,
            'next_expected_punch' => $currentState === 'clocked_in' ? 'clock_out' : 'clock_in',
        ]);
    }

    /**
     * Calculate the great-circle distance between two coordinates using the Haversine formula.
     *
     * @param float $lat1 Latitude of first point (degrees)
     * @param float $lon1 Longitude of first point (degrees)
     * @param float $lat2 Latitude of second point (degrees)
     * @param float $lon2 Longitude of second point (degrees)
     * @return float Distance in meters
     */
    private function calculateDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);

        $dlat = $lat2Rad - $lat1Rad;
        $dlon = $lon2Rad - $lon1Rad;

        $a = sin($dlat / 2) * sin($dlat / 2) +
            cos($lat1Rad) * cos($lat2Rad) * sin($dlon / 2) * sin($dlon / 2);

        $c = 2 * asin(sqrt($a));

        return self::EARTH_RADIUS_METERS * $c;
    }

    /**
     * Resolve a stored baseline photo path to an absolute local path.
     */
    private function resolveBaselinePhotoPath(?string $baselinePhotoPath): ?string
    {
        if (! $baselinePhotoPath) {
            return null;
        }

        if (is_file($baselinePhotoPath)) {
            return $baselinePhotoPath;
        }

        $normalizedPath = str($baselinePhotoPath)
            ->replace('\\', '/')
            ->replaceStart('storage/', '')
            ->toString();

        if (Storage::disk('public')->exists($normalizedPath)) {
            return Storage::disk('public')->path($normalizedPath);
        }

        return null;
    }
}
