<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * Earth's radius in meters for Haversine formula.
     */
    private const EARTH_RADIUS_METERS = 6371000;

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
        $distance = $this->calculateHaversineDistance(
            $validated['latitude'],
            $validated['longitude'],
            (float) $company->latitude,
            (float) $company->longitude
        );

        $distanceInMeters = (int) round($distance);

        // Check if user is within the geofence
        if ($distanceInMeters <= $company->geofence_radius_meters) {
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
     * Calculate distance between two geographic coordinates using Haversine formula.
     *
     * @param float $lat1 Latitude of first point (degrees)
     * @param float $lon1 Longitude of first point (degrees)
     * @param float $lat2 Latitude of second point (degrees)
     * @param float $lon2 Longitude of second point (degrees)
     * @return float Distance in meters
     */
    private function calculateHaversineDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        // Convert degrees to radians
        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);

        // Haversine formula
        $dlat = $lat2Rad - $lat1Rad;
        $dlon = $lon2Rad - $lon1Rad;

        $a = sin($dlat / 2) * sin($dlat / 2) +
            cos($lat1Rad) * cos($lat2Rad) * sin($dlon / 2) * sin($dlon / 2);

        $c = 2 * asin(sqrt($a));

        return self::EARTH_RADIUS_METERS * $c;
    }
}
