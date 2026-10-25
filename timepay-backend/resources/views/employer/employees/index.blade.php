@extends('layouts.employer')

@section('title', 'Employees - TimePay')
@section('header_title', 'Employees')

@section('content')
    <div class="space-y-6">
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-emerald-700">People</p>
                <h2 class="employer-page-heading">Employee directory</h2>
                <p class="employer-page-description">Find employees in your business and manage account setup.</p>
            </div>
            <button type="button" @click="$dispatch('open-add-employee-modal')" class="employer-primary-button">Add Employee</button>
        </header>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-base text-emerald-800" role="status">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-base text-red-800" role="alert">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-base text-red-800" role="alert">
                <p class="font-semibold">Please review the form information.</p>
                <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="employer-card overflow-hidden" aria-labelledby="employee-list-heading">
            <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h3 id="employee-list-heading" class="employer-section-heading">Employees</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ number_format($employees->total()) }} {{ \Illuminate\Support\Str::plural('employee', $employees->total()) }} in this view</p>
                </div>
                <form method="GET" action="{{ route('employer.employees.index') }}" class="flex w-full gap-2 sm:max-w-md" role="search">
                    <label class="sr-only" for="employee-search">Search employees</label>
                    <input id="employee-search" name="search" type="search" value="{{ $search }}" maxlength="100" placeholder="Search name or email" class="employer-field-control mt-0 min-w-0">
                    <button type="submit" class="employer-secondary-button">Search</button>
                </form>
            </div>

            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-[52rem] w-full table-fixed divide-y divide-slate-200 text-left">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-sm font-semibold text-slate-700">Employee</th>
                            <th scope="col" class="px-6 py-3 text-sm font-semibold text-slate-700">Role</th>
                            <th scope="col" class="px-6 py-3 text-sm font-semibold text-slate-700">Account status</th>
                            <th scope="col" class="px-6 py-3 text-right text-sm font-semibold text-slate-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($employees as $employee)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-900">{{ $employee->name }}</p>
                                    <p class="mt-0.5 text-sm text-slate-600">{{ $employee->email }}</p>
                                </td>
                                <td class="px-6 py-4 text-base text-slate-700">Employee</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-sm font-medium {{ $employee->status === 'active' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-300 bg-slate-100 text-slate-700' }}">
                                        {{ str($employee->status ?? 'active')->replace('_', ' ')->title() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @if (strtolower((string) $employee->status) !== 'inactive')
                                        <form method="POST" action="{{ route('employer.employees.deactivate', $employee) }}" onsubmit="if (!window.confirm('Deactivate ' + @js($employee->name) + '? Their access will be disabled and their history retained.')) return false; const button = this.querySelector('button[type=submit]'); button.disabled = true; button.textContent = 'Deactivating...';">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-60">Deactivate Employee</button>
                                        </form>
                                    @else
                                        <span class="text-sm text-slate-500">Deactivated</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-12 text-center text-base text-slate-600">{{ $search ? 'No employees match this search.' : 'No employees have been added yet.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-200 md:hidden">
                @forelse ($employees as $employee)
                    <article class="px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h4 class="truncate font-semibold text-slate-900">{{ $employee->name }}</h4>
                                <p class="mt-1 break-all text-sm text-slate-600">{{ $employee->email }}</p>
                            </div>
                            <span class="shrink-0 rounded-full border px-2.5 py-1 text-sm font-medium {{ $employee->status === 'active' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-300 bg-slate-100 text-slate-700' }}">{{ str($employee->status ?? 'active')->replace('_', ' ')->title() }}</span>
                        </div>
                        <p class="mt-3 text-sm text-slate-600">Role: Employee</p>
                        @if (strtolower((string) $employee->status) !== 'inactive')
                            <form method="POST" action="{{ route('employer.employees.deactivate', $employee) }}" class="mt-4" onsubmit="if (!window.confirm('Deactivate ' + @js($employee->name) + '? Their access will be disabled and their history retained.')) return false; const button = this.querySelector('button[type=submit]'); button.disabled = true; button.textContent = 'Deactivating...';">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-60">Deactivate Employee</button>
                            </form>
                        @endif
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
