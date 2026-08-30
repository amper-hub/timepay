<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased">
        <div class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6">
            <div class="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-6xl overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-timepay-sm">
                <div class="relative hidden flex-1 overflow-hidden bg-gradient-to-br from-emerald-600 via-teal-600 to-slate-900 p-10 text-white lg:flex lg:flex-col lg:justify-between">
                    <div class="absolute left-10 top-28 h-44 w-44 rounded-full border border-white/15"></div>
                    <div class="absolute bottom-10 right-10 h-56 w-56 rounded-full border border-emerald-200/20"></div>
                    <a href="/" class="relative inline-flex items-center gap-3">
                        <img src="{{ Vite::asset('resources/img/timepay-logo.png') }}" alt="TimePay" class="h-12 w-auto object-contain">
                    </a>
                    <div class="relative max-w-md">
                        <span class="inline-flex rounded-full bg-white/15 px-3 py-1 text-sm font-semibold text-emerald-50 ring-1 ring-white/20">Bright Emerald & Time</span>
                        <h1 class="mt-6 text-4xl font-bold leading-tight">Precise teams start with clean time.</h1>
                        <p class="mt-4 text-base leading-7 text-emerald-50/85">Track attendance, confirm location, and keep payroll-ready records in one crisp workspace.</p>
                    </div>
                    <div class="relative grid grid-cols-3 gap-3 text-sm">
                        <div class="rounded-lg border border-white/15 bg-white/10 p-4 backdrop-blur">
                            <p class="text-2xl font-bold">24h</p>
                            <p class="mt-1 text-emerald-50/80">live records</p>
                        </div>
                        <div class="rounded-lg border border-white/15 bg-white/10 p-4 backdrop-blur">
                            <p class="text-2xl font-bold">GPS</p>
                            <p class="mt-1 text-emerald-50/80">verified</p>
                        </div>
                        <div class="rounded-lg border border-white/15 bg-white/10 p-4 backdrop-blur">
                            <p class="text-2xl font-bold">ID</p>
                            <p class="mt-1 text-emerald-50/80">protected</p>
                        </div>
                    </div>
                </div>

                <div class="flex w-full flex-col justify-center px-6 py-8 sm:px-10 lg:w-[31rem]">
                    <div class="mb-8 lg:hidden">
                        <a href="/" class="inline-flex items-center gap-3">
                            <x-application-logo class="h-12 w-auto" />
                        </a>
                    </div>

                    <div class="w-full">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
