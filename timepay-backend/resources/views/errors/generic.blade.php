<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'TimePay') }}</title>
</head>
<body style="margin:0;min-height:100vh;padding:48px 16px;background:#f8fafc;color:#0f172a;font-family:Arial,Helvetica,sans-serif;box-sizing:border-box">
    <main style="box-sizing:border-box;max-width:560px;min-height:65vh;margin:0 auto;padding:48px 32px;display:flex;flex-direction:column;align-items:center;justify-content:center;border:1px solid #d1fae5;border-radius:20px;background:#fff;text-align:center;box-shadow:0 16px 40px rgba(15,23,42,.08)">
        <div style="width:56px;height:56px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:#fff1f2;color:#be123c" aria-hidden="true">
            <span style="font-size:24px;font-weight:700">!</span>
        </div>
        <h1 style="margin:24px 0 0;font-size:24px;letter-spacing:-.02em">Something went wrong</h1>
        <p style="max-width:420px;margin:12px 0 0;color:#475569;font-size:15px;line-height:1.6">{{ $message }}</p>
        <a href="{{ url('/') }}" style="min-height:44px;margin-top:28px;padding:12px 20px;display:inline-flex;align-items:center;justify-content:center;border-radius:12px;background:#059669;color:#fff;font-size:14px;font-weight:700;text-decoration:none">
            Return to TimePay
        </a>
    </main>
</body>
</html>
