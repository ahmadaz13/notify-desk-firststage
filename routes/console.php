<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reminders:send')->everyMinute();
Schedule::command('finance:generate-recurring-expenses')->dailyAt('06:00');
Schedule::command('finance:recognize-revenue')->dailyAt('06:30');
Schedule::command('finance:generate-subscription-renewals')->dailyAt('06:45');
Schedule::command('finance:backfill-saas-metrics --dry-run')->dailyAt('07:00');
