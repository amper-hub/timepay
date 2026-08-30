@extends('super-admin.layouts.app')

@section('title', 'View Employer')
@section('page-title', 'Employer Profile')
@section('page-description', 'Review employer account and company details.')

@section('content')
<section class="max-w-4xl rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-950">{{ $employer->company?->name ?? 'No company assigned' }}</h2>
            <p class="text-sm text-slate-500">{{ $employer->name }} - {{ $employer->email }}</p>
        </div>
        <a href="{{ route('super-admin.employers.edit', $employer) }}" class="inline-flex justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Edit</a>
    </div>

    <dl class="grid gap-5 px-5 py-5 sm:grid-cols-2">
        <div>
            <dt class="text-sm font-medium text-slate-500">Representative</dt>
            <dd class="mt-1 text-sm font-semibold text-slate-950">{{ $employer->name }}</dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-slate-500">Status</dt>
            <dd class="mt-1 text-sm font-semibold text-slate-950">{{ ucfirst($employer->status ?? 'active') }}</dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-slate-500">Company</dt>
            <dd class="mt-1 text-sm font-semibold text-slate-950">{{ $employer->company?->name ?? 'No company assigned' }}</dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-slate-500">Joined</dt>
            <dd class="mt-1 text-sm font-semibold text-slate-950">{{ $employer->created_at?->format('M d, Y') ?? '-' }}</dd>
        </div>
    </dl>
</section>
@endsection
