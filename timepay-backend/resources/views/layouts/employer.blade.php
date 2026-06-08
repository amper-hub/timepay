<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'TimePay Employer Portal')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-slate-100 text-slate-950 antialiased">
    <div class="min-h-screen lg:flex">
        <aside class="hidden w-72 flex-shrink-0 border-r border-slate-800 bg-slate-950 text-white lg:flex lg:flex-col">
            <div class="border-b border-slate-800 px-6 py-6">
                <a href="{{ route('employer.dashboard') }}" class="block text-2xl font-bold tracking-tight">TimePay</a>
                <p class="mt-1 text-sm text-slate-400">Employer Portal</p>
            </div>

            <nav class="flex-1 space-y-1 px-4 py-6">
                <a href="{{ route('employer.dashboard') }}" class="flex items-center rounded-lg px-4 py-3 text-sm font-medium transition {{ request()->routeIs('employer.dashboard') ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
                    Dashboard
                </a>
                <a href="{{ route('employer.attendance') }}" class="flex items-center rounded-lg px-4 py-3 text-sm font-medium transition {{ request()->routeIs('employer.attendance') ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
                    Attendance Log
                </a>
                <a href="{{ route('employer.geofence') }}" class="flex items-center rounded-lg px-4 py-3 text-sm font-medium transition {{ request()->routeIs('employer.geofence') ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
                    Geofence Settings
                </a>
                <a href="{{ route('employer.payroll') }}" class="flex items-center rounded-lg px-4 py-3 text-sm font-medium transition {{ request()->routeIs('employer.payroll') ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
                    Payroll Management
                </a>
            </nav>

            <div class="border-t border-slate-800 p-4">
                <p class="truncate text-sm font-semibold">{{ auth()->user()->company?->name ?? 'Company' }}</p>
                <p class="truncate text-xs text-slate-400">{{ auth()->user()->email }}</p>
            </div>
        </aside>

        <div x-data="{ mobileNavOpen: false, profileOpen: false }" class="flex min-h-screen min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" @click="mobileNavOpen = true" class="rounded-lg border border-slate-200 p-2 text-slate-700 lg:hidden">
                            <span class="sr-only">Open navigation</span>
                            <span class="block h-0.5 w-5 bg-current"></span>
                            <span class="mt-1 block h-0.5 w-5 bg-current"></span>
                            <span class="mt-1 block h-0.5 w-5 bg-current"></span>
                        </button>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">TimePay</p>
                            <h1 class="truncate text-lg font-semibold text-slate-950">@yield('header_title', 'Employer Portal')</h1>
                        </div>
                    </div>

                    <div class="relative">
                        <button type="button" @click="profileOpen = ! profileOpen" class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-950 text-xs font-bold text-white">
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
                        <span class="text-xl font-bold">TimePay</span>
                        <button type="button" @click="mobileNavOpen = false" class="rounded-md px-2 py-1 text-slate-300 hover:bg-slate-900">Close</button>
                    </div>
                    <nav class="space-y-1 px-4 py-6">
                        <a href="{{ route('employer.dashboard') }}" class="block rounded-lg px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-900 hover:text-white">Dashboard</a>
                        <a href="{{ route('employer.attendance') }}" class="block rounded-lg px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-900 hover:text-white">Attendance Log</a>
                        <a href="{{ route('employer.geofence') }}" class="block rounded-lg px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-900 hover:text-white">Geofence Settings</a>
                        <a href="{{ route('employer.payroll') }}" class="block rounded-lg px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-900 hover:text-white">Payroll Management</a>
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
