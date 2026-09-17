# Notify Desk V1 Final Architecture

## Actors and access

- Founder is the canonical live V1 internal account and has owner-level access.
- `admin` remains a compatibility/future owner-level value. `staff` remains compatibility for existing operational tests/data. No new Admin, Employee, or staff-management workflow is part of V1.
- `employee` is reserved for a future workflow and is not an active V1 identity.
- Partner is an external referral entity only and cannot authenticate.
- `EnsureActiveInternalUser`, policies, gates, and `FinancialPermissions` enforce access; Blade visibility is not authorization.

## Client operations

`ClientOperationalWorkflowService`, `OperationalQueueService`, `DailyOperationalService`, and `ClientLifecycle` own the prospect-to-subscriber operating lifecycle. Subscriber state is entered only by a successful V2 paid subscription.

The preferred operational contact is resolved from existing `client_contacts` and legacy Client fields in this order: owner/decision-maker mobile, manager mobile, another explicitly primary contact, legacy named-contact mobile, and business phone fallback. No historical contact is fabricated or migrated by Gate 6.

## Commercial model

The commercial hierarchy is `Product -> Plan -> PlanPrice -> Subscription`. An effective, versioned `PlanPrice` is the only recurring price authority. Product and Plan archival preserve historical snapshots.

## Subscription lifecycle

`SubscriptionBillingService` owns start, periods, renewal, scheduled plan change, cancellation, and reactivation.

- Monthly: one monthly period and monthly renewal.
- Annual paid in full: one annual period, one annual invoice, annual renewal.
- Annual by installments: the same annual subscription and invoice, with an invoice-linked V2 collection schedule.

Installment frequency never becomes subscription frequency and never changes ARR/MRR.

## Partners

`client_partner_attributions` is canonical for new referral attribution and commission basis-point snapshots. `clients.partner_id` remains synchronized compatibility data. `PartnerCommissionService` projects commission only from eligible net collected cash; it creates no payable or journal.

## Contracts

`ContractService` automatically creates an immutable Draft snapshot after a paid subscription commits. Manual creation is idempotent recovery/admin capability. V2 snapshots contain Product, Plan, PlanPrice, Plan services, invoice details, and installment schedules when applicable. Contract numbering and A4 layout remain unchanged.

## Billing and collections

The financial chain is:

`Invoice/InvoiceLine -> ReceivableService -> Payment -> PaymentAllocation -> CashMovement -> Accounting -> RevenueRecognition -> Reports`

Issued snapshots are immutable except through approved void, reversal, credit, and refund workflows. A payment schedule is collection timing, not a receivable or cash event.

## Accounting and revenue

Journal and accounting services are the ledger authority. `RevenueRecognitionService` owns recognition schedules and periods independently of payment timing. `FinancialStatementService` consumes accounting, receivable, cash, and recognition authorities without reproducing formulas in views.

## SaaS metrics

`SaasMetricEventService` and `SaasMetricsService` own MRR, ARR, movements, churn, and retention. Metrics derive from V2 subscription periods and events, not invoice payment timing, setup fees, tax, or one-time work.

## Time and system boundaries

`Asia/Amman` is the single V1 business timezone. Date-only commercial values remain calendar dates. See `V1_TIME_POLICY.md`.

V1 does not include deployment, real-client import, multi-timezone support, partner authentication, custom quotation, new account-management workflows, installment interest/penalties, or automated annual-contract early termination policy.
