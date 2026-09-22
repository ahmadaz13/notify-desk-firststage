<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$timezone = config('app.timezone', 'Asia/Amman');

Schedule::command('reminders:send')->everyMinute()->timezone($timezone)->withoutOverlapping(10);
Schedule::command('finance:generate-recurring-expenses')->dailyAt('06:00')->timezone($timezone)->withoutOverlapping(60);
Schedule::command('finance:recognize-revenue')->dailyAt('06:30')->timezone($timezone)->withoutOverlapping(60);
Schedule::command('finance:generate-subscription-renewals')->dailyAt('06:45')->timezone($timezone)->withoutOverlapping(60);

Artisan::command('financial-idempotency:prune', function () {
    $deleted = 0;

    do {
        $ids = DB::table('idempotency_keys')
            ->where('key', 'like', 'syn_%')
            ->whereIn('status', ['completed', 'failed'])
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(500)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            $deleted += DB::table('idempotency_keys')->whereIn('id', $ids)->delete();
        }
    } while ($ids->count() === 500);

    $this->info("Pruned {$deleted} expired synthetic idempotency records.");
})->purpose('Remove expired, safely completed synthetic financial request claims');

Schedule::command('financial-idempotency:prune')->dailyAt('03:00')->timezone($timezone)->withoutOverlapping(60);
