@extends('layouts.employer')

@section('title', 'Attendance Log - TimePay Employer Portal')
@section('header_title', 'Attendance Log')

@section('content')
    <div class="space-y-6">
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-2 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">Live Logs Matrix</h2>
                    <p class="mt-1 text-sm text-slate-500">Showing {{ $attendanceLogs->count() }} of {{ $attendanceLogs->total() }} attendance events.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Employee</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Punch Type</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Distance</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($attendanceLogs as $log)
                            <tr class="hover:bg-slate-50 {{ $log->is_suspicious ? 'bg-amber-50/40' : '' }}">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-600 text-sm font-bold text-white">
                                            {{ strtoupper(substr($log->user?->name ?? '?', 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-950">{{ $log->user?->name ?? 'Unknown Employee' }}</p>
                                            <p class="text-xs text-slate-500">{{ $log->user?->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $log->type === 'clock_in' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-teal-50 text-teal-700 ring-1 ring-teal-200' }}">
                                        {{ $log->type === 'clock_in' ? 'Clock In' : 'Clock Out' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                    {{ $log->timestamp?->timezone('Asia/Manila')->format('M d, Y g:i A') ?? '-' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                    {{ number_format((float) $log->distance_meters, 1) }} meters away
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold @if ($log->status === 'verified') bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 @elseif ($log->status === 'flagged') bg-amber-50 text-amber-700 ring-1 ring-amber-200 @elseif ($log->status === 'rejected') bg-red-50 text-red-700 ring-1 ring-red-200 @else bg-red-50 text-red-700 ring-1 ring-red-200 @endif">
                                        @if ($log->status === 'verified')
                                            Verified
                                        @elseif ($log->status === 'flagged')
                                            Flagged Review
                                        @elseif ($log->status === 'rejected')
                                            Rejected
                                        @else
                                            Out of Bounds Alert
                                        @endif
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500">No attendance logs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($attendanceLogs->hasPages())
                <div class="border-t border-slate-200 bg-slate-50 px-6 py-4">
                    {{ $attendanceLogs->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
