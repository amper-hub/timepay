<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlatformOversightController extends Controller
{
    public function index(Request $request): View
    {
        $usesFaceVerifiedColumn = Schema::hasColumn('attendance_logs', 'face_verified');
        $hasBaselinePhotoColumn = Schema::hasColumn('users', 'baseline_photo_path');
        $hasPasswordChangeColumn = Schema::hasColumn('users', 'requires_password_change');

        $logsThisMonth = AttendanceLog::query()
            ->where('timestamp', '>=', now()->startOfMonth());

        $totalApiCallsThisMonth = (clone $logsThisMonth)->count();

        $failedVerificationsThisMonth = (clone $logsThisMonth)
            ->when(
                $usesFaceVerifiedColumn,
                fn ($query) => $query->where('face_verified', false),
                fn ($query) => $query->whereIn('status', ['rejected', 'flagged'])
            )
            ->count();

        $failedVerificationRate = $totalApiCallsThisMonth > 0
            ? round(($failedVerificationsThisMonth / $totalApiCallsThisMonth) * 100, 1)
            : 0;

        $employees = User::query()
            ->with('company')
            ->where('role', User::ROLE_EMPLOYEE)
            ->when($request->filled('employee_search'), function ($query) use ($request) {
                $search = $request->string('employee_search');

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('company', fn ($company) => $company->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $companies = Company::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name')
            ->get(['id', 'name', 'latitude', 'longitude', 'geofence_radius_meters']);

        $defaultGeofenceRadius = (int) Cache::get('platform.default_geofence_radius', config('timepay.default_geofence_radius', 100));

        return view('super-admin.platform.index', compact(
            'companies',
            'defaultGeofenceRadius',
            'employees',
            'failedVerificationRate',
            'failedVerificationsThisMonth',
            'hasBaselinePhotoColumn',
            'hasPasswordChangeColumn',
            'totalApiCallsThisMonth',
            'usesFaceVerifiedColumn'
        ));
    }

    public function resetPhoto(User $employee): RedirectResponse
    {
        abort_unless($employee->role === User::ROLE_EMPLOYEE, 404);

        $updates = [];

        if (Schema::hasColumn('users', 'baseline_photo_path')) {
            $updates['baseline_photo_path'] = null;
        }

        if (Schema::hasColumn('users', 'requires_password_change')) {
            $updates['requires_password_change'] = true;
        }

        if ($updates === []) {
            return back()->with('info', 'No baseline reset columns are available on the users table yet.');
        }

        $employee->update($updates);

        return back()->with('success', "Baseline photo reset for {$employee->name}.");
    }

    public function updateGeofenceSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_geofence_radius' => ['required', 'integer', 'min:10', 'max:5000'],
        ]);

        Cache::forever('platform.default_geofence_radius', (int) $validated['default_geofence_radius']);

        return back()->with('success', 'Default geofence radius updated.');
    }
}
