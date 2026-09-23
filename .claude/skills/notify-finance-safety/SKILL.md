---
name: notify-finance-safety
description: Mandatory for Notify Desk payments, collections, subscriptions, invoices, expenses, company accounts, accounting, recognition, journals, or financial reports.
---

# Financial invariants

- Use integer minor units for authoritative money: `1 JOD = 1000 fils`. Convert display/input strings only at boundaries.
- Payment is not Revenue. Invoice is not Cash. Cash is not Profit. Accounting Revenue is not MRR or ARR.
- Posted journals are immutable, and balances are derived.
- One real-world money event must never be double-posted.
- Pending, rejected, or cancelled receipt confirmations have zero financial effect.
- Reuse authoritative domain and accounting services; do not reproduce posting logic in controllers.
- Preserve database transactions, authorization, row locking, unique guards, and financial idempotency.
- Never weaken an accounting invariant to satisfy an obsolete test. Follow the active phase and the finance sections of `NOTIFY_DESK_V1_FINAL_ARCHITECTURE.md`.
