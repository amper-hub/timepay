@extends('super-admin.layouts.app')

@section('title', 'Leave Audit')
@section('eyebrow', 'Compliance')
@section('page-title', 'Leave Audit')
@section('page-description', 'Read-only visibility into leave activity across every TimePay tenant.')

@section('content')
    <div class="space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-950">System-wide leave requests</h2>
                    <p class="mt-2 text-sm text-slate-500">
                        Audit leave submissions across companies without changing employer decisions.
                    </p>
                </div>

                <form method="GET" action="{{ route('super-admin.leaves.index') }}" class="flex flex-col gap-2 sm:flex-row">
                    <select name="status" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">All statuses</option>
                        <option value="pending" @selected($selectedStatus === 'pending')>Pending</option>
                        <option value="approved" @selected($selectedStatus === 'approved')>Approved</option>
                        <option value="rejected" @selected($selectedStatus === 'rejected')>Rejected</option>
                    </select>
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                        Filter
                    </button>
                </form>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Company Name</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Employee Name</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Leave Type</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Dates</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Submitted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($leaveRequests as $leave)
                            @php
                                $statusClass = match ($leave->status) {
                                    'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                    'rejected' => 'bg-rose-50 text-rose-700 ring-rose-200',
                                    default => 'bg-amber-50 text-amber-700 ring-amber-200',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-semibold text-slate-950">
                                    {{ $leave->company?->name ?? 'Unknown Company' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <p class="text-sm font-medium text-slate-900">{{ $leave->user?->name ?? 'Unknown Employee' }}</p>
                                    <p class="text-xs text-slate-500">{{ $leave->user?->email }}</p>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $leave->leave_type }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                    {{ $leave->start_date->format('M d, Y') }} - {{ $leave->end_date->format('M d, Y') }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold uppercase ring-1 {{ $statusClass }}">
                                        {{ $leave->status }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">
                                    {{ $leave->created_at?->format('M d, Y g:i A') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-500">
                                    No leave requests found across the platform.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($leaveRequests->hasPages())
                <div class="border-t border-slate-200 bg-slate-50 px-6 py-4">
                    {{ $leaveRequests->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
