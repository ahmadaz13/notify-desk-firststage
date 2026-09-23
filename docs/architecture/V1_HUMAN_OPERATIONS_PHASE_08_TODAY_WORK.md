# Notify Desk V1 Human Operations — Phase 08: Today & Work

## Status: APPROVED & VERIFIED (Phase 08 Complete)

## 1. Executive Summary
Phase 08 establishes the unified operational command center for daily work across Notify Desk V1. It decouples operational execution from legacy CRM/financial analytics by providing two focused working surfaces:
1. **Today**: Answers strictly "What should I do today?" with three time-scoped sections:
   - **Overdue**: Work items whose authoritative due time is past (`due_at < now`).
   - **Next**: Nearest actionable work due within 2 hours, or undated new prospects needing first contact.
   - **Later Today**: Remaining work scheduled for later today.
   - Strictly zero KPI widgets, revenue metrics, cash snapshots, or sales charts.
2. **Work**: Answers strictly "What operational work is currently open?" across the entire organization:
   - Filters: All, Calls, Appointments, Installations, Follow-ups, Collections (permission-gated).
   - Groups: Overdue, Today, Upcoming.

## 2. Unified Operational Work Projection Architecture
A dedicated read-only projection service was implemented:
- **Class**: `App\Services\UnifiedOperationalWorkProjection`
- **Read-Only Invariant**: Does not own lifecycle state, does not mutate models, does not trigger billing or workflow writes.
- **Aggregated Authorities**:
  - **Appointments**: Active scheduled/confirmed meetings via `Appointment` (`appointment_type != 'installation'`).
  - **Installations**: Active scheduled/confirmed free installations via `Appointment` (`appointment_type == 'installation'`).
  - **Follow-ups**: Authoritative open records from `follow_ups` (`completed_at IS NULL`, non-archived clients, non-closed stages).
  - **Calls / Callbacks**: Prospects needing contact from `active_contact_queue` and scheduled telephone callbacks.
  - **Reviews**: Pending `ClientReviewItem` records (`status == 'pending'`).
  - **Collections**: Authoritative outstanding invoices projected directly through `ReceivableService::outstandingInvoices()`.

## 3. Work Item Contract
Every projected item adheres to a consistent presentation contract:
- `id`: Unique composite identifier (e.g. `apt-1`, `fu-2`, `col-3`).
- `type`: `call`, `appointment`, `installation`, `follow_up`, `collection`, `review`.
- `client_id` & `client_name`: Client reference for workspace routing.
- `label`: Concise human-readable task description.
- `context`: Short supporting context (contact name, phone, notes, location, or invoice amount).
- `due_at`: Timezone-aware Carbon instance in `Asia/Amman` (or `null` for undated leads).
- `priority`: `overdue`, `today`, or `upcoming`.
- `primary_action`: Direct route/hash to existing workspace modals (`#call`, `#appointments`, `#installation`, `#follow-up`, `#payment`, `#review`).
- `secondary_actions`: Quick operational fallbacks (Call, WhatsApp, Open Client file).
- `source_reference`: Model reference.

## 4. Collection Security & Data Leak Prevention
- Collections are derived exclusively from `ReceivableService`.
- No arithmetic (`invoice.total - payments`) occurs in Blade or ViewModels.
- Permission enforcement: Accessible solely to users authorized under `FinancialPermissions::RECORD_PAYMENT` or `VIEW_FINANCIAL_REPORTS`.
- Staff users are completely shielded:
  - Collections filter is hidden from the UI.
  - No receivable, invoice, or debt records are projected or exposed to Staff.

## 5. Performance & Query Integrity
- Zero per-client queries (N+1 eliminated).
- Receivables use batch grouped SQL queries via `ReceivableService`.
- Appointments, follow-ups, and review items use eager loading with client relationship checks.

## 6. Verification & Automated Tests
Tested via `tests/Feature/Phase08TodayAndWorkTest.php`:
- 10 test cases, 58 assertions, 100% PASS.
- Regression verification against `ApplicationShellNavigationTest` and `EmptyStatesTest` passed.
