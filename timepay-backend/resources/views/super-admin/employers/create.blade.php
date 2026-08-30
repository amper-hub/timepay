@extends('super-admin.layouts.app')

@section('title', 'Create Employer')
@section('page-title', 'Create Employer')
@section('page-description', 'Add a company and its employer representative account.')

@section('content')
<section class="max-w-4xl rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-lg font-semibold text-slate-950">Employer Details</h2>
        <p class="text-sm text-slate-500">This creates both the company record and the linked employer user.</p>
    </div>

    <form method="POST" action="{{ route('super-admin.employers.store') }}" class="space-y-8 px-5 py-5">
        @csrf

        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Company Details</h3>
            <div class="mt-4">
                <label for="company_name" class="block text-sm font-semibold text-slate-700">Company Name</label>
                <input id="company_name" type="text" name="company_name" value="{{ old('company_name') }}" class="mt-1 block w-full rounded-lg text-sm shadow-sm @error('company_name') border-rose-300 focus:border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 @enderror" required>
                @error('company_name')
                    <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Representative Details</h3>
            <div class="mt-4 grid gap-5 md:grid-cols-2">
                <div>
                    <label for="name" class="block text-sm font-semibold text-slate-700">Full Name</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" class="mt-1 block w-full rounded-lg text-sm shadow-sm @error('name') border-rose-300 focus:border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 @enderror" required>
                    @error('name')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-700">Email Address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" class="mt-1 block w-full rounded-lg text-sm shadow-sm @error('email') border-rose-300 focus:border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 @enderror" required>
                    @error('email')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-700">Password</label>
                    <input id="password" type="password" name="password" class="mt-1 block w-full rounded-lg text-sm shadow-sm @error('password') border-rose-300 focus:border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 @enderror" required>
                    @error('password')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-semibold text-slate-700">Confirm Password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                </div>
            </div>
        </div>

        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Settings</h3>
            <div class="mt-4 max-w-sm">
                <label for="status" class="block text-sm font-semibold text-slate-700">Status</label>
                <select id="status" name="status" class="mt-1 block w-full rounded-lg text-sm shadow-sm @error('status') border-rose-300 focus:border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 @enderror">
                    <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                    <option value="pending" @selected(old('status') === 'pending')>Pending</option>
                </select>
                @error('status')
                    <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                @enderror
                @unless ($hasStatusColumn)
                    <p class="mt-2 text-xs text-amber-700">The status field will be accepted, but your current users table does not have a status column yet.</p>
                @endunless
            </div>
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ route('super-admin.employers.index') }}" class="inline-flex justify-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="inline-flex justify-center rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">Create Employer</button>
        </div>
    </form>
</section>
@endsection
