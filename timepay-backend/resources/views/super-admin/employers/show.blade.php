@extends('super-admin.layouts.app')

@section('title', 'View Employer')
@section('page-title', 'Employer Profile')
@section('page-description', 'Review employer account, company location, and assigned employees.')

@section('content')
@php
    $company = $employer->company;
    $hasCoordinates = $company && $company->latitude !== null && $company->longitude !== null;
    $employeeCount = $employer->employees->count();
@endphp

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIINfQPDLa8f2QpPaLHrh9tUfNf3HfQakLk=" crossorigin="">
<style>
    #employer-geofence-map {
        height: 320px;
        min-height: 320px;
    }
</style>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-950">{{ $company?->name ?? 'No company assigned' }}</h2>
            <p class="text-sm text-slate-500">{{ $employer->name }} - {{ $employer->email }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('super-admin.employers.index') }}" class="inline-flex justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to Employers</a>
            <a href="{{ route('super-admin.employers.edit', $employer) }}" class="inline-flex justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Edit</a>
        </div>
    </div>

    <section class="grid gap-4 md:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Total Employees</p>
            <p class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format($employeeCount) }}</p>
            <p class="mt-1 text-xs text-slate-500">Assigned to this company</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Employer Status</p>
            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ ucfirst($employer->status ?? 'active') }}</p>
            <p class="mt-1 text-xs text-slate-500">Account created {{ $employer->created_at?->format('M d, Y') ?? '-' }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Geofence Radius</p>
            <p class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format($company?->geofence_radius_meters ?? 100) }}m</p>
            <p class="mt-1 text-xs text-slate-500">{{ $hasCoordinates ? 'Location configured' : 'Location not configured' }}</p>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="text-lg font-semibold text-slate-950">Employer Details</h3>
            </div>
            <dl class="grid gap-5 px-5 py-5 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-slate-500">Representative</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-950">{{ $employer->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-slate-500">Email</dt>
                    <dd class="mt-1 break-all text-sm font-semibold text-slate-950">{{ $employer->email }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-slate-500">Company</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-950">{{ $company?->name ?? 'No company assigned' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-slate-500">Status</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-950">{{ ucfirst($employer->status ?? 'active') }}</dd>
                </div>
            </dl>

            <div class="border-y border-slate-200 px-5 py-4">
                <h3 class="text-lg font-semibold text-slate-950">Location</h3>
            </div>
            <dl class="grid gap-5 px-5 py-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <dt class="text-sm font-medium text-slate-500">Address</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-950">{{ $hasCompanyAddressColumn ? ($company?->address ?? 'Not configured') : 'Not configured' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-slate-500">Latitude</dt>
                    <dd class="mt-1 font-mono text-sm font-semibold text-slate-950">{{ $hasCoordinates ? number_format((float) $company->latitude, 6) : 'Not configured' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-slate-500">Longitude</dt>
                    <dd class="mt-1 font-mono text-sm font-semibold text-slate-950">{{ $hasCoordinates ? number_format((float) $company->longitude, 6) : 'Not configured' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-slate-500">Geofence Radius</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-950">{{ number_format($company?->geofence_radius_meters ?? 100) }} meters</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="text-lg font-semibold text-slate-950">Company Location</h3>
                <p class="text-sm text-slate-500">Current geofence center and permitted clock-in area.</p>
            </div>
            <div class="p-5">
                @if ($hasCoordinates)
                    <div id="employer-geofence-map" class="w-full overflow-hidden rounded-lg border border-slate-200 bg-slate-100" style="height: 320px; min-height: 320px;"></div>
                @else
                    <div class="flex h-80 items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 px-6 text-center text-sm text-slate-500">
                        This company has not configured a geofence location.
                    </div>
                @endif
            </div>
        </section>
    </div>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h3 class="text-lg font-semibold text-slate-950">Employees</h3>
            <p class="text-sm text-slate-500">{{ number_format($employeeCount) }} employee{{ $employeeCount === 1 ? '' : 's' }} assigned to {{ $company?->name ?? 'this company' }}.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Name</th>
                        <th class="px-5 py-3">Email</th>
                        <th class="px-5 py-3">Role</th>
                        <th class="px-5 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($employer->employees as $employee)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-5 py-4 font-medium text-slate-950">{{ $employee->name }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $employee->email }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ ucfirst(strtolower($employee->role)) }}</td>
                            <td class="px-5 py-4">
                                @php
                                    $employeeStatus = $employee->status ?? 'active';
                                    $statusClass = $employeeStatus === 'active'
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-rose-50 text-rose-700';
                                @endphp
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ ucfirst($employeeStatus) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-10 text-center text-slate-500">No employees are assigned to this company.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

@if ($hasCoordinates)
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const point = [{{ (float) $company->latitude }}, {{ (float) $company->longitude }}];
            const radius = {{ (int) ($company->geofence_radius_meters ?? 100) }};
            const map = L.map('employer-geofence-map', { scrollWheelZoom: false }).setView(point, 16);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            const marker = L.marker(point).addTo(map);
            const popupContent = document.createElement('strong');
            popupContent.textContent = {{ Illuminate\Support\Js::from($company->name) }};
            marker.bindPopup(popupContent).openPopup();

            const circle = L.circle(point, {
                radius: radius,
                color: '#059669',
                weight: 2,
                fillColor: '#6366f1',
                fillOpacity: 0.12
            }).addTo(map);

            map.fitBounds(circle.getBounds(), { padding: [32, 32], maxZoom: 16 });
            requestAnimationFrame(function () {
                map.invalidateSize();
            });
        });
    </script>
@endif
@endsection
