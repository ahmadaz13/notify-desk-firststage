# Notify V1 Operational Workflow

Backend Closure G2 makes the operational client workflow the single active path from prospect intake to subscriber eligibility or closure.

## Canonical lifecycle

```text
Prospect
  -> Contact
      -> Later contact
      -> Callback
      -> Review
      -> Appointment
           -> Installation
           -> Free 3 days
           -> Follow-up
                -> Subscribe
                -> Wait
                -> Decline
```

The implemented client stages are:

- `prospect`: newly created or imported operational lead.
- `contacting`: the team is trying to reach the client or has scheduled a callback/follow-up.
- `appointment`: a sales/review appointment exists.
- `installation_scheduled`: a free installation appointment exists.
- `installed_free`: the free installation is complete and trial follow-up is scheduled.
- `decision_pending`: the client needs more time after the trial or meeting.
- `subscriber`: reached only through the explicit V2 paid subscription workflow.
- `closed`: intentionally closed after an explicit reason is recorded.

## Intake rules

- Manual client creation starts as `prospect` and does not create subscriptions, invoices, payments, or payment schedules.
- CSV import always creates operational prospects, even when the uploaded row says subscriber or contains billing-oriented fields.
- Imported CSV rows do not create subscriptions, invoices, payments, or payment schedules.
- CSV import is an internal-user workflow. Imported rows are owned by the importing internal user and do not create partner ownership or partner user accounts.
- Public delegate/referral intake can preserve `partner_id` and lead-source attribution, but it cannot create users, grant privileges, or bypass review/conflict handling.
- Imported or manually created clients become subscribers only through `clients.paid-subscriptions.store`.

## Identity and referral boundary

Operational workflow is performed by internal application users only: `admin` and `staff`. Partner records are referral/history metadata and are not login identities, owners, queue scopes, or permission scopes.

`partner_id` on a client means referral attribution, not operational assignment. Authorized internal users can see and work historical partner-attributed clients. Attendees, installers, follow-up owners, CSV import actors, and operational queue users must be internal users.

## Contact outcomes

Contact attempts are recorded through `ClientOperationalWorkflowService::recordContactOutcome`.

- `appointment`: requires appointment date/time, creates an appointment, and moves the client to `appointment`.
- `callback_later`: requires a callback/follow-up time, creates a follow-up, and keeps the client in `contacting`.
- `no_contact` and `no_answer_busy`: record the attempt and keep the client in `contacting`.
- `wrong_invalid`: creates a pending `client_review_items` row for review and keeps history active.
- `not_interested`: creates a pending `client_review_items` row for review and does not close automatically.

Legacy aliases such as `call_later`, `wrong_number`, `no_answer`, and `busy` are normalized into the canonical outcomes.

## Review items

`client_review_items` is the operational review queue for ambiguous or human-decision cases. G2 currently creates review items for invalid contact data and not-interested outcomes. Review items can be resolved or dismissed only by users authorized to update the related client.

## Appointment and installation path

- Meeting outcomes can schedule a free installation appointment without creating subscription or financial records.
- Installation scheduling moves the client to `installation_scheduled`.
- Completing a free installation moves the client to `installed_free`.
- Completion always schedules a follow-up. If no follow-up date is supplied, the default follow-up is three days after installation at 10:00.
- Free installation continues to create no subscription, invoice, payment, or payment schedule.

## Trial decision path

After the free-trial follow-up:

- Subscribe: use the explicit V2 paid subscription workflow. That workflow is the only allowed subscriber gate.
- Wait: move or keep the client in `decision_pending` with a dated follow-up or recorded decision note.
- Decline: close the client with an explicit reason code and optional note.

## Close and reopen

Closing uses `ClientOperationalWorkflowService::closeClient`. It preserves history, sets `stage=closed`, maps the legacy status to `archived` for compatibility, records `closed_at`, records `closed_reason`, and writes a `client_closed` activity log.

Reopening is explicit. Closed clients cannot be moved back into active stages by an ordinary stage update. `clients.reopen` requires a reason and can reopen into `prospect`, `contacting`, `appointment`, or `decision_pending`; it writes `client_reopened` and stage-change activity logs.

## Operational queues

`OperationalQueueService` projects the daily work queues:

- `new_prospects`: prospects with no contact attempts.
- `active_contact_queue`: prospects/contacting clients without pending reviews or future scheduled callbacks.
- `callbacks_due`: due callback/follow-up rows.
- `appointments_today`: non-installation appointments for the selected date.
- `installations_today`: installation appointments for the selected date.
- `trial_followups_due`: due follow-ups for installed-free clients.
- `decision_pending`: clients waiting for a decision.
- `client_reviews_pending`: pending operational review items.

The same service provides next-action projection for client surfaces and daily operations summaries.

Operational queues are internal-user only. They do not filter work by partner ownership and they reject legacy partner-role users.

## Scheduled reminders

Backend automation projects due operational work through `NotificationService` and `reminders:send`. Reminder generation is idempotent by source occurrence and creates only notifications for active internal users.

The active reminder types are `callback_due`, `appointment_reminder`, `installation_reminder`, `trial_followup_due`, and `decision_followup_due`. Closed clients are excluded, and subscriber clients are not returned to prospect/contact queues by reminder generation.

## Financial boundary

Operational workflow is non-financial until a paid subscription is explicitly started. Prospect creation, CSV import, contact attempts, appointment scheduling, free installation, trial follow-up, review items, close, and reopen must not create subscriber state, invoices, payments, payment schedules, cash movements, revenue recognition, SaaS metrics, or accounting entries.
