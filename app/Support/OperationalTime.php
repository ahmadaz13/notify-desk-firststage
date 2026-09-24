<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Human time labels for operational work (P11). Asia/Amman is the only operational timezone;
 * every label is computed server-side so the browser timezone can never shift a task.
 */
final class OperationalTime
{
    public const TIMEZONE = 'Asia/Amman';

    public static function now(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }

    public static function inZone(Carbon $at): Carbon
    {
        return $at->copy()->timezone(self::TIMEZONE);
    }

    /** Whole minutes from $from to $to (negative when $to is earlier). */
    public static function minutesBetween(Carbon $from, Carbon $to): int
    {
        return intdiv($to->getTimestamp() - $from->getTimestamp(), 60);
    }

    /** "5 دقائق" / "ساعتين" / "3 أيام". */
    public static function duration(int $minutes): string
    {
        $minutes = max(1, $minutes);

        if ($minutes < 60) {
            return trans_choice('notify.today_board.duration.minutes', $minutes, ['count' => $minutes]);
        }

        $hours = intdiv($minutes, 60);
        if ($hours < 24) {
            return trans_choice('notify.today_board.duration.hours', $hours, ['count' => $hours]);
        }

        $days = intdiv($hours, 24);

        return trans_choice('notify.today_board.duration.days', $days, ['count' => $days]);
    }

    /** "10:30 ص" / "10:30 AM". */
    public static function clock(Carbon $at): string
    {
        return self::inZone($at)->locale(app()->getLocale())->translatedFormat('g:i A');
    }

    /** Calendar day relative to the operational today: "اليوم", "أمس", "غداً" or "الأحد 27 سبتمبر". */
    public static function day(Carbon $at, Carbon $now): string
    {
        $at = self::inZone($at)->startOfDay();
        $today = self::inZone($now)->startOfDay();

        if ($at->equalTo($today)) {
            return __('notify.today_board.days.today');
        }
        if ($at->equalTo($today->copy()->subDay())) {
            return __('notify.today_board.days.yesterday');
        }
        if ($at->equalTo($today->copy()->addDay())) {
            return __('notify.today_board.days.tomorrow');
        }

        return $at->locale(app()->getLocale())->translatedFormat('l j F');
    }

    /** Exact time for timed work: clock only for today, otherwise day + clock. */
    public static function dayAndClock(Carbon $at, Carbon $now): string
    {
        if (self::inZone($at)->isSameDay(self::inZone($now))) {
            return self::clock($at);
        }

        return Str::ucfirst(self::day($at, $now)).' · '.self::clock($at);
    }

    /** Full operational date for page context: "الخميس، 24 سبتمبر 2026". */
    public static function longDate(Carbon $at): string
    {
        return self::inZone($at)->locale(app()->getLocale())->translatedFormat(
            app()->getLocale() === 'ar' ? 'l، j F Y' : 'l, j F Y'
        );
    }
}
