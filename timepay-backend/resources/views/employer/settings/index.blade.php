@extends('layouts.employer')

@section('title', 'Business Settings - TimePay')
@section('header_title', 'Business settings')

@section('content')
    @php
        $selectedDays = old('working_days', $company->working_days ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']);
        $startTime = old('work_start_time', $company->work_start_time ? \Illuminate\Support\Carbon::parse($company->work_start_time)->format('H:i') : '09:00');
        $endTime = old('work_end_time', $company->work_end_time ? \Illuminate\Support\Carbon::parse($company->work_end_time)->format('H:i') : '17:00');
        $selectedCurrency = old('currency', $company->currency ?? 'PHP');
        $monthlySickLeaveLimit = old('monthly_sick_leave_limit', $company->monthly_sick_leave_limit ?? 2);
        $monthlyVacationLeaveLimit = old('monthly_vacation_leave_limit', $company->monthly_vacation_leave_limit ?? 2);
    @endphp

    <div class="space-y-6">
        <header class="border-b border-slate-200 pb-5">
            <p class="text-sm font-semibold text-emerald-700">Business</p>
            <h2 class="employer-page-heading">Business settings</h2>
            <p class="employer-page-description">Set the schedule, leave allowance, and payroll currency used by TimePay.</p>
        </header>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-base text-red-900" role="alert">
                <p class="font-semibold">Some settings need attention.</p>
                <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="employer-card p-5 sm:p-6" aria-labelledby="business-profile-heading">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 id="business-profile-heading" class="employer-section-heading">Business profile</h3>
                    <p class="mt-1 text-sm text-slate-600">Your business identity is managed with your employer account.</p>
                </div>
                <a href="{{ route('profile.edit') }}" class="employer-secondary-button">Account settings</a>
            </div>
            <dl class="mt-5 grid gap-4 border-t border-slate-200 pt-4 sm:grid-cols-2">
                <div><dt class="text-sm font-medium text-slate-600">Business name</dt><dd class="mt-1 text-base font-semibold text-slate-900">{{ $company->name }}</dd></div>
                <div><dt class="text-sm font-medium text-slate-600">Signed-in employer</dt><dd class="mt-1 text-base font-semibold text-slate-900">{{ auth()->user()->name }}</dd><p class="mt-0.5 break-all text-sm text-slate-600">{{ auth()->user()->email }}</p></div>
            </dl>
        </section>

        <form method="POST" action="{{ route('employer.settings.update') }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <section class="employer-card overflow-hidden" aria-labelledby="work-schedule-heading">
                <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                    <h3 id="work-schedule-heading" class="employer-section-heading">Work schedule</h3>
                    <p class="mt-1 text-sm text-slate-600">The shift and selected workdays guide attendance validation.</p>
                </div>
                <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                    <label for="work_start_time" class="employer-field-label">Shift starts
                        <input id="work_start_time" name="work_start_time" type="time" value="{{ $startTime }}" class="employer-field-control" required>
                        @error('work_start_time')<span class="mt-1 block text-sm font-medium text-red-700">{{ $message }}</span>@enderror
                    </label>
                    <label for="work_end_time" class="employer-field-label">Shift ends
                        <input id="work_end_time" name="work_end_time" type="time" value="{{ $endTime }}" class="employer-field-control" required>
                        @error('work_end_time')<span class="mt-1 block text-sm font-medium text-red-700">{{ $message }}</span>@enderror
                    </label>
                    <fieldset class="sm:col-span-2">
                        <legend class="employer-field-label">Working days</legend>
                        <p class="mt-1 text-sm text-slate-600">Select at least one day when employees are scheduled.</p>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($daysOfWeek as $day)
                                <label class="flex min-h-11 items-center gap-3 rounded-xl border border-slate-200 px-4 py-2.5 text-base font-medium text-slate-800 hover:bg-slate-50">
                                    <input type="checkbox" name="working_days[]" value="{{ $day }}" @checked(in_array($day, $selectedDays, true)) class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600">
                                    <span>{{ $day }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('working_days')<p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                        @error('working_days.*')<p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                    </fieldset>
                </div>
            </section>

            <div class="grid gap-5 xl:grid-cols-2">
                <section class="employer-card overflow-hidden" aria-labelledby="leave-allowance-heading">
                    <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                        <h3 id="leave-allowance-heading" class="employer-section-heading">Monthly leave allowance</h3>
                        <p class="mt-1 text-sm text-slate-600">Paid leave days available per month.</p>
                    </div>
                    <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
                        <label for="monthly_sick_leave_limit" class="employer-field-label">Sick leave days
                            <input id="monthly_sick_leave_limit" name="monthly_sick_leave_limit" type="number" min="0" max="31" value="{{ $monthlySickLeaveLimit }}" class="employer-field-control" required>
                            @error('monthly_sick_leave_limit')<span class="mt-1 block text-sm font-medium text-red-700">{{ $message }}</span>@enderror
                        </label>
                        <label for="monthly_vacation_leave_limit" class="employer-field-label">Vacation leave days
                            <input id="monthly_vacation_leave_limit" name="monthly_vacation_leave_limit" type="number" min="0" max="31" value="{{ $monthlyVacationLeaveLimit }}" class="employer-field-control" required>
                            @error('monthly_vacation_leave_limit')<span class="mt-1 block text-sm font-medium text-red-700">{{ $message }}</span>@enderror
                        </label>
                    </div>
                </section>

                <section class="employer-card overflow-hidden" aria-labelledby="currency-heading">
                    <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                        <h3 id="currency-heading" class="employer-section-heading">Payroll currency</h3>
                        <p class="mt-1 text-sm text-slate-600">Used for payroll totals displayed across the employer portal.</p>
                    </div>
                    <div class="p-5 sm:p-6">
                        <label for="currency" class="employer-field-label">Currency
                            <select id="currency" name="currency" class="employer-field-control" required>
                                <option value="PHP" @selected($selectedCurrency === 'PHP')>PHP (&#8369;) · Philippine Peso</option>
                                <option value="USD" @selected($selectedCurrency === 'USD')>USD ($) · US Dollar</option>
                            </select>
                            @error('currency')<span class="mt-1 block text-sm font-medium text-red-700">{{ $message }}</span>@enderror
                        </label>
                        <p class="mt-3 text-sm text-slate-600">Changing the display currency does not convert existing payroll values.</p>
                    </div>
                </section>
            </div>

            <section class="employer-card flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Attendance and location rules</h3>
                    <p class="mt-1 text-sm text-slate-600">Manage the worksite boundary and allowed clock-in radius separately.</p>
                </div>
                <a href="{{ route('employer.geofence') }}" class="employer-secondary-button">Open location rules</a>
            </section>

            <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end">
                <a href="{{ route('employer.dashboard') }}" class="employer-secondary-button">Cancel</a>
                <button type="submit" class="employer-primary-button">Save settings</button>
            </div>
        </form>
    </div>
@endsection
