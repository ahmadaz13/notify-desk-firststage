# Notify Desk V1 Human Operations - Phase 10 Final Code QA

## Status

`CODE_COMPLETE_PENDING_HUMAN_VISUAL_QA`

Phase 10 is a code-focused audit and freeze-candidate pass for the Human Operations Simplification work completed after the approved Phase 07 baseline (`7deae88`).

No deployment, merge, tag, browser visual QA, screenshots, responsive visual inspection, RTL visual inspection, or PDF visual inspection was performed in this phase.

## Recovery Summary

- Phase 08 was already committed as `f478299` (`Phase 8: unify today and work`).
- Phase 09 was already committed as `07daf79` (`Phase 9: separate daily and management surfaces`).
- Interrupted uncommitted Phase 10 work was found in `tests/Feature/Phase10FreezeCandidateTest.php`.
- Untracked `docs/audits/` content was present and intentionally left untouched.

## Route Audit

The active route table was reviewed for the daily and management surfaces:

- Daily: `dashboard`, `work`, `clients.index`, `clients.show`.
- Guided subscription: `clients.guided-subscription.catalog`, `clients.guided-subscription.preview`, `clients.guided-subscription.store`.
- Payments: `clients.payments.normal.store`, `clients.collections.payments.store`, manual allocation/reversal/refund routes.
- Contracts: `contracts.preview`, `contracts.print`, `contracts.download`, `contracts.download-pdf`, issue/void/supersede routes.
- Management and advanced surfaces remain behind the existing authenticated active-internal group and controller/gate checks.

The interrupted Phase 10 test referenced a non-existent `clients.payments.normal.create` route. The test was corrected to assert the actual V2 normal payment store route remains forbidden for Staff.

## Permission Audit

Representative access was verified for:

- Guest users: redirected to login for daily, clients, finance, and accounting routes.
- Inactive internal users: forbidden from daily operational surfaces.
- Staff users: allowed to access Today, Work, Clients, and Client Workspace.
- Staff users: forbidden from guided subscription, payment recording, finance, accounting, and financial accounts.
- Founder/Admin users: retain access to grouped management surfaces according to existing gates.

UI visibility remains secondary to route/controller authorization.

## Internal Field Audit

Daily Today and Work surfaces were reviewed to ensure they do not introduce normal operational controls for internal engine fields such as:

- raw lifecycle `stage` / `status` mutation
- `plan_price_id`
- `financial_account_id`
- `tax_rate_bps`
- journal identifiers
- allocation internals
- MRR / ARR controls

These fields remain limited to approved management, advanced, legacy, or backend-authoritative paths where they already existed before Phases 08-10.

## Financial Regression Audit

Phases 08-10 did not modify the frozen financial authorities:

- `PlanPrice`
- `CommercialPricingService`
- `SubscriptionBillingService`
- invoice arithmetic
- contract snapshot generation
- `ReceivableService`
- `PaymentAllocationService`
- `CashMovementService`
- `FinancialAccountBalanceService`
- accounting posting
- revenue recognition
- MRR / ARR services

Collections in Today/Work are read from `ReceivableService::outstandingInvoices()` and remain hidden from Staff.

## Follow-up Work Regression

Open operational work uses `follow_ups.completed_at IS NULL`.

Completed follow-ups:

- remain in history
- do not appear in Today
- do not appear in Work
- are not resurrected by `NotificationService`

## Multi-Product Regression

The freeze-candidate regression verifies that a client can hold multiple active subscriptions for different Products, while duplicate active base subscriptions for the same Product remain blocked by the guided subscription authority.

The test was corrected to assert English product labels under English locale, rather than accidentally checking English labels while the response was rendered in Arabic.

## Localization Audit

Phase 08/09 labels exist in both:

- `lang/ar/notify.php`
- `lang/en/notify.php`

One localization defect was corrected: collection card invoice context now uses `notify.work.collection_context` instead of hardcoded Arabic text.

## Performance Audit

Today and Work use a unified read projection with batched queries:

- Appointments eager-load clients.
- Review items eager-load clients.
- Collections use `ReceivableService` batch invoice projections.
- Work rendering does not perform lifecycle, billing, payment, accounting, or notification writes.

No broad optimization project was performed.

## Verification Scope

Automated verification for this phase includes:

- Focused Phase 08 tests
- Focused Phase 09 tests
- Phase 10 freeze-candidate tests
- Full Laravel test suite
- Blade compile
- Vite build
- Route list generation
- Migration status
- Git diff whitespace check
- PHP lint for changed PHP files

Manual browser and visual QA remain required before production release.
