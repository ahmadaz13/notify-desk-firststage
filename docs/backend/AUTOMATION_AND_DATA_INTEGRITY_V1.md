# Notify V1 Automation And Data Integrity

Backend Closure G4 establishes the production scheduler, reminder, alert, and rerun-safety model for Notify V1.

## Production scheduler

Production must run Laravel's scheduler every minute:

```text
* * * * * php artisan schedule:run
```

Scheduled work is backend-only and must not depend on browser requests. The application timezone is `Asia/Amman`; `.env` and `.env.example` set `APP_TIMEZONE=Asia/Amman`, and the Laravel default falls back to `Asia/Amman` if the environment value is missing.

## Scheduled commands

- `reminders:send`: every minute, `Asia/Amman`, `withoutOverlapping(10)`.
- `finance:generate-recurring-expenses`: daily 06:00, `Asia/Amman`, `withoutOverlapping(60)`.
- `finance:recognize-revenue`: daily 06:30, `Asia/Amman`, `withoutOverlapping(60)`.
- `finance:generate-subscription-renewals`: daily 06:45, `Asia/Amman`, `withoutOverlapping(60)`.
`finance:backfill-saas-metrics --dry-run` remains available as a manual diagnostic/backfill command. It is not part of the normal production schedule because backfill/repair review should be deliberately requested, not run daily.

## Operational reminders

`NotificationService` is the scheduler-facing notification authority. `reminders:send` calls `sendScheduledAutomationNotifications`, which projects operational reminders and commercial finance alerts without changing lifecycle or financial state.

Operational reminder types:

- `callback_due`
- `appointment_reminder`
- `installation_reminder`
- `trial_followup_due`
- `decision_followup_due`

Recipients are active internal users only: `admin`, `staff`, or historical null-role internal admins. Legacy `partner` users are not recipients. Responsible internal owners, appointment attendees, and follow-up users are preferred; if the assigned user is not internal, reminders fall back to internal users.

Closed clients are excluded from active-work reminders. Subscriber clients are excluded from normal follow-up queues and are not returned to prospect/contact workflow by reminder generation.

## Commercial finance alerts

Finance alert types:

- `renewal_due`: projected from active V2 subscriptions whose `next_billing_date` is due.
- `invoice_overdue`: projected from `ReceivableService::outstandingInvoices`.
- `billing_review_required`: projected from `SubscriptionBillingService::billingReviewItems`.
- `revenue_recognition_review_required`: projected from `RevenueRecognitionSchedule` review/manual-confirmation state.

These notifications are projections only. They do not create payments, subscriptions, invoices, billing periods, cash movements, journal entries, revenue periods, SaaS metrics, or lifecycle transitions. The authoritative services remain responsible for financial effects.

## Notification idempotency

The `notifications` table has an `occurrence_key` column and a unique key over `user_id`, `type`, `source_type`, `source_id`, and `occurrence_key`. Scheduled notifications use deterministic occurrence keys based on due date/time or review occurrence. Re-running the scheduler does not create duplicate notifications for the same source occurrence.

## Financial command idempotency

Recurring expenses use `recurring_expense_obligations` uniqueness by template and due date. Subscription renewals use V2 billing periods and invoice linkage to avoid duplicate renewal effects. Revenue recognition uses revenue-recognition period status and journal event keys so a recognized period is not reposted.

Financial commands return non-zero when real revenue-recognition execution produces ambiguous failures. Failures are reported to application logs and the command can be retried after inspection; unrelated records must not be duplicated by a retry.

## Data integrity boundaries

G4 adds only the notification occurrence uniqueness needed for scheduler idempotency. Existing G1-G3 and finance structures keep their authoritative constraints, including recurring obligation uniqueness, subscription billing-period uniqueness, revenue schedule and period uniqueness, subscription metric event uniqueness, cash movement event keys, and reversal uniqueness.

Multi-record business effects remain transaction-bound in their authoritative services: subscription start and renewal, payment allocation/cash movement, refunds, expenses/cash movement, funding/cash movement, asset acquisition/cash movement, free-installation completion plus follow-up, and operational review resolution.
