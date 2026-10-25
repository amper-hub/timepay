<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Super Admin') - TimePay</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full antialiased super-admin-shell">
@php
    $navigation = [
        ['label' => 'Dashboard', 'route' => 'super-admin.dashboard', 'match' => 'super-admin.dashboard', 'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h3.75c.621 0 1.125.504 1.125 1.125v6.75C9 20.496 8.496 21 7.875 21h-3.75A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 4.125C9.75 3.504 10.254 3 10.875 3h9C20.496 3 21 3.504 21 4.125v4.5c0 .621-.504 1.125-1.125 1.125h-9A1.125 1.125 0 0 1 9.75 8.625v-4.5ZM10.875 12h9c.621 0 1.125.504 1.125 1.125v6.75c0 .621-.504 1.125-1.125 1.125h-9a1.125 1.125 0 0 1-1.125-1.125v-6.75c0-.621.504-1.125 1.125-1.125ZM3 4.125C3 3.504 3.504 3 4.125 3h3.75C8.496 3 9 3.504 9 4.125v4.5C9 9.246 8.496 9.75 7.875 9.75h-3.75A1.125 1.125 0 0 1 3 8.625v-4.5Z'],
        ['label' => 'Employers', 'route' => 'super-admin.employers.index', 'match' => 'super-admin.employers.*', 'icon' => 'M3.75 21h16.5M4.5 3h15l-.75 18H5.25L4.5 3Zm4.125 4.5h6.75M8.625 11.25h6.75M8.625 15h6.75'],
        ['label' => 'Admins', 'route' => 'super-admin.admins.index', 'match' => 'super-admin.admins.*', 'icon' => 'M15 19.128a9.003 9.003 0 0 0-6 0m6 0a9.003 9.003 0 0 1 6 0m-6 0v-3.375a3 3 0 1 0-6 0v3.375m6 0v.003M12 12.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z'],
        ['label' => 'Platform', 'route' => 'super-admin.platform.index', 'match' => 'super-admin.platform.*', 'icon' => 'M10.5 6h3m-7.5 4.5h12m-13.5 9h15a1.5 1.5 0 0 0 1.5-1.5V5.25A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25V18a1.5 1.5 0 0 0 1.5 1.5Zm3-5.25h2.25v2.25H7.5v-2.25Zm4.5 0h2.25v2.25H12v-2.25Zm4.5 0h2.25v2.25H16.5v-2.25Z'],
        ['label' => 'Leaves', 'route' => 'super-admin.leaves.index', 'match' => 'super-admin.leaves.*', 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M5.25 5.25h13.5A1.5 1.5 0 0 1 20.25 6.75v12A2.25 2.25 0 0 1 18 21H6a2.25 2.25 0 0 1-2.25-2.25v-12A1.5 1.5 0 0 1 5.25 5.25Zm3 7.5h2.25v2.25H8.25v-2.25Zm4.5 0H15v2.25h-2.25v-2.25Z'],
        ['label' => 'Reports', 'route' => 'super-admin.reports.index', 'match' => 'super-admin.reports.*', 'icon' => 'M3 13.5h2.25v6H3v-6Zm5.25-9h2.25v15H8.25v-15Zm5.25 5.25h2.25V19.5H13.5V9.75Zm5.25-3h2.25V19.5h-2.25V6.75Z'],
    ];
@endphp

<div class="min-h-screen bg-slate-50">
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-72 border-r border-slate-200 bg-white px-5 py-6 text-slate-700 lg:block">
        <div class="flex items-center gap-3 px-2">
            <img src="{{ Vite::asset('resources/img/timepay-logo.png') }}" alt="TimePay" class="h-10 w-auto object-contain">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Super Admin</p>
            </div>
        </div>

        <nav aria-label="Super Admin navigation" class="mt-8 space-y-1">
            @foreach ($navigation as $item)
                @php $active = request()->routeIs($item['match']); @endphp
                <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif class="flex min-h-11 items-center gap-3 rounded-xl border-l-4 px-3 py-2.5 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2 {{ $active ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-transparent text-slate-600 hover:bg-slate-50 hover:text-emerald-700' }}">
                    <svg class="h-5 w-5 flex-none" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="{{ $item['icon'] }}" />
                    </svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="absolute bottom-6 left-5 right-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-950">{{ auth()->user()->name }}</p>
            <p class="mt-1 truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
        </div>
    </aside>

    <div class="lg:pl-72">
        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="flex flex-col gap-4 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                <div>
                    <p class="text-sm font-semibold text-emerald-700">@yield('eyebrow', 'Super Admin')</p>
                    <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-900">@yield('page-title', 'Dashboard')</h1>
                    <p class="mt-1 text-base text-slate-600">@yield('page-description', 'Manage platform operations from one place.')</p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <nav aria-label="Super Admin navigation" class="flex max-w-full gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-slate-50 p-1 lg:hidden">
                        @foreach ($navigation as $item)
                            @php $active = request()->routeIs($item['match']); @endphp
                            <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif class="shrink-0 rounded-lg px-3 py-2 text-center text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 {{ $active ? 'bg-white text-emerald-800 shadow-sm' : 'text-slate-600 hover:bg-white hover:text-emerald-700' }}">{{ $item['label'] }}</a>
                        @endforeach
                    </nav>

                    <div class="flex items-center justify-between gap-3">
                        <div class="hidden rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 sm:block">
                            {{ now()->format('M d, Y') }}
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="min-h-11 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2">
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="px-4 py-6 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
            @endif
            @if (session('info'))
                <div class="mb-5 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-medium text-sky-800">{{ session('info') }}</div>
            @endif
            @if (isset($errors) && $errors->any())
                <div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
                    Please review the highlighted fields and try again.
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
