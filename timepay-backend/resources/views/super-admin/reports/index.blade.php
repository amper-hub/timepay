@extends('super-admin.layouts.app')

@section('title', 'Reports')
@section('page-title', 'Reports & Analytics')
@section('page-description', 'Review system totals and export platform-level reporting data.')

@section('content')
<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Employers</p>
            <p class="mt-3 text-3xl font-semibold text-slate-950">{{ number_format($employerCount) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Employees</p>
            <p class="mt-3 text-3xl font-semibold text-slate-950">{{ number_format($employeeCount) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Admin Users</p>
            <p class="mt-3 text-3xl font-semibold text-slate-950">{{ number_format($adminCount) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Companies</p>
            <p class="mt-3 text-3xl font-semibold text-slate-950">{{ number_format($companyCount) }}</p>
        </div>
    </div>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">Recent Employer Report</h2>
                <p class="text-sm text-slate-500">A lightweight reporting table you can later swap for CSV or PDF export.</p>
            </div>
            <form method="POST" action="{{ route('super-admin.reports.export') }}" class="flex gap-2">
                @csrf
                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">Export CSV</button>
                <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Export PDF</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Company</th>
                        <th class="px-5 py-3">Employer</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recentEmployers as $employer)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-5 py-4 font-medium text-slate-950">{{ $employer->company?->name ?? 'No company assigned' }}</td>
                            <td class="px-5 py-4">
                                <p class="font-medium text-slate-800">{{ $employer->name }}</p>
                                <p class="text-xs text-slate-500">{{ $employer->email }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ ucfirst($employer->status ?? 'active') }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $employer->created_at?->format('M d, Y') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-10 text-center text-slate-500">No report data available yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-5 py-4">
            {{ $recentEmployers->links() }}
        </div>
    </section>
</div>
@endsection
