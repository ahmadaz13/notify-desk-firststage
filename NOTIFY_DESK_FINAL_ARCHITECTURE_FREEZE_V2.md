# Notify Desk Final Architecture Freeze V2

## Document Status
- **Status**: FINAL_ARCHITECTURE_FROZEN
- **Revision Date**: 2026-09-22
- **Authors**: Principal Software Architect, Product UX Architect, SaaS Financial Systems Architect
- **Target System**: Notify Desk (Laravel 11 / Blade / Alpine.js / Vite / Tailwind-free Vanilla CSS)
- **Repository Path**: `C:\notifydesk-laravel`
- **Notice & Freeze Statement**: This document is the single authoritative architecture for the Notify Desk recovery and maintenance program. Implementation agents must not redefine navigation, workflow philosophy, financial authority, accounting semantics, input contracts, or UX layering without explicit owner approval and a formal architecture revision.

---

## Executive Summary
Notify Desk is a specialized Daily Operations Platform and SaaS Commercial Engine designed for merchant service providers, POS distributors, and recurring service businesses. Its design philosophy balances **extreme operational simplicity at the human interface** with **rigorous, accounting-grade, policy-driven financial integrity in the core backend**.

The current codebase has reached exceptional functional depth through ten prior development phases, featuring an integer-fils Money value object (`App\Support\Money`), double-entry general ledger posting (`JournalPostingService`), date-effective catalog pricing (`PlanPriceService`), automated contract snapshots (`ContractService`), deferred revenue amortization (`RevenueRecognitionService`), and an event-sourced SaaS metrics engine (`SaasMetricsService`).

However, architectural drift and layered iterations have produced real friction:
1. Fragmented navigation featuring 6 top-level management areas and exposed technical subledgers.
2. Production leaks of unmapped validation exceptions (`The plan price id field is required.`) and translation keys (`notify.actions.record_outcome`, `notify.actions.open_client`).
3. Parallel coexistence of modern V2 services and legacy unhedged write endpoints (`POST /payments`, `POST /expenses`, `POST /investments`).
4. Excessive visual table dominance across management pages.
5. Inefficient background execution of legacy floating-point financial calculations during standard daily operations.

This final architecture candidate unifies Notify Desk into **4 Core Product Areas** (Today, Clients, Finance, Administration) across **3 Strict Layers** (Daily, Management, Engine), establishes a shared daily board with personal auto-saving Daily Notes, orders Financial Safety as Phase 1, forbids blind redirects of incompatible legacy writes, enforces progressive disclosure across all financial surfaces, and specifies clean non-linear operational workflows.

---

## Why the Current System Needs Recovery
1. **Unhedged Financial Entry Points**: Coexistence of legacy write routes (`POST /payments`, `POST /expenses`) allows writes that bypass `CashMovement`, `PaymentAllocation`, and double-entry general ledger posting.
2. **Double-Submit Risks (P0)**: Irreversible financial commands (recording payments, creating paid subscriptions, issuing credit notes) lack database-backed atomic request idempotency locks, exposing the business to duplicate charges upon rapid button clicks.
3. **Cognitive Table Overload**: Navigating to Finance, Collections, Expenses, or Accounting currently greets the user with 3 to 5 fully expanded, high-density data tables, obscuring actionable business metrics.
4. **Broken Human Experience**: Real browser sessions reveal literal translation keys in Today cards and technical snake_case validation attributes when encountering catalog edge-cases.
5. **Daily Board Contamination**: The Today operational feed runs legacy unindexed floating-point database sums on every hit, coupling field staff performance to deprecated calculation routines.
6. **ERP Remnants in Client Workspace**: Even within collapsed accordions, the operational client file embeds legacy invoice creation forms, manual allocation widgets, and unallocated payment listings.

---

## Current Repository Baseline
- **Git Branch**: `codex/human-operations-simplification`
- **Head Commit**: `3b5dccf Phase 10: finalize automated QA candidate`
- **Git Status**: Clean working directory (ahead of origin by 10 commits, untracked `NOTIFY_DESK_FINAL_ARCHITECTURE_FREEZE_V2.md` and `docs/audits/`)
- **Route Inventory**: 148 registered routes verified via `php artisan route:list --except-vendor`
- **Database Schema**: 42 migrations defined, all 42 in `Ran` status verified via `php artisan migrate:status`
- **Automated Test Suite**: 402 tests, 2,684 assertions, 100% passing verified via `php artisan test`
- **Frontend Assets**: Vite v6.4.3 production build passes in 29.17s (`public/build/assets/app-kR0cbibF.css` 100.49 kB, `app-DuIJm4no.js` 118.04 kB)
- **Blade Compilation**: `php artisan view:cache` compiles with exit code 0.

---

## Architecture Principles
1. **Human Simplicity at the Surface, Rigorous Accounting in the Core**: Field staff and account managers enter only real-world facts, dates, categories, and business decisions. The backend derives pricing, taxes, receivables, contracts, cash movements, accounting entries, and metrics.
2. **Subledger to General Ledger Flow**:
   $$\text{Simple Human Input} \longrightarrow \text{Domain/Subledger Record} \longrightarrow \text{Accounting Event} \longrightarrow \text{Balanced Journal Entry} \longrightarrow \text{General Ledger} \longrightarrow \text{Reports}$$
3. **Decoupled SaaS Metrics Flow**:
   $$\text{Subscription Lifecycle} \longrightarrow \text{Subscription Economic Event} \longrightarrow \text{SaaS Metric Event} \longrightarrow \text{MRR / ARR / Expansion / Contraction / Churn}$$
   SaaS metrics are strictly non-cash and non-revenue. They track recurring contract economics, completely independent of cash timing or accounting revenue recognition.
4. **Critical Conceptual Separations**:
   - **Payment $\neq$ Revenue**: Payments are cash settlements affecting cash and receivables; revenue is earned over time.
   - **Invoice $\neq$ Cash**: Invoices create receivables; they do not represent liquid cash.
   - **Cash $\neq$ Profit**: Operating cash flow differs fundamentally from accrual net income.
   - **Accounting Revenue $\neq$ SaaS MRR/ARR**: Amortized recognized accounting revenue differs from normalized annual recurring contract run-rates.
   - **Investment / Loan $\neq$ Revenue**: Founder funding and debt are equity/liabilities, never operating turnover.
   - **Loan Repayment $\neq$ Expense**: Repaying principal reduces liabilities and cash; it is never an operating expense.
   - **Owner Distribution $\neq$ Operating Expense**: Equity drawings reduce equity and cash; they do not impact EBITDA or net income.
   - **Custom Solution Revenue $\neq$ SaaS MRR/ARR**: One-time implementation and custom project revenue are explicitly excluded from SaaS recurring metrics.
5. **Progressive Disclosure**: Management screens speak first in headline numbers, status indicators, trends, and actionable alerts. Dense audit tables appear only upon deliberate demand.
6. **Zero Floating Point in Financial Values**: Authoritative money calculations exclusively utilize integer minor units (fils: 1 JOD = 1,000 fils) managed by `App\Support\Money`.

---

## Final Product Definition
Notify Desk is an **Operational Business System** built for high-velocity daily field execution and accounting-grade commercial control.

### Top-Level Product Areas (4 Domains)
1. **Today**: The daily operational nerve center for the internal team.
2. **Clients**: The prospect/client operational directory and single-account execution workspaces.
3. **Finance**: The single unified home for Cash, Collections, Expenses, Capital, SaaS Metrics, and General Ledger Accounting.
4. **Administration**: Commercial Catalog (Products & Plans), Partners, Team, Conflict Resolution, CSV Imports, and System Governance.

### Architectural Layers (3 Tiers)
1. **Daily Layer**: `Today` and `Clients` (used continuously by field staff and account managers).
2. **Management Layer**: `Finance` and `Administration` (used periodically by founders, finance officers, and administrators).
3. **Engine Layer**: Subordinate, accounting-grade services (`Accounting`, `Financial Accounts`, `Revenue Recognition`, `Reconciliation`, `Credits`, `Refunds`, `Reversals`, `Manual Allocations`, `Period Controls`, `Legacy Recovery`) housed securely within Finance Advanced.

---

## Final Information Architecture

```
Notify Desk V2
├── Daily Layer (High Frequency / Operational)
│   ├── Today
│   │   ├── Mode: Today (Overdue, Next, Later Today)
│   │   ├── Mode: All Work (Overdue, Today, Upcoming - Type Filtered)
│   │   ├── Team Filter: All Work vs. My Work
│   │   └── Daily Notes (Personal, Autosaved, Asia/Amman)
│   └── Clients
│       ├── Directory & Index (Search, Stage Filter, 4 Operational Columns)
│       ├── Intake (Streamlined 5-Field Prospect Creation)
│       └── Client Workspace (Command Card, Preferred Contact, Subscriptions, Due Summary)
├── Management Layer (Periodic / Governance)
│   ├── Finance
│   │   ├── Overview (Unified Cockpit: Accounting KPIs + Separate SaaS Block)
│   │   ├── Collections (Receivables, Aging, Overpayments, Credit Notes, Refunds)
│   │   ├── Expenses (Operating Expenses, Recurring Obligations, Vendors)
│   │   ├── Capital & Assets (Contributions, Loans, Repayments, Distributions, Assets)
│   │   └── Reports (P&L, Balance Sheet, Cash Flow, Trial Balance, SaaS Intelligence)
│   └── Administration
│       ├── Products & Pricing (Products, Plans, Date-Effective Price Matrix)
│       ├── Partners (Referral Partners, Commission Rates, Attributions)
│       ├── Team & Permissions (Internal Staff, Role Assignments)
│       ├── Import (CSV Bulk Ingress)
│       ├── Conflicts / Reviews (Duplicate Ingress & Referral Adjudication)
│       └── Settings (System Configuration, Activity Audit Log)
└── Engine Layer (Subordinate / Gated within Finance -> Advanced)
    ├── Financial Accounts & Cash Subledger
    ├── General Ledger & Journal Entries
    ├── Revenue Recognition Schedules & Runner
    ├── Accounting Reconciliation Tools
    ├── Manual Allocations, Credit Applications & Reversals
    └── Accounting Period Locks (Close / Reopen)
```

---

## Final Navigation

### Role-Aware Navigation Visibility Contract
Navigation items are rendered strictly according to authenticated role capabilities, while server-side authorization middleware enforces security independently:

| Area | Staff | Finance Role | Founder / Admin |
| :--- | :---: | :---: | :---: |
| **Today** | Visible | Visible | Visible |
| **Clients** | Visible | Visible | Visible |
| **Finance** | **Hidden** | Visible | Visible |
| **Administration** | **Hidden** | Visible (Only permitted sub-items) | Visible (All sub-items) |
| **Finance -> Advanced** | **Hidden** | Visible | Visible |

*Security Invariant*: Hiding a navigation item is an ergonomics feature, not a security boundary. All routes are strictly guarded by `EnsureActiveInternalUser`, `FinancialPermissions`, and model policies.

### Desktop Sidebar Navigation
- **Top Brand**: Notify Desk logo with current application title and locale context.
- **Primary Items (Role-Filtered)**:
  - `Today` (`route('dashboard', ['mode' => 'daily'])`)
  - `Clients` (`route('clients.index')`)
  - `Finance` (`route('finance.index')`)
  - `Administration` (`route('commercial-catalog.index')` or dedicated Admin hub)
- **Sidebar Collapse Behavior**:
  - Expanded mode displays full text labels and section groupings.
  - Collapsed mode displays icon-only touch targets (64px width).
  - State persisted via `localStorage` per user device.
- **Subordinate Engine Isolation**: Advanced Accounting and Financial Accounts links appear exclusively within the `Finance -> Advanced` tab for authorized personnel; they are never rendered as top-level sidebar items.

### Mobile Bottom Navigation
- **Architecture Rule**: Work is NEVER a top-level navigation destination. Work remains an internal mode/filter of Today for every role. Do not invent a fourth Staff slot merely to preserve a 4-item layout.
- **Staff Mobile Navigation (3 Items)**:
  1. `Today` (Icon: Home)
  2. `Clients` (Icon: Users)
  3. `More` (Icon: Menu - triggers mobile slide-over sheet)
- **Finance & Admin Mobile Navigation (4 Items)**:
  1. `Today` (Icon: Home)
  2. `Clients` (Icon: Users)
  3. `Finance` (Icon: Wallet / Chart)
  4. `More` (Icon: Menu - triggers mobile slide-over sheet)
- **Mobile Slide-Over Sheet (`More`)**:
  - Administration links (permission-gated).
  - Language toggle (`العربية` / `English`).
  - User identity badge and Logout button.
  - Subledger and accounting links are nested within Finance, never exposed at the top of the mobile sheet.

---

## Daily Layer

### Today Shared Board Contract
1. **Primary Home Screen**: Today is the landing route (`/`) and primary operational workspace for all internal users.
2. **Core Purpose**: Answers only one operational question: *What must happen today?*
3. **Shared Board Model**:
   - Displays the shared operational workload across active internal team members, filtered by role capabilities.
   - Each card clearly displays the assigned/responsible staff member when an assignment exists.
   - **Filter Control**: Simple toggle between **All Work** (team-wide) and **My Work** (items assigned to or owned by the authenticated user).
   - Staff dashboards are not fragmented into separate siloed pages; one unified board supports the entire team.
4. **Operational Modes**:
   - `Today Mode`: Prioritizes immediate work categorized into:
     - **Overdue** (Red count badge, past-due callbacks, missed appointments, delayed installations).
     - **Next** (Immediate upcoming scheduled item).
     - **Later Today** (Items scheduled for subsequent hours of the current business date).
   - `All Work Mode`: Comprehensive categorized queue:
     - **Overdue**, **Today**, **Upcoming**.
     - Filter chips: All, Calls, Appointments, Installations, Follow-ups, Collections (permission-gated).
5. **Work Card Contract**:
   - **Must Show**: Client Business Name, Area, Task Type, Due Time / Overdue State, Short Context Note, Responsible Staff Name, One Obvious Primary Action Button.
   - **May Show**: Status badge, secondary "Open Client" link.
   - **Must Not Show**: Raw IDs, accounting metrics, lifecycle codes, or excessive historical logs.
6. **Strict Today Exclusions**: Today shall never render P&L widgets, revenue figures, MRR/ARR dashboards, bank balances, or wide financial tables.
7. **Performance Guard**: The Today controller route shall execute zero legacy floating-point financial queries (`$todayCollections`, `$monthIncome`, etc.) in the background.

---

## Daily Notes Auto-Save Contract
1. **Placement**: Directly embedded on the main Today operational screen, accessible without navigation or context switching.
2. **Title**: Daily Notes / ملاحظات اليوم.
3. **Interaction & UI**: Clean, multiline text editor with zero horizontal scroll, responsive across mobile (390px) and desktop.
4. **Ownership Semantics**:
   - Notes are **personal per internal user and per business date**, backed by the verified database unique constraint on `daily_notes(user_id, date)`.
   - Notes remain personal scratchpads; they are completely independent of the shared team operational work board.
5. **Timezone & Date Rule**:
   - Canonical business date evaluated under `Asia/Amman`.
   - Defaults to the current business date on load.
   - Historical notes access is treated as a secondary query; the primary view strictly displays today's note.
6. **Autosave Behavior**:
   - Zero manual "Save" button.
   - Text changes are saved automatically via debounced background requests (500ms debounce interval) to `PUT /daily-notes`.
   - **Save States Displayed**:
     - `Saving...` (transient during HTTP request)
     - `Saved` (with timestamp confirmation)
     - `Save failed` (displayed prominently upon network or server error)
7. **Failure & Persistence Invariants**:
   - Navigating away or refreshing the browser preserves the saved note.
   - If an autosave request fails, the editor **never silently pretends success**. The unsaved text is retained in the browser DOM/localStorage with an explicit error alert and a retry action.
   - Daily Notes are operational scratchpads; they produce zero accounting side effects, zero billing changes, and zero client lifecycle transitions.

---

## Clients Index
- **Desktop**: Fast, clean 4-column operational table:
  1. Client Name & Location Tag.
  2. Lifecycle Stage Badge (Prospect, Contacted, Appointment, Installed Trial, Subscriber, Closed).
  3. Next Scheduled Action & Due Time.
  4. Contextual Quick Action Button.
- **Mobile**: Touch-friendly card stack with prominent Call, WhatsApp, and Open Workspace actions.
- **Search & Filters**: Debounced search across business name, contact person, phone, and area; quick filter pills for lifecycle stages.

---

## Client Workspace

### Client Workspace Financial Summary Contract
The Client Workspace (`clients.show`) is the central operational file for a single merchant account. It must never function as a mini-ERP or expose dense accounting tables.

1. **Required Operational Visibility**:
   - Who is the client? (Business Name, Category, Location).
   - How do I reach them? (Preferred Contact Name, Role, Phone, Call & WhatsApp direct links).
   - What is happening now? (Stage Badge, Contextual Next Action block).
   - What should I do next? (Visually dominant Hero Action button).
   - What are they subscribed to? (Independent summary cards for each active product subscription).
   - Do they owe money? (Amount Due summary box with clear zero-balance or overdue indicator).
   - Is there a contract? (Contract number, View HTML, Download PDF, Print links).
2. **Normal Financial Summary Components**:
   - **Amount Due Box**: Authoritatively projected from `ReceivableService`; shows total outstanding in JOD, paid-in-full badge, and an explicit "Record Payment" button for authorized roles.
   - **Subscriptions by Product**: Clean cards showing Product Name, Plan Name, Term (Monthly/Annual), Next Renewal Date, and Outstanding Installment balance if applicable.
   - **Contract Summary**: Status badge and artifact action links.
3. **De-cluttering & ERP Removal**:
   - Raw invoice tables, raw payment logs, credit note registers, and manual allocation forms are **completely removed from the normal Client Workspace view**.
   - A single clean secondary action: **"View Financial Details"** redirects authorized users to the client's financial context inside `Finance -> Collections`.
4. **Collapsed Operational Sections**:
   - Full Historical Timeline (audit activities, contact attempts, past appointments).
   - Client Operational Details (Branch counts, social profiles, maps URL, tax numbers).
   - Neither collapsed section shall contain legacy write forms or unhedged financial inputs.

---

## Final Workflow Contracts

### Non-Linear Client Workflow Model
Notify Desk documents real-world operational truth rather than enforcing a rigid artificial sales funnel. The following direct operational transitions are explicitly valid:

```
[ Prospect Created ]
   │
   ├───> Record Call (Optional)
   ├───> Schedule Appointment
   ├───> Schedule Free Installation (Directly Accessible)
   └───> Start Paid Subscription (Directly Accessible for Authorized Roles)

[ Free Installation Completed ]
   │
   ├───> Automatic 3-Day Follow-up Generated
   └───> Explicit Hero Action: "Start Subscription / Convert to Subscriber"

[ Close Client ]
   │
   └───> Secondary Action under More Menu (Requires Reason, Preserves History)
```

#### Forbidden Artificial Dependencies
1. A phone call is **never mandatory** before booking an appointment or scheduling an installation.
2. An appointment is **never mandatory** before scheduling an installation.
3. A trial installation is **never mandatory** before starting a paid subscription.
4. Completing a follow-up is **never mandatory** before starting a subscription.

---

## Installation Product Context Rule
1. **No Guessing**: The system shall never guess or assume a commercial Product purely to reduce clicks.
2. **Authoritative Resolution**:
   - `FreeInstallationService` records the installed service/item.
   - If the installed item unambiguously maps to exactly one commercial Product in the catalog, that Product is preselected in the Start Subscription modal.
   - If multiple Products are possible or no direct link exists, the form explicitly requires the user to select the intended Product.
3. **Explicit Conversion**: Completing an installation **never automatically starts a paid subscription or creates an invoice**. Conversion to a paid subscriber is always an explicit, authorized commercial decision executed via the Start Subscription workflow.

---

## Close Client Contract
1. **Secondary Placement**: Closing a client is an exception, not a primary daily goal. It is strictly housed under the workspace **"More / Actions"** overflow menu, never as a hero primary button.
2. **Meaningful Reason Required**: Closing requires selecting a validated reason code (`not_interested`, `competitor`, `out_of_business`, `pricing`, `other` with mandatory note).
3. **History Preservation**: Closing a client updates lifecycle state to `CLOSED` and resolves active operational review items. It **strictly preserves all historical appointments, contacts, notes, contracts, invoices, and payments**.
4. **No Financial Destruction**: Closing a client never voids issued invoices, deletes payment records, or mutates general ledger journals.
5. **Reopening**: Reopening a closed client is an explicit action requiring a reactivation note, returning the account safely to `PROSPECT` without historical loss.

---

## Final Input Contracts & Complexity Reductions

| Workflow | Target Normal Inputs | Mandatory Fields | Optional / More Fields | Backend-Derived Values |
| :--- | :---: | :--- | :--- | :--- |
| **Add Client** | **5** | Business name, Category, Phone, Area, Lead source | Contact person, Referring partner (if source=Partner) | `business_type`, `city`, `stage=PROSPECT`, `status=prospect`, `branches=1`, `owner_id` |
| **Record Call** | **1-2** | Result outcome | Callback date/time, Short note | Method defaults to phone, actor, stage effects, activity log |
| **Create Appointment** | **3** | Date, Time, Type | Location note, Assigned staff | Status=`scheduled`, client link, creator |
| **Schedule Free Install**| **2** | Date, Time | Service/Product (only when not safely derivable from context), Assigned installer, Note | Type=`installation`, appointment link, trial lifecycle. Time is a normal scheduling input; Product/Service is not forced when already known. |
| **Complete Install** | **1** | Completion outcome / item verification | Note | Actor, completed timestamp, auto 3-day follow-up linking |
| **Complete Follow-up** | **1-2** | Completion outcome | Next follow-up date, Note | `completed_at=now()`, `completed_by=auth()`, transactional safety |
| **Start Subscription** | **3** | Product (only when not already known), Plan, Billing Interval (Monthly or Annual) | Start Date (defaults to Today in Asia/Amman; not mandatory primary field), Terms (Full/Installments when Annual), Installments count (when Installments), Installment due day (when Installments) | `plan_price_id` resolution, branches, tax, invoice, contract snapshot, revenue schedule, SaaS metric events |
| **Contract** | **0** | None (Fully Automated) | None | Sequential number, snapshot JSON, HTML/PDF artifact |
| **Record Payment** | **2** | Amount Received, Payment Method | Received date (default today), Reference note | Account resolver, oldest-first auto allocation, cash movement, GL posting |
| **Record Expense** | **3-4** | Amount, Category, Paid-From Account | Description, Date (default today), Vendor | Cash movement (if company paid), GL expense journal lines |
| **Capital Funding** | **3-4** | Amount, Source, Type, Destination Account | Date (default today), Reference | Cash movement, GL Equity or Loan liability journal lines |

---

## Financial Command Idempotency Contract
Irreversible financial commands must be completely immune to network retries, browser reloads, and rapid double-clicking:
1. **Target Endpoints**:
   - `POST /clients/{client}/payments/normal` (Collections)
   - `POST /clients/{client}/guided-subscription` (Subscription Billing)
   - `POST /operating-expenses` (Expense Logging)
   - `POST /capital-funding-transactions` (Capital Funding)
   - `POST /clients/{client}/credit-notes` (Credit Note Issuance)
   - `POST /payments/{payment}/refunds` (Payment Refunds)
2. **Idempotency Mechanism**:
   - Database-backed `idempotency_keys` table storing `key`, `request_hash`, `response_code`, and cached `response_body`.
   - Frontend forms generate a UUIDv4 request token attached as an `X-Idempotency-Key` header or hidden input.
   - If an identical request arrives while processing, the concurrent request waits or returns the authoritative cached response without executing duplicate business logic, cash movements, or journal lines.
3. **Button Disabling**: Frontend UI buttons disable immediately upon first click with a loading state, providing defense-in-depth before the network layer.

---

## Legacy Financial Write Isolation Contract
Legacy unhedged financial endpoints shall never be blindly redirected to modern controllers without semantic compatibility guarantees:

| Legacy Route | Handler | Classification | Target Isolation Policy |
| :--- | :--- | :---: | :--- |
| `POST /payments` | `DashboardController@storePayment` | **DISABLE_WRITE_AFTER_CUTOVER** | Remove from all UI entry points immediately. In Phase 1, attach deprecation logger. At Phase 8 cutover, reject with 410 Gone; modern writes must use `POST /clients/{client}/payments/normal`. |
| `POST /expenses` | `ExpenseController@store` | **DISABLE_WRITE_AFTER_CUTOVER** | Remove from all UI entry points. Legacy table lacks account linkage. At Phase 8 cutover, disable permanently; modern writes must use `POST /operating-expenses`. |
| `POST /investments` | `InvestmentController@store` | **READ_ONLY_HISTORY** | Superseded by `capital-funding-transactions`. Retain read access for historical review; disable write endpoint. |
| `POST /capital-expenses`| `CapitalExpenseController@store` | **READ_ONLY_HISTORY** | Superseded by `fixed-assets`. Retain read access; disable write endpoint. |
| `POST /clients/{client}/paid-subscriptions` | `BillingController@startPaidSubscription` | **SAFE_ADAPTER** | Internal adapter translates legacy form inputs to `SubscriptionBillingService` with explicit attribute mapping, eliminating raw `plan_price_id` validation exceptions before full retirement. |
| `POST /clients/{client}/convert` | `ClientController@convert` | **DISABLE_WRITE_AFTER_CUTOVER** | Legacy V1 conversion action. Remove button from UI; retire route at cutover. |

---

## Custom Solutions Minimal Domain Decision
Notify Desk maintains a custom software development and bespoke solution line alongside recurring POS subscriptions. This capability must be cleanly supported without building an oversized project management ERP:

1. **Architecture Decision**:
   - `CustomProject` is officially part of the FINAL architecture.
   - Implementation timing is explicitly deferred to Phase 7.
   - In Phase 0–6, bespoke solution billing continues authoritatively using **One-Time Invoices** (`InvoiceLine::TYPE_CUSTOM_SOLUTION` or setup fees).
   - In Phase 7, introduce the lightweight, additive entity: **`CustomProject`** (`id`, `client_id`, `name`, `agreed_contract_value_minor`, `start_date`, `target_completion_date`, `status`, `notes`).
2. **Financial Rules**:
   - Custom solution billings are **strictly non-recurring**.
   - Custom solution revenue posts to GL Account `4020` (One-Time Solutions Revenue).
   - Custom solution revenue is **strictly excluded from SaaS MRR, ARR, Expansion MRR, Contraction MRR, and Churn MRR metrics**.
   - Invoices and payments for custom projects flow through the standard authoritative receivable and cash subledgers.
3. **Explicitly Deferred Project ERP Features**:
   - Zero Gantt charts, zero employee timesheets, zero percentage-of-completion revenue accounting, zero complex WIP (work-in-progress) cost allocation.

---

## Finance Architecture & Cockpit

### Structure of Finance Cockpit (`/finance`)
Finance is organized into 5 primary functional tabs and 1 gated engine tab:

1. **Overview**: Executive numbers-first cockpit:
   - *Accounting Block*: Available Cash, Accounts Receivable, Overdue Receivables, Recognized Revenue, Operating Expenses, Net Income.
   - *Separate SaaS Block*: MRR, ARR, Active Subscriptions, Upcoming Renewals.
   - *Rule*: Accounting Revenue and SaaS MRR/ARR shall never appear in the same card or be mathematically combined.
2. **Collections**: Single receivables cockpit: AR Aging buckets, outstanding invoices list, unallocated customer cash, credit notes, payment refunds.
3. **Expenses**: Operating expenses log, recurring obligations schedule, vendor directory.
4. **Capital & Assets**: Equity contributions, founder loans, loan repayments, owner distributions, expense reimbursements, fixed assets register.
5. **Reports**: Core financial statements (Profit & Loss, Balance Sheet, Cash Flow, Trial Balance) and SaaS intelligence exports.
6. **Advanced (Gated Engine)**: Accessible only to authorized finance/admin roles:
   - Financial accounts list & cash subledger.
   - General Ledger & raw journal entry audit.
   - Revenue recognition monthly batch runner.
   - Accounting period locks (close / reopen).
   - Manual subledger-to-GL reconciliation utilities.

### Table & Progressive Disclosure Rules
- **Rule**: Management screens shall never load multiple expanded data tables by default.
- **Pattern**:
  $$\text{Headline Metric} \longrightarrow \text{Status Badge} \longrightarrow \text{Trend / Exception Alert} \longrightarrow \text{Primary Action} \longrightarrow \text{View Details Trigger}$$
- Detail tables open inside slide-over drawers, accordion panels, or dedicated sub-pages upon explicit user click.

---

## Administration Architecture
Administration (`/administration` or `/commercial-catalog`) is the dedicated governance hub:
1. **Products & Pricing**: Products, Plans, Date-effective Price matrix, Branch tier fees, Tax rates.
2. **Partners**: Referral partner directory, commission rate percentages, partner attribution logs.
3. **Team & Permissions**: Internal staff user management, role assignments (Staff, Finance, Admin).
4. **Import**: Batch CSV ingress tools for clients and historical data.
5. **Conflicts / Reviews**: Review queue for duplicate client submissions and referral attribution disputes.
6. **Settings**:
   - Active operational settings (inactivity thresholds, review intervals, company legal info).
   - Deprecated financial settings (`commission_rate`, `partner_share_percentage`, `financial_revenue_formula`) are **hidden from normal administrative views** and restricted to technical maintenance tooling.

---

## Accounting Architecture & Policy Corrections
1. **Language Standard**: Notify Desk utilizes an **accounting-grade, policy-driven, auditable financial architecture** (replacing the informal "GAAP-grade" label).
2. **Double-Entry General Ledger**:
   - Balanced journal lines: $\sum \text{Debit Minor} = \sum \text{Credit Minor}$.
   - Standard 5-bucket Chart of Accounts: Assets (1xxx), Liabilities (2xxx), Equity (3xxx), Revenue (4xxx), Expenses (5xxx).
   - Posted journals are strictly immutable. Corrections utilize append-only reversing journal entries (`reversal_of_journal_entry_id`).
3. **Expense Invariant Correction**:
   - *Previous Overly Strict Assumption*: Every Expense produces exactly one CashMovement.
   - *Corrected Rule*: An expense paid directly from company funds produces an immediate `CashMovement`. Expenses paid personally by founders, unpaid recurring obligations, and accruals post correctly to general ledger expense accounts with offsetting liabilities (Founder Loan / Accounts Payable), producing a `CashMovement` only upon cash settlement or reimbursement.
4. **Opening Balances Rule**:
   - The system shall **never auto-manufacture historical journal entries** from incomplete legacy database records.
   - Accounting opening balances may only be posted as of an approved cutover date from verified, reconciled, and explicitly approved opening balance schedules.
5. **Jurisdictional Boundary**: While configurable for regional requirements (including standard Jordanian tax rates and JOD currency precision), specific statutory filings remain subject to local professional accounting validation.

---

## SaaS Metrics Architecture
1. **Event-Sourced Foundations**: SaaS metrics are derived strictly from normalized `SubscriptionMetricEvent` records generated on subscription lifecycle changes.
2. **MRR Derivation**:
   $$\text{Normalized ARR Minor} = \begin{cases} 
   \text{Monthly Price Minor} \times 12 & (\text{Monthly Interval}) \\
   \text{Annual Base Minor} + \text{Branch Tiers Minor} & (\text{Annual Interval})
   \end{cases}$$
   $$\text{Normalized MRR Minor} = \lfloor \text{Normalized ARR Minor} / 12 \rfloor$$
3. **Movement Equations**:
   $$\text{Ending ARR} = \text{Starting ARR} + \text{New} + \text{Expansion} + \text{Reactivation} - \text{Contraction} - \text{Churn}$$
4. **Strict Isolation**: Setup fees, hardware charges, custom software invoices, and annual cash installments never contaminate MRR or ARR calculations.

---

## Financial Source of Truth Matrix

| Subject | Authoritative Truth | Secondary Source | Reconciliation Service | Primary Invariant |
| :--- | :--- | :--- | :--- | :--- |
| **Commercial Price** | `PlanPrice` model | `PlanPriceService` | Effective date validator | Zero overlapping active dates for same plan |
| **Subscription State** | `Subscription` + `SubscriptionBillingPeriod` | `SubscriptionEvent` | `SubscriptionBillingService` | Active billing period matches real-time date |
| **Accounts Receivable**| `ReceivableService` projection derived from Invoice + PaymentAllocations + Credit/CreditNote effects | GL Account `1100` (Secondary Control) | `AccountingReconciliationService` | ReceivableService/subledger outstanding must reconcile to GL Accounts Receivable; stored `Invoice.balance` or `amount_paid` is a persisted cache/projection only, never an independent source of truth |
| **Cash on Hand** | `CashMovement` subledger | `FinancialAccount` balance | `AccountingReconciliationService` | Subledger cash == GL Account 1010/1020 |
| **Recognized Revenue** | GL Accounts `4010` & `4020` | `RevenueRecognitionPeriod` | `RevenueRecognitionService` | Revenue amortized monthly; Cash != Revenue |
| **Deferred Revenue** | `RevenueRecognitionSchedule` unposted | GL Account `2100` | `RevenueRecognitionService` | Unamortized annual prepaid held in liability |
| **Operating Expense** | `Expense` (V2 model) | GL Account `5010` | `OperatingExpenseService` | Matches company cash or founder liability |
| **Founder Loan** | `CapitalFundingTransaction` (loan) | GL Account `2200` | `CapitalManagementService` | Loan repayment != Operating expense |
| **Fixed Assets** | `FixedAsset` register | GL Account `1200` | `CapitalManagementService` | Equipment purchases capitalized as assets |
| **SaaS MRR / ARR** | `SubscriptionMetricEvent` | `SaasMetricsService` | `SaasMetricsReconciliationService` | Cash timing and setup fees completely excluded |

---

## UI/UX Design System Rules
1. **Responsive Viewport Contracts**:
   - **Primary Mobile Contract**: 390px viewport width (standard modern phone).
   - **Tablet Contract**: 768px viewport width.
   - **Desktop Contract**: 1440px viewport width with constrained, readable content max-widths (1280px container).
2. **Action Hierarchy**:
   - Exactly **one visually dominant Primary Action** per screen or card.
   - Secondary actions styled neutrally (soft/ghost variants).
   - Dangerous, rare, or destructive actions (Close Client, Void Contract, Reopen Period) placed inside overflow menus or confirmed via modal dialogs.
3. **Typography & Locales**:
   - Full native **RTL layout for Arabic** using Cairo typography.
   - Full native **LTR layout for English** using Inter typography.
   - Directionality set via HTML `dir="rtl"` / `dir="ltr"` and CSS logical properties (`margin-inline-start`, `padding-inline-end`).
4. **Number Presentation & Semantics**:
   - User-facing values display standard JOD currency formatting (e.g. `250.000 JOD` or `250 د.أ`).
   - Minor units (fils) and basis points (BPS) are strictly converted before display; zero `*_minor` or `*_bps` labels visible to users.
   - Semantic color coding applied with restraint: Green (Reconciled/Positive), Red (Overdue/Exception), Orange (Attention needed), Neutral Blue (Informational).
5. **No Technical Leakage**: Zero raw database enums, zero snake_case field names, zero raw Laravel validation attributes visible in production UI.

---

## Current Code vs Final Architecture Conflict Matrix

| Target Area | Current Implementation | Target Architecture | Alignment | Conflict | Files / Services | Data Risk | Required Action | Phase |
| :--- | :--- | :--- | :---: | :--- | :--- | :---: | :--- | :---: |
| **Financial Safety Order** | Idempotency and delete guards planned late | Safety hardening executed in Phase 1 | **CONFLICT** | Critical P0 risks remain open during UI refactoring | Controllers, Migrations | High | Move Safety & Idempotency to Phase 1 | Phase 1 |
| **Today Dashboard** | Unified projection active; runs legacy float queries | Shared board; zero financial queries | **PARTIAL** | DashboardController executes unindexed legacy float sums | `DashboardController.php`, `dashboard.blade.php` | Low | Remove legacy query block from daily mode | Phase 2 |
| **Daily Notes** | Table exists; hidden span in Blade; no UI editor | Embedded autosaving editor on Today | **PARTIAL** | Working backend exists but frontend editor missing | `DailyNoteController.php`, `dashboard.blade.php` | None | Add Alpine.js debounced autosaving editor | Phase 2 |
| **Navigation** | 3 layers, 6 groups, advanced engine visible | 4 clean areas; role-aware visibility | **PARTIAL** | Fragmented destinations, advanced engine exposed | `app-shell.blade.php`, `mobile-nav.blade.php` | None | Rebuild shell with role-aware 4-area nav | Phase 2 |
| **Client Workspace** | Command card present; embeds mini-ERP forms | Command card + summaries; ERP forms removed | **ALIGNED** | Collapsed section contains duplicate raw tables | `clients/show.blade.php`, `workspace/*.blade.php` | None | Streamline collapsed section; remove raw forms | Phase 3 |
| **Subscriptions** | Guided modal active; legacy route exists | Guided modal exclusive path | **PARTIAL** | Direct POST to BillingController throws raw validation | `BillingController.php`, `GuidedSubscriptionController.php` | Medium | Wrap BillingController in safe adapter | Phase 4 |
| **Payments** | Payment modal active; legacy route exists | Unified payment dialog with auto-account | **ALIGNED** | Legacy `DashboardController@storePayment` still active | `DashboardController.php`, `CollectionsController.php` | High | Deprecate legacy route; log warnings | Phase 4 |
| **Finance Cockpit** | 6 separate pages; dense tables expanded | Unified Finance Cockpit; numbers first | **CONFLICT** | Extreme table dominance and cognitive load | `finance/*.blade.php`, `collections/*.blade.php` | None | Consolidate under `/finance` with progressive tabs | Phase 5 |
| **Administration** | Catalog, Partners, Conflicts scattered | Unified Administration domain | **PARTIAL** | No single admin landing container | `commercial-catalog/*.blade.php`, `settings/*.blade.php` | None | Create unified Administration container | Phase 6 |
| **Custom Solutions** | One-time invoice lines only; no project entity | Lightweight project entity candidate | **PARTIAL** | No project milestone or contract value tracking | `BillingController.php`, `InvoiceService.php` | None | Define CustomProject candidate entity | Phase 7 |

---

## Components to Preserve, Refactor, and Hide

### Components to Preserve (Do Not Rebuild)
- `App\Support\Money`: Integer fils arithmetic value object.
- `App\Services\SubscriptionBillingService`: Recurring billing engine, renewals, and installment schedules.
- `App\Services\CommercialPricingService` & `PlanPriceService`: Catalog price resolution and tax derivation.
- `App\Services\JournalPostingService` & `BillingAccountingService`: Double-entry journal balancer and immutable ledger.
- `App\Services\ReceivableService` & `PaymentAllocationService`: Oldest-first auto-allocation and receivable aging.
- `App\Services\RevenueRecognitionService`: Deferred revenue schedule creator and monthly recognizer.
- `App\Services\SaasMetricsService` & `SaasMetricEventService`: Event-sourced SaaS metrics engine.
- `App\Services\FreeInstallationService`: Free trial installation lifecycle linker.
- `App\Services\ContractService` & `ContractPdfService`: Contract snapshotting and DomPDF generation.
- `App\Support\FinancialPermissions`: Role-based security boundaries.

### Components to Refactor
- `App\Http\Controllers\DashboardController`: Remove legacy financial float queries and decouple Today from legacy code.
- `App\Services\UnifiedOperationalWorkProjection`: Correct translation key paths for `record_outcome` and `open_client`.
- `resources/views/dashboard.blade.php`: Add Alpine.js Daily Notes autosaving editor; remove hidden compatibility spans once tests refactored.
- `resources/views/clients/show.blade.php`: Clean out legacy mini-ERP tab remnants from the collapsed accordion.
- `resources/views/finance/index.blade.php` & `collections/index.blade.php`: Implement progressive disclosure with KPI cards and on-demand tables.

### Components to Hide from Normal UX
- Full Chart of Accounts table (relegated to Finance $\to$ Advanced $\to$ Accounting).
- Journal Entry raw lines table (relegated to Finance $\to$ Advanced $\to$ General Ledger).
- Cash Movements audit subledger (relegated to Finance $\to$ Advanced $\to$ Cash Subledger).
- Deprecated financial settings (commission rate, partner share percentage, revenue formula).

---

## Required Backend Hardening & Database Plan

### Backend Hardening (Phase 1 Priority)
1. **HTTP Idempotency Protection**: Implement database-backed atomic request locking on irreversible financial endpoints (`POST payments`, `POST subscriptions`, `POST expenses`, `POST capital`).
2. **Financial History Delete Protection**: Restrict client deletion via policy guards; prohibit hard deletion of clients with active invoices, subscriptions, or payments. Enforce soft archiving.
3. **Validation Language Mapping**: Map `plan_price_id`, `product_id`, `billing_interval`, and `payment_terms` to human-readable Arabic and English names in `lang/{locale}/validation.php`.
4. **Translation Key Fixes**: Alias missing action keys in `lang/ar/notify.php` and `lang/en/notify.php` under `actions.record_outcome` and `actions.open_client`.

### Database Changes Plan (Additive Only)
- **Zero Destructive Migrations**: No existing tables, columns, or foreign keys shall be dropped.
- **Additive Idempotency Table (Phase 1)**:
  - Table: `idempotency_keys` (`id`, `key` VARCHAR(64) UNIQUE, `request_hash`, `response_code`, `response_body` LONGTEXT, `created_at`).
- **Custom Project Entity Candidate (Phase 7/8)**:
  - Table: `custom_projects` (`id`, `client_id`, `name`, `agreed_value_minor`, `start_date`, `target_completion_date`, `status`, `created_at`, `updated_at`).

---

## Data & Accounting Cutover Strategy
1. **Historical Ledger Preservation**: All existing posted journal entries, invoices, and payment records remain permanently intact.
2. **No Invented Historical Journals**: The system shall never auto-manufacture journal entries for incomplete legacy records.
3. **Opening Balance Confirmation**: If historical accounts lack initial ledger postings, a one-time script posts approved, balanced opening journal entries for Cash on Hand and Accounts Receivable as of the formal cutover date.
4. **Subledger Verification**: Run `AccountingReconciliationService` prior to closing cutover to ensure subledger cash equals GL cash, and subledger AR equals GL AR.

---

## Test Strategy & Migration Roadmap

### Test Classification
- **Preserve Unchanged (320+ tests)**: All financial formulas, Money arithmetic, journal balancing, SaaS metrics, and permission unit tests.
- **Refactor from DOM Assertions to Behavioral Assertions (~25 tests)**:
  - `Tests\Feature\Phase1VerificationTest`: Replace assertions checking for hidden Arabic strings with assertions verifying route response codes and model state.
- **New Required Test Families**:
  1. `FinancialIdempotencyTest`: Verify double-submit rejection on payments and subscriptions.
  2. `DailyNotesAutosaveTest`: Verify note loading, debounced saving, and user isolation.
  3. `RoleAwareNavigationTest`: Verify 4-area navigation and role-based visibility.
  4. `ProgressiveDisclosureTest`: Verify management tables are collapsed/deferred by default.
  5. `LocalizationIntegrityTest`: Assert zero literal `notify.*` translation keys across all routes.

---

## Revised 10-Phase Recovery Roadmap

```
Phase 0: Final Architecture Freeze (Human Review & Owner Freeze Sign-off)
   │
Phase 1: Financial Safety, Legacy Isolation & Critical Blockers (P0 Priority)
   │     ├── HTTP financial idempotency table & middleware
   │     ├── Financial history delete protection & soft archiving
   │     ├── Translation key blockers & human validation mapping
   │     └── Legacy write route classification & safe adapters
   │
Phase 2: Final Shell, Today Board & Daily Notes
   │     ├── 4-area role-aware navigation & collapsible sidebar
   │     ├── Today shared operational board (All / My Work toggle)
   │     ├── Daily Notes autosaving editor (Asia/Amman, debounced)
   │     └── Decouple Today from legacy floating-point financial queries
   │
Phase 3: Client Workspace & Flexible Daily Operations
   │     ├── Streamline command card & non-linear workflows (optional calls)
   │     ├── Direct Free Installation scheduling
   │     ├── Secondary Close Client action under More
   │     └── Remove mini-ERP tables and legacy write forms from workspace
   │
Phase 4: Installation, Subscription, Contract & Payment
   │     ├── Installation -> explicit Start Subscription conversion
   │     ├── Authoritative product context reuse
   │     ├── Guided subscription exclusive normal UX
   │     ├── Simplified Record Payment with auto-account resolver
   │     └── Automated contract artifact generation
   │
Phase 5: Finance Cockpit Consolidation
   │     ├── Consolidate under /finance (Overview, Collections, Expenses, Capital, Reports)
   │     ├── Numbers-first layout with distinct Accounting and SaaS metric blocks
   │     └── Progressive disclosure: tables collapsed by default or in detail drawers
   │
Phase 6: Administration Area Consolidation
   │     ├── Group Products/Pricing, Partners, Team, Imports, Conflicts, Settings
   │     └── Hide deprecated financial settings from normal views
   │
Phase 7: Advanced Accounting Engine UX Isolation
   │     ├── Isolate General Ledger, Journals, Cash Subledger into Finance -> Advanced
   │     └── Introduce lightweight CustomProject entity candidate
   │
Phase 8: Accounting Cutover & Legacy Historical Isolation
   │     ├── Permanently disable unhedged legacy write endpoints
   │     ├── Verify 1:1 subledger to GL reconciliation
   │     └── Establish approved opening balances where required
   │
Phase 9: Full End-to-End Verification & Production Freeze
         ├── Full test suite regression (402+ tests)
         ├── Responsive QA (390px, 768px, 1440px)
         ├── Localization verification (Zero literal keys)
         └── Final owner sign-off and production release freeze
```

---

## Phase-by-Phase Acceptance Gates

- **Phase 0 Gate**: Written owner approval of this revised architecture document.
- **Phase 1 Gate**:
  - Duplicate financial HTTP request cannot create duplicate business transactions.
  - No normal UI invokes unprotected legacy financial writes.
  - Client financial history cannot be destroyed by normal hard deletion.
  - Zero literal translation keys in production.
  - Zero technical `plan_price_id` validation messages in normal UX.
- **Phase 2 Gate**:
  - Today is the primary landing experience.
  - Shared operational board loads with zero legacy float queries.
  - All / My Work filter functions cleanly.
  - Daily Notes load automatically, auto-save with debounce, and report Saving/Saved/Error states.
  - Browser refresh preserves Daily Notes content.
- **Phase 3 Gate**:
  - Prospect may schedule Free Installation directly from workspace.
  - Calling is optional.
  - Closing client is secondary under More and preserves all records.
  - Zero legacy financial write forms inside Client Workspace.
- **Phase 4 Gate**:
  - Prospect may Start Subscription directly when authorized.
  - Completed installation provides explicit Start Subscription action.
  - Product context reused only when authoritatively mapped.
  - Payment asks only human business inputs and auto-allocates.
- **Phase 5 Gate**:
  - Finance opens numbers-first with distinct Accounting and SaaS blocks.
  - Zero multi-table visual overload on initial render.
- **Phase 6 Gate**:
  - Administration sub-modules accessible from single unified domain.
  - Deprecated settings hidden from normal view.
- **Phase 7 Gate**:
  - Advanced accounting tools accessible only within Finance Advanced tab.
  - Custom project records track non-recurring value without polluting SaaS MRR.
- **Phase 8 Gate**:
  - All unhedged legacy write routes return 410 or safe redirects.
  - Subledger Cash equals GL Cash; Subledger AR equals GL AR.
- **Phase 9 Gate**:
  - All automated tests pass (402+ tests, 0 failures, 0 skipped).
  - Clean responsive performance verified on 390px, 768px, and 1440px viewports.
  - Full manual real-world journey completed successfully by owner.

---

## Risk & Rollback Strategy

| Risk | Likelihood | Impact | Mitigation Strategy | Rollback Plan |
| :--- | :---: | :---: | :--- | :--- |
| **Legacy Route Callers** | Medium | Medium | Maintain safe adapters during transition phases; log all legacy invocations | Re-enable legacy controller methods from git history |
| **Test Failures from DOM Updates**| High | Low | Refactor DOM text tests to behavioral tests before updating Blade views | Retain hidden compatibility spans until test suite refactored |
| **Financial Re-posting Duplicate** | Low | High | Enforce atomic request locking and database uniqueness constraints | Database compensating reversal entries |
| **Mobile Nav Touch Congestion** | Low | Low | Adhere strictly to 4-slot bottom bar for finance/admin and 3-slot for staff with 48px touch targets | Revert mobile navigation template |

---

## Deferred Features (Explicitly Out of Scope)
1. **Multi-Currency**: System remains exclusively denominated in Jordanian Dinar (JOD) with 3-decimal fils precision.
2. **Heavy Project ERP**: Gantt charts, employee timesheets, percentage-of-completion accounting, and complex WIP cost tracking are explicitly deferred.
3. **Automated Bank Feeds**: Cash subledger remains manually logged or CSV-imported.
4. **Self-Service Client Portal**: External client authentication remains deferred.

---

## Owner Decisions Summary

### Owner Decisions Accepted in Candidate (All Decisions Finalized)
- [x] Today is the primary home screen.
- [x] Work is inside Today (an internal mode/filter of Today for every role, never a top-level destination).
- [x] Today is a shared operational board with All / My Work filter.
- [x] Daily Notes are personal per user/date and auto-save.
- [x] Call logging is optional.
- [x] Appointments are optional.
- [x] Free Installation can be scheduled directly.
- [x] Paid Subscription can start directly for an authorized user.
- [x] Completed installation exposes explicit Start Subscription.
- [x] Close Client is secondary under More.
- [x] Finance is one conceptual management area.
- [x] Administration is one conceptual governance area.
- [x] Advanced accounting remains subordinate under Finance.
- [x] Large tables use progressive disclosure.
- [x] Accounting core is preserved.
- [x] SaaS metrics remain separate from accounting revenue.
- [x] Financial Safety is the first implementation phase.
- [x] Legacy financial writes must be isolated safely.
- [x] CustomProject is part of the final architecture but deferred to Phase 7.

---

## Architecture Freeze Checklist
- [x] Repository baseline re-verified against active code (`codex/human-operations-simplification` at `3b5dccf`).
- [x] Existing `daily_notes` data model verified (personal per user/date, unique constraint on `user_id, date`).
- [x] One-time invoice capability verified as non-SaaS revenue.
- [x] Financial Safety moved to Phase 1 of roadmap.
- [x] Legacy financial routes classified with explicit isolation policies.
- [x] Role-aware navigation visibility specified for Staff, Finance, and Admin (Staff mobile: Today, Clients, More).
- [x] Client Workspace de-cluttered of mini-ERP tables.
- [x] Installation product context and input contracts clarified (Date & Time normal inputs).
- [x] Start subscription input contracts clarified (Product when unknown, Plan, Interval; Start Date defaults to Today).
- [x] Receivable source of truth defined as ReceivableService projection reconciled to GL AR.
- [x] CustomProject officially incorporated and deferred to Phase 7.
- [x] Accounting language corrected to recognized accounting revenue and auditable policy.
- [x] Expense invariant corrected for non-cash settlement flows.
- [x] Zero unresolved pending owner decisions remaining.
- [x] **Final Owner Freeze Sign-off**: `FINAL_ARCHITECTURE_FROZEN`.
