@extends('super-admin.layouts.app')

@section('title', 'Admin Users')
@section('page-title', 'Admin Users')
@section('page-description', 'Create and manage internal administrator accounts.')

@section('content')
<section class="rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-950">Admin Accounts</h2>
            <p class="text-sm text-slate-500">Search, review, edit, and remove administrator users.</p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <form method="GET" action="{{ route('super-admin.admins.index') }}" class="flex flex-col gap-2 sm:flex-row">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search admins" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-64">
                @if ($hasStatusColumn)
                    <select name="status" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All statuses</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
                    </select>
                @endif
                <button type="submit" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Search</button>
            </form>
            <a href="{{ route('super-admin.admins.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700">Create Admin</a>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Name</th>
                    <th class="px-5 py-3">Email</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Created</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($admins as $admin)
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-4 font-medium text-slate-950">{{ $admin->name }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $admin->email }}</td>
                        <td class="px-5 py-4">
                            <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">{{ ucfirst($admin->status ?? 'active') }}</span>
                        </td>
                        <td class="px-5 py-4 text-slate-600">{{ $admin->created_at?->format('M d, Y') ?? '-' }}</td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('super-admin.admins.show', $admin) }}" class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">View</a>
                                <a href="{{ route('super-admin.admins.edit', $admin) }}" class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                                <form method="POST" action="{{ route('super-admin.admins.destroy', $admin) }}" onsubmit="return confirm('Delete this admin account?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-slate-500">No admin users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="border-t border-slate-200 px-5 py-4">
        {{ $admins->links() }}
    </div>
</section>
@endsection
