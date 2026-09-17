# Notify Desk V1 Time Policy

## Business timezone

Notify Desk V1 has one business timezone: `Asia/Amman`. `APP_TIMEZONE` is the deployment configuration source and `config/app.php` uses `Asia/Amman` as its fallback. Fixed offsets such as `UTC+3` are not authoritative because timezone rules belong to the IANA zone.

Laravel's configured clock is the application clock. Business code should use `now()`, `today()`, `Carbon::now()`, or an existing `CarbonImmutable` value without overriding the timezone.

## Datetime semantics

Appointments, callbacks, payment events, contract creation, audit timestamps, and other datetimes are entered, interpreted, queried, and displayed as Jordan business time. V1 does not implement per-user timezone conversion or a separate UTC-normalization architecture.

Database `created_at` and `updated_at` defaults are persistence metadata. Their presence in migrations does not create a second business-time policy.

## Date-only semantics

Subscription period dates, renewal dates, invoice issue/due dates, installment due dates, recognition dates, and other SQL `DATE` values are calendar dates. They must be persisted and rendered without timezone conversion, so an offset cannot move them to an adjacent day.

## Today semantics

The Today board, appointment lists, callbacks, reminders, and daily operational queues use `today()` or `Carbon::today()` under the application timezone. Cache keys that include a date use the same Jordan calendar day.

## Billing calendar arithmetic

- Monthly periods use `addMonthNoOverflow()`.
- Annual periods use `addYearNoOverflow()`.
- Period ends are the day before the next period starts.
- January 31 clamps deterministically to the final February calendar day.
- A February 29 annual anniversary clamps to February 28 in a non-leap year.
- Annual installment dates use `addMonthsNoOverflow()` and remain collection dates inside one annual term.
- Reporting windows that intentionally use `addDays(30)` are horizons, not subscription month calculations.

## Scheduling semantics

The schedules in `routes/console.php` explicitly use `config('app.timezone', 'Asia/Amman')`. Production cron invokes `php artisan schedule:run`; Laravel applies the Jordan timezone to each defined business clock time.
