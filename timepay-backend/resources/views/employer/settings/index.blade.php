@extends('layouts.employer')

@section('title', 'Business Settings - TimePay Employer Portal')
@section('header_title', 'Business Settings')

@section('content')
    @php
        $selectedDays = old('working_days', $company->working_days ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']);
        $startTime = old('work_start_time', $company->work_start_time ? \Illuminate\Support\Carbon::parse($company->work_start_time)->format('H:i') : '09:00');
        $endTime = old('work_end_time', $company->work_end_time ? \Illuminate\Support\Carbon::parse($company->work_end_time)->format('H:i') : '17:00');
        $selectedCurrency = old('currency', $company->currency ?? 'PHP');
    @endphp

    <form method="POST" action="{{ route('employer.settings.update') }}" class="space-y-6">
        @csrf
        @method('PATCH')

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-base font-semibold text-slate-950">Time Management</h2>
                <p class="mt-1 text-sm text-slate-500">Clock-in is allowed only on selected days and within the configured shift window.</p>
            </div>

            <div class="space-y-6 p-6">
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="work_start_time" class="block text-sm font-medium text-slate-700">Shift Start Time</label>
                        <input
                            id="work_start_time"
                            name="work_start_time"
                            type="time"
                            value="{{ $startTime }}"
                            class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15"
                            required
                        >
                        @error('work_start_time')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="work_end_time" class="block text-sm font-medium text-slate-700">Shift End Time</label>
                        <input
                            id="work_end_time"
                            name="work_end_time"
                            type="time"
                            value="{{ $endTime }}"
                            class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15"
                            required
                        >
                        @error('work_end_time')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <p class="block text-sm font-medium text-slate-700">Working Days</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($daysOfWeek as $day)
                            <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
                                <input
                                    type="checkbox"
                                    name="working_days[]"
                                    value="{{ $day }}"
                                    @checked(in_array($day, $selectedDays, true))
                                    class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600"
                                >
                                <span>{{ $day }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('working_days')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    @error('working_days.*')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-base font-semibold text-slate-950">Payroll Currency</h2>
                <p class="mt-1 text-sm text-slate-500">Choose the currency symbol used across payroll and dashboard pay calculations.</p>
            </div>

            <div class="p-6">
                <label for="currency" class="block text-sm font-medium text-slate-700">Currency</label>
                <select
                    id="currency"
                    name="currency"
                    class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 md:max-w-md"
                    required
                >
                    <option value="PHP" @selected($selectedCurrency === 'PHP')>PHP (₱) - Philippine Peso</option>
                    <option value="USD" @selected($selectedCurrency === 'USD')>USD ($) - US Dollar</option>
                </select>
                @error('currency')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </section>

        <div class="flex justify-end gap-3">
            <a href="{{ route('employer.dashboard') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">Cancel</a>
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                Save Settings
            </button>
        </div>
    </form>
@endsection
