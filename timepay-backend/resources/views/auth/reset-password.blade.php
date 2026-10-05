<x-guest-layout>
    <div class="mb-7">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-emerald-700">Account recovery</p>
        <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Enter your code</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">Enter the six-digit code sent to your email and choose a new password. The code expires in 15 minutes.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <div class="space-y-2">
            <x-input-label for="email" :value="__('Email address')" />
            <x-text-input id="email" class="block mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-emerald-500 focus:ring-emerald-500" type="email" name="email" :value="old('email', session('password_reset_email'))" required autofocus autocomplete="email" placeholder="you@company.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="otp" :value="__('Six-digit code')" />
            <x-text-input id="otp" class="mt-1 block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-center text-xl tracking-[0.4em] focus:border-emerald-500 focus:ring-emerald-500" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" name="otp" :value="old('otp')" required autocomplete="one-time-code" />
            <x-input-error :messages="$errors->get('otp')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('New password')" />
            <x-text-input id="password" class="mt-1 block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-emerald-500 focus:ring-emerald-500" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm new password')" />
            <x-text-input id="password_confirmation" class="mt-1 block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-emerald-500 focus:ring-emerald-500" type="password" name="password_confirmation" required autocomplete="new-password" />
        </div>

        <div class="mt-6 space-y-4">
            <x-primary-button class="w-full justify-center rounded-xl bg-emerald-600 py-3 text-sm font-semibold hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-800">
                {{ __('Reset Password') }}
            </x-primary-button>
            <a href="{{ route('password.request') }}" class="block text-center text-sm font-medium text-slate-600 transition hover:text-emerald-700">Request another code</a>
        </div>
    </form>
</x-guest-layout>
