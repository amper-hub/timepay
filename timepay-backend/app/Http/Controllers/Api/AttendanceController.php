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

    /**
     * Employees may clock in shortly before the configured shift start.
     */
    private const CLOCK_IN_EARLY_BUFFER_MINUTES = 15;

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

        if ($scheduleError = $this->validateClockInSchedule($company)) {
            return $scheduleError;
        }

        $officeLatitude = $this->companyGeofenceLatitude($company);
        $officeLongitude = $this->companyGeofenceLongitude($company);
        $geofenceRadius = $this->companyGeofenceRadius($company);

        if ($officeLatitude === null || $officeLongitude === null) {
            return response()->json([
                'success' => false,
                'message' => 'Check-in failed. Company geofence coordinates are not configured.',
            ], 422);
        }

        // Calculate distance using Haversine formula
        $distance = $this->calculateDistance(
            $validated['latitude'],
            $validated['longitude'],
            $officeLatitude,
            $officeLongitude
        );

        $distanceInMeters = (int) round($distance);

        // Check if user is within the geofence
        if ($distanceInMeters <= $geofenceRadius) {
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
                'geofence_radius_meters' => $geofenceRadius,
            ], 200);
        }

        // User is outside the geofence
        $distanceOutside = $distanceInMeters - $geofenceRadius;

        return response()->json([
            'success' => false,
            'message' => 'Check-in failed. You are outside the office geofence.',
            'distance_from_office_meters' => $distanceOutside,
            'your_coordinates' => [
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
            ],
            'office_coordinates' => [
                'latitude' => $officeLatitude,
                'longitude' => $officeLongitude,
            ],
            'geofence_radius_meters' => $geofenceRadius,
        ], 403);
    }

    /**
     * Handle explicit clock-in route after biometric verification.
     */
    public function clockIn(Request $request): JsonResponse
    {
        $request->merge([
            'type' => 'clock_in',
        ]);

        return $this->store($request);
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

        if ($validated['type'] === 'clock_in') {
            if ($scheduleError = $this->validateClockInSchedule($company)) {
                return $scheduleError;
            }
        }

        $officeLatitude = $this->companyGeofenceLatitude($company);
        $officeLongitude = $this->companyGeofenceLongitude($company);
        $geofenceRadius = $this->companyGeofenceRadius($company);

        if ($officeLatitude !== null && $officeLongitude !== null) {
            $distanceInMeters = (int) round($this->calculateDistance(
                $validated['latitude'],
                $validated['longitude'],
                $officeLatitude,
                $officeLongitude
            ));

            if ($distanceInMeters > $geofenceRadius) {
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

        $distanceInMeters = $officeLatitude !== null && $officeLongitude !== null
            ? (float) round($this->calculateDistance(
                $validated['latitude'],
                $validated['longitude'],
                $officeLatitude,
                $officeLongitude
            ), 2)
            : null;
        $isWithinGeofence = $distanceInMeters === null || $distanceInMeters <= $geofenceRadius;

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
                        'latitude' => $officeLatitude,
                        'longitude' => $officeLongitude,
                    ],
                    'geofence_radius_meters' => $geofenceRadius,
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
                    'latitude' => $officeLatitude,
                    'longitude' => $officeLongitude,
                ],
                'geofence_radius_meters' => $geofenceRadius,
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
     * Return grouped attendance history for the authenticated employee.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        $logs = AttendanceLog::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->latest('timestamp')
            ->limit(120)
            ->get()
            ->groupBy(fn (AttendanceLog $log) => $log->timestamp->toDateString());

        $history = $logs->map(function ($dayLogs, string $date) {
            $clockIn = $dayLogs
                ->where('type', 'clock_in')
                ->sortBy('timestamp')
                ->first();

            $clockOut = $dayLogs
                ->where('type', 'clock_out')
                ->sortByDesc('timestamp')
                ->first();

            $totalHours = $clockIn && $clockOut
                ? round($clockIn->timestamp->floatDiffInHours($clockOut->timestamp), 2)
                : null;

            $lateThreshold = Carbon::parse($date)->setTime(9, 0);
            $status = $clockIn && $clockIn->timestamp->greaterThan($lateThreshold)
                ? 'late'
                : 'on_time';

            return [
                'date' => $date,
                'time_in' => $clockIn?->timestamp?->toIso8601String(),
                'time_out' => $clockOut?->timestamp?->toIso8601String(),
                'total_hours' => $totalHours,
                'status' => $status,
            ];
        })->values();

        return response()->json([
            'data' => $history,
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
     * Enforce employer-configured clock-in days and hours.
     */
    private function validateClockInSchedule($company): ?JsonResponse
    {
        $timezone = config('timepay.attendance_timezone', config('app.timezone', 'UTC'));
        $now = Carbon::now($timezone);
        $workingDays = $company->working_days ?? [];

        if (is_string($workingDays)) {
            $workingDays = json_decode($workingDays, true) ?: [];
        }

        $workingDays = array_map(
            fn (string $day): string => strtolower($day),
            array_filter($workingDays)
        );

        if ($workingDays !== [] && ! in_array(strtolower($now->format('l')), $workingDays, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot clock in on a closed day',
            ], 403);
        }

        if (! $company->work_start_time || ! $company->work_end_time) {
            return null;
        }

        $workStartTime = Carbon::parse($company->work_start_time, $timezone)->format('H:i:s');
        $workEndTime = Carbon::parse($company->work_end_time, $timezone)->format('H:i:s');

        $shiftStart = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $now->toDateString() . ' ' . $workStartTime,
            $timezone
        )
            ->subMinutes(self::CLOCK_IN_EARLY_BUFFER_MINUTES);

        $shiftEnd = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $now->toDateString() . ' ' . $workEndTime,
            $timezone
        );

        if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
            $shiftEnd->addDay();

            $endToday = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $now->toDateString() . ' ' . $workEndTime,
                $timezone
            );

            if ($now->lessThanOrEqualTo($endToday)) {
                $shiftStart->subDay();
                $shiftEnd->subDay();
            }
        }

        if ($now->lt($shiftStart) || $now->gt($shiftEnd)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot clock in outside working hours',
            ], 403);
        }

        return null;
    }

    /**
     * Prefer the strict geofence settings, with legacy coordinate fallback.
     */
    private function companyGeofenceLatitude($company): ?float
    {
        $latitude = $company->geofence_latitude ?? $company->latitude;

        return $latitude === null ? null : (float) $latitude;
    }

    /**
     * Prefer the strict geofence settings, with legacy coordinate fallback.
     */
    private function companyGeofenceLongitude($company): ?float
    {
        $longitude = $company->geofence_longitude ?? $company->longitude;

        return $longitude === null ? null : (float) $longitude;
    }

    /**
     * Prefer the strict geofence radius, with legacy and default fallback.
     */
    private function companyGeofenceRadius($company): int
    {
        return (int) ($company->geofence_radius ?? $company->geofence_radius_meters ?? self::GEOFENCE_RADIUS_METERS);
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
