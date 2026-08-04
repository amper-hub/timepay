@extends('super-admin.layouts.app')

@section('title', 'View Admin')
@section('page-title', 'Admin Profile')
@section('page-description', 'Review administrator account details.')

@section('content')
<section class="max-w-3xl rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-950">{{ $admin->name }}</h2>
            <p class="text-sm text-slate-500">{{ $admin->email }}</p>
        </div>
        <a href="{{ route('super-admin.admins.edit', $admin) }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Edit</a>
    </div>

    <dl class="grid gap-5 px-5 py-5 sm:grid-cols-2">
        <div>
            <dt class="text-sm font-medium text-slate-500">Role</dt>
            <dd class="mt-1 text-sm font-semibold text-slate-950">{{ $admin->role }}</dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-slate-500">Status</dt>
            <dd class="mt-1 text-sm font-semibold text-slate-950">{{ ucfirst($admin->status ?? 'active') }}</dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-slate-500">Created</dt>
            <dd class="mt-1 text-sm font-semibold text-slate-950">{{ $admin->created_at?->format('M d, Y') ?? '-' }}</dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-slate-500">Last Updated</dt>
            <dd class="mt-1 text-sm font-semibold text-slate-950">{{ $admin->updated_at?->format('M d, Y') ?? '-' }}</dd>
        </div>
    </dl>
</section>
@endsection
