# Notify V1 Legacy Route Inventory

Generated during Backend Closure G3 from `php artisan route:list --no-ansi`, which reported 142 routes.

## Authoritative V1

- Client CRM and lifecycle: `clients.index`, `clients.store`, `clients.show`, `clients.update`, `clients.destroy`, `clients.stage.update`, `clients.reopen`, client contacts, contact attempts, follow-ups, offers, appointment and meeting outcome routes, free-installation schedule/complete routes, and client review item resolve/dismiss routes.
- Commercial catalog: `commercial-catalog.index`, plan create/update/archive routes, and `commercial-catalog.prices.store`.
- Paid subscription start and lifecycle: `clients.paid-subscriptions.store`, `subscriptions.plan-change.schedule`, `subscriptions.cancel`, `subscriptions.cancel.undo`, `subscriptions.reactivate`, `subscription-billing.index`, `subscription-billing.generate-renewals`, `subscription-billing.backfill-periods`.
- Billing and invoices: `clients.one-time-invoices.store`, `invoices.void`.
- Collections, payments, credits, refunds: `collections.index`, `clients.collections.payments.store`, payment allocation/auto-allocation/reversal/refund routes, credit-note create/apply/reverse/void/refund routes.
- Cash accounts: `financial-accounts.index`, `financial-accounts.store`, account archive, financial transfer, transfer reversal, and `cash-events.assign-account`.
- Operating expenses: `operating-expenses.index`, `operating-expenses.store`, `operating-expenses.reverse`, expense category routes, vendor routes, recurring expense template routes, and recurring expense obligation generate/pay/skip/cancel routes.
- Capital management: `capital-management.index`, funding source routes, `capital-funding-transactions.store`, funding reversal, asset category routes, fixed asset create/reverse/status routes.
- Accounting and revenue recognition: `accounting.index`, chart-account archive, period close/reopen, accounting backfill, revenue schedule backfill, revenue recognition run/confirm.
- Reporting: `finance.index`, `finance.export`, `executive.index`, `saas-metrics.index`, `saas-metrics.export`.
- Settings/admin utilities: `settings.index`, `settings.export`, and the non-deprecated portions of `settings.update`.
- Public/auth/ops: login/logout, health/up, public referral client capture, notifications, conflicts, CSV import, contracts, partner referrer directory management, and daily notes.

## Legacy Read Only

- Dashboard financial mode (`dashboard` with `mode=financial`) now displays a deprecation panel and links to authoritative reporting routes.
- Client show may still display imported/historical payment schedule information, but V2 receivables are invoice/allocation based.
- CSV import preserves uploaded customer/business data but now imports every row as an operational prospect. Legacy subscriber import semantics are quarantined and do not create subscriptions, invoices, payments, or payment schedules.
- Contact outcomes that previously implied a terminal decision now create review items or explicit follow-up work. `not_interested` and invalid/wrong contact outcomes no longer auto-close the client.
- Capital management may display legacy investment and capital-expense rows for historical context, but those rows are not funding transactions or fixed assets.
- Settings page displays deprecated operational-cost and market-valuation settings as historical disabled values.
- Partner records, `partner_id`, lead source/referrer fields, and legacy partner percentage/share metadata are preserved as historical/referral metadata only.
- Public partner/delegate links can still create referral-attributed prospects or conflict-review requests, but they do not create users or grant privileges.

## Deprecated Write

- `clients.convert` (`POST /clients/{client}/convert`): remains registered for compatibility but returns 410 after authorization. Replacement: `clients.paid-subscriptions.store`.
- `payments.store` (`POST /payments`): remains registered for compatibility but returns 410 after `RECORD_PAYMENT` authorization. Replacement: `clients.collections.payments.store`.
- `expenses.store` (`POST /expenses`): remains registered for compatibility but returns 410 after `MANAGE_EXPENSES` authorization. Replacement: `operating-expenses.store`.
- `investments.store` (`POST /investments`): remains registered for compatibility but returns 410 after `MANAGE_INVESTMENTS` authorization. Replacement: `capital-funding-transactions.store`.
- `capital-expenses.store` (`POST /capital-expenses`): remains registered for compatibility but returns 410 after `MANAGE_CAPITAL_EXPENSES` authorization. Replacement: `fixed-assets.store`.
- `partner.dashboard` (`GET /partner/dashboard`): remains registered for compatibility but returns 410 for internal users and is blocked for legacy partner-role users by internal-user middleware.
- `partners.reset-password` (`POST /partners/{partner}/reset-password`): remains registered for compatibility but returns 410 after admin authorization.
- `settings.update`: ignores deprecated `operational_cost_percentage` and `market_valuation_multiplier` inputs. Review-only settings such as global tax and default due day remain stored but do not override explicit V2 snapshots.

## Candidate For Future Removal Or Archival

- Deprecated route registrations: `clients.convert`, `payments.store`, `expenses.store`, `investments.store`, and `capital-expenses.store`.
- Legacy controller methods kept as compatibility stubs: `DashboardController@storePayment`, `DashboardController@storeExpense`, `DashboardController@convert`, `ExpenseController@store`, `InvestmentController@store`, `CapitalExpenseController@store`.
- Historical financial tables/models that should remain data-preserved until an approved archival/removal plan exists: `payment_schedules`, `investments`, `capital_expenses`, and pre-V2 payment/expense rows.
- Remaining operational cleanup candidates: migrate frontend surfaces to consume G2 queue/review projections directly.
- Legacy dashboard financial copy and compatibility variables that can be removed after all frontend references move fully to Finance, Executive, SaaS Metrics, Accounting, Collections, Operating Expenses, and Capital Management.
- Retired partner compatibility artifacts that should remain only until an approved archival/removal plan exists: `partner.dashboard`, `partners.reset-password`, `PartnerDashboardController`, partner dashboard/layout Blade files, and legacy `role=partner` user rows.
