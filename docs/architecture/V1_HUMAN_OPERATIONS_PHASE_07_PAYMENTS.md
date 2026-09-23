# Human Operations Simplification - Phase 07: Payments

## Existing Payment Authority Map

Normal payment recording remains on the existing V2 financial chain:

ReceivableService -> PaymentAllocationService -> CashMovementService -> FinancialAccountBalanceService -> AccountingEventPostingService / BillingAccountingService -> JournalPostingService.

`PaymentAllocationService::recordV2Payment()` remains the write authority for V2 payments. It persists the `Payment`, records the cash receipt through `CashMovementService`, and auto-allocates through the existing oldest-invoice allocator when requested.

## Normal Payment UX

Founder/Admin daily payment recording now asks for only:

- Amount Received
- Payment Method
- Received At under More Options

Amount Due is read-only and comes from `ReceivableService::clientSummary()`. The workspace normal form defaults Amount Received to the current outstanding amount when there is a positive balance. Financial account selection, invoice selection, allocation rows, cash movement IDs, journal IDs, and receivable internals remain out of the normal form.

Manual allocations remain available in the existing Management & Finance collection workflow.

## Account Resolver Design

`PaymentFinancialAccountResolver` maps canonical `PaymentMethods` values to existing `FinancialAccount::type` values:

- `cash` -> `cash`
- `bank_transfer`, `cliq` -> `bank`
- `e_wallet`, `zain_cash`, `orange_money` -> `wallet`
- `other` -> `other`

Resolution requires exactly one active, unarchived, JOD account of the mapped type. It fails safely when mapping has no account, no active eligible account, or multiple eligible accounts. The normal browser flow does not submit or control `financial_account_id`; if one is posted anyway, the normal route ignores it and uses the resolver result.

## Allocation Behavior

The normal route calls `PaymentAllocationService::recordV2Payment()` with `autoAllocateOldest = true` and no manual allocation rows. The existing allocator orders issued invoices by `due_date`, then `id`, and allocates until the payment is exhausted.

## Overpayment Behavior

The current domain supports unallocated customer credit through `ReceivableService::paymentUnallocatedMinor()`. Phase 07 preserves that behavior. Overpayment records one V2 payment, allocates outstanding invoices oldest-first, and leaves the remainder as unallocated payment credit.

## Cash Movement And Balance

Payment receipts still go through `CashMovementService::recordPaymentReceipt()`. Financial account balances remain derived from posted cash movements by `FinancialAccountBalanceService`; no controller or Blade balance math was added.

## Accounting Posting

Cash receipts post the cash-side journal through `AccountingEventPostingService::postCashMovement()`. Payment allocations post the receivable-clearing journal through `BillingAccountingService::postPaymentAllocation()`. Journal creation remains idempotent in `JournalPostingService`.

## Transaction Integrity

The normal path relies on the existing `DB::transaction()` in `PaymentAllocationService::recordV2Payment()`. A downstream allocation accounting failure rolls back the payment, allocation, cash movement, and receipt journal.

## Permissions

The normal route is inside the authenticated active-internal route group and enforces:

- `FinancialPermissions::RECORD_PAYMENT`
- `ClientPolicy::view`

Staff users remain forbidden from direct route execution. Presentation gating remains secondary to server authorization.

## Tests

Focused coverage was added in `tests/Feature/Phase07PaymentsTest.php` for:

- Amount Due from `ReceivableService`
- Staff denial and Founder/Admin success
- Canonical method-to-account resolution
- Browser `financial_account_id` override resistance
- Missing, ambiguous, and inactive mapping failures
- Zero and negative amount rejection
- Partial, exact, overpayment, and oldest-first allocation behavior
- Payment, allocation, cash movement, balance, and accounting posting persistence
- Transaction rollback on downstream allocation accounting failure
- No subscription, PlanPrice, or contract side effects
- Workspace amount refresh after payment
- Existing manual allocation path unchanged

## Unresolved Issues

No schema migration was added. If Notify later needs different accounts for the same account type, an explicit payment-method account configuration table should be approved before hiding account selection in those cases.
