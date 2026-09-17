# Human Operations Simplification - Phase 05: Daily Operations

## Overview
Phase 05 simplifies recurring human operational workflows inside the Client Workspace (`clients.show`) according to the principles established in `V1_HUMAN_OPERATIONS_UX_ARCHITECTURE.md`:
- **Outcome-First**: Operational users select the business result first, revealing only the specific required inputs for that outcome.
- **Strict Orchestration Boundaries**: `FollowUpService` coordinates completion and delegates lifecycle stage transitions to `ClientOperationalWorkflowService`, appointment scheduling to appointment services, review creation to `ClientReviewItem`, and installations to `FreeInstallationService`. Domain transition logic is never duplicated.
- **Zero Auto-Subscription**: No operational outcome (neither follow-up "Subscribe" nor appointment "Start Subscription") creates a subscription automatically. Subscriptions remain behind `MANAGE_SUBSCRIPTION_BILLING`.
- **Modular Presentation Architecture**: Action modals are factored into focused reusable partials under `resources/views/clients/workspace/actions/` and composed cleanly into `show.blade.php`.

---

## 1. Database Changes
An additive, non-breaking migration was introduced for authoritative follow-up completion:
- **Migration**: `database/migrations/2026_09_17_030000_add_completion_semantics_to_follow_ups_table.php` (Batch 30)
- **Columns Added**:
  - `completed_at` (nullable timestamp, indexed)
  - `completed_by` (nullable foreign key to `users.id`, `nullOnDelete`)
  - `completion_outcome` (nullable string)
- **Data Integrity**:
  - Historical rows remain `completed_at = NULL` and open until explicitly completed.
  - No SQLite / MySQL table drops, rebuilds, or data alterations occurred.
  - `php artisan migrate:fresh` was NOT run.

---

## 2. Core Operational Workflows

### 2.1 Record Call (`ContactAttemptController`)
- Route: `POST /clients/{client}/contact-attempts`
- Automatically defaults `method` to `'phone'` when omitted.
- Quick logging of call outcomes (`answered`, `no_answer_busy`, etc.).
- When `result === 'no_answer_busy'`, records attempt with zero extra mandatory input.

### 2.2 Schedule Appointment (`AppointmentController`)
- Route: `POST /appointments`
- Validates `client_id`, `appointment_date`, `appointment_time`, `appointment_type`, and notes.
- Supports appointment types: `sales_meeting`, `demo`, `installation`, and `follow_up`.

### 2.3 Compact Appointment Result (`CompactAppointmentOutcomeController`)
- Route: `POST /appointments/{appointment}/compact-outcome` (name: `appointments.compact-outcome.store`)
- Implements `UX-D07` compact modal flow as a dedicated controller adapter, preserving the legacy `MeetingOutcomeController` intact.
- First decision options:
  1. `attended`:
     - `installation`: schedules a free installation appointment without creating subscriptions; updates stage to `installation_scheduled`.
     - `follow_up`: creates a new follow-up and schedules next contact date.
     - `start_subscription`: delegates client stage to `decision_pending` via `ClientOperationalWorkflowService`; never creates subscription.
     - `not_interested`: logs meeting outcome and records activity note.
  2. `no_show`: marks appointment `missed` / `no_show`, schedules next follow-up if requested, logs activity.
  3. `reschedule`: updates date/time, keeps status `scheduled`, logs activity history.
  4. `cancelled`: marks appointment `cancelled` with reason note.

### 2.4 Complete Installation (`FreeInstallationController`)
- Route: `POST /clients/{client}/installations/complete`
- Resolves `appointment_id` deterministically:
  - Uses explicit `appointment_id` from request if provided.
  - If omitted, checks eligible installation appointments for the client:
    - If exactly one active installation appointment exists, resolves it automatically.
    - If multiple active installation appointments exist, throws `ValidationException` requiring explicit appointment selection.
- Defaults `installed_at` to `now()` and `installed_by` to authenticated user if omitted.
- Advances client stage to `installed` and marks appointment completed.

### 2.5 Follow-up Completion & Orchestration (`FollowUpService`)
- Route: `POST /follow-ups/{followUp}/complete`
- Canonical completion outcomes:
  - `subscribe`: marks follow-up completed (`completed_at`, `completed_by`, `completion_outcome`), transitions client stage to `decision_pending` via `ClientOperationalWorkflowService`. Never creates subscription.
  - `callback_later`: completes current follow-up and creates a new follow-up with `next_follow_up_date` / `follow_up_date_time`.
  - `appointment`: completes current follow-up and creates an appointment (`appointment_date`, `appointment_time`, `appointment_type`).
  - `no_answer`: requires `next_follow_up_date` (or `follow_up_date_time`), completes current follow-up, and creates next follow-up so work never disappears.
  - `not_interested`: requires note, creates a `ClientReviewItem` for supervisory audit, transitions stage to `contacting`, never auto-closes client.
- **Transactional Integrity**: All multi-model operations run within `DB::transaction()`. Any failure creating downstream items rolls back the completion.
- **Exclusion from Open Work Queues**:
  - `OperationalQueueService` scopes follow-ups with `whereNull('completed_at')`.
  - `DailyOperationalService::getPendingFollowUps()` scopes with `whereNull('completed_at')`.
  - `NotificationService::dueFollowUpsQuery()` scopes with `whereNull('follow_ups.completed_at')`.
  - Completed follow-ups remain fully visible in historical timelines.

### 2.6 Guided Close & Reopen (`ClientStageController`)
- Route: `POST /clients/{client}/close`
  - Validates structured reason code (`price`, `not_interested`, `competitor`, `unresponsive`, `other`).
  - Requires mandatory note when reason code is `other`.
  - Sets client stage to `closed`, status to `archived`, sets `closed_at` and `closed_reason`.
  - Logs `client_closed` activity.
- Route: `POST /clients/{client}/reopen`
  - Requires mandatory justification note.
  - Resets client stage to `prospect`, status to `prospect`, clears `closed_at`.
  - Logs `client_reopened` activity.

---

## 3. Modular UI Architecture
The action forms are separated into focused, reusable Blade partials:
- `resources/views/clients/workspace/actions/record-call.blade.php`
- `resources/views/clients/workspace/actions/create-appointment.blade.php`
- `resources/views/clients/workspace/actions/appointment-result.blade.php`
- `resources/views/clients/workspace/actions/complete-installation.blade.php`
- `resources/views/clients/workspace/actions/follow-up.blade.php`
- `resources/views/clients/workspace/actions/close-client.blade.php`

These are included into `resources/views/clients/show.blade.php` and managed by lightweight modal lifecycle controllers in `resources/views/clients/workspace/workspace-scripts.blade.php`.

---

## 4. Permission & Security Boundaries
- All write routes are protected by `auth` middleware. Inactive users are rejected with `403 Forbidden`.
- `FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING` gate is preserved. Operational staff without this permission cannot view or trigger subscription creation routes or payment recording.
- Compact appointment outcomes and follow-up completions never bypass permission checks.

---

## 5. Verification & Test Coverage
- **Dedicated Test Suite**: `tests/Feature/DailyOperationsPhase05Test.php` (26 tests, 104 assertions).
  - Call recording & zero-input defaults.
  - Appointment creation & validation.
  - Compact appointment outcome handling (all branches: attended/install, attended/followup, attended/decision, attended/not_interested, no_show, reschedule, cancelled).
  - Installation completion with single vs ambiguous appointments.
  - Follow-up completion with all 5 canonical outcomes.
  - Transactional rollback on dependent failure.
  - Queue and notification service exclusion of completed follow-ups.
  - Close with structured reasons & mandatory note for 'other'.
  - Reopen with mandatory reason and prospect reset.
  - Permission boundaries for guest, inactive, and staff roles.
- **Full Test Suite**: 355 tests, 2340 assertions (100% PASS, 0 errors, 0 failures).
- **Template Cache**: `php artisan view:cache` (SUCCESS).
- **Asset Compilation**: `npm run build` (Vite production build SUCCESS).
- **PHP Linting**: `php -l` passed with zero errors on all modified/created files.
- **Git Diff**: `git diff --check` passed with zero warnings.
