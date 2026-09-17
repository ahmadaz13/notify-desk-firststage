<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$timezone = config('app.timezone', 'Asia/Amman');

Schedule::command('reminders:send')->everyMinute()->timezone($timezone)->withoutOverlapping(10);
Schedule::command('finance:generate-recurring-expenses')->dailyAt('06:00')->timezone($timezone)->withoutOverlapping(60);
Schedule::command('finance:recognize-revenue')->dailyAt('06:30')->timezone($timezone)->withoutOverlapping(60);
Schedule::command('finance:generate-subscription-renewals')->dailyAt('06:45')->timezone($timezone)->withoutOverlapping(60);
