@extends('layouts.employer')

@section('title', 'Payroll Management - TimePay Employer Portal')
@section('header_title', 'Payroll Management')

@section('content')
    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Employees</p>
                <p class="mt-3 text-3xl font-bold text-slate-950">{{ $employeePayroll->count() }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Verified Hours</p>
                <p class="mt-3 text-3xl font-bold text-teal-600">{{ number_format($employeePayroll->sum('hours_worked'), 2) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Pending Pay Calculations</p>
                <p class="mt-3 text-3xl font-bold text-emerald-600">{{ $company->formatMoney($employeePayroll->sum('pending_pay')) }}</p>
            </div>
        </div>

        <section class="rounded-lg border border-emerald-100 bg-emerald-50 px-5 py-4">
            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.75fr)] lg:items-center">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Quick Guide: Setting Hourly Base Pay</h2>
                    <p class="mt-1 text-sm font-medium text-slate-700">
                        Daily Minimum Wage &divide; 8 Hours = Hourly Base Pay
                    </p>
                </div>
                <div class="space-y-2 text-sm">
                    <ul class="list-disc space-y-1 pl-5 text-emerald-700">
                        <li>
                            If your region's minimum wage is <span class="font-semibold">{{ $company->formatMoney(350) }}/day</span>, the hourly base pay should be set to <span class="font-semibold">{{ $company->formatMoney(43.75) }}</span> ({{ $company->formatMoney(350) }} &divide; 8).
                        </li>
                    </ul>
                    <p class="text-xs leading-5 text-slate-500">
                        Note: Minimum wage rates vary by region and industry (e.g., Caraga vs. NCR). Check your local RTWPB for current rates.
                    </p>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">Employee Contract Configurations</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Current period: {{ $periodStart->format('M d, Y') }} to {{ $periodEnd->format('M d, Y') }}.
                    </p>
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

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Employee</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Hours</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Hourly Base Pay</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Payment Method</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Pending Pay Calculations</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($employeePayroll as $payroll)
                            @php($formId = 'payroll-form-' . $payroll['employee']->id)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <form id="{{ $formId }}" method="POST" action="{{ route('employer.payroll.update', $payroll['employee']) }}">
                                        @csrf
                                    </form>
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-600 text-sm font-bold text-white">
                                            {{ strtoupper(substr($payroll['employee']->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-950">{{ $payroll['employee']->name }}</p>
                                            <p class="text-xs text-slate-500">{{ $payroll['employee']->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-700">
                                    {{ number_format($payroll['hours_worked'], 2) }} hrs
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="relative w-36">
                                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500">{{ $company->currencySymbol() }}</span>
                                        <input
                                            form="{{ $formId }}"
                                            name="hourly_rate"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="999999.99"
                                            value="{{ number_format($payroll['hourly_rate'], 2, '.', '') }}"
                                            class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-7 pr-3 text-sm shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15"
                                            required
                                        >
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <select form="{{ $formId }}" name="payment_method" class="w-48 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15" required>
                                        <option value="manual_cash" @selected($payroll['payment_method'] === 'manual_cash')>Cash (Manual)</option>
                                        <option value="manual_bank_deposit" @selected($payroll['payment_method'] === 'manual_bank_deposit')>Bank Deposit (Manual)</option>
                                        <option value="manual_cheque" @selected($payroll['payment_method'] === 'manual_cheque')>Cheque (Manual)</option>
                                    </select>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <p class="text-sm font-semibold text-emerald-700">{{ $company->formatMoney($payroll['pending_pay']) }}</p>
                                    <p class="text-xs text-slate-500">Based on verified paired punches</p>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center justify-end gap-3">
                                        <button form="{{ $formId }}" type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                                            Save Configurations
                                        </button>
                                        @if ($payroll['pending_pay'] > 0)
                                            <form
                                                method="POST"
                                                action="{{ route('employer.payroll.mark-as-paid', $payroll['employee']) }}"
                                                onsubmit="return confirm('Are you sure you want to mark {{ $company->formatMoney($payroll['pending_pay']) }} as paid? This confirms you have manually transferred the funds offline.');"
                                            >
                                                @csrf
                                                <input type="hidden" name="period_start" value="{{ $periodStart->toDateString() }}">
                                                <input type="hidden" name="period_end" value="{{ $periodEnd->toDateString() }}">
                                                <button type="submit" class="rounded-lg border border-emerald-600 px-4 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-50">
                                                    Mark as Paid
                                                </button>
                                            </form>
                                        @endif
                                        <form
                                            method="POST"
                                            action="{{ route('employer.employees.destroy', $payroll['employee']) }}"
                                            onsubmit="return confirm('Are you sure you want to remove this employee? This action cannot be undone.');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm font-medium text-red-600 transition hover:text-red-800">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No employee payroll records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @include('employer.partials.add-employee-modal')
@endsection
