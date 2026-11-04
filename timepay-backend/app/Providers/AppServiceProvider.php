<?php

namespace App\Providers;

use App\Mail\Transport\ResendApiTransport;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Mail::extend('resend', function ($app) {
            return new ResendApiTransport((string) config('services.resend.key'));
        });
    }
}
