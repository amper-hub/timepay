@extends('layouts.employer')

@section('title', 'Leave Management - TimePay Employer Portal')
@section('header_title', 'Leave Management')

@section('content')
    <div class="space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-emerald-600">Employer Review Queue</p>
                    <h2 class="mt-2 text-2xl font-bold text-slate-950">Employee leave requests</h2>
                    <p class="mt-2 max-w-2xl text-sm text-slate-500">
                        Review pending requests, approve time away, or decline with a clear note for the employee.
                    </p>
                </div>

                <form method="GET" action="{{ route('employer.leaves.index') }}" class="flex flex-col gap-2 sm:flex-row">
                    <select name="status" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                        <option value="">All statuses</option>
                        <option value="pending" @selected($selectedStatus === 'pending')>Pending</option>
                        <option value="approved" @selected($selectedStatus === 'approved')>Approved</option>
                        <option value="rejected" @selected($selectedStatus === 'rejected')>Rejected</option>
                    </select>
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                        Filter
                    </button>
                </form>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <p class="text-sm font-semibold text-slate-950">
                    Showing {{ $leaveRequests->count() }} of {{ $leaveRequests->total() }} requests
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Employee</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Leave Type</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Dates</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Reason</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Decision</th>
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
                            <tr class="align-top hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <p class="text-sm font-semibold text-slate-950">{{ $leave->user?->name ?? 'Unknown Employee' }}</p>
                                    <p class="text-xs text-slate-500">{{ $leave->user?->email }}</p>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-700">{{ $leave->leave_type }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                    {{ $leave->start_date->format('M d, Y') }} - {{ $leave->end_date->format('M d, Y') }}
                                </td>
                                <td class="min-w-72 px-6 py-4">
                                    <p class="text-sm leading-6 text-slate-600">{{ $leave->reason }}</p>
                                    @if ($leave->admin_notes)
                                        <div class="mt-3 rounded-lg bg-slate-100 p-3">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Admin notes</p>
                                            <p class="mt-1 text-sm text-slate-700">{{ $leave->admin_notes }}</p>
                                        </div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold uppercase ring-1 {{ $statusClass }}">
                                        {{ $leave->status }}
                                    </span>
                                </td>
                                <td class="min-w-80 px-6 py-4">
                                    <form method="POST" action="{{ route('employer.leaves.update', $leave) }}" class="space-y-3">
                                        @csrf
                                        @method('PATCH')
                                        <textarea name="admin_notes" rows="2" placeholder="Optional admin notes..." class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">{{ old('admin_notes', $leave->admin_notes) }}</textarea>
                                        <div class="flex flex-wrap gap-2">
                                            <button name="status" value="approved" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-emerald-700">
                                                Approve
                                            </button>
                                            <button name="status" value="rejected" class="rounded-lg bg-rose-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-rose-700">
                                                Decline
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-500">
                                    No leave requests found for this company.
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
