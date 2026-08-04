<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Super Admin') - TimePay</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full antialiased">
@php
    $navigation = [
        ['label' => 'Dashboard', 'route' => 'super-admin.dashboard', 'match' => 'super-admin.dashboard', 'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h3.75c.621 0 1.125.504 1.125 1.125v6.75C9 20.496 8.496 21 7.875 21h-3.75A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 4.125C9.75 3.504 10.254 3 10.875 3h9C20.496 3 21 3.504 21 4.125v4.5c0 .621-.504 1.125-1.125 1.125h-9A1.125 1.125 0 0 1 9.75 8.625v-4.5ZM10.875 12h9c.621 0 1.125.504 1.125 1.125v6.75c0 .621-.504 1.125-1.125 1.125h-9a1.125 1.125 0 0 1-1.125-1.125v-6.75c0-.621.504-1.125 1.125-1.125ZM3 4.125C3 3.504 3.504 3 4.125 3h3.75C8.496 3 9 3.504 9 4.125v4.5C9 9.246 8.496 9.75 7.875 9.75h-3.75A1.125 1.125 0 0 1 3 8.625v-4.5Z'],
        ['label' => 'Employers', 'route' => 'super-admin.employers.index', 'match' => 'super-admin.employers.*', 'icon' => 'M3.75 21h16.5M4.5 3h15l-.75 18H5.25L4.5 3Zm4.125 4.5h6.75M8.625 11.25h6.75M8.625 15h6.75'],
        ['label' => 'Admins', 'route' => 'super-admin.admins.index', 'match' => 'super-admin.admins.*', 'icon' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Zm6.75 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z'],
        ['label' => 'Platform', 'route' => 'super-admin.platform.index', 'match' => 'super-admin.platform.*', 'icon' => 'M10.5 6h3m-7.5 4.5h12m-13.5 9h15a1.5 1.5 0 0 0 1.5-1.5V5.25A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25V18a1.5 1.5 0 0 0 1.5 1.5Zm3-5.25h2.25v2.25H7.5v-2.25Zm4.5 0h2.25v2.25H12v-2.25Zm4.5 0h2.25v2.25H16.5v-2.25Z'],
        ['label' => 'Reports', 'route' => 'super-admin.reports.index', 'match' => 'super-admin.reports.*', 'icon' => 'M3 13.5h2.25v6H3v-6Zm5.25-9h2.25v15H8.25v-15Zm5.25 5.25h2.25V19.5H13.5V9.75Zm5.25-3h2.25V19.5h-2.25V6.75Z'],
    ];
@endphp

<div class="min-h-screen bg-slate-100">
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-72 border-r border-slate-800 bg-slate-950 px-5 py-6 text-slate-200 lg:block">
        <div class="flex items-center gap-3 px-2">
            <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-indigo-500 text-sm font-bold text-white shadow-lg shadow-indigo-950/30">TP</div>
            <div>
                <p class="text-lg font-semibold text-white">TimePay</p>
                <p class="text-xs uppercase tracking-wide text-slate-400">Super Admin</p>
            </div>
        </div>

        <nav class="mt-8 space-y-1">
            @foreach ($navigation as $item)
                @php $active = request()->routeIs($item['match']); @endphp
                <a href="{{ route($item['route']) }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $active ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
                    <svg class="h-5 w-5 flex-none" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="{{ $item['icon'] }}" />
                    </svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="absolute bottom-6 left-5 right-5 rounded-lg border border-slate-800 bg-slate-900/70 p-4">
            <p class="text-sm font-medium text-white">{{ auth()->user()->name }}</p>
            <p class="mt-1 truncate text-xs text-slate-400">{{ auth()->user()->email }}</p>
        </div>
    </aside>

    <div class="lg:pl-72">
        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="flex flex-col gap-4 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">@yield('eyebrow', 'Super Admin')</p>
                    <h1 class="mt-1 text-2xl font-semibold text-slate-950">@yield('page-title', 'Dashboard')</h1>
                    <p class="mt-1 text-sm text-slate-500">@yield('page-description', 'Manage platform operations from one place.')</p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <nav class="grid grid-cols-5 gap-1 rounded-lg border border-slate-200 bg-slate-50 p-1 lg:hidden">
                        @foreach ($navigation as $item)
                            @php $active = request()->routeIs($item['match']); @endphp
                            <a href="{{ route($item['route']) }}" class="rounded-md px-2 py-2 text-center text-xs font-medium {{ $active ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500' }}">{{ $item['label'] }}</a>
                        @endforeach
                    </nav>

                    <div class="flex items-center justify-between gap-3">
                        <div class="hidden rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-500 sm:block">
                            {{ now()->format('M d, Y') }}
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
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
</body>
</html>
