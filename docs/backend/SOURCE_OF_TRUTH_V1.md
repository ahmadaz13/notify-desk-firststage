# Notify V1 Backend Source Of Truth

Backend Closure G1 establishes these implemented sources as authoritative for V1. G2 establishes the operational workflow. G3 establishes the active identity and partner/referral policy. Legacy tables and routes can remain readable for historical compatibility, but active workflows must not use them as competing sources of truth.

Backend Closure G4 establishes scheduler, reminders, finance alerts, and rerun-safety boundaries. Reference: `docs/backend/AUTOMATION_AND_DATA_INTEGRITY_V1.md`.

Gate 6 freezes `Asia/Amman` as the single V1 business timezone and the final architecture/legacy boundaries. References: `docs/backend/V1_TIME_POLICY.md`, `docs/backend/V1_FINAL_ARCHITECTURE.md`, and `docs/backend/V1_LEGACY_COMPATIBILITY_MATRIX.md`.

## Identity and authorization

- Canonical live V1 users: active internal `founder` accounts. Existing `admin` and `staff` values remain accepted compatibility identities; `employee` is reserved and Partner is never an active identity.
- Authoritative model/helper: `User::isActiveApplicationUser()`.
- Authoritative middleware: `EnsureActiveInternalUser` on authenticated application routes.
- Authorization boundary: staff can perform normal CRM and operational work; admin or existing strict `FinancialPermissions` gates are required for financial/system configuration, accounting administration, reporting exports, settings, and legacy/archive administration.
- Legacy partner role: `role=partner` can remain in historical data but is not an active application identity. `User::isPartner()` returns false for active workflow decisions.
- Detailed policy reference: `docs/backend/IDENTITY_AND_PERMISSIONS_V1.md`.

## Clients and lifecycle

- Authoritative records: `clients`, client contacts, contact attempts, appointments, follow-ups, offers, and free-installation state.
- Authoritative records added by G2: `client_review_items`.
- Authoritative controllers/services: `ClientController`, `ClientStageController`, `ContactAttemptController`, `MeetingOutcomeController`, `FreeInstallationController`, `ClientReviewItemController`, `ClientOperationalWorkflowService`, `OperationalQueueService`, `DailyOperationalService`, `FreeInstallationService`, `ClientLifecycle`.
- Notes: client lifecycle changes are explicit stage/status transitions. Payment receipt, legacy conversion, manual stage update, CSV import, contact attempts, appointments, free installation, and follow-up must not promote a prospect into a subscriber. The subscriber stage is entered only through the explicit V2 paid subscription workflow.
- Assignment boundary: internal ownership/attendees/installers reference internal users. `partner_id` remains referral/history attribution only and must not scope authorization or visibility.
- Operational workflow reference: `docs/backend/OPERATIONAL_WORKFLOW_V1.md`.

## Partner referrals

- Authoritative records: `partners` and `client_partner_attributions`.
- Authoritative services: `ClientPartnerAttributionService` for assignment snapshots and `PartnerCommissionService` for read-only performance reporting.
- Notes: `clients.partner_id` remains synchronized for legacy compatibility. New commission agreements are stored as basis-point snapshots per Client. Commission reporting uses canonical V2 cash movements for payment receipts less payment reversals and refunds; it does not create payables, expenses, journals, or a second collection ledger.
- Identity boundary: Partners are external referral records only and never active application users.

## Commercial catalog

- Authoritative records: `plans`, `plan_prices`, and `services`.
- Authoritative controllers/services: `CommercialCatalogController`, `CommercialPricingService`, `PlanPriceService`.
- Notes: `PlanPrice` rows are the source for recurring V2 commercial price. Annual prices are explicit versioned rows. V2 pricing does not use a hidden 100 JOD fallback or global discount.

## Subscriptions

- Authoritative records: V2 `subscriptions`, `subscription_billing_periods`, and subscription events.
- Authoritative controllers/services: `BillingController@startPaidSubscription`, `SubscriptionController`, `SubscriptionBillingService`.
- Deprecated compatibility: `ClientController@convert` now returns 410 after authorization and must not bypass PlanPrice, billing periods, invoices, accounting, revenue recognition, or the G2 operational subscriber boundary.

## Billing periods

- Authoritative records: `subscription_billing_periods`.
- Authoritative controllers/services: `SubscriptionBillingController`, `SubscriptionBillingService`.
- Notes: recurring renewals and billing state are generated through V2 billing periods, not legacy payment schedules.

## Billing terms and annual installments

- Monthly and annual term authority remains the selected effective `PlanPrice` plus `SubscriptionBillingService`; annual installments never create a monthly subscription or a synthetic monthly recurring price.
- A V2 annual installment schedule is linked to one annual issued invoice and records collection dates and exact minor-unit portions only. Its rows must sum exactly to that invoice total, and reruns for the same invoice are idempotent.
- The annual invoice remains the sole receivable obligation. Actual payment allocations drive collected and remaining values; schedule dates alone do not create cash, revenue, journals, receivables, SaaS movement events, or partner commission.
- Renewal remains annual and creates one new annual period, one annual invoice, and, when selected, one new invoice-linked collection schedule.

## Invoices

- Authoritative records: `invoices` and `invoice_lines`.
- Authoritative controllers/services: `BillingController`, `InvoiceService`, V2 one-time invoice and paid-subscription flows.
- Notes: invoice snapshots and issued invoices are immutable financial history except through approved void/correction flows.

## Receivables

- Authoritative service: `ReceivableService`.
- Authoritative inputs: issued invoices, V2 payment allocations, credit note applications, refunds, and reversals.
- Schedule exclusion: neither legacy nor V2 `payment_schedules` rows are receivables. V2 rows are collection-timing projections subordinate to an issued invoice.

## Payments

- Authoritative records: V2 `payments`, `payment_allocations`, payment reversals, and refunds.
- Authoritative controllers/services: `CollectionsController`, `PaymentAllocationService`, `CollectionCorrectionService`, `RefundService`, `CashMovementService`.
- Deprecated compatibility: top-level `payments.store` now returns 410 after `RECORD_PAYMENT` authorization. New receipts must be recorded through `clients.collections.payments.store` with a financial account and allocation/cash handling.

## Credits/refunds

- Authoritative records: credit notes, credit note applications, payment refunds, and credit-note refunds.
- Authoritative controllers/services: `CollectionsController`, `ReceivableService`, `RefundService`, `BillingAccountingService`.
- Notes: unallocated V2 payment credit and credit notes are the supported customer-credit mechanisms.

## Cash accounts

- Authoritative records: `financial_accounts`, `cash_movements`, financial transfers, and cash-event account assignments.
- Authoritative controllers/services: `FinancialAccountController`, `FinancialAccountService`, `CashMovementService`.
- Notes: cash balances come from D1 financial-account cash movements, not dashboard liquidity formulas.

## Expenses

- Authoritative records: V2 `expenses` with `expense_engine_version = v2`, recurring expense templates, recurring expense obligations, vendors, and expense categories.
- Authoritative controllers/services: `OperatingExpenseController`, `OperatingExpenseService`, `RecurringExpenseService`.
- Deprecated compatibility: legacy `expenses.store` and `DashboardController@storeExpense` now return 410 after `MANAGE_EXPENSES` authorization. Historical legacy expense rows remain readable, but they are not authoritative V2 accounting/cash records.

## Funding

- Authoritative records: funding sources and `capital_funding_transactions`.
- Authoritative controllers/services: `CapitalManagementController`, `CapitalManagementService`.
- Legacy exclusion: old `investments` rows remain historical and are not automatically capital funding.

## Assets

- Authoritative records: asset categories and `fixed_assets`.
- Authoritative controllers/services: `CapitalManagementController`, `CapitalManagementService`.
- Legacy exclusion: old `capital_expenses` rows remain historical and are not automatically fixed assets.

## Accounting

- Authoritative records: `chart_accounts`, `journal_entries`, `journal_lines`, and accounting periods.
- Authoritative controllers/services: `AccountingController`, `AccountingEventPostingService`, `BillingAccountingService`, `AccountingReconciliationService`.
- Notes: journals are not rewritten by G1. Backfills and period changes remain explicit accounting actions.

## Revenue recognition

- Authoritative records: `revenue_recognition_schedules` and recognition periods/journals.
- Authoritative controllers/services: `RevenueRecognitionService`, accounting revenue-recognition routes.
- Notes: recognized revenue is not calculated from raw payment totals.

## Financial statements

- Authoritative controllers/services: `FinanceReportController`, `FinancialStatementService`, Finance F1 reporting services.
- Authoritative exports: `FinanceReportController@export` and `FinancialReportExport` now use F1 financial statement values instead of duplicate dashboard formulas.
- Deprecated exclusions: old gross revenue, synthetic operating cost, net revenue, net profit, market value, legacy ARR, and liquidity formulas must not be presented as authoritative.

## SaaS metrics

- Authoritative records: subscription metric events and V2 subscription state.
- Authoritative controllers/services: `SaasMetricsController`, `SaasMetricsService`, SaaS reconciliation service.
- Notes: MRR/ARR must come from approved subscription metric sources, not legacy payment/invoice formulas.

## Automation and scheduler

- Production scheduler: run `php artisan schedule:run` every minute from cron.
- Timezone: `Asia/Amman`.
- Authoritative notification service: `NotificationService`.
- Scheduled operational reminders and finance alerts are idempotent projections. They must not change client lifecycle, create subscriber state, create invoices/payments/accounting entries, or duplicate notifications for the same source occurrence.

## Executive reporting

- Authoritative controllers/services: `ExecutiveDashboardController` and F2 executive services.
- Notes: executive financial values must consume approved F1/F2 services and not duplicate legacy dashboard calculations.

## Legacy read-only domains

- `investments`: historical metadata only; `investments.store` returns 410 after authorization.
- `capital_expenses`: historical metadata only; `capital-expenses.store` returns 410 after authorization.
- Legacy `payment_schedules` rows remain historical contract/reminder compatibility only. Rows marked `schedule_engine_version=v2` are permitted solely as invoice-linked annual collection timing and are excluded from legacy dashboard receivable totals.
- Deprecated settings: `operational_cost_percentage`, `market_valuation_multiplier`, default global annual discount behavior, and global tax auto-application are not V2 authority.
- Partner/referral metadata: partner records, `partner_id`, lead source/referrer fields, attribution snapshots, and read-only commission projections do not drive authorization, visibility, company revenue, recognized revenue, SaaS metrics, or accounting. Legacy partner percentage/earned-share accessors remain non-authoritative compatibility only. No partner payable or accounting workflow exists in V1 Gate 2.
- Retired partner workflows: partner login, partner dashboard, partner password reset, partner credential generation, partner navigation, partner-specific operational notifications, partner-specific CSV ownership, and active partner financial/share dashboard logic.
- Legacy dashboard financial mode: retained only as a deprecation panel with links to `/finance`, `/executive`, `/saas-metrics`, and `/accounting`.
