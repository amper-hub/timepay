@extends('layouts.employer')

@section('title', 'Dashboard - TimePay Employer Portal')
@section('header_title', 'Dashboard')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-950">Overview</h2>
                <p class="mt-1 text-sm text-slate-500">Monitor attendance activity and manage your workforce.</p>
            </div>
            <button
                type="button"
                @click="$dispatch('open-add-employee-modal')"
                class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700"
            >
                <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Add Employee
            </button>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
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

            <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-6 shadow-sm">
                <p class="text-sm font-medium text-emerald-700">Average Hourly Rate</p>
                <p class="mt-3 text-3xl font-bold text-emerald-700">{{ $company->formatMoney($averageHourlyRate) }}</p>
                <p class="mt-2 text-sm text-emerald-700/80">Across active employees</p>
            </div>

            <div class="rounded-lg border border-teal-100 bg-teal-50 p-6 shadow-sm">
                <p class="text-sm font-medium text-teal-700">Pending Payroll</p>
                <p class="mt-3 text-3xl font-bold text-teal-700">{{ $company->formatMoney($pendingPayroll) }}</p>
                <p class="mt-2 text-sm text-teal-700/80">Current month verified work</p>
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
                <p class="mt-1 text-sm text-slate-500">The latest verified, flagged, and rejected attendance logs.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-emerald-50/60">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-emerald-800">Employee</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-emerald-800">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-emerald-800">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-emerald-800">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($recentPunches as $log)
                            <tr class="transition hover:bg-emerald-50/50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-950">{{ $log->user?->name ?? 'Unknown Employee' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ str($log->type)->replace('_', ' ')->title() }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $log->timestamp->format('M d, Y g:i A') }}</td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold @if ($log->status === 'verified') bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 @elseif ($log->status === 'flagged') bg-amber-50 text-amber-700 ring-1 ring-amber-200 @else bg-red-50 text-red-700 ring-1 ring-red-200 @endif">
                                        @if ($log->status === 'verified')
                                            Verified
                                        @elseif ($log->status === 'flagged')
                                            Flagged Review
                                        @else
                                            Out of Bounds Alert
                                        @endif
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

    @include('employer.partials.add-employee-modal')
@endsection
