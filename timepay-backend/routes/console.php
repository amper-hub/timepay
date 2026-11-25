<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Close non-overtime shifts at the employer-configured end time.
Schedule::command('attendance:auto-clock-out')->everyMinute()->withoutOverlapping();
