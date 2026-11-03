@extends('layouts.employer')

@section('title', 'Employees - TimePay')
@section('header_title', 'Employees')

@section('content')
    <div class="space-y-6">
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-emerald-700">People</p>
                <h2 class="employer-page-heading">Employee directory</h2>
                <p class="employer-page-description">Employee accounts, attendance review, and finalized payroll summaries.</p>
            </div>
            <button type="button" @click="$dispatch('open-add-employee-modal')" class="employer-primary-button">Add Employee</button>
        </header>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-base text-emerald-800" role="status">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-base text-amber-900" role="alert">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-base text-red-800" role="alert">
                <p class="font-semibold">Please review the form information.</p>
                <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <aside class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950" role="status">
            <p class="font-semibold">Worked time requires review</p>
            <p class="mt-1 text-sm">A known cross-period attendance allocation issue can omit time at payroll boundaries. This page does not estimate payable hours; review source attendance and resolve that issue before relying on hours.</p>
        </aside>

        <section class="employer-card overflow-hidden" aria-labelledby="employee-list-heading">
            <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h3 id="employee-list-heading" class="employer-section-heading">Employees</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ number_format($employees->total()) }} {{ \Illuminate\Support\Str::plural('employee', $employees->total()) }} · page {{ $employees->currentPage() }} of {{ max(1, $employees->lastPage()) }}</p>
                </div>
                <div class="flex w-full flex-col gap-2 sm:max-w-xl sm:flex-row">
                    <form method="GET" action="{{ route('employer.employees.index') }}" class="flex min-w-0 flex-1 gap-2" role="search">
                        <input type="hidden" name="period_id" value="{{ $selectedPeriod?->id }}">
                        <label class="sr-only" for="employee-search">Search by employee name, email, or ID</label>
                        <input id="employee-search" name="search" type="search" value="{{ $search }}" maxlength="100" placeholder="Search name, email, or ID" class="employer-field-control mt-0 min-w-0">
                        <button type="submit" class="employer-secondary-button">Search</button>
                    </form>
                    @if ($periods->isNotEmpty())
                        <form method="GET" action="{{ route('employer.employees.index') }}" class="flex gap-2">
                            <input type="hidden" name="search" value="{{ $search }}">
                            <label class="sr-only" for="employee-period">Payroll period</label>
                            <select id="employee-period" name="period_id" class="employer-field-control mt-0" onchange="this.form.requestSubmit()">
                                @foreach ($periods as $period)
                                    <option value="{{ $period->id }}" @selected($selectedPeriod?->id === $period->id)>{{ $period->period_start->format('M d, Y') }} – {{ $period->period_end->format('M d, Y') }} ({{ str($period->status)->replace('_', ' ') }})</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                </div>
            </div>

            @if ($selectedPeriod)
                <p class="border-b border-slate-200 bg-slate-50 px-5 py-3 text-sm text-slate-700 sm:px-6">
                    Selected period: <strong>{{ $selectedPeriod->period_start->format('M d, Y') }} – {{ $selectedPeriod->period_end->format('M d, Y') }}</strong> · Payroll currency: {{ $currency }}
                </p>
            @endif

            <div class="hidden overflow-x-auto lg:block">
                <table class="min-w-[90rem] w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-slate-700">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold">Employee</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Employment</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Current compensation</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Attendance in period</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Payroll summary</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Face enrollment</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($employees as $employee)
                            @php
                                $attendance = $attendanceSummary[$employee->id] ?? ['events' => 0, 'shifts' => 0, 'review' => 0];
                                $entry = $payrollEntries[$employee->id] ?? null;
                                $periodFinal = $selectedPeriod && in_array($selectedPeriod->status, ['approved', 'partially_paid', 'paid'], true);
                                $entryClear = $entry && count(array_intersect($criticalReviewFlagCodes, $entry->review_flags ?? [])) === 0;
                                $showAmounts = $periodFinal && $entryClear && ! ($attendance['boundary'] ?? false);
                                $rate = $employee->current_compensation;
                            @endphp
                            <tr class="align-top hover:bg-slate-50">
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">{{ $employee->name }}</p>
                                    <p class="mt-1 break-all text-slate-600">{{ $employee->email }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Employee ID #{{ $employee->id }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full border px-2 py-1 text-xs font-medium {{ $employee->status === 'active' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-300 bg-slate-100 text-slate-700' }}">{{ str($employee->status ?? 'active')->replace('_', ' ')->title() }}</span>
                                    <p class="mt-2 text-slate-600">Account created: {{ $employee->created_at?->timezone(config('app.timezone'))->format('M d, Y') ?? 'Unavailable' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Position, department, and separate hire date are not stored.</p>
                                </td>
                                <td class="px-4 py-4">
                                    @if ($rate)
                                        <p class="font-medium text-slate-900">{{ str($rate->basis)->title() }} · {{ $currency }} {{ number_format((float) $rate->rate, 2) }}</p>
                                        <p class="mt-1 text-slate-600">Effective {{ $rate->effective_from->format('M d, Y') }}{{ $rate->effective_to ? ' through '.$rate->effective_to->format('M d, Y') : '' }}</p>
                                    @else
                                        <p class="text-amber-800">Rate unavailable</p>
                                    @endif
                                    <p class="mt-1 text-xs text-slate-500">{{ str($employee->payrollProfile?->pay_frequency ?? 'not configured')->replace('_', ' ') }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p>{{ $attendance['shifts'] }} recorded clock-ins · {{ $attendance['events'] }} events</p>
                                    <p class="mt-1 font-medium text-amber-800">{{ $attendance['events'] ? 'Hours: needs review' : 'No recorded hours' }}</p>
                                    <p class="mt-1 text-slate-600">{{ $attendance['review'] }} records needing review</p>
                                    <a class="mt-2 inline-block font-semibold text-emerald-800 underline" href="{{ route('employer.attendance', ['employee_id' => $employee->id, 'from' => $selectedPeriod?->period_start->toDateString(), 'to' => $selectedPeriod?->period_end->toDateString()]) }}">Inspect attendance</a>
                                </td>
                                <td class="px-4 py-4">
                                    @if ($showAmounts)
                                        <p class="text-xs font-semibold uppercase text-emerald-800">{{ str($selectedPeriod->status)->replace('_', ' ') }}</p>
                                        <p class="mt-1">Gross: <strong>{{ $currency }} {{ number_format((float) $entry->gross_pay, 2) }}</strong></p>
                                        <p class="mt-1">Employee deductions: {{ $currency }} {{ number_format((float) $entry->employee_deductions, 2) }}</p>
                                        <p class="mt-1">Net pay: <strong>{{ $currency }} {{ number_format((float) $entry->net_pay, 2) }}</strong></p>
                                        <p class="mt-1">Paid: {{ $currency }} {{ number_format((float) $entry->amount_paid, 2) }}</p>
                                    @else
                                        <p class="font-semibold text-amber-800">{{ ($attendance['boundary'] ?? false) ? 'Needs review · cross-period shift' : ($entry && $selectedPeriod && in_array($selectedPeriod->status, ['draft', 'pending_review'], true) ? 'Not final · '.$selectedPeriod->status : ($entry ? 'Needs review · amounts hidden' : 'No payroll entry')) }}</p>
                                        @if ($entry && $entry->review_flags)
                                            <p class="mt-1 text-xs text-amber-900">Review flags: {{ count($entry->review_flags) }}</p>
                                        @endif
                                    @endif
                                    @if ($selectedPeriod)
                                        <a class="mt-2 inline-block font-semibold text-emerald-800 underline" href="{{ route('employer.payroll', ['section' => 'details', 'period_id' => $selectedPeriod->id, 'search' => $employee->name]) }}">Open payroll details</a>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <span class="font-medium {{ $employee->baseline_enrolled ? 'text-emerald-800' : 'text-amber-800' }}">{{ $employee->baseline_enrolled ? 'Enrolled' : ($employee->baseline_photo_path ? 'Reference unavailable · reset needed' : 'Not enrolled') }}</span>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <a href="{{ route('employer.employees.show', $employee) }}" class="mb-2 inline-flex min-h-10 items-center justify-center rounded-lg border border-emerald-700 px-3 py-2 font-semibold text-emerald-900 hover:bg-emerald-50">Profile &amp; payroll</a>
                                    @if ($employee->baseline_photo_path)
                                        <form method="POST" action="{{ route('employer.employees.reset-baseline', $employee) }}" onsubmit="if (!window.confirm('Reset ' + @js($employee->name) + '’s face baseline? They will need to enroll again at their next attendance punch.')) return false; const button = this.querySelector('button[type=submit]'); button.disabled = true; button.textContent = 'Resetting…';">
                                            @csrf
                                            <button type="submit" class="rounded-lg border border-amber-300 px-3 py-2 font-semibold text-amber-900 hover:bg-amber-50 disabled:opacity-60" aria-label="Reset baseline photo for {{ $employee->name }}">Reset baseline</button>
                                        </form>
                                    @else
                                        <span class="text-slate-500">No reset needed</span>
                                    @endif
                                    @if (strtolower((string) $employee->status) !== 'inactive')
                                        <form method="POST" action="{{ route('employer.employees.deactivate', $employee) }}" class="mt-2" onsubmit="if (!window.confirm('Deactivate ' + @js($employee->name) + '? Their access will be disabled and their history retained.')) return false; const button = this.querySelector('button[type=submit]'); button.disabled = true; button.textContent = 'Deactivating…';">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 font-semibold text-red-700 hover:bg-red-50 disabled:opacity-60">Deactivate Employee</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-12 text-center text-base text-slate-600">{{ $search ? 'No employees match this search.' : 'No employees have been added yet.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-200 lg:hidden">
                @forelse ($employees as $employee)
                    @php
                        $attendance = $attendanceSummary[$employee->id] ?? ['events' => 0, 'shifts' => 0, 'review' => 0];
                        $entry = $payrollEntries[$employee->id] ?? null;
                        $periodFinal = $selectedPeriod && in_array($selectedPeriod->status, ['approved', 'partially_paid', 'paid'], true);
                        $entryClear = $entry && count(array_intersect($criticalReviewFlagCodes, $entry->review_flags ?? [])) === 0;
                        $showAmounts = $periodFinal && $entryClear && ! ($attendance['boundary'] ?? false);
                    @endphp
                    <article class="space-y-4 px-4 py-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><h4 class="truncate font-semibold text-slate-900">{{ $employee->name }}</h4><p class="break-all text-sm text-slate-600">{{ $employee->email }} · ID #{{ $employee->id }}</p></div>
                            <span class="shrink-0 rounded-full border px-2 py-1 text-xs {{ $employee->status === 'active' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-300 bg-slate-100 text-slate-700' }}">{{ str($employee->status ?? 'active')->title() }}</span>
                        </div>
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                            <div><dt class="text-slate-500">Current compensation</dt><dd class="font-medium">{{ $employee->current_compensation ? str($employee->current_compensation->basis)->title().' · '.$currency.' '.number_format((float) $employee->current_compensation->rate, 2) : 'Unavailable' }}</dd>@if ($employee->current_compensation)<dd class="text-xs text-slate-500">Effective {{ $employee->current_compensation->effective_from->format('M d, Y') }} to {{ $employee->current_compensation->effective_to?->format('M d, Y') ?? 'current' }}</dd>@endif</div>
                            <div><dt class="text-slate-500">Account created</dt><dd>{{ $employee->created_at?->timezone(config('app.timezone'))->format('M d, Y') ?? 'Unavailable' }}</dd><dd class="text-xs text-slate-500">Hire date, position, and department are not stored.</dd></div>
                            <div><dt class="text-slate-500">Attendance</dt><dd>{{ $attendance['shifts'] }} clock-ins · {{ $attendance['review'] }} need review</dd><dd class="font-medium text-amber-800">{{ $attendance['events'] ? 'Hours need review' : 'No recorded hours' }}</dd></div>
                            <div><dt class="text-slate-500">Payroll</dt><dd>{{ $selectedPeriod ? str($selectedPeriod->status)->replace('_', ' ')->title() : 'No period' }}</dd>@if ($showAmounts)<dd>Gross {{ $currency }} {{ number_format((float) $entry->gross_pay, 2) }}</dd><dd>Deductions {{ $currency }} {{ number_format((float) $entry->employee_deductions, 2) }}</dd><dd>Net {{ $currency }} {{ number_format((float) $entry->net_pay, 2) }} · Paid {{ $currency }} {{ number_format((float) $entry->amount_paid, 2) }}</dd>@else<dd class="text-amber-800">{{ ($attendance['boundary'] ?? false) ? 'Cross-period shift · needs review' : 'Amounts not final or need review' }}</dd>@endif</div>
                        </dl>
                        <div class="flex flex-wrap items-center gap-3 text-sm">
                            <span>Face baseline: {{ $employee->baseline_enrolled ? 'Enrolled' : ($employee->baseline_photo_path ? 'Reference unavailable · reset needed' : 'Not enrolled') }}</span>
                            <a class="font-semibold text-emerald-800 underline" href="{{ route('employer.attendance', ['employee_id' => $employee->id, 'from' => $selectedPeriod?->period_start->toDateString(), 'to' => $selectedPeriod?->period_end->toDateString()]) }}">Inspect attendance</a>
                            @if ($selectedPeriod)<a class="font-semibold text-emerald-800 underline" href="{{ route('employer.payroll', ['section' => 'details', 'period_id' => $selectedPeriod->id, 'search' => $employee->name]) }}">Payroll details</a>@endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('employer.employees.show', $employee) }}" class="inline-flex min-h-10 items-center rounded-lg border border-emerald-700 px-3 py-2 font-semibold text-emerald-900">Profile &amp; payroll settings</a>
                            @if ($employee->baseline_photo_path)
                                <form method="POST" action="{{ route('employer.employees.reset-baseline', $employee) }}" onsubmit="if (!window.confirm('Reset this employee’s face baseline? They will need to enroll again at their next attendance punch.')) return false; const button = this.querySelector('button[type=submit]'); button.disabled = true; button.textContent = 'Resetting…';">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-amber-300 px-3 py-2 font-semibold text-amber-900">Reset baseline photo</button>
                                </form>
                            @endif
                            @if (strtolower((string) $employee->status) !== 'inactive')
                                <form method="POST" action="{{ route('employer.employees.deactivate', $employee) }}" onsubmit="return window.confirm('Deactivate ' + @js($employee->name) + '? Their access will be disabled and their history retained.');">@csrf @method('PATCH')<button type="submit" class="rounded-lg border border-red-200 px-3 py-2 font-semibold text-red-700">Deactivate Employee</button></form>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="px-4 py-10 text-center text-base text-slate-600">{{ $search ? 'No employees match this search.' : 'No employees have been added yet.' }}</p>
                @endforelse
            </div>

            @if ($employees->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">{{ $employees->links() }}</div>
            @endif
        </section>
    </div>

    @include('employer.partials.add-employee-modal')
@endsection
