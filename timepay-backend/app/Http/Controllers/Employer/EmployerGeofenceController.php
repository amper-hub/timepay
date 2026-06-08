<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployerGeofenceController extends Controller
{
    /**
     * Display the geofence settings edit form.
     */
    public function edit(): View
    {
        $company = auth()->user()->company;

        return view('employer.geofence', [
            'company' => $company,
        ]);
    }

    /**
     * Update the geofence settings for the company.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'geofence_radius_meters' => 'required|integer|min:10|max:10000',
        ]);

        $company = auth()->user()->company;
        $company->update($validated);

        return redirect()->route('employer.geofence')
            ->with('success', 'Geofence settings updated successfully.');
    }
}
