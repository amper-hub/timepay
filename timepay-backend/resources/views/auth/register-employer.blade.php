<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Employer - TimePay</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="min-h-screen overflow-hidden px-4 py-8 sm:px-6">
        <section class="relative">
            <div class="relative mx-auto grid min-h-[calc(100vh-4rem)] max-w-7xl overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-sm lg:grid-cols-[1.05fr_0.95fr]">
                <div class="relative overflow-hidden bg-gradient-to-br from-emerald-600 via-teal-600 to-slate-900 p-8 text-white sm:p-10 lg:flex lg:flex-col lg:justify-between">
                    <div class="absolute left-12 top-28 h-48 w-48 rounded-full border border-white/15"></div>
                    <div class="absolute bottom-12 right-12 h-64 w-64 rounded-full border border-emerald-200/20"></div>
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-3">
                        <img src="{{ Vite::asset('resources/img/timepay-logo.png') }}" alt="TimePay" class="h-12 w-auto object-contain">
                    </a>

                    <div class="relative mt-14 max-w-2xl">
                        <p class="inline-flex rounded-full border border-white/15 bg-white/15 px-4 py-2 text-sm font-bold text-emerald-50 backdrop-blur">
                            Geofence + Face ID time tracking for modern teams
                        </p>
                        <h1 class="mt-8 text-4xl font-black tracking-tight text-white sm:text-5xl">
                            Stop chasing timesheets. Verify work where it happens.
                        </h1>
                        <p class="mt-6 text-lg leading-8 text-emerald-50/85">
                            TimePay helps employers track clock-ins and clock-outs with GPS geofencing, facial verification, attendance history, leave workflows, and payroll-ready records.
                        </p>
                    </div>

                    <div class="relative mt-10 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-lg border border-white/15 bg-white/10 p-5 backdrop-blur">
                            <p class="text-2xl font-black text-emerald-300">GPS</p>
                            <p class="mt-2 text-sm text-emerald-50/80">Confirm staff are inside the approved work zone.</p>
                        </div>
                        <div class="rounded-lg border border-white/15 bg-white/10 p-5 backdrop-blur">
                            <p class="text-2xl font-black text-emerald-300">Face ID</p>
                            <p class="mt-2 text-sm text-emerald-50/80">Reduce buddy punching with biometric verification.</p>
                        </div>
                        <div class="rounded-lg border border-white/15 bg-white/10 p-5 backdrop-blur">
                            <p class="text-2xl font-black text-teal-200">SaaS</p>
                            <p class="mt-2 text-sm text-emerald-50/80">Multi-tenant tools for employers and platform admins.</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 text-slate-950 sm:p-8 lg:p-10">
                    <div>
                        <p class="text-sm font-black uppercase tracking-wide text-emerald-600">Create your employer account</p>
                        <h2 class="mt-2 text-3xl font-black">Start using TimePay</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Register your company and first employer account. You can configure geofence coordinates after sign in.
                        </p>
                    </div>

                    @if ($errors->any())
                        <div class="mt-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                            Please review the form and try again.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('employer.register.store') }}" class="mt-7 space-y-5">
                        @csrf

                        <div>
                            <label for="company_name" class="block text-sm font-bold text-slate-700">Company Name</label>
                            <input id="company_name" name="company_name" type="text" value="{{ old('company_name') }}" required autofocus class="mt-2 block w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm shadow-sm outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                            @error('company_name')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="employer_name" class="block text-sm font-bold text-slate-700">Employer Name</label>
                            <input id="employer_name" name="employer_name" type="text" value="{{ old('employer_name') }}" required class="mt-2 block w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm shadow-sm outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                            @error('employer_name')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-bold text-slate-700">Email Address</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-2 block w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm shadow-sm outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                            @error('email')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="password" class="block text-sm font-bold text-slate-700">Password</label>
                                <input id="password" name="password" type="password" required class="mt-2 block w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm shadow-sm outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                                @error('password')
                                    <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-sm font-bold text-slate-700">Confirm Password</label>
                                <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-2 block w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm shadow-sm outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                            </div>
                        </div>

                        <button type="submit" class="flex w-full items-center justify-center rounded-lg bg-emerald-600 px-5 py-3.5 text-sm font-black text-white shadow-lg shadow-emerald-600/20 transition hover:bg-emerald-700">
                            Register Company
                        </button>

                        <p class="text-center text-sm text-slate-500">
                            Already registered?
                            <a href="{{ route('login') }}" class="font-bold text-emerald-600 hover:text-emerald-700">Sign in</a>
                        </p>
                    </form>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
