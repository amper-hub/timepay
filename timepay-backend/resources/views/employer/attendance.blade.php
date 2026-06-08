@extends('layouts.employer')

@section('title', 'Attendance Log - TimePay Employer Portal')
@section('header_title', 'Attendance Log')

@section('content')
    <div x-data="{ modalOpen: false, selectedImage: '', selectedEmployee: '' }" class="space-y-6">
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
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Selfie Proof</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($attendanceLogs as $log)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-950 text-sm font-bold text-white">
                                            {{ strtoupper(substr($log->user?->name ?? '?', 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-950">{{ $log->user?->name ?? 'Unknown Employee' }}</p>
                                            <p class="text-xs text-slate-500">{{ $log->user?->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $log->type === 'clock_in' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-blue-50 text-blue-700 ring-1 ring-blue-200' }}">
                                        {{ $log->type === 'clock_in' ? 'Clock In' : 'Clock Out' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                    {{ $log->timestamp->format('M d, Y g:i A') }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                    {{ number_format((float) $log->distance_meters, 1) }} meters away
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $log->status === 'verified' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-red-50 text-red-700 ring-1 ring-red-200' }}">
                                        {{ $log->status === 'verified' ? 'Verified' : 'Out of Bounds Alert' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    @if ($log->photo_path)
                                        @php($selfieUrl = asset('storage/selfies/' . basename($log->photo_path)))
                                        <button
                                            type="button"
                                            @click="selectedImage = '{{ $selfieUrl }}'; selectedEmployee = '{{ addslashes($log->user?->name ?? 'Employee') }}'; modalOpen = true"
                                            class="h-14 w-14 overflow-hidden rounded-lg border border-slate-200 bg-slate-100 shadow-sm ring-offset-2 transition hover:ring-2 hover:ring-slate-950"
                                        >
                                            <img src="{{ $selfieUrl }}" alt="Selfie proof for {{ $log->user?->name ?? 'employee' }}" class="h-full w-full object-cover">
                                        </button>
                                    @else
                                        <div class="flex h-14 w-14 items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 text-xs font-medium text-slate-400">
                                            None
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No attendance logs found.</td>
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

        <div x-cloak x-show="modalOpen" @keydown.escape.window="modalOpen = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/75 p-4">
            <div class="absolute inset-0" @click="modalOpen = false"></div>
            <div class="relative max-h-[90vh] w-full max-w-3xl overflow-hidden rounded-lg bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-950">Selfie Proof</h3>
                        <p class="text-xs text-slate-500" x-text="selectedEmployee"></p>
                    </div>
                    <button type="button" @click="modalOpen = false" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Close</button>
                </div>
                <div class="bg-slate-950 p-4">
                    <img :src="selectedImage" alt="Full-size selfie proof" class="mx-auto max-h-[70vh] rounded-md object-contain">
                </div>
            </div>
        </div>
    </div>
@endsection
