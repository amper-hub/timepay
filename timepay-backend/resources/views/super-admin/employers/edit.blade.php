@extends('super-admin.layouts.app')

@section('title', 'Edit Employer')
@section('page-title', 'Edit Employer')
@section('page-description', 'Update employer representative details and account status.')

@section('content')
<section class="max-w-3xl rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-lg font-semibold text-slate-950">{{ $employer->company?->name ?? 'Employer Account' }}</h2>
        <p class="text-sm text-slate-500">Company assignment is shown for context; this form updates the user account.</p>
    </div>

    <form method="POST" action="{{ route('super-admin.employers.update', $employer) }}" class="space-y-5 px-5 py-5">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="block text-sm font-semibold text-slate-700">Representative name</label>
            <input id="name" type="text" name="name" value="{{ old('name', $employer->name) }}" class="mt-1 block w-full rounded-lg text-sm shadow-sm @error('name') border-rose-300 focus:border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 @enderror" required>
            @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-semibold text-slate-700">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email', $employer->email) }}" class="mt-1 block w-full rounded-lg text-sm shadow-sm @error('email') border-rose-300 focus:border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 @enderror" required>
            @error('email') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        @if ($hasStatusColumn)
            <div>
                <label for="status" class="block text-sm font-semibold text-slate-700">Status</label>
                <select id="status" name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="pending" @selected(old('status', $employer->status) === 'pending')>Pending</option>
                    <option value="active" @selected(old('status', $employer->status ?? 'active') === 'active')>Active</option>
                    <option value="suspended" @selected(old('status', $employer->status) === 'suspended')>Suspended</option>
                    <option value="rejected" @selected(old('status', $employer->status) === 'rejected')>Rejected</option>
                </select>
                @error('status') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        @endif

        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ route('super-admin.employers.index') }}" class="inline-flex justify-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="inline-flex justify-center rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">Save Changes</button>
        </div>
    </form>
</section>
@endsection
