# Notify V1 UI Backend Contract

This contract is for UI/UX work after backend freeze. UI may reorganize screens and navigation, but it must consume backend routes, services, and projections instead of reproducing business rules in Blade.

## Stable Operational Sources

- Client list/detail: `clients.index`, `clients.show`, `clients.store`, `clients.update`.
- Contacts: `clients.contacts.store`, `clients.contacts.update`.
- Contact outcomes: `clients.contact-attempts.store` through `ClientOperationalWorkflowService`.
- Stage changes: `clients.stage.update`, `clients.reopen`; subscriber transition must use paid subscription routes.
- Meeting outcome: `appointments.outcome.store`.
- Free installation: `clients.installations.schedule`, `clients.installations.complete`.
- Operational queues and next action: `OperationalQueueService` and `DailyOperationalService`.
- Review items: `client-review-items.resolve`, `client-review-items.dismiss`.

UI must not mutate lifecycle state by writing arbitrary stage/status values outside approved routes.

## Billing And Collections

- Paid subscriptions: `clients.paid-subscriptions.store`.
- One-time invoices: `clients.one-time-invoices.store`.
- Subscription operations: `subscriptions.plan-change.schedule`, `subscriptions.cancel`, `subscriptions.cancel.undo`, `subscriptions.reactivate`.
- Renewal generation/review: `subscription-billing.index`, `subscription-billing.generate-renewals`, `subscription-billing.backfill-periods`.
- Collections: `clients.collections.payments.store`, `payments.allocations.store`, `payments.auto-allocate`, reversal/refund/credit-note routes.
- Annual installment preview and persistence are backend-owned by `SubscriptionBillingService` and `PaymentScheduleService`. UI sends the annual `PlanPrice`, payment-term choice, installment count, and allowed due day; it displays the returned/persisted schedule without dividing totals locally.

UI must not calculate price, tax, discounts, invoice totals, AR, settlement status, renewal status, or subscription state. Use backend services and route responses.

## Finance Reporting Sources

- Financial statements and management reporting: `finance.index`, `finance.export`, `FinancialStatementService`.
- Executive reporting: `executive.index`.
- Accounting and revenue recognition: `accounting.index`, accounting action routes, `RevenueRecognitionService`.
- Financial accounts, transfers, cash assignment: `financial-accounts.*`, `financial-transfers.*`, `cash-events.assign-account`.
- Expenses: `operating-expenses.*`, categories, vendors, recurring templates, obligations.
- Capital/funding/assets: `capital-management.index`, funding, asset-category, fixed-asset routes.

Blade must not calculate MRR, ARR, accounts receivable, cash balances, P&L, balance sheet values, revenue recognition, taxes, pricing, or subscription movement metrics.

## SaaS Reporting Sources

- Dashboard/export: `saas-metrics.index`, `saas-metrics.export`.
- Authoritative service: `SaasMetricsService`.
- Authoritative records: V2 subscription periods and `subscription_metric_events`.

UI must display SaaS metric projections from the service and must not derive ARR/MRR from raw invoice, payment, or subscription price fields.

## Permissions Expected By UI

- All authenticated application screens are internal-only through `auth` plus `EnsureActiveInternalUser`.
- Staff can work operational CRM flows.
- Admin or `FinancialPermissions` gates are required for catalog, finance, collections, accounting, reporting exports, funding, assets, settings, and legacy/archive administration.
- Legacy partner users are blocked from active application routes.
- Public referral intake can create only referral-attributed prospects/conflict review records; it cannot create users, subscriptions, invoices, payments, or privileged state.

## Notifications And Automation

- User notifications: `notifications.index`, `notifications.read`, `notifications.read-all`.
- Backend scheduler: production cron runs `php artisan schedule:run` every minute.
- Reminder/alert projections come from `NotificationService`; UI should render them only.

## Deprecated Or Read-Only Compatibility

- `clients.convert`, `payments.store`, `expenses.store`, `investments.store`, and `capital-expenses.store` are deprecated/blocked compatibility paths and must not be presented as active primary UI actions.
- Partner dashboard/login/credential/reset workflows are retired; partner data is referral/history metadata only.
