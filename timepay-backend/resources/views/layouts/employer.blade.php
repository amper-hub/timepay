<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'TimePay Employer Portal')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="employer-shell min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    @php
        $employerNavGroups = [
            ['label' => 'Workspace', 'items' => [
                ['label' => 'Overview', 'route' => 'employer.dashboard', 'active' => 'employer.dashboard', 'icon' => 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z'],
            ]],
            ['label' => 'People', 'items' => [
                ['label' => 'Employees', 'route' => 'employer.employees.index', 'active' => 'employer.employees.*', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
                ['label' => 'Attendance', 'route' => 'employer.attendance', 'active' => 'employer.attendance*', 'icon' => 'M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z'],
                ['label' => 'Leave requests', 'route' => 'employer.leaves.index', 'active' => 'employer.leaves.*', 'icon' => 'M7 3h8l5 5v13H7zM15 3v5h5M10 13h7M10 17h7'],
            ]],
            ['label' => 'Pay and workplace', 'items' => [
                ['label' => 'Payroll', 'route' => 'employer.payroll', 'active' => 'employer.payroll*', 'icon' => 'M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
                ['label' => 'Business settings', 'route' => 'employer.settings.index', 'active' => 'employer.settings.*', 'icon' => 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-1.7 2.94-.08-.02a1.7 1.7 0 0 0-1.76.57l-.05.07h-3.4l-.05-.07a1.7 1.7 0 0 0-1.76-.57l-.08.02-1.7-2.94.06-.06A1.7 1.7 0 0 0 9.6 15l-.08-.04v-3.4l.08-.04a1.7 1.7 0 0 0-.34-1.88l-.06-.06 1.7-2.94.08.02a1.7 1.7 0 0 0 1.76-.57l.05-.07h3.4l.05.07a1.7 1.7 0 0 0 1.76.57l.08-.02 1.7 2.94-.06.06a1.7 1.7 0 0 0-.34 1.88l.08.04v3.4z'],
                ['label' => 'Location rules', 'route' => 'employer.geofence', 'active' => 'employer.geofence*', 'icon' => 'M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0zM12 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4z'],
            ]],
        ];
        $navLinkClass = 'group flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-[15px] font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2';
        $navActiveClass = 'bg-emerald-50 text-emerald-800';
        $navInactiveClass = 'text-slate-600 hover:bg-slate-100 hover:text-slate-950';
    @endphp

    <div class="min-h-screen lg:flex">
        <aside class="hidden w-64 shrink-0 border-r border-slate-200 bg-white lg:fixed lg:inset-y-0 lg:flex lg:flex-col">
            <a href="{{ route('employer.dashboard') }}" class="flex h-20 items-center border-b border-slate-200 px-6" aria-label="TimePay employer overview">
                <img src="{{ Vite::asset('resources/img/timepay-logo.png') }}" alt="TimePay" class="h-10 w-auto object-contain">
                <span class="ml-3 border-l border-slate-200 pl-3 text-sm font-medium text-slate-500">Employer</span>
            </a>
            <nav class="flex-1 space-y-6 overflow-y-auto px-4 py-6" aria-label="Employer navigation">
                @foreach ($employerNavGroups as $group)
                    <div>
                        <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $group['label'] }}</p>
                        <div class="mt-2 space-y-1">
                            @foreach ($group['items'] as $item)
                                @php($isActive = request()->routeIs($item['active']))
                                <a href="{{ route($item['route']) }}" @if ($isActive) aria-current="page" @endif class="{{ $navLinkClass }} {{ $isActive ? $navActiveClass : $navInactiveClass }}">
                                    <svg class="h-5 w-5 shrink-0 {{ $isActive ? 'text-emerald-700' : 'text-slate-400 group-hover:text-slate-600' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $item['icon'] }}" /></svg>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>
            <div class="border-t border-slate-200 p-4">
                <p class="truncate text-sm font-semibold text-slate-900">{{ auth()->user()->company?->name ?? 'Your business' }}</p>
                <p class="mt-0.5 truncate text-sm text-slate-500">{{ auth()->user()->email }}</p>
            </div>
        </aside>

        <div x-data="{ mobileNavOpen: false, profileOpen: false }" class="min-h-screen min-w-0 flex-1 lg:ml-64">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
                <div class="flex min-h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" @click="mobileNavOpen = true" :aria-expanded="mobileNavOpen.toString()" aria-controls="employer-mobile-navigation" aria-label="Open navigation" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 lg:hidden">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                        </button>
                        <div class="min-w-0">
                            <p class="hidden text-xs font-semibold uppercase tracking-wider text-emerald-700 sm:block">{{ auth()->user()->company?->name ?? 'TimePay' }}</p>
                            <h1 class="truncate text-lg font-semibold text-slate-950 sm:text-xl">@yield('header_title', 'Employer Portal')</h1>
                        </div>
                    </div>

                    <div class="relative shrink-0">
                        <button type="button" @click="profileOpen = !profileOpen" :aria-expanded="profileOpen.toString()" class="flex min-h-10 items-center gap-2 rounded-xl px-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 sm:border sm:border-slate-200 sm:px-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-sm font-semibold text-emerald-800">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                            <span class="hidden max-w-40 truncate sm:block">{{ auth()->user()->name }}</span>
                            <svg class="hidden h-4 w-4 text-slate-400 sm:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06z" clip-rule="evenodd" /></svg>
                        </button>
                        <div x-cloak x-show="profileOpen" @click.outside="profileOpen = false" @keydown.escape.window="profileOpen = false" class="absolute right-0 mt-2 w-60 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                            <div class="px-3 py-2">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</p>
                                <p class="truncate text-sm text-slate-500">{{ auth()->user()->email }}</p>
                            </div>
                            <a href="{{ route('profile.edit') }}" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Account settings</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-red-700 hover:bg-red-50">Log out</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <div x-cloak x-show="mobileNavOpen" x-transition.opacity class="fixed inset-0 z-50 lg:hidden" id="employer-mobile-navigation" @keydown.escape.window="mobileNavOpen = false">
                <button type="button" class="absolute inset-0 bg-slate-950/30" aria-label="Close navigation" @click="mobileNavOpen = false"></button>
                <aside x-show="mobileNavOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="relative flex h-full w-[min(19rem,88vw)] flex-col border-r border-slate-200 bg-white shadow-xl">
                    <div class="flex h-16 items-center justify-between border-b border-slate-200 px-5">
                        <img src="{{ Vite::asset('resources/img/timepay-logo.png') }}" alt="TimePay" class="h-9 w-auto object-contain">
                        <button type="button" @click="mobileNavOpen = false" class="min-h-10 rounded-lg px-3 text-sm font-medium text-slate-600 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">Close</button>
                    </div>
                    <nav class="flex-1 space-y-6 overflow-y-auto px-4 py-6" aria-label="Mobile employer navigation">
                        @foreach ($employerNavGroups as $group)
                            <div>
                                <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $group['label'] }}</p>
                                <div class="mt-2 space-y-1">
                                    @foreach ($group['items'] as $item)
                                        @php($isActive = request()->routeIs($item['active']))
                                        <a href="{{ route($item['route']) }}" @if ($isActive) aria-current="page" @endif class="{{ $navLinkClass }} {{ $isActive ? $navActiveClass : $navInactiveClass }}">
                                            <svg class="h-5 w-5 shrink-0 {{ $isActive ? 'text-emerald-700' : 'text-slate-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $item['icon'] }}" /></svg>
                                            <span>{{ $item['label'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </nav>
                    <div class="border-t border-slate-200 px-5 py-4">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ auth()->user()->company?->name ?? 'Your business' }}</p>
                        <p class="mt-0.5 truncate text-sm text-slate-500">{{ auth()->user()->email }}</p>
                    </div>
                </aside>
            </div>

            <main class="min-h-[calc(100vh-4rem)] px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <div class="mx-auto max-w-7xl space-y-6">
                    @if (session('success'))
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">{{ session('success') }}</div>
                    @endif
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</body>
</html>
