<x-guest-layout>
    <div class="mb-7">
        <p class="text-sm font-semibold text-emerald-700">TimePay account</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Welcome back</h1>
        <p class="mt-2 text-base leading-6 text-slate-600">Sign in to continue to TimePay.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email address')" class="text-base font-semibold text-slate-800" />
            <x-text-input id="email" class="mt-2 block min-h-12 w-full rounded-xl border-slate-300 bg-white px-4 text-base shadow-sm focus:border-emerald-600 focus:ring-emerald-600" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <div class="flex items-center justify-between gap-3">
                <x-input-label for="password" :value="__('Password')" class="text-base font-semibold text-slate-800" />
                @if (Route::has('password.request'))
                    <a class="rounded text-sm font-semibold text-emerald-700 hover:text-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700" href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a>
                @endif
            </div>
            <div class="relative mt-2">
                <x-text-input id="password" class="block min-h-12 w-full rounded-xl border-slate-300 bg-white px-4 pe-20 text-base shadow-sm focus:border-emerald-600 focus:ring-emerald-600" type="password" name="password" required autocomplete="current-password" />
                <button id="toggle-password" type="button" class="absolute inset-y-0 right-0 rounded-r-xl px-4 text-sm font-semibold text-slate-600 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-700" aria-controls="password" aria-pressed="false">Show</button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="flex min-h-11 cursor-pointer items-center gap-3 text-sm text-slate-700">
            <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-emerald-700 shadow-sm focus:ring-emerald-600" name="remember">
            <span>{{ __('Remember me') }}</span>
        </label>

        <x-primary-button class="min-h-12 w-full justify-center rounded-xl bg-emerald-600 px-5 text-base font-semibold hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-800">
            {{ __('Sign In') }}
        </x-primary-button>
    </form>

    @if (Route::has('employer.register'))
        <p class="mt-6 text-center text-sm text-slate-600">
            New to TimePay?
            <a href="{{ route('employer.register') }}" class="font-semibold text-emerald-700 hover:text-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700">Register your business</a>
        </p>
    @endif

    <p class="mt-3 text-center text-sm text-slate-500">Employee accounts are created by their employer.</p>

    @push('scripts')
        <script>
            document.getElementById('toggle-password')?.addEventListener('click', function () {
                const password = document.getElementById(this.getAttribute('aria-controls'));
                const visible = password.type === 'password';
                password.type = visible ? 'text' : 'password';
                this.textContent = visible ? 'Hide' : 'Show';
                this.setAttribute('aria-pressed', String(visible));
            });
        </script>
    @endpush
</x-guest-layout>
