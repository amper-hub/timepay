<x-guest-layout>
    <div class="mb-7">
        <p class="text-sm font-semibold text-emerald-700">Business registration</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Create your TimePay business account</h1>
        <p class="mt-2 text-base leading-6 text-slate-600">Set up your company and employer sign-in. You can configure your work location after signing in.</p>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
            <p class="font-semibold">Please check the highlighted fields.</p>
            <p class="mt-1">Your entered information has been kept so you can correct and resubmit.</p>
        </div>
    @endif

    <form id="employer-registration-form" method="POST" action="{{ route('employer.register.store') }}" class="space-y-5">
        @csrf

        <div>
            <label for="company_name" class="block text-base font-semibold text-slate-800">Business name <span aria-hidden="true" class="text-rose-600">*</span></label>
            <input id="company_name" name="company_name" type="text" value="{{ old('company_name') }}" required autofocus autocomplete="organization" aria-describedby="company_name-error" class="mt-2 block min-h-12 w-full rounded-xl border-slate-300 bg-white px-4 text-base shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
            @error('company_name')<p id="company_name-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="employer_name" class="block text-base font-semibold text-slate-800">Account owner name <span aria-hidden="true" class="text-rose-600">*</span></label>
            <input id="employer_name" name="employer_name" type="text" value="{{ old('employer_name') }}" required autocomplete="name" aria-describedby="employer_name-error" class="mt-2 block min-h-12 w-full rounded-xl border-slate-300 bg-white px-4 text-base shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
            @error('employer_name')<p id="employer_name-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="email" class="block text-base font-semibold text-slate-800">Work email <span aria-hidden="true" class="text-rose-600">*</span></label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" aria-describedby="email-error" class="mt-2 block min-h-12 w-full rounded-xl border-slate-300 bg-white px-4 text-base shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
            @error('email')<p id="email-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="password" class="block text-base font-semibold text-slate-800">Password <span aria-hidden="true" class="text-rose-600">*</span></label>
                <input id="password" name="password" type="password" required autocomplete="new-password" aria-describedby="password-error" class="mt-2 block min-h-12 w-full rounded-xl border-slate-300 bg-white px-4 text-base shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                @error('password')<p id="password-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="block text-base font-semibold text-slate-800">Confirm password <span aria-hidden="true" class="text-rose-600">*</span></label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 block min-h-12 w-full rounded-xl border-slate-300 bg-white px-4 text-base shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
            </div>
        </div>

        <button id="employer-registration-submit" type="submit" class="min-h-12 w-full rounded-xl bg-emerald-600 px-5 py-3 text-base font-semibold text-white transition hover:bg-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2">
            Register business
        </button>

        <p class="text-center text-sm text-slate-600">
            Already registered?
            <a href="{{ route('login') }}" class="font-semibold text-emerald-700 hover:text-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700">Sign in</a>
        </p>
        <p class="text-center text-sm text-slate-500">Employee accounts are created by their employer.</p>
    </form>

    @push('scripts')
        <script>
            document.getElementById('employer-registration-form')?.addEventListener('submit', function () {
                const button = document.getElementById('employer-registration-submit');
                if (!button || !this.reportValidity()) return;
                button.disabled = true;
                button.textContent = 'Submitting…';
                this.setAttribute('aria-busy', 'true');
            });
        </script>
    @endpush
</x-guest-layout>
