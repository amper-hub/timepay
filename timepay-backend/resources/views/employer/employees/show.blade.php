@extends('layouts.employer')

@section('title', 'Employee Payroll Settings - TimePay')
@section('header_title', 'Employee Profile')

@section('content')
    @php
        $profile = $employee->payrollProfile;
        $returnQuery = ['return_to' => 'employee_profile', 'employee_id' => $employee->id];
    @endphp
    <div class="mx-auto max-w-6xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ route('employer.employees.index') }}" class="text-sm font-semibold text-emerald-800 underline">&larr; Employees</a>
                <p class="mt-4 text-sm font-semibold text-emerald-700">Employee profile</p>
                <h2 class="employer-page-heading">{{ $employee->name }}</h2>
                <p class="employer-page-description">{{ $employee->email }} · Employee ID #{{ $employee->id }} · {{ str($employee->status)->title() }}</p>
            </div>
            <a href="{{ route('employer.payroll') }}" class="employer-secondary-button">Open Payroll</a>
        </header>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-base text-emerald-800" role="status">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-base text-red-800" role="alert">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-base text-red-800" role="alert">
                <p class="font-semibold">Please review the payroll settings below.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <nav aria-label="Employee profile sections" class="flex flex-wrap gap-2 border-b border-slate-200 pb-3">
            <span aria-current="page" class="inline-flex min-h-10 items-center rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-900">Payroll settings</span>
        </nav>

        <section class="employer-card space-y-4 p-4 sm:p-6" aria-labelledby="pay-profile-heading">
            <div>
                <h3 id="pay-profile-heading" class="employer-section-heading">1. Pay profile</h3>
                <p class="mt-1 text-sm text-slate-600">Changes retain their effective dates. Finalized payroll snapshots are not changed.</p>
            </div>
            <form method="POST" action="{{ route('employer.payroll.profiles.update', $returnQuery) }}" data-submit-once class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="user_id" value="{{ $employee->id }}">
                <p class="text-base font-semibold text-slate-900">Employee <span class="mt-1 block font-normal">{{ $employee->name }}</span></p>
                <label for="salary_type" class="text-base font-semibold text-slate-900">Salary type
                    <select id="salary_type" name="salary_type" required class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                        @foreach (['hourly' => 'Hourly', 'daily' => 'Daily', 'monthly' => 'Monthly'] as $value => $label)<option value="{{ $value }}" @selected(old('salary_type', $profile?->salary_type ?? 'hourly') === $value)>{{ $label }}</option>@endforeach
                    </select>
                    @error('salary_type')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                </label>
                <label for="rate" class="text-base font-semibold text-slate-900">Rate
                    <input id="rate" name="rate" type="number" inputmode="decimal" step="0.01" min="0" max="99999999.99" value="{{ old('rate', $currentRate?->rate) }}" required class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                    @error('rate')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                </label>
                <label for="pay_frequency" class="text-base font-semibold text-slate-900">Pay frequency
                    <select id="pay_frequency" name="pay_frequency" required class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                        @foreach ($frequencyLabels as $value => $label)<option value="{{ $value }}" @selected(old('pay_frequency', $profile?->pay_frequency ?? 'monthly') === $value)>{{ $label }}</option>@endforeach
                    </select>
                    @error('pay_frequency')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                </label>
                <label for="effective_from" class="text-base font-semibold text-slate-900">Effective date
                    <input id="effective_from" name="effective_from" type="date" value="{{ old('effective_from', now()->toDateString()) }}" required class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                    @error('effective_from')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                </label>
                <label for="payment_method" class="text-base font-semibold text-slate-900">Preferred manual payment method
                    <select id="payment_method" name="payment_method" class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                        <option value="manual_cash" @selected(old('payment_method', $employee->payment_method ?? 'manual_cash') === 'manual_cash')>Cash</option>
                        <option value="manual_bank_deposit" @selected(old('payment_method', $employee->payment_method) === 'manual_bank_deposit')>Bank deposit</option>
                        <option value="manual_cheque" @selected(old('payment_method', $employee->payment_method) === 'manual_cheque')>Cheque</option>
                    </select>
                    @error('payment_method')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                </label>
                <label for="standard_hours_per_day" class="text-base font-semibold text-slate-900">Standard hours per workday
                    <input id="standard_hours_per_day" name="standard_hours_per_day" type="number" min="1" max="24" value="{{ old('standard_hours_per_day', $profile?->standard_hours_per_day ?? 8) }}" class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                    @error('standard_hours_per_day')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                </label>
                <label for="standard_work_days_per_month" class="text-base font-semibold text-slate-900">Standard workdays per month
                    <input id="standard_work_days_per_month" name="standard_work_days_per_month" type="number" min="1" max="31" value="{{ old('standard_work_days_per_month', $profile?->standard_work_days_per_month ?? 22) }}" class="mt-1.5 block min-h-11 w-full rounded-lg border-slate-300 text-base focus:border-emerald-700 focus:ring-emerald-700">
                    @error('standard_work_days_per_month')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                </label>
                <div class="flex items-end"><button type="submit" data-loading-label="Saving profile..." class="min-h-11 w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-base font-semibold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700">Save pay profile</button></div>
            </form>
            @if ($employee->compensationHistory->isNotEmpty())
                <details class="rounded-lg border border-slate-200 p-3">
                    <summary class="cursor-pointer font-semibold text-slate-800">Compensation history</summary>
                    <ul class="mt-3 divide-y divide-slate-200">@foreach ($employee->compensationHistory->sortByDesc('effective_from') as $rate)<li class="py-2 text-sm text-slate-700">{{ str($rate->basis)->title() }} · {{ $rate->rate }} · {{ $rate->effective_from->format('M d, Y') }} through {{ $rate->effective_to?->format('M d, Y') ?? 'Current' }}</li>@endforeach</ul>
                </details>
            @endif
        </section>


@endsection
