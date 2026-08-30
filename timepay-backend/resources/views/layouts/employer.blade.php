<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'TimePay Employer Portal')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-emerald-50/40 text-slate-950 antialiased">
    @if (session()->has('impersonated_by'))
        <div class="sticky top-0 z-50 border-b border-red-950 bg-red-800 px-4 py-3 text-white shadow-lg">
            <div class="mx-auto flex max-w-7xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm font-semibold">
                    You are currently impersonating {{ auth()->user()->name }}.
                </p>
                <form method="POST" action="{{ route('impersonation.leave') }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-red-800 transition hover:bg-red-50">
                        Leave Impersonation
                    </button>
                </form>
            </div>
        </div>
    @endif

    <div class="min-h-screen lg:flex">
        <aside class="hidden w-72 flex-shrink-0 border-r border-slate-800 bg-slate-950 text-white lg:flex lg:flex-col">
            <div class="border-b border-slate-800 px-6 py-6">
                <a href="{{ route('employer.dashboard') }}" class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-emerald-500 text-sm font-black text-slate-950 shadow-lg shadow-emerald-950/30">TP</span>
                    <span>
                        <span class="block text-2xl font-bold tracking-tight">TimePay</span>
                        <span class="mt-1 block text-sm text-emerald-200">Employer Portal</span>
                    </span>
                </a>
            </div>

            <nav class="flex-1 space-y-1 px-4 py-6">
                <a href="{{ route('employer.dashboard') }}" class="flex items-center rounded-lg px-4 py-3 text-sm font-medium transition {{ request()->routeIs('employer.dashboard') ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/30' : 'text-slate-300 hover:bg-slate-900 hover:text-emerald-100' }}">
                    Dashboard
                </a>
                <a href="{{ route('employer.attendance') }}" class="flex items-center rounded-lg px-4 py-3 text-sm font-medium transition {{ request()->routeIs('employer.attendance') ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/30' : 'text-slate-300 hover:bg-slate-900 hover:text-emerald-100' }}">
                    Attendance Log
                </a>
                <a href="{{ route('employer.leaves.index') }}" class="flex items-center rounded-lg px-4 py-3 text-sm font-medium transition {{ request()->routeIs('employer.leaves.*') ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/30' : 'text-slate-300 hover:bg-slate-900 hover:text-emerald-100' }}">
                    Leave Management
                </a>
                <a href="{{ route('employer.geofence') }}" class="flex items-center rounded-lg px-4 py-3 text-sm font-medium transition {{ request()->routeIs('employer.geofence') ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/30' : 'text-slate-300 hover:bg-slate-900 hover:text-emerald-100' }}">
                    Geofence Settings
                </a>
                <a href="{{ route('employer.settings.index') }}" class="flex items-center rounded-lg px-4 py-3 text-sm font-medium transition {{ request()->routeIs('employer.settings.*') ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/30' : 'text-slate-300 hover:bg-slate-900 hover:text-emerald-100' }}">
                    Business Settings
                </a>
                <a href="{{ route('employer.payroll') }}" class="flex items-center rounded-lg px-4 py-3 text-sm font-medium transition {{ request()->routeIs('employer.payroll') ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/30' : 'text-slate-300 hover:bg-slate-900 hover:text-emerald-100' }}">
                    Payroll Management
                </a>
            </nav>

            <div class="border-t border-slate-800 p-4">
                <p class="truncate text-sm font-semibold">{{ auth()->user()->company?->name ?? 'Company' }}</p>
                <p class="truncate text-xs text-emerald-200/80">{{ auth()->user()->email }}</p>
            </div>
        </aside>

        <div x-data="{ mobileNavOpen: false, profileOpen: false }" class="flex min-h-screen min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" @click="mobileNavOpen = true" class="rounded-lg border border-emerald-200 p-2 text-emerald-700 lg:hidden">
                            <span class="sr-only">Open navigation</span>
                            <span class="block h-0.5 w-5 bg-current"></span>
                            <span class="mt-1 block h-0.5 w-5 bg-current"></span>
                            <span class="mt-1 block h-0.5 w-5 bg-current"></span>
                        </button>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-emerald-600">TimePay</p>
                            <h1 class="truncate text-lg font-semibold text-slate-950">@yield('header_title', 'Employer Portal')</h1>
                        </div>
                    </div>

                    <div class="relative">
                        <button type="button" @click="profileOpen = ! profileOpen" class="flex items-center gap-3 rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-emerald-50">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </span>
                            <span class="hidden max-w-40 truncate sm:block">{{ auth()->user()->name }}</span>
                        </button>

                        <div x-cloak x-show="profileOpen" @click.outside="profileOpen = false" class="absolute right-0 mt-2 w-56 rounded-lg border border-slate-200 bg-white p-2 shadow-xl">
                            <div class="px-3 py-2">
                                <p class="truncate text-sm font-semibold text-slate-950">{{ auth()->user()->name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
                            </div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full rounded-md px-3 py-2 text-left text-sm font-medium text-red-600 transition hover:bg-red-50">
                                    Log Out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <div x-cloak x-show="mobileNavOpen" class="fixed inset-0 z-40 lg:hidden">
                <div class="absolute inset-0 bg-slate-950/50" @click="mobileNavOpen = false"></div>
                <aside class="relative flex h-full w-72 flex-col bg-slate-950 text-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-800 px-6 py-5">
                        <span class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500 text-xs font-black text-slate-950">TP</span>
                            <span class="text-xl font-bold">TimePay</span>
                        </span>
                        <button type="button" @click="mobileNavOpen = false" class="rounded-md px-2 py-1 text-slate-300 hover:bg-slate-900 hover:text-emerald-100">Close</button>
                    </div>
                    <nav class="space-y-1 px-4 py-6">
                        <a href="{{ route('employer.dashboard') }}" class="block rounded-lg px-4 py-3 text-sm font-medium transition {{ request()->routeIs('employer.dashboard') ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/30' : 'text-slate-300 hover:bg-slate-900 hover:text-emerald-100' }}">Dashboard</a>
                        <a href="{{ route('employer.attendance') }}" class="block rounded-lg px-4 py-3 text-sm font-medium transition {{ request()->routeIs('employer.attendance') ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/30' : 'text-slate-300 hover:bg-slate-900 hover:text-emerald-100' }}">Attendance Log</a>
                        <a href="{{ route('employer.leaves.index') }}" class="block rounded-lg px-4 py-3 text-sm font-medium transition {{ request()->routeIs('employer.leaves.*') ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/30' : 'text-slate-300 hover:bg-slate-900 hover:text-emerald-100' }}">Leave Management</a>
                        <a href="{{ route('employer.geofence') }}" class="block rounded-lg px-4 py-3 text-sm font-medium transition {{ request()->routeIs('employer.geofence') ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/30' : 'text-slate-300 hover:bg-slate-900 hover:text-emerald-100' }}">Geofence Settings</a>
                        <a href="{{ route('employer.settings.index') }}" class="block rounded-lg px-4 py-3 text-sm font-medium transition {{ request()->routeIs('employer.settings.*') ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/30' : 'text-slate-300 hover:bg-slate-900 hover:text-emerald-100' }}">Business Settings</a>
                        <a href="{{ route('employer.payroll') }}" class="block rounded-lg px-4 py-3 text-sm font-medium transition {{ request()->routeIs('employer.payroll') ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/30' : 'text-slate-300 hover:bg-slate-900 hover:text-emerald-100' }}">Payroll Management</a>
                    </nav>
                </aside>
            </div>

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    @if (session('success'))
                        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                            {{ session('success') }}
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</body>
</html>
