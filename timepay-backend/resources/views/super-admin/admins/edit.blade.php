@extends('super-admin.layouts.app')

@section('title', 'Edit Admin')
@section('page-title', 'Edit Admin Account')
@section('page-description', 'Update administrator account information and access status.')

@section('content')
<section class="max-w-3xl rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-lg font-semibold text-slate-950">Account Details</h2>
        <p class="text-sm text-slate-500">Leave password fields blank to keep the current password.</p>
    </div>

    <form method="POST" action="{{ route('super-admin.admins.update', $admin) }}" class="space-y-5 px-5 py-5">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="block text-sm font-semibold text-slate-700">Full name</label>
            <input id="name" type="text" name="name" value="{{ old('name', $admin->name) }}" class="mt-1 block w-full rounded-lg text-sm shadow-sm @error('name') border-rose-300 focus:border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 @enderror" required>
            @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-semibold text-slate-700">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email', $admin->email) }}" class="mt-1 block w-full rounded-lg text-sm shadow-sm @error('email') border-rose-300 focus:border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 @enderror" required>
            @error('email') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="password" class="block text-sm font-semibold text-slate-700">New password</label>
                <input id="password" type="password" name="password" class="mt-1 block w-full rounded-lg text-sm shadow-sm @error('password') border-rose-300 focus:border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 @enderror">
                @error('password') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-semibold text-slate-700">Confirm new password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>

        @if ($hasStatusColumn)
            <div>
                <label for="status" class="block text-sm font-semibold text-slate-700">Status</label>
                <select id="status" name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="active" @selected(old('status', $admin->status ?? 'active') === 'active')>Active</option>
                    <option value="suspended" @selected(old('status', $admin->status) === 'suspended')>Suspended</option>
                </select>
                @error('status') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        @endif

        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ route('super-admin.admins.index') }}" class="inline-flex justify-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="inline-flex justify-center rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">Save Changes</button>
        </div>
    </form>
</section>
@endsection
