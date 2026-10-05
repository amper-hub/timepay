<x-guest-layout>
    <div class="mb-7">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-emerald-700">Account recovery</p>
        <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Forgot your password?</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">
            Enter the email address associated with your account. We’ll send a six-digit code to reset your password.
        </p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="space-y-2">
            <x-input-label for="email" :value="__('Email address')" />
            <x-text-input id="email" class="block mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-emerald-500 focus:ring-emerald-500" type="email" name="email" :value="old('email')" required autofocus autocomplete="email" placeholder="you@company.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-6 space-y-4">
            <x-primary-button class="w-full justify-center rounded-xl bg-emerald-600 py-3 text-sm font-semibold hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-800">
                {{ __('Send reset code') }}
            </x-primary-button>
            <a href="{{ route('login') }}" class="block text-center text-sm font-medium text-slate-600 transition hover:text-emerald-700">Back to sign in</a>
        </div>
    </form>
</x-guest-layout>
