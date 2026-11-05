@extends('layouts.employer')

@section('title', 'Account Settings - TimePay')
@section('header_title', 'Account settings')

@section('content')
    <div class="space-y-6">
        <header class="border-b border-slate-200 pb-5">
            <p class="text-sm font-semibold text-emerald-700">Account</p>
            <h2 class="employer-page-heading">Account settings</h2>
            <p class="employer-page-description">Update your sign-in details and account security.</p>
        </header>

        @if (session('status') === 'profile-updated')
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">Account information saved.</div>
        @endif

        <section class="employer-card p-5 sm:p-6">
            <div class="max-w-2xl">@include('profile.partials.update-profile-information-form')</div>
        </section>
        <section class="employer-card p-5 sm:p-6">
            <div class="max-w-2xl">@include('profile.partials.update-password-form')</div>
        </section>
        <section class="rounded-2xl border border-red-200 bg-white p-5 sm:p-6">
            <div class="max-w-2xl">@include('profile.partials.delete-user-form')</div>
        </section>
    </div>
@endsection
