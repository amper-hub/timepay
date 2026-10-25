@extends('layouts.employer')

@section('title', 'Payroll Management - TimePay')
@section('header_title', 'Payroll Management')

@section('content')
    @php
        $activePeriod = $selectedPeriod;
        $payrollSection = $payrollSection ?? 'overview';
        $hasCalculation = $activePeriod?->calculated_at !== null;
        $statusLabels = [
            'draft' => 'Draft',
            'pending_review' => 'Awaiting review',
            'approved' => 'Approved',
            'partially_paid' => 'Partially paid',
            'paid' => 'Paid',
            'voided' => 'Voided',
        ];
        $frequencyLabels = [
            'hourly' => 'Hourly',
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'semimonthly' => 'Semi-monthly',
            'monthly' => 'Monthly',
            'yearly' => '13th-month run',
        ];
        $reviewFlagLabels = [
            'employee_status_requires_review' => 'Employee is inactive. Ordinary payroll approval is blocked; review the employee’s outstanding pay separately.',
            'attendance_already_used_or_paid' => 'One or more linked attendance records are already paid or assigned to another payroll. Review the linked attendance and payment history.',
            'duplicate_attendance_requires_review' => 'Duplicate attendance timestamps were found. Review and correct the attendance records, then recalculate.',
            'incomplete_attendance_requires_review' => 'A verified clock-in is missing its matching clock-out. Correct the affected shift and recalculate.',
            'missing_effective_pay_rate' => 'No applicable compensation rate covers part of this employee’s payable period. Review the rate and effective dates, then recalculate.',
            'missing_effective_hourly_rate' => 'No hourly rate covers part of a payable shift. Review compensation history and effective dates, then recalculate.',
            'zero_compensation_rate_requires_review' => 'A zero compensation rate applies to payable work. Correct the rate explicitly and recalculate.',
            'long_shift_overtime_requires_approval' => 'A shift exceeds the configured standard hours and overtime is not separately calculated. Verify hours and applicable overtime pay before recalculating.',
            'negative_net_pay_requires_review' => 'Employee deductions exceed gross earnings. Correct the rates, attendance, or deduction entries and recalculate.',
            'monthly_salary_prorated_by_calendar_day' => 'Monthly pay was prorated by calendar days.',
            'overtime_eligibility_disabled' => 'The employee is not marked as overtime eligible.',
            'approved_leave_policy_requires_review' => 'Approved leave needs policy review.',
            '13th_month_uses_approved_basic_pay_snapshots' => '13th-month pay uses basic pay from approved payroll runs.',
            'calendar_year_not_completed' => 'The calendar year is still in progress.',
            'taxable_benefit_over_90000_includes_only_this_13th_month_payment' => 'The taxable 13th-month amount needs review.',
            'inactive_employee_13th_month_requires_review' => 'This employee is inactive. Review this separate 13th-month amount as final pay; the account remains deactivated.',
        ];
        $criticalReviewFlags = \App\Support\PayrollReviewFlags::critical();
        $settingsErrorFields = [
            'salary_type', 'rate', 'pay_frequency', 'effective_from', 'payment_method',
            'standard_hours_per_day', 'standard_work_days_per_month', 'payroll_eligible',
            'overtime_eligible', 'rank_and_file', 'sss_enabled', 'philhealth_enabled',
            'pagibig_enabled', 'withholding_tax_enabled', 'category', 'code', 'label',
            'amount', 'taxable', 'authorization_reference', 'effective_to',
        ];
    @endphp

    <style>
        .payroll-page button:disabled {
            cursor: wait;
            filter: saturate(0.65);
        }
    </style>

    <div class="payroll-page min-w-0 space-y-6" x-data>
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-emerald-700">Employer workspace</p>
                <h2 class="employer-page-heading">Payroll</h2>
                <p class="employer-page-description">Manage payroll periods, review calculations, and track manual payments.</p>
            </div>
            <a href="{{ route('employer.payroll', ['section' => 'create']) }}" class="employer-primary-button">
                Create Payroll Period
            </a>
        </header>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-base text-red-900" role="alert" aria-labelledby="payroll-errors-title">
                <p id="payroll-errors-title" class="font-semibold">Please review the highlighted payroll information.</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <nav aria-label="Payroll sections" class="grid grid-cols-2 gap-1 rounded-xl border border-slate-200 bg-white p-1.5 sm:flex sm:overflow-x-auto">
            @foreach ([
                'overview' => 'Overview',
                'periods' => 'Payroll periods',
                'settings' => 'Employee settings',
                'payments' => 'Payments and history',
            ] as $section => $label)
                <a href="{{ route('employer.payroll', array_filter(['section' => $section, 'period_id' => in_array($section, ['payments'], true) ? $activePeriod?->id : null])) }}"
                    @if ($payrollSection === $section) aria-current="page" @endif
                    class="inline-flex min-h-10 w-full items-center justify-center rounded-lg px-2 py-2 text-center text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 sm:w-auto sm:shrink-0 sm:justify-start sm:px-3 sm:text-left {{ $payrollSection === $section ? 'bg-emerald-50 text-emerald-900' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">{{ $label }}</a>
            @endforeach
        </nav>

        @if ($payrollSection === 'overview')
        <section class="space-y-4">
        <h2 class="employer-section-heading">Payroll overview</h2>
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Payroll summary">
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-base font-semibold text-slate-700">Eligible Employees</p>
                <p class="mt-2 text-3xl font-bold leading-tight text-slate-950">{{ number_format($eligibleEmployeeCount) }}</p>
                <p class="mt-1 text-sm text-slate-600">Based on current payroll settings</p>
            </article>
            @foreach ([
                ['Gross payroll', 'gross_total'],
                ['Total deductions', 'deduction_total'],
                ['Net payroll', 'net_total'],
            ] as [$label, $field])
                @php
                    $isNetPayroll = $field === 'net_total';
                @endphp
                <article class="rounded-xl border p-5 shadow-sm {{ $isNetPayroll ? 'border-emerald-300 bg-emerald-50 ring-1 ring-emerald-200' : 'border-slate-200 bg-white' }}">
                    <p class="text-base font-semibold {{ $isNetPayroll ? 'text-emerald-900' : 'text-slate-700' }}">{{ $label }}</p>
                    @if (! $activePeriod)
                        <p class="mt-2 text-lg font-semibold text-slate-700">Select a payroll period</p>
                        <p class="mt-1 text-sm text-slate-600">Totals appear when a period is selected</p>
                    @elseif (! $hasCalculation)
                        <p class="mt-2 text-lg font-semibold {{ $isNetPayroll ? 'text-emerald-900' : 'text-slate-700' }}">Not calculated</p>
                        <p class="mt-1 text-sm text-slate-600">Calculate this draft to see actual totals</p>
                    @else
                        <p class="mt-2 whitespace-nowrap text-3xl font-bold leading-tight {{ $isNetPayroll ? 'text-emerald-800' : 'text-slate-950' }}">{{ $company->formatMoney($activePeriod->{$field}) }}</p>
                        <p class="mt-1 text-sm text-slate-600">Selected period totals</p>
                    @endif
                </article>
            @endforeach
        </section>

        <div class="rounded-xl border border-slate-200 bg-white p-5">
            @if ($activePeriod)
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-600">Selected payroll period</p>
                        <p class="mt-1 text-lg font-semibold text-slate-950">{{ $activePeriod->period_start->format('M d, Y') }} – {{ $activePeriod->period_end->format('M d, Y') }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $frequencyLabels[$activePeriod->frequency] ?? 'Payroll' }} · {{ $statusLabels[$activePeriod->status] ?? 'Needs review' }}</p>
                    </div>
                    <a href="{{ route('employer.payroll', ['section' => 'details', 'period_id' => $activePeriod->id]) }}" class="employer-secondary-button">Open period details</a>
                </div>
            @else
                <p class="font-semibold text-slate-900">No payroll period selected</p>
                <p class="mt-1 text-sm text-slate-600">Create a period to review payroll dates and real calculation totals.</p>
                <a href="{{ route('employer.payroll', ['section' => 'periods']) }}" class="mt-3 inline-flex min-h-10 items-center text-sm font-semibold text-emerald-800 underline-offset-2 hover:underline">Browse payroll periods</a>
            @endif
        </div>
        </section>
        @endif

        @if ($payrollSection === 'create')
        <section id="create-period" class="scroll-mt-24 rounded-xl border border-slate-200 bg-white shadow-sm">
            <details x-ref="periodFormDetails" open>
                <summary class="flex min-h-16 cursor-pointer list-none flex-wrap items-center justify-between gap-3 px-5 py-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-700">
                    <span>
                        <span class="block text-sm font-semibold text-emerald-800">Step 1</span>
                        <span class="mt-1 block text-[1.375rem] font-semibold text-slate-950">Create a payroll period</span>
                    </span>
                    <span class="text-base text-slate-600">Choose dates and frequency</span>
                </summary>
                <div class="border-t border-slate-200 px-5 py-5">
                    <p class="mb-4 max-w-3xl text-base text-slate-600">A new period starts as a draft. Calculating it creates a reviewable payroll snapshot; it does not pay employees.</p>
                    <form id="period-create-form" method="POST" action="{{ route('employer.payroll.periods.store') }}" data-submit-once class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @csrf
                        <label for="period_start" class="block text-base font-semibold text-slate-900">Period start
                            <input id="period_start" name="period_start" type="date" value="{{ old('period_start') }}" required class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                            @error('period_start') <span class="mt-1 block text-sm font-medium text-red-700">{{ $message }}</span> @enderror
                        </label>
                        <label for="period_end" class="block text-base font-semibold text-slate-900">Period end
                            <input id="period_end" name="period_end" type="date" value="{{ old('period_end') }}" required class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                            @error('period_end') <span class="mt-1 block text-sm font-medium text-red-700">{{ $message }}</span> @enderror
                        </label>
                        <label for="pay_date" class="block text-base font-semibold text-slate-900">Pay date <span class="font-normal text-slate-600">(optional)</span>
                            <input id="pay_date" name="pay_date" type="date" value="{{ old('pay_date') }}" class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                            @error('pay_date') <span class="mt-1 block text-sm font-medium text-red-700">{{ $message }}</span> @enderror
                        </label>
                        <label for="frequency" class="block text-base font-semibold text-slate-900">Payroll frequency
                            <select id="frequency" name="frequency" required class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                @foreach ($frequencyLabels as $value => $label)
                                    <option value="{{ $value }}" @selected(old('frequency', 'monthly') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('frequency') <span class="mt-1 block text-sm font-medium text-red-700">{{ $message }}</span> @enderror
                        </label>
                        <div class="flex items-end">
                            <button type="submit" data-loading-label="Creating period..." class="min-h-11 w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-base font-semibold text-white transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2">Create Payroll Period</button>
                        </div>
                    </form>
                    <p class="mt-3 text-sm text-slate-600">Frequency choices follow the payroll rules configured in TimePay. The backend validates period length, overlap, and yearly 13th-month dates.</p>
                </div>
            </details>
        </section>
        @endif

        @if ($payrollSection === 'periods')
        <section id="payroll-periods" class="scroll-mt-24 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold text-emerald-800">Step 2</p>
                    <h2 class="mt-1 text-[1.375rem] font-semibold text-slate-950">Choose a payroll period</h2>
                    <p class="mt-1 text-base text-slate-600">Open a period to review employee pay and its current status.</p>
                </div>
                <form class="grid gap-2 sm:grid-cols-2 lg:flex lg:flex-wrap" method="GET" action="{{ route('employer.payroll') }}" aria-label="Filter payroll periods">
                    <input type="hidden" name="section" value="periods">
                    <label class="sr-only" for="period_status">Payroll status</label>
                    <select id="period_status" name="period_status" class="min-h-11 rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                        <option value="">All statuses</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['period_status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <label class="sr-only" for="period_from">Start date from</label>
                    <input id="period_from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="min-h-11 rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                    <label class="sr-only" for="period_to">End date through</label>
                    <input id="period_to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="min-h-11 rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                    <button type="submit" class="min-h-11 rounded-lg border border-slate-300 px-4 py-2 text-base font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700">Apply filters</button>
                </form>
            </div>

            <div class="space-y-3 p-4 md:hidden">
                @forelse ($periods as $period)
                    @php
                        $mobileStatusTone = match ($period->status) {
                            'paid', 'approved' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                            'voided' => 'border-slate-300 bg-slate-100 text-slate-800',
                            default => 'border-amber-200 bg-amber-50 text-amber-900',
                        };
                    @endphp
                    <article class="rounded-lg border {{ $activePeriod?->id === $period->id ? 'border-emerald-300 bg-emerald-50/50' : 'border-slate-200 bg-white' }} p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-600">Payroll period</p>
                                <p class="mt-1 text-lg font-bold text-slate-950">{{ $period->period_start->format('M d, Y') }} - {{ $period->period_end->format('M d, Y') }}</p>
                            </div>
                            <span class="inline-flex min-h-8 w-fit items-center rounded-full border px-3 py-1 text-sm font-semibold {{ $mobileStatusTone }}">{{ $statusLabels[$period->status] ?? 'Needs review' }}</span>
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 border-t border-slate-200 pt-3">
                            <div><dt class="text-sm text-slate-600">Pay date</dt><dd class="mt-0.5 font-semibold text-slate-900">{{ $period->pay_date?->format('M d, Y') ?? 'Not set' }}</dd></div>
                            <div><dt class="text-sm text-slate-600">Frequency</dt><dd class="mt-0.5 font-semibold text-slate-900">{{ $frequencyLabels[$period->frequency] ?? 'Other' }}</dd></div>
                            <div><dt class="text-sm text-slate-600">Employees</dt><dd class="mt-0.5 font-semibold text-slate-900">{{ $period->calculated_at ? number_format($period->entries_count) : 'Not calculated' }}</dd></div>
                            <div><dt class="text-sm text-slate-600">Gross payroll</dt><dd class="mt-0.5 font-semibold text-slate-900">{{ $period->calculated_at ? $company->formatMoney($period->gross_total) : '-' }}</dd></div>
                            <div><dt class="text-sm text-slate-600">Paid amount</dt><dd class="mt-0.5 font-semibold text-slate-900">{{ $period->calculated_at ? $company->formatMoney($period->paid_total) : '-' }}</dd></div>
                            <div class="rounded-md bg-emerald-50 px-3 py-2"><dt class="text-sm font-semibold text-emerald-900">Net payroll</dt><dd class="mt-0.5 font-bold text-emerald-800">{{ $period->calculated_at ? $company->formatMoney($period->net_total) : '-' }}</dd></div>
                        </dl>
                        <a href="{{ route('employer.payroll', ['section' => 'details', 'period_id' => $period->id]) }}" class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-emerald-700 px-4 py-2 text-base font-semibold text-emerald-800 transition hover:bg-emerald-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700">{{ $activePeriod?->id === $period->id ? 'Viewing details' : 'View details' }}</a>
                    </article>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-300 bg-white px-4 py-8 text-center">
                        @if ($periods->total() === 0 && empty($filters['period_status']) && empty($filters['from']) && empty($filters['to']))
                            <p class="font-semibold text-slate-900">No payroll periods yet. Create your first payroll period to get started.</p>
                            <a href="{{ route('employer.payroll', ['section' => 'create']) }}" class="mt-3 inline-flex min-h-11 items-center rounded-lg bg-emerald-700 px-4 py-2 text-base font-semibold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2">Create Payroll Period</a>
                        @else
                            <p class="text-base text-slate-600">No payroll periods match these filters.</p>
                        @endif
                    </div>
                @endforelse
                @if ($periods->hasPages())
                    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">{{ $periods->links() }}</div>
                @endif
            </div>

            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-[980px] divide-y divide-slate-200 text-base">
                    <thead class="bg-slate-50 text-left text-sm font-bold text-slate-700">
                        <tr>
                            <th scope="col" class="px-4 py-3">Payroll period</th>
                            <th scope="col" class="px-4 py-3">Pay date</th>
                            <th scope="col" class="px-4 py-3">Frequency</th>
                            <th scope="col" class="px-4 py-3">Employees</th>
                            <th scope="col" class="px-4 py-3">Gross</th>
                            <th scope="col" class="px-4 py-3">Net payroll</th>
                            <th scope="col" class="px-4 py-3">Paid amount</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span>Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($periods as $period)
                            @php
                                $rowStatusTone = match ($period->status) {
                                    'paid', 'approved' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                                    'voided' => 'border-slate-300 bg-slate-100 text-slate-800',
                                    default => 'border-amber-200 bg-amber-50 text-amber-900',
                                };
                            @endphp
                            <tr class="{{ $activePeriod?->id === $period->id ? 'bg-emerald-50/50' : 'hover:bg-slate-50' }}">
                                <td class="whitespace-nowrap px-4 py-4 font-semibold text-slate-900">{{ $period->period_start->format('M d, Y') }} - {{ $period->period_end->format('M d, Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-slate-700">{{ $period->pay_date?->format('M d, Y') ?? 'Not set' }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-slate-700">{{ $frequencyLabels[$period->frequency] ?? 'Other' }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-slate-700">{{ $period->calculated_at ? number_format($period->entries_count) : 'Not calculated' }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-slate-700">{{ $period->calculated_at ? $company->formatMoney($period->gross_total) : '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-4 font-bold text-emerald-800">{{ $period->calculated_at ? $company->formatMoney($period->net_total) : '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-slate-700">{{ $period->calculated_at ? $company->formatMoney($period->paid_total) : '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-4"><span class="inline-flex rounded-full border px-2.5 py-1 text-sm font-semibold {{ $rowStatusTone }}">{{ $statusLabels[$period->status] ?? 'Needs review' }}</span></td>
                        <td class="whitespace-nowrap px-4 py-4"><a href="{{ route('employer.payroll', ['section' => 'details', 'period_id' => $period->id]) }}" class="inline-flex min-h-11 items-center rounded-lg border border-emerald-200 px-3 py-1.5 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700">{{ $activePeriod?->id === $period->id ? 'Viewing' : 'View details' }}</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-10 text-center">
                                    @if ($periods->total() === 0 && empty($filters['period_status']) && empty($filters['from']) && empty($filters['to']))
                                        <p class="font-semibold text-slate-900">No payroll periods yet. Create your first payroll period to get started.</p>
                                        <a href="{{ route('employer.payroll', ['section' => 'create']) }}" class="mt-3 inline-flex min-h-11 items-center rounded-lg bg-emerald-700 px-4 py-2 text-base font-semibold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2">Create Payroll Period</a>
                                    @else
                                        <p class="text-slate-600">No payroll periods match these filters.</p>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="hidden border-t border-slate-200 px-5 py-3 md:block">{{ $periods->links() }}</div>
        </section>
        @endif

        @if (in_array($payrollSection, ['details', 'payments'], true))
        <section id="approval" class="scroll-mt-24 space-y-4" aria-label="Payroll review and approval">
            @if ($payrollSection === 'details')
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-emerald-800">Step 3</p>
                        <h2 class="mt-1 text-[1.375rem] font-semibold text-slate-950">Review and approve payroll</h2>
                        @if ($activePeriod)
                            <p class="mt-1 text-base text-slate-600">{{ $activePeriod->period_start->format('M d, Y') }} - {{ $activePeriod->period_end->format('M d, Y') }} · {{ $frequencyLabels[$activePeriod->frequency] ?? 'Payroll' }}</p>
                        @else
                            <p class="mt-1 text-base text-slate-600">Select a payroll period above to review its calculations and next action.</p>
                        @endif
                    </div>
                    @if ($activePeriod)
                        <span class="inline-flex min-h-8 items-center rounded-full border px-3 py-1 text-base font-semibold {{ match ($activePeriod->status) { 'paid', 'approved' => 'border-emerald-200 bg-emerald-50 text-emerald-900', 'voided' => 'border-slate-300 bg-slate-100 text-slate-800', default => 'border-amber-200 bg-amber-50 text-amber-900' } }}">Status: {{ $statusLabels[$activePeriod->status] ?? 'Needs review' }}</span>
                    @endif
                </div>

                @if ($activePeriod)
                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3" aria-label="Selected payroll breakdown">
                        @foreach ([
                            ['Gross pay', 'gross_total'],
                            ['Employee deductions', 'deduction_total'],
                            ['Employer contributions', 'employer_contribution_total'],
                            ['Net pay', 'net_total'],
                            ['Paid so far', 'paid_total'],
                        ] as [$label, $field])
                            @php
                                $isNetPay = $field === 'net_total';
                            @endphp
                            <div class="rounded-lg border px-4 py-4 {{ $isNetPay ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50' }}">
                                <p class="text-sm font-semibold {{ $isNetPay ? 'text-emerald-900' : 'text-slate-700' }}">{{ $label }}</p>
                                <p class="mt-1 {{ $isNetPay ? 'text-[1.375rem] font-bold text-emerald-800' : 'text-base font-semibold text-slate-950' }}">{{ $hasCalculation ? $company->formatMoney($activePeriod->{$field}) : 'Not calculated' }}</p>
                            </div>
                        @endforeach
                    </div>

                    @if ($activePeriod->frequency === 'yearly')
                        <p class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-base text-slate-700">The 13th-month amount comes from basic pay in approved payroll snapshots for this calendar year. Review the backend calculation flags before approval.</p>
                    @endif

                    @if ($payrollSetupIssues->isNotEmpty())
                        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4" role="status">
                            <h3 class="font-semibold text-amber-950">Check employee setup before calculating</h3>
                            <p class="mt-1 text-base text-amber-900">These employees may be excluded from this period under the current payroll settings.</p>
                            <ul class="mt-3 space-y-2 text-base text-amber-950">
                                @foreach ($payrollSetupIssues as $issue)
                                    <li><span class="font-semibold">{{ $issue['name'] }}:</span> {{ $issue['reason'] }}</li>
                                @endforeach
                            </ul>
                            <a href="{{ route('employer.payroll', ['section' => 'settings']) }}" class="mt-3 inline-flex min-h-11 items-center font-semibold text-amber-950 underline underline-offset-2">Open Employee Payroll Settings</a>
                        </div>
                    @endif

                    @if ($activePeriod->status === 'draft')
                        <div class="mt-5 flex flex-col gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="font-semibold text-emerald-950">Ready to build the employee register?</h3>
                                <p class="mt-1 text-base text-emerald-900">Calculating creates a snapshot and moves this period to Awaiting review. The backend applies pay rates, attendance, deductions, and statutory rules.</p>
                            </div>
                            <form method="POST" action="{{ route('employer.payroll.calculate', $activePeriod) }}" data-submit-once data-confirm="Calculate this payroll period and create a reviewable snapshot?">
                                @csrf
                                <button type="submit" data-loading-label="Calculating..." class="min-h-11 w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-base font-semibold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2 sm:w-auto">Calculate and send for review</button>
                            </form>
                        </div>
                    @elseif ($activePeriod->status === 'pending_review')
                        <div class="mt-5 rounded-lg border {{ $hasReviewFlags ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }} p-4">
                            @if ($hasReviewFlags)
                                <h3 class="font-semibold text-amber-950">Review flags before approval</h3>
                                <p class="mt-1 text-base text-amber-900">Critical issues must be corrected and recalculated. Other warnings require a review note. The server checks the saved register against current payroll records before approval.</p>
                            @else
                                <h3 class="font-semibold text-emerald-950">Payroll is awaiting your approval</h3>
                                <p class="mt-1 text-base text-emerald-900">Review the employee totals and calculation details below. Approval locks this payroll snapshot.</p>
                            @endif
                            <form method="POST" action="{{ route('employer.payroll.approve', $activePeriod) }}" data-submit-once data-confirm="Approve this payroll snapshot? Approved payroll cannot be recalculated.">
                                @csrf
                                @if ($hasReviewFlags)
                                    <label for="review_note" class="mt-4 block text-base font-semibold text-amber-950">Review note
                                        <textarea id="review_note" name="review_note" minlength="10" maxlength="1000" required rows="3" placeholder="Describe what you checked and why approval is appropriate." class="mt-1.5 block w-full rounded-lg border-amber-300 text-base placeholder:text-slate-600 focus:border-amber-700 focus:ring-amber-700">{{ old('review_note') }}</textarea>
                                        @error('review_note') <span class="mt-1 block text-sm font-medium text-red-700">{{ $message }}</span> @enderror
                                    </label>
                                @endif
                                @if ($hasCriticalReviewFlags)
                                    <p class="mt-3 text-sm font-semibold text-red-800">Approval is blocked until critical issues are corrected and the period is recalculated.</p>
                                @endif
                                <button type="submit" @disabled($hasCriticalReviewFlags) data-loading-label="Approving..." class="mt-3 min-h-11 rounded-lg bg-slate-900 px-4 py-2.5 text-base font-semibold text-white hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">Approve payroll</button>
                            </form>
                        </div>
                    @elseif ($activePeriod->status === 'approved')
                        <p class="mt-5 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-base text-slate-700"><strong>Approved:</strong> the finalized calculation is locked. Record employee payments in Step 4; current outstanding balance is {{ $company->formatMoney($activePeriod->outstanding_total ?? 0) }}.</p>
                    @elseif ($activePeriod->status === 'partially_paid')
                        <p class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-base text-amber-900"><strong>Partially paid:</strong> the calculation is locked and some payments are recorded. Record only remaining balances in Step 4; outstanding balance is {{ $company->formatMoney($activePeriod->outstanding_total ?? 0) }}.</p>
                    @elseif ($activePeriod->status === 'paid')
                        <p class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-base text-emerald-900"><strong>Paid:</strong> all register balances are settled. The finalized calculation and payment history remain available; no further payment can be recorded.</p>
                    @elseif ($activePeriod->status === 'voided')
                        <p class="mt-5 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-base text-slate-700">This payroll period is voided and cannot be recalculated or paid.</p>
                    @endif

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        @if ($activePeriod->entries_count > 0)
                            <a href="{{ route('employer.payroll.export.xlsx', $activePeriod) }}" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 py-2 text-base font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700">Download Excel register</a>
                            <a href="{{ route('employer.payroll.export.pdf', $activePeriod) }}" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 py-2 text-base font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700">Download PDF register</a>
                        @endif
                        @if ($activePeriod->status === 'pending_review')
                            <form method="POST" action="{{ route('employer.payroll.calculate', $activePeriod) }}" data-submit-once data-confirm="Recalculating replaces this unapproved snapshot and resets the review step. Continue?">
                                @csrf
                                <button type="submit" data-loading-label="Recalculating..." class="min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-base font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700">Recalculate draft</button>
                            </form>
                        @endif
                        @if (in_array($activePeriod->status, ['draft', 'pending_review', 'approved'], true) && $activePeriod->payments_count === 0)
                            <form method="POST" action="{{ route('employer.payroll.void', $activePeriod) }}" data-submit-once data-confirm="Void this payroll period? Its source attendance remains reserved for audit.">
                                @csrf
                                <button type="submit" data-loading-label="Voiding..." class="min-h-11 rounded-lg border border-red-200 px-3 py-2 text-base font-semibold text-red-800 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700">Void period</button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
            @endif

            @if ($activePeriod)
                @if ($payrollSection === 'payments')
                <div id="payments" class="scroll-mt-24 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-semibold text-emerald-800">Step 4</p>
                    <h3 class="mt-1 text-[1.375rem] font-semibold text-slate-950">Record payments</h3>
                    @if (in_array($activePeriod->status, ['approved', 'partially_paid', 'paid'], true))
                        <p class="mt-1 text-base text-slate-600">Payments are entered manually after the employer pays the employee. TimePay does not initiate a transfer.</p>
                        <p class="mt-3 text-base font-semibold text-slate-900">Paid so far: {{ $company->formatMoney($activePeriod->paid_total) }} <span class="mx-1 text-slate-600">|</span> Outstanding: {{ $company->formatMoney($activePeriod->outstanding_total ?? 0) }} <span class="mx-1 text-slate-600">|</span> Payroll status: {{ $statusLabels[$activePeriod->status] ?? 'Needs review' }}</p>
                    @elseif ($activePeriod->status === 'voided')
                        <p class="mt-1 text-base text-slate-600">Voided payroll periods cannot receive payments.</p>
                    @else
                        <p class="mt-1 text-base text-slate-600">Payments become available after the payroll has been approved.</p>
                    @endif
                </div>
                @endif

                <div id="employee-register" class="scroll-mt-24 space-y-3">
                    <div class="flex flex-col gap-3 border-b border-slate-200 pb-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h3 class="text-[1.375rem] font-semibold text-slate-950">Employee register</h3>
                            <p class="mt-1 text-base text-slate-600">Review each employee’s pay lines and any calculation warnings.</p>
                        </div>
                        <form class="grid gap-2 sm:grid-cols-[minmax(12rem,1fr)_auto_auto]" method="GET" action="{{ route('employer.payroll') }}" aria-label="Search payroll employees">
                            <input type="hidden" name="period_id" value="{{ $activePeriod->id }}">
                            <input type="hidden" name="section" value="{{ $payrollSection }}">
                            <label class="sr-only" for="employee-search">Search employees</label>
                            <input id="employee-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search employee name or email" class="min-h-11 rounded-lg border-slate-300 text-base placeholder:text-slate-600 focus:border-emerald-700 focus:ring-emerald-700">
                            <label class="sr-only" for="employee-status">Employee status</label>
                            <select id="employee-status" name="employee_status" class="min-h-11 rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                <option value="">All employee statuses</option>
                                @foreach (['active', 'pending', 'suspended', 'inactive'] as $status)
                                    <option value="{{ $status }}" @selected(($filters['employee_status'] ?? '') === $status)>{{ str($status)->title() }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-base font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700">Search</button>
                        </form>
                    </div>

                    @if (! $hasCalculation)
                        <p class="rounded-lg border border-slate-200 bg-white px-4 py-5 text-base text-slate-600">Calculate this draft to build the employee register. The setup check above explains missing profiles, frequency mismatches, and rates that may exclude employees.</p>
                    @else
                        @forelse ($entries as $entry)
                            @php
                                $paymentLabels = ['unpaid' => 'Unpaid', 'partially_paid' => 'Partially paid', 'paid' => 'Paid'];
                                $paymentTone = match ($entry->payment_status) {
                                    'paid' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                                    'partially_paid' => 'border-amber-200 bg-amber-50 text-amber-900',
                                    default => 'border-slate-200 bg-slate-50 text-slate-800',
                                };
                                $showPaymentErrors = (int) old('_payroll_entry_id', 0) === (int) $entry->id;
                            @endphp
                            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <h4 class="text-lg font-semibold text-slate-950">{{ data_get($entry->calculation_snapshot, 'employee_snapshot.name', $entry->employee?->name ?? 'Employee') }}</h4>
                                        @if ($entry->employee?->status === 'inactive')
                                            <p class="mt-1 inline-flex rounded-full border border-slate-300 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Inactive now · historical payroll snapshot</p>
                                        @endif
                                        <p class="mt-0.5 text-sm text-slate-600">Employee ID: {{ $entry->user_id }} · Period: {{ $activePeriod->period_start->format('M d, Y') }}–{{ $activePeriod->period_end->format('M d, Y') }}</p>
                                        @if ($entry->employee?->email)
                                            <p class="mt-0.5 text-sm text-slate-600">{{ $entry->employee->email }}</p>
                                        @endif
                                        <p class="mt-1 text-sm text-slate-600">{{ number_format($entry->worked_minutes / 60, 2) }} hours · {{ number_format($entry->payable_days) }} payable days</p>
                                    </div>
                                    @if (($entry->review_flags ?? []) !== [])
                                        <span class="inline-flex min-h-8 items-center rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-950">Review needed</span>
                                    @else
                                        <span class="inline-flex min-h-8 items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-900">No review flags</span>
                                    @endif
                                </div>

                                @if (($entry->review_flags ?? []) !== [])
                                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3" role="status">
                                        <p class="text-base font-semibold text-amber-950">Calculation issues for {{ data_get($entry->calculation_snapshot, 'employee_snapshot.name', $entry->employee?->name ?? 'Employee') }} (ID {{ $entry->user_id }})</p>
                                        <ul class="mt-1 list-inside list-disc space-y-1 text-base text-amber-900">
                                            @foreach ($entry->review_flags as $flag)
                                                <li>
                                                    <strong>{{ in_array($flag, $criticalReviewFlags, true) ? 'Critical' : (in_array($flag, ['monthly_salary_prorated_by_calendar_day', 'overtime_eligibility_disabled', 'approved_leave_policy_requires_review', 'inactive_employee_13th_month_requires_review'], true) ? 'Warning' : 'Informational') }}:</strong>
                                                    {{ $reviewFlagLabels[$flag] ?? 'Review this calculation detail before approval.' }}
                                                    @if (in_array($flag, ['missing_effective_pay_rate', 'missing_effective_hourly_rate', 'zero_compensation_rate_requires_review'], true))
                                                        @foreach (data_get($entry->calculation_snapshot, 'hourly_daily_monthly_rates', []) as $rate)
                                                            <span class="block pl-5">{{ str($rate['basis'])->title() }} rate {{ $company->formatMoney($rate['rate']) }}, effective {{ $rate['effective_from'] }}{{ $rate['effective_to'] ? ' through '.$rate['effective_to'] : ' onward' }}.</span>
                                                        @endforeach
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                        @foreach ($entry->attendanceLogs as $attendanceLog)
                                            <p class="mt-2 text-sm text-amber-950">Attendance #{{ $attendanceLog->id }}: {{ str($attendanceLog->type)->replace('_', ' ')->title() }} at {{ $attendanceLog->timestamp->format('M d, Y g:i A') }}.</p>
                                        @endforeach
                                        @if ($entry->attendanceLogs->isNotEmpty() || count(array_intersect(['incomplete_attendance_requires_review', 'duplicate_attendance_requires_review', 'attendance_already_used_or_paid'], $entry->review_flags ?? [])) > 0)
                                            <a href="{{ route('employer.attendance', ['employee_id' => $entry->user_id, 'from' => $activePeriod->period_start->toDateString(), 'to' => $activePeriod->period_end->toDateString()]) }}" class="mt-2 inline-flex min-h-10 items-center font-semibold text-amber-950 underline underline-offset-2">Review this employee’s attendance in the period</a>
                                        @endif
                                        <a href="{{ route('employer.payroll', ['section' => 'settings']) }}" class="mt-2 ml-4 inline-flex min-h-10 items-center font-semibold text-amber-950 underline underline-offset-2">Review payroll profile and rate history</a>
                                    </div>
                                @endif

                                <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <div><dt class="text-sm text-slate-600">Gross pay</dt><dd class="mt-1 font-medium text-slate-900">{{ $company->formatMoney($entry->gross_pay) }}</dd></div>
                                    <div><dt class="text-sm text-slate-600">Employee deductions</dt><dd class="mt-1 font-medium text-slate-900">{{ $company->formatMoney($entry->employee_deductions) }}</dd></div>
                                    <div><dt class="text-sm text-slate-600">Employer contributions</dt><dd class="mt-1 font-medium text-slate-900">{{ $company->formatMoney($entry->employer_contributions) }}</dd></div>
                                    <div><dt class="text-sm font-semibold text-slate-700">Take-home pay</dt><dd class="mt-1 text-[1.375rem] font-bold text-emerald-800">{{ $company->formatMoney($entry->net_pay) }}</dd></div>
                                </dl>

                                <details class="mt-4 border-t border-slate-200 pt-3">
                                    <summary class="min-h-11 cursor-pointer py-1 text-base font-semibold text-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700">View earnings and deductions</summary>
                                    @if ($entry->lines->isNotEmpty())
                                        <ul class="mt-2 divide-y divide-slate-100 text-base">
                                            @foreach ($entry->lines as $line)
                                                <li class="flex flex-col justify-between gap-1 py-2 sm:flex-row sm:items-center sm:gap-4">
                                                    <span class="font-medium text-slate-800">{{ $line->label }} <span class="font-normal text-slate-600">({{ ['earning' => 'Earning', 'employee_deduction' => 'Employee deduction', 'employer_contribution' => 'Employer contribution'][$line->category] ?? 'Payroll item' }})</span></span>
                                                    <span class="font-medium text-slate-900">{{ $company->formatMoney($line->amount) }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="mt-2 text-base text-slate-600">No itemized lines were returned for this employee.</p>
                                    @endif
                                </details>

                                @if ($payrollSection === 'payments')
                                <div class="mt-4 border-t border-slate-200 pt-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h5 class="text-base font-semibold text-slate-900">Payment status</h5>
                                        <span class="inline-flex rounded-full border px-2.5 py-1 text-sm font-semibold {{ $paymentTone }}">{{ $paymentLabels[$entry->payment_status] ?? 'Unpaid' }}</span>
                                        <span class="text-sm text-slate-600">Paid {{ $company->formatMoney($entry->amount_paid) }} · Remaining {{ $company->formatMoney($entry->remaining_balance) }}</span>
                                    </div>

                                    @if ($entry->payments->isNotEmpty())
                                        <ul class="mt-2 space-y-1 text-sm text-slate-600">
                                            @foreach ($entry->payments as $payment)
                                                <li>{{ $payment->payment_date->format('M d, Y') }} · {{ $company->formatMoney($payment->amount) }} · {{ ['manual_cash' => 'Cash', 'manual_bank_deposit' => 'Bank deposit', 'manual_cheque' => 'Cheque'][$payment->method] ?? 'Manual payment' }}@if ($payment->reference_number) · Reference {{ $payment->reference_number }}@endif</li>
                                            @endforeach
                                        </ul>
                                    @endif

                                    @if (in_array($activePeriod->status, ['approved', 'partially_paid'], true) && $entry->remaining_balance > 0)
                                        <form method="POST" action="{{ route('employer.payroll.payments.store', ['payrollEntry' => $entry, 'section' => 'payments']) }}" data-submit-once data-confirm="Record this manual payment for {{ data_get($entry->calculation_snapshot, 'employee_snapshot.name', $entry->employee?->name ?? 'this employee') }}?" class="mt-3 grid gap-3 rounded-lg bg-slate-50 p-3 sm:grid-cols-2 xl:grid-cols-3">
                                            @csrf
                                            <input type="hidden" name="_payroll_entry_id" value="{{ $entry->id }}">
                                            <label for="payment_amount_{{ $entry->id }}" class="text-base font-semibold text-slate-900">Payment amount
                                                <input id="payment_amount_{{ $entry->id }}" name="amount" type="number" min="0.01" max="{{ $entry->remaining_balance }}" step="0.01" value="{{ $showPaymentErrors ? old('amount') : $entry->remaining_balance }}" required class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                                @if ($showPaymentErrors) @error('amount') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror @endif
                                            </label>
                                            <label for="payment_method_{{ $entry->id }}" class="text-base font-semibold text-slate-900">Payment method
                                                <select id="payment_method_{{ $entry->id }}" name="method" required class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                                    <option value="manual_cash" @selected(($showPaymentErrors ? old('method') : null) === 'manual_cash')>Cash</option>
                                                    <option value="manual_bank_deposit" @selected(($showPaymentErrors ? old('method') : null) === 'manual_bank_deposit')>Bank deposit</option>
                                                    <option value="manual_cheque" @selected(($showPaymentErrors ? old('method') : null) === 'manual_cheque')>Cheque</option>
                                                </select>
                                                @if ($showPaymentErrors) @error('method') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror @endif
                                            </label>
                                            <label for="payment_date_{{ $entry->id }}" class="text-base font-semibold text-slate-900">Payment date
                                                <input id="payment_date_{{ $entry->id }}" name="payment_date" type="date" value="{{ $showPaymentErrors ? old('payment_date') : now()->toDateString() }}" required class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                                @if ($showPaymentErrors) @error('payment_date') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror @endif
                                            </label>
                                            <label for="payment_reference_{{ $entry->id }}" class="text-base font-semibold text-slate-900">Reference <span class="font-normal text-slate-600">(optional)</span>
                                                <input id="payment_reference_{{ $entry->id }}" name="reference_number" type="text" maxlength="120" value="{{ $showPaymentErrors ? old('reference_number') : '' }}" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                                @if ($showPaymentErrors) @error('reference_number') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror @endif
                                            </label>
                                            <div class="flex items-end sm:col-span-2 xl:col-span-1">
                                                <button type="submit" data-loading-label="Recording..." class="min-h-11 w-full rounded-lg border border-emerald-700 bg-emerald-700 px-3 py-2 text-base font-semibold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2">Record payment</button>
                                            </div>
                                        </form>
                                    @elseif ($activePeriod->status === 'paid')
                                        <p class="mt-3 text-base font-medium text-emerald-800">This employee's payroll is fully paid.</p>
                                    @endif
                                </div>
                                @else
                                    <a href="{{ route('employer.payroll', ['section' => 'payments', 'period_id' => $activePeriod->id]) }}" class="mt-4 inline-flex min-h-10 items-center border-t border-slate-200 pt-3 text-sm font-semibold text-emerald-800 underline-offset-2 hover:underline">View payment history and balances</a>
                                @endif
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-8 text-center">
                                <p class="font-semibold text-slate-900">No employee entries are available for this payroll period.</p>
                                <p class="mt-1 text-base text-slate-600">Check payroll eligibility, matching pay frequency, effective rates, and approved basic pay for a 13th-month run.</p>
                                <a href="{{ route('employer.payroll', ['section' => 'settings']) }}" class="mt-3 inline-flex min-h-11 items-center rounded-lg border border-emerald-700 px-4 py-2 text-base font-semibold text-emerald-800 hover:bg-emerald-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700">Review Employee Payroll Settings</a>
                            </div>
                        @endforelse
                        @if ($entries->hasPages())
                            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">{{ $entries->links() }}</div>
                        @endif
                    @endif
                </div>
            @else
                <div id="payments" class="scroll-mt-24 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="employer-section-heading">Select a payroll period</h3>
                    <p class="mt-1 text-base text-slate-600">Choose a period to view its calculation details, review status, and payment actions.</p>
                    <a href="{{ route('employer.payroll', ['section' => 'periods']) }}" class="mt-3 inline-flex min-h-11 items-center font-semibold text-emerald-800 underline-offset-2 hover:underline">Open payroll periods</a>
                </div>
            @endif
        </section>
        @endif

        @if ($payrollSection === 'settings')
        <section id="employee-settings" class="scroll-mt-24 rounded-xl border border-slate-200 bg-white shadow-sm">
            <details @if ($errors->hasAny($settingsErrorFields)) open @endif>
                <summary class="flex min-h-16 cursor-pointer list-none flex-wrap items-center justify-between gap-3 px-5 py-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-700">
                    <span>
                        <span class="block text-[1.375rem] font-semibold text-slate-950">Employee Payroll Settings</span>
                        <span class="mt-1 block text-base font-normal text-slate-600">Edit compensation, eligibility, and recurring items when needed.</span>
                    </span>
                    <span class="text-base font-semibold text-emerald-800">Open settings</span>
                </summary>
                <div class="space-y-3 border-t border-slate-200 p-4 sm:p-5">
                    @if ($employees->isEmpty())
                        <p class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-base text-slate-700">There are no employees to configure yet. Add an employee before setting up payroll.</p>
                    @else
                        <details @if ($errors->hasAny(['salary_type', 'rate', 'pay_frequency', 'effective_from', 'payment_method', 'standard_hours_per_day', 'standard_work_days_per_month'])) open @endif class="rounded-lg border border-slate-200">
                            <summary class="cursor-pointer px-4 py-4 font-semibold text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-700">
                                <span class="block text-[1.375rem] font-semibold">Pay Profile</span>
                                <span class="mt-1 block text-base font-normal text-slate-600">Set the employee's pay basis, rate, frequency, and effective date.</span>
                            </summary>
                            <div class="border-t border-slate-200 p-4">
                                <form method="POST" action="{{ route('employer.payroll.profiles.update', ['section' => 'settings']) }}" data-submit-once data-pay-profile-form data-restored="{{ session()->hasOldInput('salary_type') || session()->hasOldInput('rate') || session()->hasOldInput('pay_frequency') ? 'true' : 'false' }}" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    @csrf
                                    @method('PATCH')
                                    <label for="pay_profile_employee" class="text-base font-semibold text-slate-900">Employee
                                        <select id="pay_profile_employee" name="user_id" required class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                            @foreach ($employees as $employee)
                                                <option value="{{ $employee->id }}" data-salary-type="{{ $employee->payrollProfile?->salary_type ?? 'hourly' }}" data-current-rate="{{ $employee->payroll_rate_default ?? '' }}" data-pay-frequency="{{ $employee->payrollProfile?->pay_frequency ?? 'monthly' }}" data-payment-method="{{ $employee->payment_method ?? 'manual_cash' }}" data-standard-hours="{{ $employee->payrollProfile?->standard_hours_per_day ?? 8 }}" data-standard-days="{{ $employee->payrollProfile?->standard_work_days_per_month ?? 22 }}" @selected((string) old('user_id') === (string) $employee->id)>{{ $employee->name }} ({{ str($employee->status)->title() }})</option>
                                            @endforeach
                                        </select>
                                        @error('user_id') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                                    </label>
                                    <label for="salary_type" class="text-base font-semibold text-slate-900">Salary type
                                        <select id="salary_type" name="salary_type" required class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                            @foreach (['hourly' => 'Hourly', 'daily' => 'Daily', 'monthly' => 'Monthly'] as $value => $label)
                                                <option value="{{ $value }}" @selected(old('salary_type', 'hourly') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('salary_type') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                                    </label>
                                    <label for="rate" class="text-base font-semibold text-slate-900">Rate per selected salary type
                                        <input id="rate" name="rate" type="number" inputmode="decimal" step="0.01" min="0" max="99999999.99" value="{{ old('rate') }}" required class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                        @error('rate') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                                    </label>
                                    <label for="pay_frequency" class="text-base font-semibold text-slate-900">Pay frequency
                                        <select id="pay_frequency" name="pay_frequency" required class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                            @foreach ($frequencyLabels as $value => $label)
                                                <option value="{{ $value }}" @selected(old('pay_frequency', 'monthly') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('pay_frequency') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                                    </label>
                                    <label for="effective_from" class="text-base font-semibold text-slate-900">Effective from
                                        <input id="effective_from" name="effective_from" type="date" value="{{ old('effective_from', now()->toDateString()) }}" required class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                        @error('effective_from') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                                    </label>
                                    <div class="flex items-end">
                                        <button type="submit" data-loading-label="Saving profile..." class="min-h-11 w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-base font-semibold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2">Save pay profile</button>
                                    </div>
                                    <details class="rounded-lg border border-slate-200 p-3 sm:col-span-2 xl:col-span-3">
                                        <summary class="cursor-pointer text-base font-semibold text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700">Advanced work and payment settings</summary>
                                        <div class="mt-3 grid gap-4 sm:grid-cols-3">
                                            <label for="standard_hours_per_day" class="text-base font-semibold text-slate-900">Standard hours per workday
                                                <input id="standard_hours_per_day" name="standard_hours_per_day" type="number" min="1" max="24" value="{{ old('standard_hours_per_day', 8) }}" class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                                @error('standard_hours_per_day') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                                            </label>
                                            <label for="standard_work_days_per_month" class="text-base font-semibold text-slate-900">Standard workdays per month
                                                <input id="standard_work_days_per_month" name="standard_work_days_per_month" type="number" min="1" max="31" value="{{ old('standard_work_days_per_month', 22) }}" class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                                @error('standard_work_days_per_month') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                                            </label>
                                            <label for="payment_method" class="text-base font-semibold text-slate-900">Preferred manual payment method
                                                <select id="payment_method" name="payment_method" class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                                    <option value="manual_cash" @selected(old('payment_method', 'manual_cash') === 'manual_cash')>Cash</option>
                                                    <option value="manual_bank_deposit" @selected(old('payment_method') === 'manual_bank_deposit')>Bank deposit</option>
                                                    <option value="manual_cheque" @selected(old('payment_method') === 'manual_cheque')>Cheque</option>
                                                </select>
                                                @error('payment_method') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                                            </label>
                                        </div>
                                    </details>
                                </form>
                                <p class="mt-3 text-sm text-slate-600">A rate change takes effect on the date you choose. Finalized payroll snapshots keep their original rates.</p>
                            </div>
                        </details>

                        <details @if ($errors->hasAny(['payroll_eligible', 'overtime_eligible', 'rank_and_file', 'sss_enabled', 'philhealth_enabled', 'pagibig_enabled', 'withholding_tax_enabled'])) open @endif class="rounded-lg border border-slate-200">
                            <summary class="cursor-pointer px-4 py-4 font-semibold text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-700">
                                <span class="block text-[1.375rem] font-semibold">Eligibility and Contributions</span>
                                <span class="mt-1 block text-base font-normal text-slate-600">Choose whether payroll and statutory contribution rules apply.</span>
                            </summary>
                            <div class="border-t border-slate-200 p-4">
                                <form method="POST" action="{{ route('employer.payroll.profiles.update', ['section' => 'settings']) }}" data-submit-once data-eligibility-form data-restored="{{ session()->hasOldInput('payroll_eligible') || session()->hasOldInput('sss_enabled') || session()->hasOldInput('withholding_tax_enabled') ? 'true' : 'false' }}" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    @csrf
                                    @method('PATCH')
                                    <label for="eligibility_employee" class="text-base font-semibold text-slate-900 sm:col-span-2 xl:col-span-3">Employee
                                        <select id="eligibility_employee" name="user_id" required class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700 sm:max-w-xl">
                                            @foreach ($employees as $employee)
                                                <option value="{{ $employee->id }}" data-payroll-eligible="{{ $employee->payrollProfile ? (int) $employee->payrollProfile->payroll_eligible : '' }}" data-overtime-eligible="{{ $employee->payrollProfile ? (int) $employee->payrollProfile->overtime_eligible : '' }}" data-rank-and-file="{{ $employee->payrollProfile ? (int) $employee->payrollProfile->rank_and_file : '' }}" data-sss-enabled="{{ $employee->payrollProfile ? (int) $employee->payrollProfile->sss_enabled : '' }}" data-philhealth-enabled="{{ $employee->payrollProfile ? (int) $employee->payrollProfile->philhealth_enabled : '' }}" data-pagibig-enabled="{{ $employee->payrollProfile ? (int) $employee->payrollProfile->pagibig_enabled : '' }}" data-withholding-tax-enabled="{{ $employee->payrollProfile ? (int) $employee->payrollProfile->withholding_tax_enabled : '' }}" @selected((string) old('user_id') === (string) $employee->id)>{{ $employee->name }} ({{ str($employee->status)->title() }})</option>
                                            @endforeach
                                        </select>
                                        @error('user_id') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                                    </label>
                                    @foreach ([
                                        'payroll_eligible' => ['Include in payroll', 'Employees turned off here are excluded from payroll runs.'],
                                        'overtime_eligible' => ['Overtime eligible', 'Long shifts are still flagged for employer review; overtime calculations use backend-approved inputs.'],
                                        'rank_and_file' => ['Rank-and-file employee', 'This setting determines inclusion in the 13th-month calculation.'],
                                        'sss_enabled' => ['SSS contribution', 'Applies the configured SSS employee and employer shares.'],
                                        'philhealth_enabled' => ['PhilHealth contribution', 'Applies the configured PhilHealth contribution share.'],
                                        'pagibig_enabled' => ['Pag-IBIG contribution', 'Applies the configured Pag-IBIG contribution share.'],
                                        'withholding_tax_enabled' => ['Withholding tax', 'Applies the configured BIR withholding tax rules.'],
                                    ] as $field => [$label, $description])
                                        <label for="{{ $field }}" class="block rounded-lg border border-slate-200 p-3 text-base font-semibold text-slate-900">{{ $label }}
                                            <select id="{{ $field }}" name="{{ $field }}" class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                                <option value="" @selected(old($field, '') === '')>Keep current setting</option>
                                                <option value="1" @selected((string) old($field, '') === '1')>Enabled</option>
                                                <option value="0" @selected((string) old($field, '') === '0')>Disabled</option>
                                            </select>
                                            <span class="mt-2 block text-sm font-normal leading-5 text-slate-600">{{ $description }}</span>
                                            @error($field) <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                                        </label>
                                    @endforeach
                                    <p class="text-base text-slate-600 sm:col-span-2 xl:col-span-3">Approved leave is read from existing leave records. There is no separate leave-eligibility setting in the current payroll profile.</p>
                                    <div class="sm:col-span-2 xl:col-span-3">
                                        <button type="submit" data-loading-label="Saving settings..." class="min-h-11 rounded-lg border border-emerald-700 px-4 py-2.5 text-base font-semibold text-emerald-900 hover:bg-emerald-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700">Save eligibility and contribution settings</button>
                                    </div>
                                </form>
                            </div>
                        </details>

                        <details @if ($errors->hasAny(['category', 'code', 'label', 'amount', 'taxable', 'authorization_reference', 'effective_to'])) open @endif class="rounded-lg border border-slate-200">
                            <summary class="cursor-pointer px-4 py-4 font-semibold text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-700">
                                <span class="block text-[1.375rem] font-semibold">Recurring Earnings and Deductions</span>
                                <span class="mt-1 block text-base font-normal text-slate-600">Add an item that applies to payroll periods within its effective dates.</span>
                            </summary>
                            <div class="border-t border-slate-200 p-4">
                                <form method="POST" action="{{ route('employer.payroll.profile-items.store', ['section' => 'settings']) }}" data-submit-once data-recurring-item-form class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    @csrf
                                    <label for="item_employee" class="text-base font-semibold text-slate-900">Employee
                                        <select id="item_employee" name="user_id" required class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                            @foreach ($employees as $employee)
                                                <option value="{{ $employee->id }}" @selected((string) old('user_id') === (string) $employee->id)>{{ $employee->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('user_id') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                                    </label>
                                    <label for="category" class="text-base font-semibold text-slate-900">Item type
                                        <select id="category" name="category" required class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                            <option value="earning" @selected(old('category', 'earning') === 'earning')>Earning</option>
                                            <option value="deduction" @selected(old('category') === 'deduction')>Authorized deduction</option>
                                        </select>
                                        @error('category') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                                    </label>
                                    <label for="item_code" class="text-base font-semibold text-slate-900">Item reference
                                        <input id="item_code" name="code" pattern="[A-Za-z0-9_-]+" maxlength="60" value="{{ old('code') }}" required placeholder="For example, meal_allowance" class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base placeholder:text-slate-600 focus:border-emerald-700 focus:ring-emerald-700">
                                        @error('code') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                                    </label>
                                    <label for="item_label" class="text-base font-semibold text-slate-900">Payslip description
                                        <input id="item_label" name="label" maxlength="120" value="{{ old('label') }}" required class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                        @error('label') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                                    </label>
                                    <label for="item_amount" class="text-base font-semibold text-slate-900">Amount per payroll period
                                        <input id="item_amount" name="amount" type="number" inputmode="decimal" min="0.01" max="99999999.99" step="0.01" value="{{ old('amount') }}" required class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                        @error('amount') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                                    </label>
                                    <label for="item_effective_from" class="text-base font-semibold text-slate-900">Effective from
                                        <input id="item_effective_from" name="effective_from" type="date" value="{{ old('effective_from', now()->toDateString()) }}" required class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                        @error('effective_from') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                                    </label>
                                    <label for="item_effective_to" class="text-base font-semibold text-slate-900">Effective through <span class="font-normal text-slate-600">(optional)</span>
                                        <input id="item_effective_to" name="effective_to" type="date" value="{{ old('effective_to') }}" class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                                        @error('effective_to') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                                    </label>
                                    <label for="authorization_reference" class="text-base font-semibold text-slate-900">Deduction authorization reference
                                        <input id="authorization_reference" name="authorization_reference" maxlength="120" value="{{ old('authorization_reference') }}" placeholder="Required for deductions" class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base placeholder:text-slate-600 focus:border-emerald-700 focus:ring-emerald-700">
                                        @error('authorization_reference') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                                    </label>
                                    <label id="taxable-earning-label" class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 px-3 text-base font-semibold text-slate-900">
                                        <input id="item_taxable" name="taxable" value="1" type="checkbox" @checked(old('taxable', '1') === '1') class="h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-700">
                                        Taxable earning
                                    </label>
                                    @error('taxable') <span class="text-sm text-red-700">{{ $message }}</span> @enderror
                                    <div class="sm:col-span-2 xl:col-span-3">
                                        <button type="submit" data-loading-label="Saving item..." class="min-h-11 rounded-lg bg-emerald-700 px-4 py-2.5 text-base font-semibold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2">Save recurring item</button>
                                    </div>
                                </form>
                                <p class="mt-3 text-sm text-slate-600">Deductions need an authorization reference. Tax treatment and effective dates are saved with the item and applied by the backend.</p>
                            </div>
                        </details>
                    @endif
                </div>
            </details>
        </section>
        @endif

        @if ($payrollSection === 'overview')
        @if ($deadlines->isNotEmpty())
            <details class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <summary class="cursor-pointer px-5 py-4 text-[1.375rem] font-semibold text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-700">Upcoming pay dates <span class="text-base font-normal text-slate-600">({{ $deadlines->count() }})</span></summary>
                <ul class="divide-y divide-slate-100 border-t border-slate-200 px-5">
                    @foreach ($deadlines as $deadline)
                        <li class="flex flex-col gap-1 py-3 text-base sm:flex-row sm:items-center sm:justify-between">
                            <a href="{{ route('employer.payroll', ['period_id' => $deadline->id]) }}" class="font-medium text-emerald-800 underline-offset-2 hover:underline">{{ $deadline->period_start->format('M d') }} - {{ $deadline->period_end->format('M d, Y') }}</a>
                            <span class="text-slate-600">Pay date {{ $deadline->pay_date?->format('M d, Y') ?? 'Not set' }}</span>
                            <span class="font-semibold text-slate-900">{{ $company->formatMoney($deadline->net_total) }} · {{ $statusLabels[$deadline->status] ?? 'Needs review' }}</span>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif

        <p class="text-sm leading-5 text-slate-600">Payroll totals and statutory contributions come from the backend calculation snapshot. Review warnings before approval. Attendance gaps, overtime inputs, leave policy, and statutory exceptions may require employer review.</p>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('form[data-submit-once]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    if (form.dataset.submitting === 'true') {
                        event.preventDefault();
                        return;
                    }

                    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
                        event.preventDefault();
                        return;
                    }

                    form.dataset.submitting = 'true';
                    const button = event.submitter || form.querySelector('button[type="submit"]');
                    if (button) {
                        button.disabled = true;
                        button.setAttribute('aria-busy', 'true');
                        if (button.dataset.loadingLabel) button.textContent = button.dataset.loadingLabel;
                    }
                });
            });

            const periodForm = document.getElementById('period-create-form');
            if (periodForm) {
                const start = periodForm.elements.namedItem('period_start');
                const end = periodForm.elements.namedItem('period_end');
                const validatePeriodDates = () => {
                    end.min = start.value || '';
                    end.setCustomValidity(start.value && end.value && end.value < start.value
                        ? 'Period end cannot be before period start.'
                        : '');
                };
                start.addEventListener('change', validatePeriodDates);
                end.addEventListener('change', validatePeriodDates);
                validatePeriodDates();
            }

            const profileDefaults = {
                salary_type: 'salaryType',
                rate: 'currentRate',
                pay_frequency: 'payFrequency',
                payment_method: 'paymentMethod',
                standard_hours_per_day: 'standardHours',
                standard_work_days_per_month: 'standardDays',
            };
            document.querySelectorAll('form[data-pay-profile-form], form[data-eligibility-form]').forEach((form) => {
                const employeeSelect = form.elements.namedItem('user_id');
                const applyEmployeeDefaults = (force = false) => {
                    const option = employeeSelect?.selectedOptions?.[0];
                    if (!option || (form.dataset.restored === 'true' && !force)) return;

                    const fields = form.matches('[data-pay-profile-form]')
                        ? profileDefaults
                        : {
                            payroll_eligible: 'payrollEligible',
                            overtime_eligible: 'overtimeEligible',
                            rank_and_file: 'rankAndFile',
                            sss_enabled: 'sssEnabled',
                            philhealth_enabled: 'philhealthEnabled',
                            pagibig_enabled: 'pagibigEnabled',
                            withholding_tax_enabled: 'withholdingTaxEnabled',
                        };

                    Object.entries(fields).forEach(([fieldName, datasetName]) => {
                        const field = form.elements.namedItem(fieldName);
                        if (field && option.dataset[datasetName] !== undefined) field.value = option.dataset[datasetName];
                    });
                };

                employeeSelect?.addEventListener('change', () => applyEmployeeDefaults(true));
                applyEmployeeDefaults();
            });

            const recurringForm = document.querySelector('[data-recurring-item-form]');
            if (recurringForm) {
                const category = recurringForm.elements.namedItem('category');
                const authorizationReference = recurringForm.elements.namedItem('authorization_reference');
                const taxableEarning = recurringForm.elements.namedItem('taxable');
                const taxableEarningLabel = document.getElementById('taxable-earning-label');
                const updateAuthorizationRequirement = () => {
                    const isDeduction = category.value === 'deduction';
                    authorizationReference.required = isDeduction;
                    taxableEarningLabel.hidden = isDeduction;
                    taxableEarning.disabled = isDeduction;
                    if (isDeduction) taxableEarning.checked = false;
                };
                category.addEventListener('change', updateAuthorizationRequirement);
                updateAuthorizationRequirement();
            }
        });
    </script>
@endsection
