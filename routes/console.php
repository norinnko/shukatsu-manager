<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run `php artisan schedule:run` from cron every minute (see README).
Schedule::command('shukatsu:fetch-mail')->everyTenMinutes()->withoutOverlapping();
Schedule::command('shukatsu:assess-mail')->everyMinute()->withoutOverlapping();
Schedule::command('shukatsu:line mail')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('shukatsu:line morning')->dailyAt(config('services.line.morning_at'))->timezone('Asia/Tokyo');
