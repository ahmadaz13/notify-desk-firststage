# Notify V1 Backend Freeze

Backend Closure G5 freezes the Notify V1 backend after approved G1-G4. The backend is ready for UI/UX work as long as future UI consumes these routes and services without duplicating business rules.

## Approved Domains

- Client lifecycle, contacts, appointments, free installation, three-day follow-up, close/reopen, and operational queues.
- Active internal identity model for `admin` and `staff`.
- Partner/referral history as metadata only.
- Commercial catalog, PlanPrice pricing, V2 subscriptions, billing periods, invoices, receivables, collections, credits, refunds, cash, expenses, funding, fixed assets, accounting, revenue recognition, financial reporting, SaaS metrics, scheduled automation, notifications, and reminders.

## Authoritative Sources

- Lifecycle and operational workflow: `ClientOperationalWorkflowService`, `FreeInstallationService`, `MeetingOutcomeService`, `OperationalQueueService`, `DailyOperationalService`, `ClientLifecycle`.
- Identity and authorization: `User::isActiveApplicationUser()`, `EnsureActiveInternalUser`, policies, gates, and controller authorization.
- Commercial catalog and pricing: `plans`, `plan_prices`, `CommercialPricingService`, `PlanPriceService`.
- Subscriptions and renewal automation: V2 `subscriptions`, `subscription_billing_periods`, `subscription_events`, `SubscriptionBillingService`.
- Invoices and receivables: `InvoiceService`, `ReceivableService`, V2 invoices, payment allocations, credit applications, refunds, and reversals.
- Cash and financial accounts: `FinancialAccountService`, `CashMovementService`, `financial_accounts`, `cash_movements`.
- Expenses: V2 `OperatingExpenseService`, `RecurringExpenseService`, vendors, categories, recurring templates, obligations.
- Accounting: `AccountingSetupService`, `JournalPostingService`, `BillingAccountingService`, `AccountingReconciliationService`.
- Revenue recognition: `RevenueRecognitionService`, `revenue_recognition_schedules`, `revenue_recognition_periods`.
- Financial reporting: `FinancialStatementService`, `FinancialReportingReconciliationService`.
- SaaS metrics: `SaasMetricEventService`, `SaasMetricsService`, `SaasMetricsReconciliationService`.
- Automation and reminders: `routes/console.php`, `NotificationService`, deterministic `notifications.occurrence_key`.

## Canonical Client Stages

`prospect`, `contacting`, `appointment`, `installation_scheduled`, `installed_free`, `decision_pending`, `subscriber`, `closed`.

The `subscriber` stage is entered only through the explicit V2 paid subscription workflow. Closed clients require explicit reopen. Reminder generation never changes lifecycle state.

## Roles

- `founder`: canonical live V1 account with full owner-level authority.
- `admin`: owner-level compatibility/future value; no account-management workflow is part of V1.
- `staff`: existing operational compatibility value; no new staff-management workflow is part of V1.
- `employee`: reserved future value and not active in V1.
- `partner`: preserved historical role only; not an active application identity, owner scope, notification recipient, or financial authority.

## Financial Invariants

- V2 money is stored in exact minor units.
- Invoice, payment, allocation, credit, refund, cash, journal, revenue-recognition, SaaS metric, and reversal history is append-only except through approved void/reversal flows.
- Legacy dashboard formulas, legacy conversion, legacy expense/investment/capital write paths, and partner share accessors are not authoritative financial sources.
- Scheduled finance jobs are idempotent and safe to rerun.
- Backfill/repair commands remain manual operational tools, not normal daily production jobs unless a future approved runbook changes that.

## Scheduler Requirements

Production cron must run:

```text
* * * * * php artisan schedule:run
```

Scheduled commands:

- `reminders:send`: every minute, `Asia/Amman`, `withoutOverlapping(10)`.
- `finance:generate-recurring-expenses`: daily 06:00, `Asia/Amman`, `withoutOverlapping(60)`.
- `finance:recognize-revenue`: daily 06:30, `Asia/Amman`, `withoutOverlapping(60)`.
- `finance:generate-subscription-renewals`: daily 06:45, `Asia/Amman`, `withoutOverlapping(60)`.

Manual diagnostic/backfill command:

- `finance:backfill-saas-metrics --dry-run`.

## Preserved Legacy Compatibility

Historical partner records, partner attribution, legacy payment schedules, legacy investments, legacy capital expenses, legacy subscriptions/contracts, and deprecated compatibility routes remain only for history, read compatibility, or explicit 410 responses. They must not become active business authority again.

## Non-Blocking Issues

- Current local reconciliation commands may emit existing Laravel vendor `BelongsTo` deprecation warnings; reconciliation results remain `ok=true`.
- MySQL 8 validation was not executed unless an isolated local/test MySQL database is explicitly configured.
