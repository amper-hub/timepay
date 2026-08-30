@extends('super-admin.layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Super Admin Dashboard')
@section('page-description', 'Monitor platform health, employer growth, and administrative activity.')

@section('content')
<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Total Employers</p>
            <p class="mt-3 text-3xl font-semibold text-slate-950">{{ number_format($stats['totalEmployers']) }}</p>
            <p class="mt-2 text-xs text-emerald-600">{{ number_format($stats['recentSignups']) }} joined in 30 days</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Active Employers</p>
            <p class="mt-3 text-3xl font-semibold text-slate-950">{{ number_format($stats['activeEmployers']) }}</p>
            <p class="mt-2 text-xs text-slate-500">Available for tenant operations</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Companies</p>
            <p class="mt-3 text-3xl font-semibold text-slate-950">{{ number_format($stats['companies']) }}</p>
            <p class="mt-2 text-xs text-slate-500">Registered company profiles</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Admin Users</p>
            <p class="mt-3 text-3xl font-semibold text-slate-950">{{ number_format($stats['adminUsers']) }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ number_format($stats['employees']) }} employee accounts tracked</p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[2fr_1fr]">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Recent Employers</h2>
                    <p class="text-sm text-slate-500">Newest employer accounts and company records.</p>
                </div>
                <a href="{{ route('super-admin.employers.index') }}" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">Manage Employers</a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-emerald-50/60 text-xs font-semibold uppercase tracking-wide text-emerald-800">
                        <tr>
                            <th class="px-5 py-3">Company</th>
                            <th class="px-5 py-3">Representative</th>
                            <th class="px-5 py-3">Joined</th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($employers as $employer)
                            <tr class="transition hover:bg-emerald-50/50">
                                <td class="px-5 py-4 font-medium text-slate-950">{{ $employer->company?->name ?? 'No company assigned' }}</td>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-800">{{ $employer->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $employer->email }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-600">{{ $employer->created_at?->format('M d, Y') ?? '-' }}</td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('super-admin.employers.show', $employer) }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-800">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-slate-500">No employers found yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">Quick Actions</h2>
            <div class="mt-4 space-y-3">
                <a href="{{ route('super-admin.admins.create') }}" class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700">
                    Create admin account
                    <span aria-hidden="true">+</span>
                </a>
                <a href="{{ route('super-admin.reports.index') }}" class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700">
                    View system reports
                    <span aria-hidden="true">-></span>
                </a>
            </div>
        </section>
    </div>
</div>
@endsection
