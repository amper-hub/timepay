@extends('layouts.employer')

@section('title', 'Payroll Management - TimePay')
@section('header_title', 'Payroll Management')

@section('content')
    @php($activePeriod = $selectedPeriod)
    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                <p class="font-semibold">Please review the following:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Payroll overview">
            @php($cards = [
                ['Employees', $activePeriod?->entries_count ?? 0, 'employee_count'],
                ['Gross payroll', $activePeriod ? $company->formatMoney($activePeriod->gross_total) : $company->formatMoney(0), 'gross'],
                ['Employee deductions', $activePeriod ? $company->formatMoney($activePeriod->deduction_total) : $company->formatMoney(0), 'deductions'],
                ['Employer contributions', $activePeriod ? $company->formatMoney($activePeriod->employer_contribution_total) : $company->formatMoney(0), 'employer'],
                ['Net payroll', $activePeriod ? $company->formatMoney($activePeriod->net_total) : $company->formatMoney(0), 'net'],
                ['Employer payroll cost', $activePeriod ? $company->formatMoney($activePeriod->employer_cost_total) : $company->formatMoney(0), 'cost'],
            ])
            @foreach ($cards as [$label, $value, $key])
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">{{ $label }}{{ $activePeriod ? ' · selected period' : '' }}</p>
                    <p class="mt-3 text-2xl font-bold text-slate-950">{{ $value }}</p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Create a payroll period</h2>
                        <p class="mt-1 text-sm text-slate-500">A reporting view never creates a payroll run. Yearly runs calculate 13th-month pay from approved basic-pay snapshots.</p>
                    </div>
                </div>
                <form class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5" method="POST" action="{{ route('employer.payroll.periods.store') }}">
                    @csrf
                    <label class="text-sm font-medium text-slate-700">Period start
                        <input type="date" name="period_start" value="{{ old('period_start') }}" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-emerald-600 focus:ring-emerald-600">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Period end
                        <input type="date" name="period_end" value="{{ old('period_end') }}" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-emerald-600 focus:ring-emerald-600">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Pay date
                        <input type="date" name="pay_date" value="{{ old('pay_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-emerald-600 focus:ring-emerald-600">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Payroll frequency
                        <select name="frequency" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-emerald-600 focus:ring-emerald-600">
                            <option value="monthly">Monthly</option><option value="semimonthly">Semimonthly</option><option value="weekly">Weekly</option>
                            <option value="daily">Daily</option><option value="hourly">Hourly</option><option value="yearly">Year-end 13th month</option>
                        </select>
                    </label>
                    <div class="flex items-end"><button class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2">Create period</button></div>
                </form>
            </div>

            <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Payroll status</h2>
                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    @foreach (['draft' => 'Draft', 'pending_review' => 'Awaiting review', 'approved' => 'Approved', 'partially_paid' => 'Partially paid', 'paid' => 'Paid'] as $status => $label)
                        <div class="rounded-xl bg-slate-50 px-3 py-2"><span class="block text-slate-500">{{ $label }}</span><span class="mt-1 block font-semibold text-slate-900">{{ (int) ($statusCounts[$status] ?? 0) }}</span></div>
                    @endforeach
                </div>
            </aside>
        </section>

        <section class="grid gap-4 xl:grid-cols-3" aria-label="Employee payroll profiles and recurring items">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Employee pay profile</h2>
                <p class="mt-1 text-sm text-slate-500">Compensation changes are effective dated and cannot rewrite finalized payroll.</p>
                <form class="mt-4 grid gap-3 sm:grid-cols-2" method="POST" action="{{ route('employer.payroll.profiles.update') }}">
                    @csrf @method('PATCH')
                    <label class="text-sm font-medium text-slate-700">Employee
                        <select name="user_id" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            @foreach ($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }} ({{ $employee->status }})</option>@endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium text-slate-700">Salary type
                        <select name="salary_type" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm"><option value="hourly">Hourly</option><option value="daily">Daily</option><option value="monthly">Monthly</option></select>
                    </label>
                    <label class="text-sm font-medium text-slate-700">Rate
                        <input name="rate" type="number" step="0.01" min="0" max="99999999.99" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Pay frequency
                        <select name="pay_frequency" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm"><option value="hourly">Hourly</option><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="semimonthly">Semimonthly</option><option value="monthly" selected>Monthly</option></select>
                    </label>
                    <label class="text-sm font-medium text-slate-700">Effective from
                        <input name="effective_from" type="date" value="{{ now()->toDateString() }}" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <div class="flex items-end"><button class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Save pay profile</button></div>
                </form>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Eligibility and statutory settings</h2>
                <p class="mt-1 text-sm text-slate-500">Each setting can be left unchanged or explicitly enabled or disabled for the selected employee.</p>
                <form class="mt-4 grid gap-3" method="POST" action="{{ route('employer.payroll.profiles.update') }}">
                    @csrf @method('PATCH')
                    <label class="text-sm font-medium text-slate-700">Employee
                        <select name="user_id" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            @foreach ($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }} ({{ $employee->status }})</option>@endforeach
                        </select>
                    </label>
                    @foreach ([
                        'payroll_eligible' => 'Include in payroll',
                        'rank_and_file' => 'Rank-and-file employee',
                        'overtime_eligible' => 'Overtime eligible',
                        'sss_enabled' => 'SSS employee contribution',
                        'philhealth_enabled' => 'PhilHealth employee contribution',
                        'pagibig_enabled' => 'Pag-IBIG employee contribution',
                        'withholding_tax_enabled' => 'Withholding tax',
                    ] as $field => $label)
                        <label class="text-sm font-medium text-slate-700">{{ $label }}
                            <select name="{{ $field }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                <option value="" selected>Leave unchanged</option>
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </label>
                    @endforeach
                    <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Save eligibility settings</button>
                </form>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Recurring earnings or deductions</h2>
                <p class="mt-1 text-sm text-slate-500">Use an authorization reference for deductions. Tax treatment is explicit for each earning.</p>
                <form class="mt-4 grid gap-3 sm:grid-cols-2" method="POST" action="{{ route('employer.payroll.profile-items.store') }}">
                    @csrf
                    <label class="text-sm font-medium text-slate-700">Employee
                        <select name="user_id" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            @foreach ($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium text-slate-700">Item type
                        <select name="category" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm"><option value="earning">Earning</option><option value="deduction">Authorized deduction</option></select>
                    </label>
                    <label class="text-sm font-medium text-slate-700">Item code
                        <input name="code" pattern="[A-Za-z0-9_-]+" maxlength="60" required placeholder="meal_allowance" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Payslip label
                        <input name="label" maxlength="120" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Amount per payroll period
                        <input name="amount" type="number" min="0.01" max="99999999.99" step="0.01" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Effective from
                        <input name="effective_from" type="date" value="{{ now()->toDateString() }}" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Effective to (optional)
                        <input name="effective_to" type="date" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Deduction authorization reference
                        <input name="authorization_reference" maxlength="120" placeholder="Required for deductions" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700 sm:col-span-2"><input name="taxable" value="1" type="checkbox" checked class="rounded border-slate-300 text-emerald-600"> Taxable earning</label>
                    <div class="sm:col-span-2"><button class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Save recurring item</button></div>
                </form>
            </article>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <div><h2 class="text-lg font-semibold text-slate-950">Payroll periods</h2><p class="text-sm text-slate-500">Select a period to inspect its calculation and payment state.</p></div>
                <form class="flex flex-wrap gap-2" method="GET" action="{{ route('employer.payroll') }}">
                    <select name="period_status" aria-label="Filter periods by status" class="rounded-lg border-slate-300 text-sm">
                        <option value="">All statuses</option>
                        @foreach (['draft', 'pending_review', 'approved', 'partially_paid', 'paid', 'voided'] as $status)<option value="{{ $status }}" @selected(($filters['period_status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach
                    </select>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="Period start from" class="rounded-lg border-slate-300 text-sm">
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="Period end to" class="rounded-lg border-slate-300 text-sm">
                    <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Filter</button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><tr>
                        <th class="px-5 py-3">Period</th><th class="px-5 py-3">Frequency</th><th class="px-5 py-3">Employees</th><th class="px-5 py-3">Net payroll</th><th class="px-5 py-3">Paid</th><th class="px-5 py-3">Status</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($periods as $period)
                            <tr class="{{ $activePeriod?->id === $period->id ? 'bg-emerald-50/60' : 'hover:bg-slate-50' }}">
                                <td class="whitespace-nowrap px-5 py-3"><a href="{{ route('employer.payroll', ['period_id' => $period->id]) }}" class="font-semibold text-emerald-700 hover:underline">{{ $period->period_start->format('M d, Y') }} – {{ $period->period_end->format('M d, Y') }}</a>@if ($period->pay_date)<span class="block text-xs text-slate-500">Pay date {{ $period->pay_date->format('M d, Y') }}</span>@endif</td>
                                <td class="px-5 py-3 capitalize">{{ $period->frequency === 'yearly' ? '13th month' : $period->frequency }}</td>
                                <td class="px-5 py-3">{{ $period->entries_count }}</td>
                                <td class="px-5 py-3 font-semibold">{{ $company->formatMoney($period->net_total) }}</td>
                                <td class="px-5 py-3">{{ $company->formatMoney($period->paid_total) }}</td>
                                <td class="px-5 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ in_array($period->status, ['paid', 'approved'], true) ? 'bg-emerald-100 text-emerald-800' : ($period->status === 'voided' ? 'bg-slate-200 text-slate-700' : 'bg-amber-100 text-amber-800') }}">{{ str($period->status)->replace('_', ' ')->title() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-slate-500">No payroll periods match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-3">{{ $periods->links() }}</div>
        </section>

        @if ($activePeriod)
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Pay register · {{ $activePeriod->period_start->format('M d, Y') }} – {{ $activePeriod->period_end->format('M d, Y') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">Gross {{ $company->formatMoney($activePeriod->gross_total) }} · Deductions {{ $company->formatMoney($activePeriod->deduction_total) }} · Net {{ $company->formatMoney($activePeriod->net_total) }} · Employer cost {{ $company->formatMoney($activePeriod->employer_cost_total) }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($activePeriod->entries_count > 0)
                            <a href="{{ route('employer.payroll.export.xlsx', $activePeriod) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Excel register</a>
                            <a href="{{ route('employer.payroll.export.pdf', $activePeriod) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">PDF register</a>
                        @endif
                        @if (in_array($activePeriod->status, ['draft', 'pending_review'], true))
                            <form method="POST" action="{{ route('employer.payroll.calculate', $activePeriod) }}">@csrf<button class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">{{ $activePeriod->status === 'draft' ? 'Calculate payroll' : 'Recalculate draft' }}</button></form>
                        @endif
                        @if ($activePeriod->status === 'pending_review')
                            <form class="flex max-w-xl flex-wrap items-end gap-2" method="POST" action="{{ route('employer.payroll.approve', $activePeriod) }}" onsubmit="return confirm('Approve this payroll snapshot? Approved payroll cannot be recalculated.');">
                                @csrf
                                @if ($hasReviewFlags)
                                    <label class="min-w-64 flex-1 text-xs font-medium text-amber-900">Review note required because calculation flags are present
                                        <textarea name="review_note" minlength="10" maxlength="1000" required rows="2" class="mt-1 block w-full rounded-lg border-amber-300 text-sm" placeholder="Describe what you checked and why approval is appropriate."></textarea>
                                    </label>
                                @endif
                                <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-700">Approve payroll</button>
                            </form>
                        @endif
                        @if (in_array($activePeriod->status, ['draft', 'pending_review', 'approved'], true) && $activePeriod->payments_count === 0)
                            <form method="POST" action="{{ route('employer.payroll.void', $activePeriod) }}" onsubmit="return confirm('Void this payroll period? Its source attendance remains reserved for audit.');">@csrf<button class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Void</button></form>
                        @endif
                    </div>
                </div>

                @if ($activePeriod->status === 'pending_review')
                    <div class="border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-900">Review attendance flags, effective rates, and itemized deductions before approval. Approval locks the calculated snapshot.</div>
                @endif
                @if ($activePeriod->frequency === 'yearly')
                    <div class="border-b border-slate-200 bg-slate-50 px-5 py-3 text-sm text-slate-600">The 13th-month amount uses basic-pay lines from approved payroll runs for this calendar year. Allowances, overtime, leave, and prior employers are not included unless classified as basic pay.</div>
                @endif

                <form class="border-b border-slate-200 px-5 py-3" method="GET" action="{{ route('employer.payroll') }}">
                    <input type="hidden" name="period_id" value="{{ $activePeriod->id }}">
                    <label class="sr-only" for="employee-search">Search employees</label>
                    <input id="employee-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search employees in this period" class="w-full rounded-lg border-slate-300 text-sm sm:max-w-sm">
                    <label class="sr-only" for="employee-status">Filter employee status</label>
                    <select id="employee-status" name="employee_status" class="mt-2 rounded-lg border-slate-300 text-sm sm:mt-0">
                        <option value="">All employee statuses</option>
                        @foreach (['active', 'pending', 'suspended', 'inactive'] as $status)
                            <option value="{{ $status }}" @selected(($filters['employee_status'] ?? '') === $status)>{{ str($status)->title() }}</option>
                        @endforeach
                    </select>
                    <button class="ml-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">Search</button>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Employee</th><th class="px-5 py-3">Hours / days</th><th class="px-5 py-3">Gross</th><th class="px-5 py-3">Employee deductions</th><th class="px-5 py-3">Employer contributions</th><th class="px-5 py-3">Net</th><th class="px-5 py-3">Payment</th><th class="px-5 py-3">Review</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($entries as $entry)
                                @php($balance = $entry->remaining_balance)
                                <tr class="align-top">
                                    <td class="whitespace-nowrap px-5 py-4"><p class="font-semibold text-slate-950">{{ data_get($entry->calculation_snapshot, 'employee_snapshot.name', $entry->employee?->name ?? 'Employee') }}</p><p class="text-xs text-slate-500">{{ $entry->employee?->email }}</p></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ number_format($entry->worked_minutes / 60, 2) }} hrs<span class="block text-xs text-slate-500">{{ $entry->payable_days }} days</span></td>
                                    <td class="whitespace-nowrap px-5 py-4 font-medium">{{ $company->formatMoney($entry->gross_pay) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4">{{ $company->formatMoney($entry->employee_deductions) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4">{{ $company->formatMoney($entry->employer_contributions) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 font-semibold">{{ $company->formatMoney($entry->net_pay) }}</td>
                                    <td class="min-w-64 px-5 py-4">
                                        <p class="text-xs text-slate-500">Paid {{ $company->formatMoney($entry->amount_paid) }} · balance {{ $company->formatMoney($balance) }}</p>
                                        @if (in_array($activePeriod->status, ['approved', 'partially_paid'], true) && $balance > 0)
                                            <form class="mt-2 space-y-2" method="POST" action="{{ route('employer.payroll.payments.store', $entry) }}">
                                                @csrf
                                                <div class="flex gap-2"><input name="amount" type="number" min="0.01" max="{{ $balance }}" step="0.01" value="{{ $balance }}" class="w-28 rounded-lg border-slate-300 text-xs" aria-label="Payment amount">
                                                    <select name="method" class="min-w-36 rounded-lg border-slate-300 text-xs" aria-label="Payment method"><option value="manual_cash">Cash</option><option value="manual_bank_deposit">Bank deposit</option><option value="manual_cheque">Cheque</option></select>
                                                </div>
                                                <div class="flex gap-2"><input name="payment_date" type="date" value="{{ now()->toDateString() }}" required class="rounded-lg border-slate-300 text-xs" aria-label="Payment date"><input name="reference_number" type="text" maxlength="120" placeholder="Reference (optional)" class="min-w-36 rounded-lg border-slate-300 text-xs" aria-label="Payment reference"></div>
                                                <button class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">Record manual payment</button>
                                            </form>
                                        @endif
                                    </td>
                                    <td class="min-w-52 px-5 py-4">
                                        @if (($entry->review_flags ?? []) !== [])
                                            <ul class="space-y-1 text-xs text-amber-800">@foreach ($entry->review_flags as $flag)<li>• {{ str($flag)->replace('_', ' ')->ucfirst() }}</li>@endforeach</ul>
                                        @else
                                            <span class="text-xs font-medium text-emerald-700">No calculation flags</span>
                                        @endif
                                        <details class="mt-3"><summary class="cursor-pointer text-xs font-semibold text-slate-600">Earnings and deductions</summary><ul class="mt-2 space-y-1 text-xs text-slate-600">
                                            @foreach ($entry->lines as $line)<li class="flex justify-between gap-3"><span>{{ $line->label }} <span class="text-slate-400">({{ str($line->category)->replace('_', ' ') }})</span></span><span class="whitespace-nowrap">{{ $company->formatMoney($line->amount) }}</span></li>@endforeach
                                        </ul></details>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-5 py-10 text-center text-sm text-slate-500">No calculated employee entries are available for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($entries->hasPages())
                    <div class="border-t border-slate-200 px-5 py-3">{{ $entries->links() }}</div>
                @endif
            </section>
        @endif

        @if ($deadlines->isNotEmpty())
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Upcoming payroll deadlines</h2>
                <ul class="mt-3 divide-y divide-slate-100">
                    @foreach ($deadlines as $deadline)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm"><a href="{{ route('employer.payroll', ['period_id' => $deadline->id]) }}" class="font-medium text-emerald-700 hover:underline">{{ $deadline->period_start->format('M d') }}–{{ $deadline->period_end->format('M d, Y') }}</a><span class="text-slate-600">Pay by {{ $deadline->pay_date?->format('M d, Y') ?? 'date not set' }}</span><span class="font-semibold text-slate-950">{{ $company->formatMoney($deadline->net_total) }} · {{ str($deadline->status)->replace('_', ' ') }}</span></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <p class="text-xs leading-5 text-slate-500">Payroll rule snapshots currently use SSS, PhilHealth, Pag-IBIG, and BIR schedules stored with effective dates. Holidays, night shift premiums, break deductions, approved overtime, and statutory employee exceptions require company or employee inputs that the current attendance/profile schema does not hold; affected records are flagged for review. Verify each calculation before approval.</p>
    </div>
@endsection
