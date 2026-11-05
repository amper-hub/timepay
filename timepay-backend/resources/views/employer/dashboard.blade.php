@extends('layouts.employer')

@section('title', 'Overview - TimePay Employer Portal')
@section('header_title', 'Overview')

@section('content')
    <div class="space-y-7">
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-emerald-700">{{ $company->name }}</p>
                <h2 class="employer-page-heading">Business overview</h2>
                <p class="employer-page-description">A quick look at your team and the items that need attention.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <a href="{{ route('employer.employees.index') }}" class="employer-secondary-button">View employees</a>
                <button type="button" @click="$dispatch('open-add-employee-modal')" class="employer-primary-button">Add Employee</button>
            </div>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Business summary">
            <article class="employer-card p-5">
                <p class="text-sm font-semibold text-slate-600">Active employees</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-slate-950">{{ number_format($activeEmployeeCount) }}</p>
                <a href="{{ route('employer.employees.index') }}" class="mt-3 inline-flex min-h-10 items-center text-sm font-semibold text-emerald-800 underline-offset-2 hover:underline">Open employee directory</a>
            </article>

            <article class="employer-card p-5">
                <p class="text-sm font-semibold text-slate-600">Verified attendance today</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-slate-950">{{ number_format($verifiedPunchesToday) }}</p>
                <p class="mt-2 text-sm text-slate-600">Verified clock events</p>
                @if ($attentionPunchesToday > 0)
                    <p class="mt-2 text-sm font-medium text-amber-800">{{ number_format($attentionPunchesToday) }} flagged or rejected events to review</p>
                @endif
            </article>

            <article class="employer-card p-5">
                <p class="text-sm font-semibold text-slate-600">Pending leave requests</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-slate-950">{{ number_format($pendingLeaveRequests) }}</p>
                <a href="{{ route('employer.leaves.index', ['status' => 'pending']) }}" class="mt-3 inline-flex min-h-10 items-center text-sm font-semibold text-emerald-800 underline-offset-2 hover:underline">Review requests</a>
            </article>

            <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                <p class="text-sm font-semibold text-emerald-900">Latest calculated payroll</p>
                @if ($latestPayroll)
                    <p class="mt-2 text-3xl font-bold tracking-tight text-emerald-900">{{ $company->formatMoney($latestPayroll->net_total) }}</p>
                    <p class="mt-2 text-sm text-emerald-900">{{ $latestPayroll->period_start->format('M d') }}–{{ $latestPayroll->period_end->format('M d, Y') }} · {{ str($latestPayroll->status)->replace('_', ' ')->title() }}</p>
                    <a href="{{ route('employer.payroll', ['section' => 'details', 'period_id' => $latestPayroll->id]) }}" class="mt-3 inline-flex min-h-10 items-center text-sm font-semibold text-emerald-900 underline-offset-2 hover:underline">View payroll details</a>
                @else
                    <p class="mt-2 text-base font-semibold text-emerald-950">No calculated payroll yet</p>
                    <a href="{{ route('employer.payroll', ['section' => 'overview']) }}" class="mt-3 inline-flex min-h-10 items-center text-sm font-semibold text-emerald-900 underline-offset-2 hover:underline">Open Payroll</a>
                @endif
            </article>
        </section>

        <section class="employer-card overflow-hidden" aria-labelledby="recent-attendance-heading">
            <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h3 id="recent-attendance-heading" class="employer-section-heading">Recent attendance</h3>
                    <p class="mt-1 text-sm text-slate-600">Latest employee clock events and verification status.</p>
                </div>
                <a href="{{ route('employer.attendance') }}" class="employer-secondary-button">View attendance</a>
            </div>

            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full divide-y divide-slate-200 text-left">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-sm font-semibold text-slate-700">Employee</th>
                            <th scope="col" class="px-6 py-3 text-sm font-semibold text-slate-700">Event</th>
                            <th scope="col" class="px-6 py-3 text-sm font-semibold text-slate-700">Date and time</th>
                            <th scope="col" class="px-6 py-3 text-sm font-semibold text-slate-700">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentPunches as $log)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-900">{{ $log->user?->name ?? 'Unknown employee' }}</p>
                                    <p class="text-sm text-slate-600">{{ $log->user?->email }}</p>
                                </td>
                                <td class="px-6 py-4 text-base text-slate-700">{{ str($log->type)->replace('_', ' ')->title() }}</td>
                                <td class="px-6 py-4 text-base text-slate-700">{{ $log->timestamp?->timezone('Asia/Manila')->format('M d, Y g:i A') ?? '—' }}</td>
                                <td class="px-6 py-4">
                                    @php($tone = match ($log->status) { 'verified' => 'border-emerald-200 bg-emerald-50 text-emerald-800', 'flagged' => 'border-amber-200 bg-amber-50 text-amber-900', default => 'border-red-200 bg-red-50 text-red-800' })
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-sm font-medium {{ $tone }}">{{ str($log->status)->replace('_', ' ')->title() }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-12 text-center text-base text-slate-600">No employee attendance events have been recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="divide-y divide-slate-200 md:hidden">
                @forelse ($recentPunches as $log)
                    <article class="space-y-2 px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><h4 class="truncate font-semibold text-slate-900">{{ $log->user?->name ?? 'Unknown employee' }}</h4><p class="text-sm text-slate-600">{{ str($log->type)->replace('_', ' ')->title() }} · {{ $log->timestamp?->timezone('Asia/Manila')->format('M d, Y g:i A') ?? '—' }}</p></div>
                            <span class="shrink-0 rounded-full border px-2.5 py-1 text-sm font-medium {{ match ($log->status) { 'verified' => 'border-emerald-200 bg-emerald-50 text-emerald-800', 'flagged' => 'border-amber-200 bg-amber-50 text-amber-900', default => 'border-red-200 bg-red-50 text-red-800' } }}">{{ str($log->status)->replace('_', ' ')->title() }}</span>
                        </div>
                        <p class="break-all text-sm text-slate-600">{{ $log->user?->email }}</p>
                    </article>
                @empty
                    <p class="px-4 py-10 text-center text-base text-slate-600">No employee attendance events have been recorded yet.</p>
                @endforelse
            </div>
        </section>
    </div>

    @include('employer.partials.add-employee-modal')
@endsection
