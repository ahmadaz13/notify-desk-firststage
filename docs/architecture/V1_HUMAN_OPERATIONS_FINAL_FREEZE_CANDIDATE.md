# Notify Desk V1 Human Operations - Final Freeze Candidate

## Status

`CODE_COMPLETE_PENDING_HUMAN_VISUAL_QA`

The repository is prepared as a code-complete freeze candidate for the Notify Desk V1 Human Operations Simplification stream.

This is not a production-ready declaration. Human visual and browser workflow QA remain required.

## Included Phase Commits

- `f478299` - Phase 8: unify today and work
- `07daf79` - Phase 9: separate daily and management surfaces
- Phase 10 final QA changes are expected to be committed separately as `Phase 10: finalize automated QA candidate`.

## Preserved Architecture

The frozen operational and financial chain remains unchanged:

Prospect -> Calls -> Appointment -> Installation -> Follow-up -> Subscription -> Invoice -> Contract -> Receivable -> Payment -> Allocation -> Cash Movement -> Financial Account -> Accounting

Phases 08-10 remain limited to:

- read projections
- navigation
- permission-aware visibility
- UX consolidation
- regression and QA coverage

The write/domain engine was not redesigned.

## Final Daily Surface

Daily work is now concentrated into:

- Today
- Clients
- Work

Today contains:

- Overdue
- Next
- Later Today

Work contains:

- All
- Calls
- Appointments
- Installations
- Follow-ups
- Collections, only for authorized financial users

Work groups are:

- Overdue
- Today
- Upcoming

## Final Management Surface

Founder/Admin management is grouped into:

- Commercial
- Money
- Reports
- Operations Admin
- System

Advanced engine tools are subordinate to management:

- Financial Accounts
- Accounting

Staff primarily sees Daily surfaces and remains blocked from unauthorized finance, accounting, subscription-management, and advanced tools by direct route authorization.

## Freeze-Candidate Notes

- Unified operational work projection is read-only.
- Collections are sourced from `ReceivableService`.
- Completed follow-ups are excluded from open work by `completed_at IS NULL`.
- Cancelled and completed appointments are excluded from open work.
- Review-required items are projected into Work.
- Today and Work rendering does not mutate domain state.
- Multi-Product subscription behavior remains intact.
- Direct authorization remains authoritative over UI visibility.

## Manual QA Remaining

Before any production release, a human must still verify:

- Full browser workflow
- 390px mobile
- 768px tablet
- 1440px desktop
- Arabic RTL
- English LTR
- Arabic PDF rendering
- Real end-to-end business workflow

## Stop Point

Do not deploy.
Do not merge.
Do not tag.
