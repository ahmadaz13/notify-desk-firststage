<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\Carbon;

/**
 * Operations settings (§16) as the application reads them. Every retained key here drives behaviour:
 *
 *  appointment_duration        how long an appointment stays "now" on Today before it is late
 *  free_installation_duration  the same window for installation visits
 *  post_install_followup_days  due date of the automatic follow-up after a free installation
 *  workday_start               default time for date-only work and time suggestions in scheduling forms
 *  workday_end                 end-of-day anchor for date-only work on Today (e.g. a payment due today)
 *  workday_start/end           operational reminders are held outside working hours (§16)
 *
 * Values are validated on save (SettingsController); reads fall back to the defaults so a missing or
 * damaged row can never break Today or scheduling.
 */
final class OperationalSettings
{
    public const DEFAULTS = [
        'appointment_duration' => 60,
        'free_installation_duration' => 60,
        'post_install_followup_days' => 3,
        'workday_start' => '09:00',
        'workday_end' => '17:00',
    ];

    public const MIN_DURATION = 15;
    public const MAX_DURATION = 480;
    public const MIN_FOLLOW_UP_DAYS = 1;
    public const MAX_FOLLOW_UP_DAYS = 60;

    /** @param array<string, int|string> $values */
    private function __construct(private readonly array $values) {}

    /** One query for all operations keys. */
    public static function current(): self
    {
        $stored = Setting::query()->whereIn('key', array_keys(self::DEFAULTS))->pluck('value', 'key')->all();

        $values = [];
        foreach (self::DEFAULTS as $key => $default) {
            $raw = $stored[$key] ?? null;
            $values[$key] = is_int($default)
                ? (is_numeric($raw) ? (int) $raw : $default)
                : (is_string($raw) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', substr($raw, 0, 5)) ? substr($raw, 0, 5) : $default);
        }

        $values['appointment_duration'] = self::clamp($values['appointment_duration'], self::MIN_DURATION, self::MAX_DURATION);
        $values['free_installation_duration'] = self::clamp($values['free_installation_duration'], self::MIN_DURATION, self::MAX_DURATION);
        $values['post_install_followup_days'] = self::clamp($values['post_install_followup_days'], self::MIN_FOLLOW_UP_DAYS, self::MAX_FOLLOW_UP_DAYS);
        if ($values['workday_end'] <= $values['workday_start']) {
            $values['workday_start'] = self::DEFAULTS['workday_start'];
            $values['workday_end'] = self::DEFAULTS['workday_end'];
        }

        return new self($values);
    }

    public function appointmentMinutes(): int
    {
        return $this->values['appointment_duration'];
    }

    public function installationMinutes(): int
    {
        return $this->values['free_installation_duration'];
    }

    public function postInstallFollowUpDays(): int
    {
        return $this->values['post_install_followup_days'];
    }

    /** "09:00" */
    public function workdayStart(): string
    {
        return $this->values['workday_start'];
    }

    /** "17:00" */
    public function workdayEnd(): string
    {
        return $this->values['workday_end'];
    }

    /** $day at the start of the working day, Asia/Amman. */
    public function startOf(Carbon $day): Carbon
    {
        return Carbon::parse(OperationalTime::inZone($day)->toDateString().' '.$this->workdayStart(), OperationalTime::TIMEZONE);
    }

    /** $day at the end of the working day, Asia/Amman. */
    public function endOf(Carbon $day): Carbon
    {
        return Carbon::parse(OperationalTime::inZone($day)->toDateString().' '.$this->workdayEnd(), OperationalTime::TIMEZONE);
    }

    /** True from workday_start (inclusive) until workday_end (exclusive), Asia/Amman. */
    public function isWithinWorkday(Carbon $at): bool
    {
        $clock = OperationalTime::inZone($at)->format('H:i');

        return $clock >= $this->workdayStart() && $clock < $this->workdayEnd();
    }

    /** Suggested date-time for "tomorrow" in scheduling forms: next day at the start of work. */
    public function nextWorkdayStart(?Carbon $from = null): Carbon
    {
        return $this->startOf(($from ?? OperationalTime::now())->copy()->addDay());
    }

    private static function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }
}
