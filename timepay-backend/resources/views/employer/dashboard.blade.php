@extends('layouts.employer')

@section('title', 'Dashboard - TimePay Employer Portal')
@section('header_title', 'Dashboard')

@section('content')
    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Total Staff</p>
                <p class="mt-3 text-3xl font-bold text-slate-950">{{ number_format($totalEmployees) }}</p>
                <p class="mt-2 text-sm text-slate-500">Active employees in your company</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Verified Punches</p>
                <p class="mt-3 text-3xl font-bold text-emerald-600">{{ number_format($verifiedPunchesToday) }}</p>
                <p class="mt-2 text-sm text-slate-500">Approved attendance events today</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Geofence Violations</p>
                <p class="mt-3 text-3xl font-bold text-red-600">{{ number_format($rejectedViolationsToday) }}</p>
                <p class="mt-2 text-sm text-slate-500">Out-of-bounds attempts today</p>
            </div>
        </div>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-950">Recent Punch Activity</h2>
                <p class="mt-1 text-sm text-slate-500">The latest verified and rejected attendance logs.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Employee</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($recentPunches as $log)
                            <tr>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-950">{{ $log->user?->name ?? 'Unknown Employee' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ str($log->type)->replace('_', ' ')->title() }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $log->timestamp->format('M d, Y g:i A') }}</td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $log->status === 'verified' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-red-50 text-red-700 ring-1 ring-red-200' }}">
                                        {{ $log->status === 'verified' ? 'Verified' : 'Out of Bounds Alert' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500">No attendance activity has been recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
