# Notify Desk V1 Legacy Compatibility Matrix

Classification values are `REMOVE_NOW`, `KEEP_FOR_HISTORICAL_COMPATIBILITY`, and `DEFER_TO_V2_SCHEMA_CLEANUP`.

| Legacy item | File / symbol | Production callers | Historical read dependency | New V1 write dependency | Classification | Reason / future removal condition |
| --- | --- | --- | --- | --- | --- | --- |
| Native PHP year rendering | `resources/views/contracts/template.blade.php` `date('Y')` | Contract print/preview | None | None | REMOVE_NOW | Replaced with `now()->year` so the configured business clock is used. |
| Repeated contact-phone resolution | `ClientListViewModel`, `ClientWorkspaceViewModel` | Client list/workspace call and WhatsApp actions | Legacy Client fallback required | Yes | REMOVE_NOW | Replaced by `Client::preferredOperationalContact()` while preserving all old fields. |
| `isAdmin()` name includes Founder | `User::isAdmin()` | Policies, gates, controllers, app shell | Existing admin/null-role users | Yes, as compatibility authorization alias | KEEP_FOR_HISTORICAL_COMPATIBILITY | Deeply used and security-sensitive. Remove only with a separately approved authorization migration. |
| Old admin/staff role wording | `User` role constants and existing tests/data | Internal middleware and operational permissions | Existing users | Founder is canonical; compatibility values remain accepted | KEEP_FOR_HISTORICAL_COMPATIBILITY | Live V1 provisions founders only; deleting accepted values would strand historical users. |
| Partner role user rows | `User::ROLE_PARTNER`, `hasLegacyPartnerRole()` | Blocking tests/middleware | Yes | No | KEEP_FOR_HISTORICAL_COMPATIBILITY | Required to deny historical partner users safely. Remove after rows are archived outside `users`. |
| Partner dashboard/reset routes | `partner.dashboard`, `partners.reset-password` | Explicit 410 compatibility responses | Route/bookmark compatibility | No | KEEP_FOR_HISTORICAL_COMPATIBILITY | Keeping a protected 410 is safer than reviving or silently dropping the workflow. |
| Partner legacy fields | `partners` historical percentages; `clients.partner_id` | Historical UI and attribution fallback | Yes | `clients.partner_id` synchronized only | DEFER_TO_V2_SCHEMA_CLEANUP | Canonical new attribution is `client_partner_attributions`; remove after approved data migration. |
| `billing_type` duplication | `subscriptions.billing_type` | Legacy display/readers | Yes | V2 writes mirror `billing_interval_v2` for compatibility | DEFER_TO_V2_SCHEMA_CLEANUP | `billing_interval_v2` is authoritative. Column removal needs historical migration. |
| Float subscription pricing | `SubscriptionPricingService` | No production callers; legacy tests only | Legacy behavior verification | No | KEEP_FOR_HISTORICAL_COMPATIBILITY | Quarantined from routes. Remove with legacy subscription test/archive package. |
| Float schedule generator/lifecycle | `PaymentScheduleService::generateSchedules()`, `cancelSubscription()`, `renewSubscription()` | No production callers; legacy tests only | Legacy test/history behavior | No | KEEP_FOR_HISTORICAL_COMPATIBILITY | V2 writes use integer invoice-linked methods only. Remove together with legacy subscription writer archive. |
| `annual_discount_percentage` | setting and subscription legacy columns | Settings compatibility and historical display | Yes | No effect on V2 PlanPrice calculations | DEFER_TO_V2_SCHEMA_CLEANUP | Keep readable until legacy subscriptions/settings are archived. |
| `payments.payment_schedule_id` | `Payment`, `PaymentSchedule::payments()` | Historical payment readers | Yes | V2 explicitly writes `null` | DEFER_TO_V2_SCHEMA_CLEANUP | Remove only after legacy payment migration and retention approval. |
| Legacy schedule rows | `payment_schedules.schedule_engine_version IS NULL` | Historical reminders/contracts/client display | Yes | No | KEEP_FOR_HISTORICAL_COMPATIBILITY | V2 rows are invoice-linked timing projections; legacy rows remain readable. |
| Legacy dashboard receivable calculation | `DashboardController` deprecated financial panel | Deprecated display only | Compatibility panel | No; V2 rows excluded | KEEP_FOR_HISTORICAL_COMPATIBILITY | Authoritative AR is `ReceivableService`; remove panel after UI/bookmark retirement. |
| `subscription_service` pivot | `Subscription::services()`, legacy branch of `ContractService` | Historical contract snapshots | Yes | New V2 contracts use Plan services | DEFER_TO_V2_SCHEMA_CLEANUP | Remove after all legacy contracts/subscriptions are permanently archived. |
| Pre-Product plans | nullable `plans.product_id` and catalog compatibility queries | Catalog and historical subscriptions | Yes | New catalog supports Product hierarchy | KEEP_FOR_HISTORICAL_COMPATIBILITY | Needed for old standalone plans. Remove only after approved catalog migration. |
| Manual contract creation | `contracts.store`, `ContractService::ensureDraftContract()` | Recovery/admin UI | Existing missing artifacts | Idempotent recovery only | KEEP_FOR_HISTORICAL_COMPATIBILITY | Auto Draft is canonical; manual action safely repairs without duplicate contracts. |
| Deprecated conversion/payment/expense/capital writes | `clients.convert`, `payments.store`, `expenses.store`, `investments.store`, `capital-expenses.store` | Protected 410 stubs | Route compatibility and explicit deprecation tests | No | KEEP_FOR_HISTORICAL_COMPATIBILITY | Remove routes only after consumers/bookmarks are formally retired. |
| Legacy investment/capital tables | `investments`, `capital_expenses` | Historical capital display | Yes | No | DEFER_TO_V2_SCHEMA_CLEANUP | Preserve financial history; never reinterpret automatically. |
| Global tax/default due-day settings | `SettingsController`, settings UI | Review/config compatibility | Yes | Do not override issued V2 snapshots | KEEP_FOR_HISTORICAL_COMPATIBILITY | Retained as non-authoritative settings until a later schema cleanup. |
| Historical client fields | `clients.phone`, `contact_person`, `business_phone` | Imports, contracts, lists, workspace | Yes | New Client writes synchronize a primary `client_contacts` row | KEEP_FOR_HISTORICAL_COMPATIBILITY | Existing schema now resolves them consistently; no mass migration in Gate 6. |

## Dead-code audit conclusion

No controller, route, view, command, or service was deleted in Gate 6. Every apparent orphan was either registered, used by the UI/tests, retained as a protected deprecation response, or required to read historical data. The `phase3-source` directory is reference material rather than runtime code and is not modified by the freeze.

## Active legacy write paths

No known route writes legacy subscriptions, float schedules, schedule-linked V2 payments, partner users, legacy investments/capital expenses, or legacy subscriber imports. Compatibility settings may still be stored, but they do not override PlanPrice, issued invoices, accounting, revenue recognition, or SaaS metrics.
