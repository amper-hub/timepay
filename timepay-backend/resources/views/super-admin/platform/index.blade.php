@extends('super-admin.layouts.app')

@section('title', 'Platform Oversight')
@section('page-title', 'Platform Oversight')
@section('page-description', 'Monitor Cloud Face ID health, baseline enrollment, and company geofence locations.')

@section('content')
@php
    $companyMapPoints = $companies->map(fn ($company) => [
        'id' => $company->id,
        'name' => $company->name,
        'latitude' => $company->latitude !== null ? (float) $company->latitude : null,
        'longitude' => $company->longitude !== null ? (float) $company->longitude : null,
        'radius' => (int) ($company->geofence_radius_meters ?? $defaultGeofenceRadius),
    ])->values();
@endphp

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIINfQPDLa8f2QpPaLHrh9tUfNf3HfQakLk=" crossorigin="">

<div class="space-y-6">
    <section class="grid gap-4 md:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Total API Calls This Month</p>
            <p class="mt-3 text-3xl font-semibold text-slate-950">{{ number_format($totalApiCallsThisMonth) }}</p>
            <p class="mt-2 text-xs text-slate-500">Estimated from attendance verification logs</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">System-Wide Failed Verification Rate</p>
            <p class="mt-3 text-3xl font-semibold {{ $failedVerificationRate >= 10 ? 'text-rose-700' : 'text-slate-950' }}">{{ $failedVerificationRate }}%</p>
            <p class="mt-2 text-xs text-slate-500">{{ number_format($failedVerificationsThisMonth) }} failed verifications this month</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Verification Source</p>
            <p class="mt-3 text-xl font-semibold text-slate-950">{{ $usesFaceVerifiedColumn ? 'face_verified column' : 'status fallback' }}</p>
            <p class="mt-2 text-xs text-slate-500">Uses rejected/flagged statuses when face_verified is unavailable</p>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">Baseline Photo Reset</h2>
                <p class="text-sm text-slate-500">Search employees across all companies and force face re-enrollment.</p>
            </div>

            <form method="GET" action="{{ route('super-admin.platform.index') }}" class="flex flex-col gap-2 sm:flex-row">
                <input type="search" name="employee_search" value="{{ request('employee_search') }}" placeholder="Search employee, email, or company" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:w-80">
                <button type="submit" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Search</button>
            </form>
        </div>

        @unless ($hasBaselinePhotoColumn || $hasPasswordChangeColumn)
            <div class="border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm font-medium text-amber-800">
                The users table does not currently include baseline reset columns. Add baseline_photo_path and requires_password_change to enable resets.
            </div>
        @endunless

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Employee</th>
                        <th class="px-5 py-3">Company</th>
                        <th class="px-5 py-3">Baseline Photo</th>
                        <th class="px-5 py-3">Re-Enrollment</th>
                        <th class="px-5 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($employees as $employee)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-5 py-4">
                                <p class="font-medium text-slate-950">{{ $employee->name }}</p>
                                <p class="text-xs text-slate-500">{{ $employee->email }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $employee->company?->name ?? 'No company assigned' }}</td>
                            <td class="px-5 py-4">
                                @if ($hasBaselinePhotoColumn && $employee->baseline_photo_path)
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Enrolled</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Missing</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if ($hasPasswordChangeColumn && $employee->requires_password_change)
                                    <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Required</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Not required</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <form method="POST" action="{{ route('super-admin.platform.employees.reset-photo', $employee) }}" onsubmit="return confirm('Reset this employee baseline photo?')">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                        Reset Baseline Photo
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-slate-500">No employees found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-5 py-4">
            {{ $employees->links() }}
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[380px_1fr]">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-950">Global Geofence Settings</h2>
                <p class="text-sm text-slate-500">Set the fallback radius used for companies without a custom value.</p>
            </div>

            <form method="POST" action="{{ route('super-admin.platform.geofence-settings.update') }}" class="space-y-4 px-5 py-5">
                @csrf
                @method('PATCH')

                <div>
                    <label for="default_geofence_radius" class="block text-sm font-semibold text-slate-700">Default Geofence Radius</label>
                    <div class="mt-1 flex rounded-lg shadow-sm">
                        <input id="default_geofence_radius" type="number" min="10" max="5000" name="default_geofence_radius" value="{{ old('default_geofence_radius', $defaultGeofenceRadius) }}" class="block w-full rounded-l-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <span class="inline-flex items-center rounded-r-lg border border-l-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500">meters</span>
                    </div>
                    @error('default_geofence_radius')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="inline-flex w-full justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                    Save Settings
                </button>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-950">Company Map View</h2>
                <p class="text-sm text-slate-500">Inspect registered company coordinates and geofence radius overlaps.</p>
            </div>

            <div class="p-5">
                <div id="company-map" class="h-[520px] w-full overflow-hidden rounded-lg border border-slate-200 bg-slate-100"></div>
                <p class="mt-3 text-xs text-slate-500">{{ $companies->count() }} registered companies loaded. Companies without coordinates are skipped until their geofence is configured.</p>
            </div>
        </section>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const companies = @json($companyMapPoints);
        const mappedCompanies = companies.filter(function (company) {
            return company.latitude !== null && company.longitude !== null;
        });
        const map = L.map('company-map');

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        if (mappedCompanies.length === 0) {
            map.setView([14.5995, 120.9842], 11);
            return;
        }

        const bounds = [];

        mappedCompanies.forEach(function (company) {
            const point = [company.latitude, company.longitude];
            bounds.push(point);

            L.marker(point)
                .addTo(map)
                .bindPopup(`<strong>${company.name}</strong><br>${company.latitude}, ${company.longitude}<br>Radius: ${company.radius}m`);

            L.circle(point, {
                radius: company.radius,
                color: '#059669',
                weight: 2,
                fillColor: '#6366f1',
                fillOpacity: 0.12
            }).addTo(map);
        });

        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
    });
</script>
@endsection
