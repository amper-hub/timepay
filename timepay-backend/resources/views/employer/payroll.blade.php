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
                <p class="mt-3 text-3xl font-bold text-blue-600">{{ number_format($employeePayroll->sum('hours_worked'), 2) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Pending Pay Calculations</p>
                <p class="mt-3 text-3xl font-bold text-emerald-600">{{ number_format($employeePayroll->sum('pending_pay'), 2) }}</p>
            </div>
        </div>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-base font-semibold text-slate-950">Employee Contract Configurations</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Current period: {{ $periodStart->format('M d, Y') }} to {{ $periodEnd->format('M d, Y') }}.
                </p>
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
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-950 text-sm font-bold text-white">
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
                                    <input
                                        form="{{ $formId }}"
                                        name="hourly_rate"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="999999.99"
                                        value="{{ number_format($payroll['hourly_rate'], 2, '.', '') }}"
                                        class="w-32 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10"
                                        required
                                    >
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <select form="{{ $formId }}" name="payment_method" class="w-44 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10" required>
                                        <option value="manual_cash" @selected($payroll['payment_method'] === 'manual_cash')>Personal/Cash</option>
                                        <option value="digital_payout" @selected($payroll['payment_method'] === 'digital_payout')>Digital Gateway</option>
                                    </select>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <p class="text-sm font-semibold text-slate-950">{{ number_format($payroll['pending_pay'], 2) }}</p>
                                    <p class="text-xs text-slate-500">Based on verified paired punches</p>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <button form="{{ $formId }}" type="submit" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                                        Save Configurations
                                    </button>
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
@endsection
