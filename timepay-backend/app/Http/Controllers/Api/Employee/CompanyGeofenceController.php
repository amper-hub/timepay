<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyGeofenceController extends Controller
{
    /** Return only the authenticated employee's current company geofence. */
    public function show(Request $request): JsonResponse
    {
        $company = $request->user()->loadMissing('company')->company;

        abort_unless($company, 404, 'Employee company record not found.');

        return response()->json([
            'latitude' => $company->latitude,
            'longitude' => $company->longitude,
            'geofence_radius_meters' => $company->geofence_radius_meters,
        ]);
    }
}
