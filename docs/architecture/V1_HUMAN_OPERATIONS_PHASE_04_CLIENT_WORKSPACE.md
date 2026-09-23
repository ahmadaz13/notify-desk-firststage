# V1 Human Operations Phase 04: Client Workspace Rebuild

## Overview
Phase 04 rebuilds the default Client Workspace into a single operational page centered on identity, preferred contact, current stage, next action, context-aware actions, subscription summary, amount due, contract access, and recent activity. Normal daily client operations no longer require navigating seven tabs.

## 1. Files Changed

### Modified Files
- `app/Http/Controllers/ClientController.php`: Eager-loaded relations (`plan.product`, `plan.services`, `contracts`, `invoices`, `contacts`, `timeline`), passed authenticated actor to ViewModel, and injected authoritative receivable projection via `ReceivableService`.
- `app/ViewModels/ClientWorkspaceViewModel.php`: Implemented read-only adapters `UX-D04` (Product-aware subscription projection and receivable summary) and `UX-D05` (richer context-action resolver adhering to strict priority order).
- `resources/views/clients/show.blade.php`: Rebuilt single-page layout following the 13 frozen sections in exact order.
- `resources/views/clients/workspace/workspace-scripts.blade.php`: Added smooth scrolling and automatic expansion for collapsed `<details>` anchors (`#collapsible-history`, `#collapsible-details`, `#collapsible-management`), retained backward-compatible `switchTab()`.
- `resources/views/clients/workspace/installation-followup.blade.php`: Updated section header to canonical Arabic styling.
- `resources/views/clients/workspace/subscription-billing.blade.php`: Compartmentalized under Management & Finance; preserved all existing backend routes and forms without altering financial logic.
- `resources/views/clients/workspace/overview.blade.php`, `contacts.blade.php`, `notes.blade.php`, `appointments.blade.php`, `timeline.blade.php`: Preserved and integrated into collapsed Details/History partials.
- `resources/css/app.css`: Added responsive styling for the single-page workspace (hero action, command card, product subscription cards, amount due box, 390px mobile single-column, 1440px desktop max-width).
- `lang/ar/notify.php`: Added Arabic localization keys for workspace header, next action, context actions, subscriptions by product, amount due, contract status, history, and details.
- `lang/en/notify.php`: Added English localization keys mirroring the Arabic catalog.

### Created Files
- `resources/views/clients/workspace/history.blade.php`: Collapsible container for full activity history, appointments, and installation/follow-up logs.
- `resources/views/clients/workspace/details.blade.php`: Collapsible container for additional contacts, business metadata, and notes.
- `resources/views/clients/workspace/management-finance.blade.php`: Collapsible, permission-gated container housing billing lifecycle, manual controls, and admin resolution tools.
- `tests/Feature/ClientWorkspacePhase04Test.php`: 15 comprehensive feature tests validating render hierarchy, context actions, permissions, financial/subscription read integrity, and activity ordering.
- `docs/architecture/V1_HUMAN_OPERATIONS_PHASE_04_CLIENT_WORKSPACE.md`: This architecture specification.

---

## 2. Final Workspace Structure (13 Frozen Sections)
The single continuous workspace renders in this exact frozen order:
1. **Client Identity**: Business name, business category, and area/location summary.
2. **Preferred Contact**: Authoritative operational contact via `Client::preferredOperationalContact()`, primary phone, large Call & WhatsApp shortcuts.
3. **Current Stage**: Read-only human badge/label (no raw stage dropdown or manual transitions).
4. **Next Action**: Visual highlight block showing recommended next operational step, due time if authoritative, and context reason.
5. **Primary Context Action**: Visually dominant hero action button resolved by `UX-D05`.
6. **Relevant Secondary Actions**: Context-filtered secondary actions (e.g. Call, WhatsApp, Schedule Appointment).
7. **Subscriptions by Product (UX-D04)**: Independent cards for each Product subscription (product name, plan name, term, status, renewal date, outstanding amount, contract status).
8. **Amount Due Summary**: Authoritative outstanding total from `ReceivableService` with clear "paid" indicator when zero.
9. **Contract Status and Access**: View, Download, and Print links for active/signed contracts respecting `ContractPolicy`.
10. **Last 3 Activities**: Authoritative timeline events ordered most-recent-first with human-readable labels.
11. **Expandable Full History**: Collapsible (`<details>`) containing complete timeline, appointment history, and installation logs.
12. **Expandable Details**: Collapsible (`<details>`) containing additional contacts, social/map URLs, and operational notes.
13. **Permissioned Management & Finance**: Collapsible (`<details>`), visible only to authorized users, housing subscription-billing controls, review items, and admin operations.

---

## 3. Primary Action Resolver Behavior (UX-D05)
Implemented in `ClientWorkspaceViewModel::resolveContextActions()` as a pure READ/PRESENTATION adapter.
Strict priority order enforced:
1. **Closed**: Primary `Reopen Client`, Secondary `View History`, `View Contracts`.
2. **Review Required**: Primary `Review Decision`, Secondary `Edit Contact`, `Record Call`, `Close Client`.
3. **Authorized Overdue Collection**: Primary `Record Payment` (if authorized) or `View Balance`, Secondary `Contact Client`, `View Subscription`, `View Contract`.
4. **Due Installation**: Primary `Complete Installation`, Secondary `Reschedule`, `Call`.
5. **Due Appointment**: Primary `Record Result` (when due) or `View Appointment`, Secondary `Reschedule`, `Call`.
6. **Due Follow-up or Callback**: Primary `Record Follow-up`, Secondary `Call`, `WhatsApp`, `Start Subscription` (if authorized).
7. **Subscriber Paid**: Primary `View Subscription`, Secondary `View Contract`, `Add Product Subscription` (if authorized).
8. **New Prospect / Contacting**: Primary `Record Call`, Secondary `Call`, `WhatsApp`, `Create Appointment`, `Edit Details`.

No domain writes, stage mutations, appointment completions, or payment entries occur during resolution.

---

## 4. Read Adapters & ViewModels Introduced
- **UX-D04 Subscriptions by Product Projection**: Groups subscriptions independently by Product model, resolves term (Monthly/Annual), status badge, next renewal date, outstanding receivable, and associated contract link. Never merges distinct products into a single badge.
- **UX-D04 Receivable Summary**: Bridges `ReceivableService::getOutstandingReceivables()` to provide minor-unit formatted total due and zero-balance status.
- **UX-D05 Context Action Presentation**: Translates model states into action metadata (label, target, action type, href).

---

## 5. Amount Due Source
- Sourced strictly from `ReceivableService::getOutstandingReceivables($client->id)` via `app/Services/ReceivableService.php`.
- Zero calculation occurs in Blade templates. Minor units are converted using `Money::fromMinorUnits()` to ensure currency fidelity.
- "Record Payment" CTA is displayed only to users passing `FinancialPermissions::RECORD_PAYMENT`.

---

## 6. Contract Access Behavior
- Contracts are resolved per subscription using `Contract::where('client_id', $client->id)`.
- Links to HTML view and print endpoints respect `ContractPolicy`.
- No PDF generation was introduced; current HTML artifact remains authoritative.
- Advanced contract lifecycle operations (regenerate, void, supersede) remain restricted to the Management section.

---

## 7. Recent Activity & History Behavior
- **Last 3 Activities**: Projected from `ClientWorkspaceViewModel::recentActivities()` using `activityLogs` and `timelineEvents`, ordered newest first. Internal IDs and raw logs are excluded.
- **Full History**: Nested within a collapsed `<details id="collapsible-history">` section, preserving all historical appointments, contact attempts, and notes without data loss.

---

## 8. Permission Behavior
- Staff users are strictly prohibited from viewing `Start Subscription` or `Record Payment` controls unless authorized by policy gates.
- Founder / Admin accounts see authorized financial controls.
- The Management & Finance section is gated: unauthorized staff cannot view invoice administration, refunds, reversals, or credit notes.
- Backend routes enforce policies independently of UI visibility.

---

## 9. Performance & Eager-Loading Decisions
- `ClientController::show()` eager-loads:
  - `contacts`
  - `subscriptions.plan.product`
  - `subscriptions.plan.services`
  - `contracts`
  - `invoices`
  - `installations`
  - `activityLogs`
- Eliminates N+1 query patterns when rendering multiple product cards and historical timelines.

---

## 10. Arabic (RTL) & English (LTR) Verification
- All UI labels utilize `notify.client_workspace.*` and `notify.installations.*` translation catalogs.
- Dynamic alignment and layout use flexbox and grid respecting HTML `dir="rtl"` and `dir="ltr"` without hardcoded margin hacks.
- Mobile layout verified for 390px viewports; desktop container constrained to 1440px max-width.

---

## 11. Manual QA Matrix
1. **390px / Arabic / New Prospect**: Identity, Next Action, and dominant "Record Call" action display cleanly without horizontal scrolling.
2. **390px / Arabic / Multi-Product Subscriber**: Multiple product subscription cards stack vertically with individual plan names, amount due displays accurately, and contract links are clickable.
3. **390px / English / Appointment Scheduled**: LTR alignment correct; primary action points to Appointment details.
4. **1440px / Arabic / Installed & Follow-up Due**: Dominant "Record Follow-up" hero CTA; History, Details, and Management sections collapsed by default.
5. **1440px / English / Closed**: "Reopen Client" displays as primary action; history is accessible via expandable section.

---

## 12. Test Execution & Verification
- **Focused Phase 04 Feature Tests**: `tests/Feature/ClientWorkspacePhase04Test.php` -> 15 tests, 57 assertions, PASS.
- **Legacy Workspace Compatibility Tests**: `tests/Feature/Phase2BClientWorkspaceTest.php` -> 4 tests, 45 assertions, PASS.
- **Full Test Suite**: `php vendor/phpunit/phpunit/phpunit` -> 329 tests, 2236 assertions, PASS.
- **Blade Template Compilation**: `php artisan view:cache` -> Blade templates cached successfully.
- **Frontend Asset Build**: `npm run build` -> Vite v6.4.3 production build successful.
- **Syntax Checks**: `php -l` on all modified/created PHP files -> No syntax errors detected.
- **Whitespace & Git Integrity**: `git diff --check` -> Clean, 0 warnings/errors.
- **Database Status**: `php artisan migrate:status` -> 0 pending migrations; no migrations created or altered.

---

## 13. Domain & Financial Authority Confirmation
- **Write-Domain Logic**: UNCHANGED. No mutations to client lifecycle, appointments, follow-ups, or installations.
- **Financial Logic**: UNCHANGED. No modifications to billing engines, invoices, payments, or ledger calculations.
- **Database Schema**: UNCHANGED. Zero migrations added, zero schema modifications made.
