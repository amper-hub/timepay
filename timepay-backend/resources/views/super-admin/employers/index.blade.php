@extends('super-admin.layouts.app')

@section('title', 'Employers')
@section('page-title', 'Employer Management')
@section('page-description', 'Review employer accounts, company ownership, and account status.')

@section('content')
<section class="rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-950">Employers</h2>
            <p class="text-sm text-slate-500">Filter accounts by company, representative, email, or status.</p>
        </div>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <form method="GET" action="{{ route('super-admin.employers.index') }}" class="flex flex-col gap-2 sm:flex-row">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search employers" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-72">
                @if ($hasStatusColumn)
                    <select name="status" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All statuses</option>
                        <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
                    </select>
                @endif
                <button type="submit" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Search</button>
            </form>
            <a href="{{ route('super-admin.employers.create') }}" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                Create Employer
            </a>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Company</th>
                    <th class="px-5 py-3">Representative</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Joined</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($employers as $employer)
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-4 font-medium text-slate-950">{{ $employer->company?->name ?? 'No company assigned' }}</td>
                        <td class="px-5 py-4">
                            <p class="font-medium text-slate-800">{{ $employer->name }}</p>
                            <p class="text-xs text-slate-500">{{ $employer->email }}</p>
                        </td>
                        <td class="px-5 py-4">
                            @php
                                $status = $employer->status ?? 'active';
                                $statusClass = match ($status) {
                                    'suspended', 'rejected' => 'bg-rose-50 text-rose-700',
                                    'pending' => 'bg-amber-50 text-amber-700',
                                    default => 'bg-emerald-50 text-emerald-700',
                                };
                            @endphp
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ ucfirst($status) }}</span>
                        </td>
                        <td class="px-5 py-4 text-slate-600">{{ $employer->created_at?->format('M d, Y') ?? '-' }}</td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap justify-end gap-2">
                                <a href="{{ route('super-admin.employers.show', $employer) }}" class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">View</a>
                                <a href="{{ route('super-admin.employers.edit', $employer) }}" class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                                <form method="POST" action="{{ route('super-admin.impersonate', $employer) }}" onsubmit="return confirm('Log in as this employer?')">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100">Login as Employer</button>
                                </form>
                                @if ($hasStatusColumn)
                                    <form method="POST" action="{{ route('super-admin.employers.approve', $employer) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('super-admin.employers.suspend', $employer) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md border border-amber-200 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-50">Suspend</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('super-admin.employers.destroy', $employer) }}" onsubmit="return confirm('Delete this employer account?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-slate-500">No employers found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="border-t border-slate-200 px-5 py-4">
        {{ $employers->links() }}
    </div>
</section>
@endsection
