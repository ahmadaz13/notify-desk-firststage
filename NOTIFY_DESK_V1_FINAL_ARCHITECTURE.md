# Notify Desk V1 — Final Architecture

## Product boundary

Notify Desk V1 is an internal, human-operated CRM and finance system. It manages clients, system access, agreed-value subscriptions, invoices, collections, contracts, custom projects, operating expenses, capital, accounting, and SaaS metrics. Partner portals, referral links, conflict resolution, partner commissions, packages, and automated pricing are outside the V1 boundary.

## Roles and authorization

- Founder and administrator accounts perform management and financial work through the existing permission gates.
- Staff accounts perform ordinary CRM and operational work but cannot start paid subscriptions, record payments, manage systems, or use restricted financial administration.
- Partner is not an application role. Referrers do not have users, credentials, dashboards, routes, or ownership rights.

## Client and referral model

The `clients` table owns optional referral metadata:

- `referred_by_name`: free-text person or organization name.
- `referral_commission_bps`: optional basis-point percentage, editable only by founders and administrators.
- `referral_note`: optional operational context.

Referral metadata creates no accounting entry, payable, payment, expense, ownership, or automatic commission. Any future referral payment must be recorded explicitly through the ordinary finance workflow.

## Systems catalog

`products` is retained as the historical physical table and Eloquent model, but its V1 business meaning is **System**. The catalog exposes only active systems with a server-generated unique code and optional monthly/annual price suggestions. Suggestions are informational and never determine invoice amounts.

The older `plans`, `plan_prices`, and service composition tables remain as a hidden compatibility layer for historical subscriptions, renewals, invoices, and contracts. V1 screens and write routes do not create or select packages or plan prices.

## Client-system access

`client_system` is the authoritative access grant table. Each grant identifies a client, a system, whether access is `free` or `paid`, grant/revoke dates, and the granting user.

Free access is independent of subscription billing. Granting free access does not create a subscription, invoice, contract, payment schedule, accounting entry, or SaaS metric event. A paid subscription grants paid access for its selected systems.

## Agreed-value subscriptions

The V1 conversion flow accepts:

- one or more selected systems;
- a monthly or annual billing cycle;
- one human-entered agreed value in JOD;
- annual full-payment or installment terms;
- a start date and optional notes.

Money is converted at the boundary to integer fils. `subscriptions.agreed_value_minor` is authoritative. `subscription_system` stores immutable system identity/name snapshots for the agreement. No setup fee, tax, package formula, branch multiplier, discount rule, or price suggestion silently changes the agreed value.

The existing invoice, accounting, collection, payment-schedule, revenue-recognition, and SaaS-metric engines remain authoritative downstream. Monthly and annual renewals copy the immutable agreed value and selected-system snapshots into each next billing period. Renewal remains idempotent through the existing billing-period boundary.

## Contracts

Every successful paid conversion creates an immutable draft contract snapshot after the subscription and first invoice commit. The snapshot includes the client, selected systems, billing cycle, agreed value, payment terms, services where historically applicable, and current-period dates. Contracts retain preview, print, HTML download, PDF download, lifecycle, numbering, and integrity behavior. Legacy contract snapshots remain readable.

## Custom projects

Custom projects remain separate from recurring subscriptions. Their agreed value is stored in integer fils and may produce invoices through the existing invoice engine. They do not change recurring SaaS metrics unless an explicit recurring subscription is created.

## Financial invariants

- Integer minor units are authoritative for new money writes.
- Issued invoices, allocations, journal entries, contract snapshots, and historical billing periods remain immutable under their existing rules.
- Idempotency claims continue to guard financial write routes.
- Free system access and referral metadata are non-financial operations.
- Revenue schedules, collection allocation, accounting postings, expenses, capital, dashboards, and metrics reuse the established services rather than parallel calculations.

## Database transition

Migration `2026_09_22_120000_simplify_v1_commercial_domain.php` adds the referral metadata, system price suggestions, access pivot, agreed-value fields, and subscription-system snapshots, then removes Partner/Conflict tables and foreign keys. It includes SQLite-safe foreign-key/index handling and guards for partially migrated environments. Existing legacy plan and price tables are intentionally preserved for historical financial readability.

## Removed V1 surface

The application no longer exposes Partner models, services, controllers, notifications, seeders, dashboards, authentication, public referral URLs, commission automation, transfer settings, conflict queues, or Package/Plan catalog write routes. Navigation, administration counts, settings, imports, client forms, and active tests now reflect the simplified domain.

## Validation strategy

The V1 verification suite covers server-generated system identifiers, optional referral metadata and permission boundaries, free access with zero financial side effects, monthly and annual agreed-value conversion, annual installments with identical economics, renewal, invoice and metric creation, contract creation and endpoints, client workspace behavior, and custom-project/financial regression paths.
