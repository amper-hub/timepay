@extends('layouts.employer')

@section('title', 'Attendance Log - TimePay Employer Portal')
@section('header_title', 'Attendance Log')

@section('content')
    <div
        x-data="{
            modalOpen: false,
            selectedImage: '',
            selectedEmployee: '',
            selectedReason: '',
            toastMessage: '',
            async rejectLog(event) {
                const form = event.target;
                const button = form.querySelector('button[type=submit]');

                if (! confirm('Reject this attendance entry and end the active shift if it is still open?')) {
                    return false;
                }

                button.disabled = true;
                button.textContent = 'Rejecting...';

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: new FormData(form),
                    });
                    const payload = await response.json();

                    if (! response.ok) {
                        throw new Error(payload.message || 'Unable to reject attendance entry.');
                    }

                    this.toastMessage = payload.message || 'Attendance entry rejected.';
                    setTimeout(() => this.toastMessage = '', 3200);

                    return true;
                } catch (error) {
                    alert(error.message || 'Unable to reject attendance entry.');
                    button.disabled = false;
                    button.textContent = 'Reject & End Shift';
                    return false;
                }
            },
        }"
        class="space-y-6"
    >
        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div
            x-cloak
            x-show="toastMessage"
            x-transition
            class="fixed right-6 top-20 z-50 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 shadow-lg"
            x-text="toastMessage"
        ></div>

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
                            <tr x-data="{ rejected: @js($log->status === 'rejected') }" class="hover:bg-slate-50 {{ $log->is_suspicious ? 'bg-amber-50/40' : '' }}">
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
                                    {{ $log->timestamp->format('M d, Y g:i A') }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                    {{ number_format((float) $log->distance_meters, 1) }} meters away
                                </td>
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
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        @if ($log->photo_path)
                                            @php($selfieUrl = asset('storage/selfies/' . basename($log->photo_path)))
                                            <button
                                                type="button"
                                                @click="selectedImage = '{{ $selfieUrl }}'; selectedEmployee = '{{ addslashes($log->user?->name ?? 'Employee') }}'; selectedReason = '{{ addslashes($log->suspicion_reason ?? '') }}'; modalOpen = true"
                                                class="h-14 w-14 overflow-hidden rounded-lg border bg-slate-100 shadow-sm ring-offset-2 transition hover:ring-2 hover:ring-slate-950 {{ $log->is_suspicious ? 'border-amber-500 ring-2 ring-amber-300' : 'border-slate-200' }}"
                                            >
                                                <img src="{{ $selfieUrl }}" alt="Selfie proof for {{ $log->user?->name ?? 'employee' }}" class="h-full w-full object-cover">
                                            </button>
                                        @else
                                            <div class="flex h-14 w-14 items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 text-xs font-medium text-slate-400">
                                                None
                                            </div>
                                        @endif

                                        @if ($log->is_suspicious)
                                            <div class="max-w-xs">
                                                <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800 ring-1 ring-amber-300">
                                                    ⚠️ Suspicious Photo
                                                </span>
                                                @if ($log->suspicion_reason)
                                                    <p class="mt-1 whitespace-normal text-xs font-medium text-amber-800">
                                                        {{ $log->suspicion_reason }}
                                                    </p>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    @if ($log->status !== 'rejected')
                                        <form method="POST" action="{{ route('employer.attendance.reject', $log) }}" onsubmit="return confirm('Reject this attendance entry?');">
                                            @csrf
                                            <button type="submit" class="rounded-lg bg-rose-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-rose-700">
                                                Reject Entry
                                            </button>
                                        </form>
                                    @else
                                        <div class="text-xs font-semibold text-rose-700">
                                            Invalidated
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-slate-500">No attendance logs found.</td>
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
                        <template x-if="selectedReason">
                            <p class="mt-1 text-xs font-semibold text-amber-700" x-text="selectedReason"></p>
                        </template>
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
