<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'TimePay') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="guest-shell antialiased">
        <main class="flex min-h-screen items-center justify-center px-4 py-8 sm:px-6">
            <section class="box-border w-full min-w-0 max-w-xl rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
                <a href="{{ url('/') }}" class="mb-8 inline-flex rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-4" aria-label="TimePay home">
                    <img src="{{ Vite::asset('resources/img/timepay-logo.png') }}" alt="TimePay" class="h-11 w-auto object-contain">
                </a>
                <div class="w-full">
                    {{ $slot }}
                </div>
            </section>
        </main>
        @stack('scripts')
    </body>
</html>
