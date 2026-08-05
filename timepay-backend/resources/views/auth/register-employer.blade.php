<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Employer - TimePay</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-white antialiased">
    <main class="min-h-screen overflow-hidden">
        <section class="relative">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(79,70,229,0.35),_transparent_35%),radial-gradient(circle_at_bottom_right,_rgba(16,185,129,0.22),_transparent_30%)]"></div>

            <div class="relative mx-auto grid min-h-screen max-w-7xl gap-10 px-6 py-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-center lg:px-8">
                <div>
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-500 text-sm font-black shadow-lg shadow-indigo-950/40">TP</span>
                        <span class="text-xl font-black tracking-tight">TimePay</span>
                    </a>

                    <div class="mt-14 max-w-2xl">
                        <p class="inline-flex rounded-full border border-white/10 bg-white/10 px-4 py-2 text-sm font-bold text-indigo-100 backdrop-blur">
                            Geofence + Face ID time tracking for modern teams
                        </p>
                        <h1 class="mt-8 text-5xl font-black tracking-tight text-white sm:text-6xl">
                            Stop chasing timesheets. Verify work where it happens.
                        </h1>
                        <p class="mt-6 text-lg leading-8 text-slate-300">
                            TimePay helps employers track clock-ins and clock-outs with GPS geofencing, facial verification, attendance history, leave workflows, and payroll-ready records.
                        </p>
                    </div>

                    <div class="mt-10 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                            <p class="text-2xl font-black text-emerald-300">GPS</p>
                            <p class="mt-2 text-sm text-slate-300">Confirm staff are inside the approved work zone.</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                            <p class="text-2xl font-black text-indigo-300">Face ID</p>
                            <p class="mt-2 text-sm text-slate-300">Reduce buddy punching with biometric verification.</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                            <p class="text-2xl font-black text-sky-300">SaaS</p>
                            <p class="mt-2 text-sm text-slate-300">Multi-tenant tools for employers and platform admins.</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-[2rem] border border-white/10 bg-white p-6 text-slate-950 shadow-2xl shadow-indigo-950/30 sm:p-8">
                    <div>
                        <p class="text-sm font-black uppercase tracking-wide text-indigo-600">Create your employer account</p>
                        <h2 class="mt-2 text-3xl font-black">Start using TimePay</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Register your company and first employer account. You can configure geofence coordinates after sign in.
                        </p>
                    </div>

                    @if ($errors->any())
                        <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                            Please review the form and try again.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('employer.register.store') }}" class="mt-7 space-y-5">
                        @csrf

                        <div>
                            <label for="company_name" class="block text-sm font-bold text-slate-700">Company Name</label>
                            <input id="company_name" name="company_name" type="text" value="{{ old('company_name') }}" required autofocus class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                            @error('company_name')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="employer_name" class="block text-sm font-bold text-slate-700">Employer Name</label>
                            <input id="employer_name" name="employer_name" type="text" value="{{ old('employer_name') }}" required class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                            @error('employer_name')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-bold text-slate-700">Email Address</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                            @error('email')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="password" class="block text-sm font-bold text-slate-700">Password</label>
                                <input id="password" name="password" type="password" required class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                                @error('password')
                                    <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-sm font-bold text-slate-700">Confirm Password</label>
                                <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                            </div>
                        </div>

                        <button type="submit" class="flex w-full items-center justify-center rounded-xl bg-indigo-600 px-5 py-3.5 text-sm font-black text-white shadow-lg shadow-indigo-600/25 transition hover:bg-indigo-700">
                            Register Company
                        </button>

                        <p class="text-center text-sm text-slate-500">
                            Already registered?
                            <a href="{{ route('login') }}" class="font-bold text-indigo-600 hover:text-indigo-700">Sign in</a>
                        </p>
                    </form>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
