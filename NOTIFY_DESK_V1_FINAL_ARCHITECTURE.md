# Notify Desk V1 — Final Architecture (Implementation Contract)

> **Status: FROZEN FOR IMPLEMENTATION**
> **Authority:** This document is the single authoritative V1 implementation contract. Where any other document, comment, test name or prior phase report conflicts with it, this document wins.
> **Baseline:** branch `codex/human-operations-simplification`, commit `bbcb6cadf3824d33aa491cb5d4a1773b15e0974e`. Audited 2026-09-23. Owner decisions frozen 2026-09-23.
> **Implementation:** not started. Authorization to implement is given separately.
> **Owner amendment D-25 (2026-09-25, P13.1) — supersedes the earlier Founder/Admin/Staff active-role model:** V1 has exactly **two active roles: Founder and Staff**. **Admin is a deferred post‑V1 role**: not creatable, not assignable, not owner‑level, and a user still carrying `admin` has no access. Wherever this document says *Owner‑level*, *Owner/Admin*, *Founder/Admin* or lists `admin`, read **Founder**. Staff permissions are unchanged. Final state — **active roles:** Founder + Staff; **database default** for `users.role`: `staff`; **dormant future‑compatible value:** `admin`. Do not reintroduce Admin in V1.
> **Superseded documents (reference only, never implementation sources):** `NOTIFY_DESK_FINAL_ARCHITECTURE_FREEZE_V2.md`, `docs/backend/V1_FINAL_ARCHITECTURE.md`, `docs/architecture/V1_HUMAN_OPERATIONS_*.md`, `docs/PARTNER_ONBOARDING_AR.md`.

**Product principle.** *The human confirms real-world events. Notify Desk automatically performs the resulting operational, financial and accounting consequences.*

**Priority rule.** Domain and financial behavior first → UX structure → visual polish.

**Reading rules for implementation agents.**
- "MUST" = required for V1 acceptance. "MUST NOT" = prohibited. Anything not stated here is out of scope; do not invent business rules. If a genuine gap is found, stop and raise it, do not guess.
- `[FROZEN D-xx]` marks an owner decision. Those are not open for reinterpretation.
- Money is always integer fils (`*_minor`, 1 JOD = 1000 fils). JOD strings are converted only at input/output boundaries via `App\Support\Money`.

---

## 0. Audit findings (baseline state)

Verified by reading code at the baseline commit.

| # | Finding | Evidence |
|---|---|---|
| F-01 | Fresh install cannot record any payment. No migration or seeder creates a `FinancialAccount`; the resolver throws `لا يوجد حساب مالي مهيأ لطريقة الدفع المحددة`. | `database/seeders/*`, `app/Services/PaymentFinancialAccountResolver.php` |
| F-02 | Method→account resolution is by account **type** and is ambiguous (Bank+CliQ → `bank`; Zain/Orange/E‑Wallet → `wallet`). | `PaymentFinancialAccountResolver::METHOD_ACCOUNT_TYPES` |
| F-03 | Payment sheet offers 7 methods regardless of configuration. | `clients/workspace/actions/record-payment.blade.php`, `App\Support\PaymentMethods` |
| F-04 | The authoritative payment pipeline exists and is correct: `CollectionsController::storeNormalPayment` → `PaymentAllocationService::recordV2Payment` → `Payment` (`amount_minor`) → `CashMovementService::recordPaymentReceipt` → `AccountingEventPostingService::postCashMovement`; auto‑allocation → `BillingAccountingService::postPaymentAllocation`; `financial.idempotency` middleware. | files named |
| F-05 | All 39 `FinancialPermissions` resolve to `isAdmin()`; Staff cannot record payments, start subscriptions, or grant access. | `App\Support\FinancialPermissions::allows` |
| F-06 | `CsvImportController` has no authorization. | file |
| F-07 | Finance is one query‑string cockpit (`/finance?section=`); "Reports" is CSV cards only; every tab computes all 12 reports + SaaS + reconciliation. | `FinanceReportController`, `resources/views/finance/**` |
| F-08 | Orphan/overlapping pages: `/subscription-billing` (unlinked), `/executive` (linked only from `/saas-metrics`), `/custom-projects` (no nav), MRR in 3 places, collections summary twice. | route/link grep |
| F-09 | Arabic UI uses "دفتر الأستاذ". | `lang/ar/notify.php` |
| F-10 | Capital + fixed assets always visible; no feature‑flag mechanism; no owner‑distribution type; expenses support `FUNDING_PERSONAL`. | `CapitalManagementController`, `CapitalFundingTransaction::TYPES`, `OperatingExpenseService` |
| F-11 | Operations settings (`appointment_duration`, `free_installation_duration`, `post_install_followup_days`, `workday_start/end`) and `company_logo` are stored but never read. | grep |
| F-12 | Contract: actions in 3 workspace places incl. 2 Print buttons; `contracts.print` and HTML `contracts.download` routes; witness block; "دفعة سنوية واحدة" printed for **monthly** subscriptions with `payment_terms=full`; dompdf without Arabic font/shaping; filename uses legacy `snapshot.product`; number assigned at draft; `contracts.contract_number` is `NOT NULL UNIQUE`. | `contracts/template.blade.php`, `ContractPdfService`, `ContractService`, contracts migration |
| F-13 | Clients list: 8‑stage dropdown; queries legacy `status` too. Nothing changes `stage` when a subscription ends (client stays `subscriber`). | `ClientController::index`, `ClientOperationalWorkflowService` |
| F-14 | Lead sources hard‑coded twice with different values; category/city free text. | `ClientController::leadSourceOptions`, `ClientLifecycle::SOURCE_TYPES` |
| F-15 | Referral columns exist but are not rendered in the workspace. | migration `2026_09_22_120000`, `clients/show.blade.php` |
| F-16 | Profile = name, email, password only; `users` lacks phone/job title/photo. | `ProfileController` |
| F-17 | No client credential storage exists; `products` has no capability flags. | grep, products migration |
| F-18 | Dark mode = one token block + head script + toggle + one test assertion; ~252 hard‑coded hex colors and ~423 inline `style=` in views. | `app.css` L56, `app-shell.blade.php`, `ApplicationShellNavigationTest` |
| F-19 | Phone bottom nav + focus‑trapped More sheet exist; breakpoints ad hoc (390/640/760/768/900/960/1024/1440). | `x-notify.mobile-nav`, `app.css` |
| F-20 | Workspace (586 lines) duplicates info via 4 legacy collapsible partials and embeds Custom Projects. | `clients/show.blade.php` |
| F-21 | New subscriptions have `plan_id/plan_price_id = null`; legacy plan paths remain for history. | `SubscriptionBillingService` |
| F-22 | Scheduler runs reminders, recurring expenses, revenue recognition, renewals, idempotency prune. | `routes/console.php` |
| F-23 | `client_contacts` exists (`name` NOT NULL, `role`, `primary_phone`, `secondary_phone`, `whatsapp_number`, `preferred_contact_method`, `is_primary`; no email). `clients` has `phone` (required, indexed) and `business_phone`. | migration `2026_09_14_000100` |
| F-24 | `custom_projects.client_id` is NOT NULL (`restrictOnDelete`). | migration `2026_09_22_100000` |
| F-25 | Hard‑coded Arabic strings in controllers/services; `PaymentMethods::labels()` concatenates AR+EN. | files named |
| F-26 | Stack: Laravel 11, PHP 8.2, Livewire 3, Alpine 3, Tailwind, dompdf 3; tests SQLite in‑memory (52 feature files); production MySQL 8. | `composer.json`, `phpunit.xml`, `docker-compose.yml` |

**Already satisfies V1 (reuse, do not rebuild):** agreed‑value subscriptions in fils; `subscription_system` snapshots; `client_system` free/paid access; contract snapshot model and PDF route; custom projects outside MRR/ARR; accounting engine (cash movements, events, journals, periods, revenue recognition, reconciliation, statements); SaaS metric events; idempotency; scheduler; bottom nav + More sheet; referral columns; Partner/Conflict removal; close‑instead‑of‑delete; `client_contacts`; Settings skeleton.

---

## Frozen owner decisions register

| ID | Frozen decision | Where applied |
|---|---|---|
| D-01 | Automatic bootstrap of **CASH-BOX** and **CLIQ** company accounts on `migrate:fresh --seed` and first production install; no owner setup before first use; never lazily created in a request. | §10 |
| D-02 | V1 payment methods are fixed: **cash**, **cliq**. No mapping UI. Cash→CASH-BOX, CliQ→CLIQ internally. Users see payment method only. | §9, §10 |
| D-03 | Ending the last subscription does **not** close the client; client moves to **former_subscriber** (Renewal / Former Subscriber). Default Clients view: Staff = Prospects; Owner‑level = Subscribers. | §4, §5 |
| D-04 | Dark Mode removed completely; tokenized Light theme retained. | §26 |
| D-05 | Capital gate = UI hiding **and** route‑level enforcement. | §13 |
| D-06 | Staff may start paid subscriptions and grant/revoke free System Access; Owner‑level users get an internal notification when Staff starts a paid subscription. | §2, §7 |
| D-07 | Staff sees operational collections only (amounts due, own pending receipts); no P&L, balances, accounting, reports, capital. | §2, §9 |
| D-08 | Referral commission % editable only by Founder/Admin. | §2, §19 |
| D-09 | Staff and Owner may reveal/copy/send credentials; every action audited; send = WhatsApp click‑to‑chat + copy; encrypted at rest; never in logs or ordinary HTML. | §18 |
| D-10 | Contracts start as **Draft**; official number assigned only at **Issue**; Issue is Owner/Admin‑only; issued PDF is the official printable contract for manual signatures; no e‑signature. | §8 |
| D-11 | Professional Arabic PDF is a release requirement; switch to **mPDF** if the spike confirms dompdf is inadequate. | §8.5 |
| D-12 | Staff cannot record company expenses. | §2, §12 |
| D-13 | Staff cannot import clients. | §2, §15 |
| D-14 | Custom Projects is a separate top‑level area; not embedded in Client Workspace; optional client link produces a Client Activity event only; Staff read‑only; Owner/Admin manages. | §3, §19, §20a |
| D-15 | Staff‑reported receipts: `received_at` ≤ now and ≥ 7 days back; Owner/Admin approval required before it becomes a Payment. | §9 |
| D-16 | Executive Dashboard concepts folded into Finance Overview. | §12.1 |
| D-17 | Normal V1 expense UX is company‑paid only; personal‑paid remains engine‑only. | §12.3 |
| D-18 | Owner distributions out of V1. | §13, §34 |
| D-19 | Report export = CSV UTF‑8 with BOM. | §14 |
| D-20 | Cities/areas: reference suggestions + free text allowed. | §15.1 |
| D-21 | Latin digits in both AR and EN. | §25 |
| D-22 | Production launches on a fresh database. Real clients are imported only on production after deployment; never into dev/local; not part of seed data. | §31, §33a |
| D-23 | Minimal Playwright smoke tests for overflow/navigation at critical viewports. | §32 |
| D-24 | Finance split approved as specified in §12. | §12 |
| D-25 | **V1 active roles are Founder and Staff only** (owner decision 2026‑09‑25, P13.1; supersedes the Founder/Admin/Staff model). Owner‑level = Founder. Admin is deferred post‑V1: never creatable/assignable, never owner‑level, no access if still present; existing `admin` rows become Staff unless the repository proves an intentional Founder account. Team creation creates Staff only; promotion to Founder is Founder‑only. | §2, §3, §15 |

**Owner decisions remaining: 0.**

---

## 1. V1 product boundary

**In scope:** internal CRM (clients, business details, primary contact, calls, appointments, installations, follow‑ups, notes); Systems catalog with credential capability; client System Access (free/paid); agreed‑value paid subscriptions (monthly / annual full / annual installments); contracts (Draft → Issue → PDF); customer payment receipts with Staff→Owner approval; collections; Custom Projects (top‑level, one‑time invoices); company‑paid expenses (one‑time and monthly recurring); Company Accounts (Cash Box, CliQ) with internal transfers; automatic accounting; financial reports with CSV export; SaaS metrics; referral metadata; client system credentials; team/roles; operational reference data; settings; AR/EN; phone/iPad/desktop.

**Feature‑gated (default OFF):** Capital & Financing — capital contributions, founder loans, external investment/financing.

**Not in V1 surface (engine code may remain):** fixed assets/acquisition/asset categories, personal‑paid expenses, owner distributions, credit‑note/refund UI beyond existing owner corrections (retained as owner‑only secondary actions), bank transfer/Zain Cash/Orange Money/E‑Wallet/Other payment methods.

---

## 2. User roles and permissions

### 2.1 Roles
`founder` (**Owner‑level**) and `staff` — the only active V1 roles [D-25]. `admin` is a deferred post‑V1 value: not active, not owner‑level, not creatable or assignable. `employee` constant unused. No partner role. Only a Founder may promote to Founder, change a Founder's role, deactivate/reactivate a Founder or reset a Founder's password. Ordinary team creation creates Staff only. The `users.role` database default is `staff`.

**Principle:** Staff operates the company; Staff cannot change the company's rules or create authoritative money records.

### 2.2 Mechanism
- New `App\Support\Permissions`: all permission constants + `ROLE_MATRIX` (`permission => roles[]`). `allows(user, perm)` = user active AND role in matrix.
- `FinancialPermissions` stays as an alias (same string values) until every call‑site is migrated, then is deleted in the same phase.
- `AppServiceProvider` keeps the single `Gate::define` loop over `Permissions::ALL`.
- Matrix is code, not database. No role editor.

### 2.3 Matrix (final)

| Capability | Permission | Owner | Staff |
|---|---|:-:|:-:|
| View/create/edit clients, business details, contacts | `ClientPolicy` | ✅ | ✅ |
| Close / reopen client | `ClientPolicy::update` | ✅ | ✅ |
| Calls, appointments, results, installations, follow‑ups, notes | `ClientPolicy::update` | ✅ | ✅ |
| Grant/revoke free System Access | `manage_system_access` | ✅ | ✅ |
| Start paid subscription | `start_paid_subscription` | ✅ | ✅ (owners notified) |
| Cancel / undo cancellation | `manage_subscription_lifecycle` | ✅ | ❌ |
| Preview contract / download PDF | `ContractPolicy::view/download` | ✅ | ✅ |
| Issue / void / supersede contract | `issue_contracts` | ✅ | ❌ |
| Submit payment receipt for approval | `submit_payment_receipt` | ✅* | ✅ |
| Record confirmed payment directly | `record_payment` | ✅ | ❌ |
| Approve / reject payment receipts | `approve_payment_receipts` | ✅ | ❌ |
| View collections due (operational) | `view_collections_due` | ✅ | ✅ |
| Corrections: manual allocation, reversal, refund, credit notes | existing correction permissions | ✅ | ❌ |
| Referral name/note | `ClientPolicy::update` | ✅ | ✅ |
| Referral commission % | `edit_referral_commission` | ✅ | ❌ (field ignored server‑side) |
| Create/edit credentials | `manage_client_credentials` | ✅ | ✅ |
| Reveal/copy/send credentials | `reveal_client_credentials` | ✅ | ✅ |
| Custom Projects: view | `view_custom_projects` | ✅ | ✅ (read‑only) |
| Custom Projects: create/edit/archive/invoice | `manage_custom_projects` | ✅ | ❌ |
| Expenses (one‑time, recurring, categories) | `manage_expenses` | ✅ | ❌ |
| Company Accounts, internal transfers | `view_cash_management`, `manage_cash_transfers` | ✅ | ❌ |
| Accounting & Entries, maintenance tools | existing accounting permissions | ✅ | ❌ |
| Finance Overview, Financial Reports, export, SaaS metrics | `view_financial_statements`, `export_financial_reports`, `view_saas_metrics` | ✅ | ❌ |
| Capital & Financing (when ON) | existing capital permissions | ✅ | ❌ |
| Systems catalog | `manage_commercial_catalog` | ✅ | ❌ |
| Team & Roles | `manage_team` | ✅ | ❌ |
| Reference data | `manage_reference_data` | ✅ | ❌ |
| Import clients | `import_clients` | ✅ | ❌ |
| Settings | `manage_company_settings` | ✅ | ❌ |

\* Owner‑level users use the direct path (§9.3); the receipt path is Staff's.

---

## 3. Navigation architecture by role and device

### 3.1 Desktop and iPad‑landscape sidebar — Owner‑level (full, not minimal)
```
Today
Clients
Custom Projects
Finance
  Overview                    /finance
  Collections & Receivables   /finance/collections
  Expenses                    /finance/expenses
  Company Accounts            /finance/accounts
  Accounting & Entries        /finance/accounting
  Financial Reports           /finance/reports
  Capital & Financing         /finance/capital        (only when feature ON)
Administration
  Systems                     /administration/systems
  Team & Roles                /administration/team
  Operational Reference Data  /administration/reference-data
  Import                      /administration/import
  Settings                    /administration/settings
```
Groups are expanded sections (not hidden sub‑menus). Badge counts: Collections (pending receipts for approval).

### 3.2 Sidebar — Staff
`Today` · `Clients` · `Collections due` (`/collections-due`, operational list) · `Custom Projects` (read‑only).

### 3.3 Header (all devices)
`AR/EN` · `Notifications` (unread count) · `Profile menu` (Profile, Change password, Logout). No theme control. "Add client" lives on the Clients page and Today quick actions, not the header.

### 3.4 Phone bottom navigation (the sidebar is never copied to phone)
- Owner‑level: **Today · Clients · Finance · More**. Finance tab opens Finance Overview; section switching via a horizontally scrollable segmented control at the top of Finance pages.
- Staff: **Today · Clients · Collections · More**.
- More sheet: Notifications, Custom Projects, Administration (owner), Profile, Change password, Language, Logout.

---

## 4. Client lifecycle

Authoritative field: `clients.stage`. Stages (final):
`prospect`, `contacting`, `appointment`, `installation_scheduled`, `installed_free`, `decision_pending`, `subscriber`, **`former_subscriber` (new)**, `closed`.

Labels: `former_subscriber` = "بانتظار التجديد / مشترك سابق" / "Renewal / Former Subscriber".

Automatic transitions (system‑performed):

| Event | New stage |
|---|---|
| Call recorded with appointment outcome | `appointment` (if not subscriber/former/closed) |
| Installation scheduled / completed | `installation_scheduled` / `installed_free` (same guard) |
| Paid subscription created (any stage incl. `former_subscriber`, `closed` → reopen implied) | `subscriber` |
| Last active paid subscription ends or cancellation takes effect | `former_subscriber` [FROZEN D-03] |
| Renewal of a `former_subscriber` (new paid subscription) | `subscriber` |

Manual: **Close** (reason required; any stage) and **Reopen** (`closed` → `prospect`, or → `former_subscriber` if the client has any past paid subscription). Closing never cancels subscriptions; closing a client with an active subscription is blocked with a message to cancel the subscription first (Owner).

Legacy `clients.status`: read‑only compatibility. A data migration copies meaningful `status` values into `stage` where `stage` is null. After Phase 4 no code reads or writes `status` for filtering.

The subscription‑end transition is implemented where cancellation/expiry takes effect in `SubscriptionBillingService` (and the renewal scheduler for non‑renewed expiries), in the same transaction, with an activity event.

---

## 5. Client segmentation

Same `Client` entity; views via `?view=` on `/clients`:

| View | Filter |
|---|---|
| Prospects / عملاء محتملون | `stage IN (prospect, contacting, appointment, installation_scheduled, installed_free, decision_pending)` |
| Subscribers / المشتركون | `stage = subscriber` |
| Renewal / Former Subscribers / بانتظار التجديد | `stage = former_subscriber` |
| Closed / مغلق | `stage = closed` |
| All / الكل | none |

Defaults [FROZEN D-03]: Staff → Prospects; Owner‑level → Subscribers. Prospects view offers stage chips as secondary filter. Row content — Prospect: name, category, city, stage, next action. Subscriber: name, systems, cycle, amount due, renewal date. Former: name, last systems, ended date, "Start subscription" action.

Consistency: nightly check via existing `ClientReviewItem` flags clients whose stage disagrees with active paid subscriptions; no silent auto‑fix.

---

## 6. Systems and client access

- `products` table/model = **System**. New column `requires_credentials` (bool, default false). Seed/data migration sets true for Smart Link, E‑Menu, E‑Store (by existing system codes) and false for Auto SMS and others; editable in Administration → Systems.
- `client_system` authoritative for access (`free|paid`, `granted_at`, `revoked_at`, `granted_by`).
- Free access: zero financial side effects.
- Paid subscription sets access `paid` for its systems. When the subscription ends, paid rows become revoked (`revoked_at`) unless the owner/staff grants free access explicitly. (Implementation must verify existing behavior and conform to this rule.)
- Staff may grant/revoke **free** access. Paid access follows subscription lifecycle only.

---

## 7. Subscription lifecycle

Input: Systems (≥1), billing cycle (monthly/annual, filtered by `allow_monthly`), agreed value (JOD→fils), start date, payment terms (annual only: full or installments N=2–12 with due day, gated by `allow_annual_installments`), optional note. Default cycle from `default_billing_cycle`.

Automatic (existing engine, unchanged): subscription (`agreed_value_minor`), `subscription_system` snapshots, billing period, first invoice, payment schedule, revenue schedule, SaaS metric event, paid access, stage → `subscriber`, Draft contract (if `auto_contract_on_paid_subscription`), activity event. **New:** when actor is Staff, notify all active Owner‑level users ("X started a paid subscription for Client Y — agreed value Z") via `NotificationService` with an occurrence key per subscription.

Renewals by scheduler copy agreed value + snapshots, idempotent per period. Packages/plan prices are never shown or selectable.

---

## 8. Contract lifecycle and document

### 8.1 States and flow [FROZEN D-10]
`draft` → (Owner **Issue**) → `issued` → optionally `voided` / `superseded` (Owner). Flow: Draft auto‑created → Preview → Issue → Download PDF → manual physical signatures. No e‑signature.

### 8.2 Rules
- Draft created automatically after paid conversion (`ContractService::ensureDraftContract`) with **no official number** (`contract_number = NULL`; UI shows "مسودة / Draft"). Requires schema change M‑7.
- **Issue** (Owner/Admin only): in one transaction (row lock): assign number `{contract_prefix}-{YYYY}-{NNNN}` (sequence per year, computed from issued contracts only; unique constraint retained), set `issued_at`, **re‑capture the company block from current Settings into the snapshot**, write `metadata.contract_number` and `metadata.issued_at`, then freeze. After Issue the snapshot is immutable; later Settings changes never affect it.
- Subscription/pricing data in the snapshot comes from the subscription snapshot at draft creation and is not re‑read at Issue.
- Draft PDF download is allowed and every page carries a visible "DRAFT / مسودة" watermark; issued PDFs have none.
- Legacy snapshots (with number already set) remain readable and render via a legacy fallback.
- Setting `initial_contract_state` is **not** introduced (drafts are mandatory).

### 8.3 Actions (exactly two, one place)
**Preview** (`contracts.preview`) and **Download PDF** (`contracts.download-pdf`), plus Owner‑only **Issue** on drafts. Rendered only in the Contract card of the Client Workspace.
Delete: `contracts.print` route + `ContractController::print`, HTML `contracts.download` route + action, all Print buttons, sticky action bar with `window.print()`, witness block.

### 8.4 Document design (`resources/views/contracts/document.blade.php`)
- A4 portrait; margins 18–20 mm; body 9.5–10 pt; headings 12/11 pt; one accent color; no dark bars, gradients or emoji.
- Sections: (1) header — company logo (if set), legal name AR/EN, contract no. (or DRAFT), issue date; (2) parties — Provider from snapshot `company.*`, Client from snapshot `client.*` (business name, business phone, primary contact name/phone if present, city/address); (3) subscription summary — systems, cycle, agreed value, start date, current period; (4) payment terms (dynamic, §8.4.1); (5) terms — `default_contract_terms` captured in snapshot, else the standard clause partial `contracts/clauses/v1.blade.php`; (6) signatures — **Notify** (authorized signatory name if set, signature line, date) and **Client** (name, signature line, date). No witnesses. (7) footer — page x/y, contract no.
- Registration/national/tax numbers are **optional**: printed only if set in Settings at Issue time. If empty, the document prints no placeholder and no registration or licensing claim.
- All amounts from `*_minor` via `Money`; never `number_format` on floats.

#### 8.4.1 Payment terms text
| `pricing.billing_interval` | `payment_terms` | Content |
|---|---|---|
| monthly | — | "اشتراك شهري بقيمة X د.أ يُستحق شهرياً في اليوم D من كل شهر." No annual wording, no annual row. |
| annual | full | "اشتراك سنوي بقيمة X د.أ يُدفع دفعة واحدة بتاريخ …" |
| annual | installments | "اشتراك سنوي بقيمة X د.أ مقسّط على N دفعات وفق الجدول التالي" + table from snapshot `schedules` (sum MUST equal agreed value; mismatch blocks Issue). |

### 8.5 Arabic PDF [FROZEN D-11]
Release requirement: correctly shaped, connected, right‑to‑left Arabic with a bundled Arabic font (e.g. Cairo/Noto Naskh, OFL). First task of the Contracts phase: generate a sample from the current dompdf path and review. If inadequate (expected), replace dompdf with **mPDF** in `ContractPdfService` only. Filename: `Notify-Contract-{client-slug}-{system-codes}-{number|draft}.pdf`.

---

## 9. Payment and collections workflow

### 9.1 Methods [FROZEN D-02]
V1 forms offer only **Cash / نقداً** and **CliQ / كليك**. Other `PaymentMethods` constants remain only to read historical records. `PaymentMethods::v1()` returns `[cash, cliq]`; labels come from lang files.

### 9.2 Staff flow — Payment Receipt Confirmation [FROZEN D-15]
Staff opens "Payment received / استلام دفعة" from the Client Workspace (or Collections due list):
- Inputs: **Amount** (JOD > 0), **Method** (Cash|CliQ, Cash preselected), optional **Reference/Note**; "more options": **Received at** (default now; allowed range: now − 7 days … now; never future).
- Result: a `payment_receipt_confirmations` row with `status = pending`. **No** Payment, Allocation, CashMovement, JournalEntry, balance or receivable change.
- Owner‑level users receive a notification + the item appears in: Finance Overview attention queue, Collections & Receivables "Pending confirmations" tab (sidebar badge).
- Staff sees their pending receipt on the client ("بانتظار التأكيد") and in Collections due; amount due is **not** reduced until approval.
- Staff may cancel their own pending receipt (status `cancelled`) before review.

### 9.3 Owner review
- **Approve** (single action; no editing of amount/method/date): in one DB transaction with `lockForUpdate` on the receipt: verify `status = pending` → call `PaymentAllocationService::recordV2Payment` with the receipt's client, amount, method, resolved account (§10), `received_at`, reference/note, auto‑allocate = true, `recorded_by` = approver → set `status = approved`, `payment_id`, `reviewed_by`, `reviewed_at` → activity event "Payment of X confirmed" → notify submitter. Idempotent: a second approve is a no‑op returning the same payment.
- **Reject** (reason required): `status = rejected`; zero financial effect; activity event; submitter notified.
- If the Staff figure was wrong, Owner rejects with reason and records the correct payment directly (§9.4). No edit‑on‑approve.

### 9.4 Owner direct entry
Owner‑level recording a payment is confirmed immediately: same sheet (Cash|CliQ, amount, optional note, received‑at not future, no backdating limit) → existing `storeNormalPayment` path → authoritative pipeline. No receipt row is created.

### 9.5 Pipeline after authoritative confirmation (existing, unchanged)
Idempotency claim → resolve Company Account (§10) → `Payment` (fils) → `CashMovement` → accounting event → journal → auto‑allocate oldest due invoice(s) → allocation journal → receivable update → unallocated remainder becomes customer credit → reports read derived data.
**Hard rule:** one real‑world payment = one `Payment`. A receipt may produce at most one payment (`payment_id` unique).

### 9.6 Collections & Receivables (Owner)
Tabs: **Pending confirmations** · **Due & overdue** · **Partially paid** · **Customer credits** · **Recent payments**. Row primary action: Approve (pending) / Record payment (due). Corrections (manual allocation, reversal, refund, credit note) are owner‑only secondary actions in the row menu.

### 9.7 Collections due (Staff)
List of subscribers with amount due/overdue and next due date, own pending receipts; row action "Payment received". No totals across the company, no balances, no reports.

---

## 10. Company Accounts

### 10.1 Accounts [FROZEN D-01]
| Code | Name AR | Name EN | Type | Currency |
|---|---|---|---|---|
| `CASH-BOX` | الصندوق | Cash Box | `cash` | JOD |
| `CLIQ` | كليك | CliQ | `bank` | JOD |

### 10.2 Resolution
`PaymentFinancialAccountResolver` is rewritten to a fixed map `cash → CASH-BOX`, `cliq → CLIQ` by `FinancialAccount.code`. Account must be active, not archived, JOD. Any other method is rejected for new records. The type‑based map is deleted. No `payment_method_accounts` table and no mapping UI.

The same resolver is used for: customer payments, receipt approvals, expenses, recurring‑obligation payments, capital funding (when ON).

### 10.3 Bootstrap
`CompanyAccountBootstrapService::ensureDefaults()` (idempotent, transactional): for each row in §10.1, find by `code` or create; ensure chart‑account mapping via `AccountingSetupService::ensureFinancialAccountMapping`; never rename, merge or archive other accounts. Called by: a data migration, `DatabaseSeeder`, and `php artisan notify:bootstrap` (deployment). Never called inside a web request.

### 10.4 Company Accounts page `/finance/accounts` (Owner)
- Account cards: Cash Box, CliQ — derived balance (from `CashMovement`), last movement date.
- Movements list per account (date, description in business language, in/out, running balance); filter by period.
- **Internal transfer** (Cash ↔ CliQ): amount, direction, date, note → existing `FinancialAccountService::createTransfer`; reversal via row menu. Transfer is neither revenue nor expense.
- Historical unassigned cash events section appears only when count > 0 (existing `assignCashEvent`).
- Cash Box and CliQ cannot be archived. No "create account" action in V1 UI (engine supports it; not exposed).

---

## 11. Automatic accounting pipeline

No redesign. Authoritative services: `CashMovementService`, `AccountingEventPostingService`, `JournalPostingService`, `BillingAccountingService`, `RevenueRecognitionService`, `SaasMetricEventService`, `AccountingSetupService`, `AccountingReconciliationService`, `FinancialStatementService`, `PaymentAllocationService`.

| Human confirms | Engine produces |
|---|---|
| Subscription (systems, value, terms) | invoice(s), schedule, revenue schedule, metric event, paid access, Draft contract |
| Payment (Owner direct or approval of receipt) | payment, cash movement, journal, allocation, receivable update |
| Staff receipt submitted / rejected | nothing financial |
| Expense paid (one‑time or recurring obligation) | expense, cash movement, journal |
| Internal transfer | two cash movements, balance‑sheet journal |
| Custom Project invoice | one‑time invoice, journal; no MRR/ARR |
| Capital funding (feature ON) | funding transaction, cash movement, equity/liability journal |

**Invariants (tested):** Payment ≠ Revenue; Invoice ≠ Cash; Cash ≠ Profit; accounting revenue ≠ MRR/ARR; 1 JOD = 1000 fils; no authoritative floats; posted journals immutable; account balances derived; free access, referral metadata, receipt submission/rejection and credential actions create no financial record; feature flags never change postings or report math.

---

## 12. Finance information architecture [FROZEN D-24]

Real routes, one home per function. Old GET URLs 301‑redirect for one release. POST route names retained where possible. Each page computes only what it displays.

| Section | Route | Replaces |
|---|---|---|
| Overview | `/finance` | `finance?section=overview`, `/executive` |
| Collections & Receivables | `/finance/collections` | `/collections`, `finance?section=collections` |
| Expenses | `/finance/expenses` | `/operating-expenses`, `finance?section=expenses` |
| Company Accounts | `/finance/accounts` | `/financial-accounts`, "advanced" |
| Accounting & Entries | `/finance/accounting` | `/accounting`, `/subscription-billing` (UI) |
| Financial Reports | `/finance/reports/{report?}` | `finance?section=reports`, `/saas-metrics` |
| Capital & Financing | `/finance/capital` (gated) | `/capital-management`, `finance?section=capital_assets` |

### 12.1 Finance Overview — visual decision dashboard [FROZEN D-16]
Answers: money available now; what customers owe; collected this month; spent this month; what needs attention.
Layout (phone = single column in this order; desktop = grid):
1. **Primary KPI row (4 large cards only):** Available Cash (CASH-BOX + CLIQ, with split), Receivables (with overdue portion), Collections This Month (vs previous month delta), Expenses This Month (vs previous month delta).
2. **Trend chart:** last 6 months, collections vs expenses (two series, monthly bars or lines), rendered server‑side data + a lightweight chart library from an allowed CDN or inline SVG; accessible table fallback.
3. **Attention queue:** pending payment confirmations (count + oldest), overdue receivables (top 5 by amount/age), renewals due in 30 days, recurring expenses due in 7 days. Each item links to its action.
4. **Compact financial result:** this month recognized revenue, expenses, net result (one row, smaller).
5. **Secondary SaaS strip:** MRR, ARR, active subscriptions — visually separate, labeled "مؤشرات الاشتراكات — ليست إيراداً محاسبياً".
6. Drill‑down links to Collections, Expenses, Accounts, Reports.
Rules: no forms, no tables of journals, no technical accounting fields, no more than 4 primary metrics.

### 12.2 Collections & Receivables — see §9.6.

### 12.3 Expenses (Owner) [FROZEN D-12, D-17]
- **One‑time expense:** Amount, Category, Method (Cash|CliQ), Date (not future), Description/Note → existing `OperatingExpenseService` with `FUNDING_COMPANY_ACCOUNT` + resolved account.
- **Monthly recurring expense:** same inputs + Start date + Monthly due day (1–28) → existing `RecurringExpenseTemplate` (monthly only). Scheduler generates obligations (existing). Obligation card: "Mark as paid" (confirms amount/method/date) → existing pay path; "Skip this month".
- Lists: recent expenses; upcoming/overdue obligations; recurring templates (pause/edit amount going forward).
- Categories managed inside Expenses (sub‑page). Vendors are not exposed in V1 UI (engine retained; nullable).
- Not exposed: personal‑paid funding, fixed assets, acquisitions, depreciation, asset categories. Reversal of an expense = owner row‑menu action (existing).

### 12.4 Company Accounts — §10.4.

### 12.5 Accounting & Entries (label "الحسابات والقيود المحاسبية")
- Normal surface (tabs): **Accounts** (chart of accounts with balances), **Journal Entries** (list + detail, read‑only), **Reconciliation** (status + discrepancies), **Accounting Periods** (close/reopen), **Revenue Recognition** (status, review queue, confirm).
- **Advanced tools** in a collapsed "أدوات متقدمة / Advanced tools" panel at the bottom, Owner‑only, each with a confirmation dialog: accounting backfill, revenue‑schedule backfill, manual revenue recognition run, manual renewal generation, billing‑period backfill.

### 12.6 Financial Reports — §14. 12.7 Capital — §13.

---

## 13. Capital & Financing feature gate [FROZEN D-05, D-18]

- Setting `feature_capital_financing` (label "Enable Capital & Investment Management"), default `0`, in Settings → Optional Features.
- `App\Support\Features::capitalEnabled()`.
- **OFF:** sidebar/More/links/cards hidden; middleware `feature:capital` on all capital GET/POST routes returns 404.
- **ON — exposed:** Funding sources; capital contributions (`founder_contribution`, `owner_contribution`); founder loans (`loan_funding`); external investment (`external_investment`); reversal of these. Funding received into Cash Box or CliQ via the resolver.
- **Never in V1 (even ON):** fixed‑asset acquisition/management/status, asset categories, depreciation, owner distributions. Their routes (`asset-categories.*`, `fixed-assets.*`) are removed from `routes/web.php`; models/services/tables remain.
- Reports always include any existing capital/equity/liability lines regardless of the flag. Turning OFF never hides data from reports.

---

## 14. Financial reporting [FROZEN D-19]

`/finance/reports/{report}`; switcher + one period bar (today, this month, previous month, this year, previous year, custom; comparison none / previous period / same period last year — existing `ReportingPeriod`). Only the selected report is computed.

| Report | Arabic | Source |
|---|---|---|
| Profit & Loss | الأرباح والخسائر | `FinancialStatementService::profitAndLoss` |
| Financial Position | المركز المالي | `balanceSheet` |
| Cash Flow | التدفقات النقدية | `cashFlow` |
| Revenue Detail | تفاصيل الإيرادات | `recognizedRevenueReport` (+ deferred sub‑view) |
| Expense Detail | تفاصيل المصاريف | `expenseReport` |
| Receivables & Collections | الذمم والتحصيل | `arAging` + payments received in period |
| Subscription Metrics | مؤشرات الاشتراكات | `SaasMetricsService::dashboard` (labeled not accounting revenue) |

On‑screen: cards on phone, tables on desktop. Export: **CSV, UTF‑8 with BOM**, per report, only on this page. No export in Settings or elsewhere (SaaS export moves here).

---

## 15. Administration [FROZEN D-13]

Hub `/administration` with five destinations: **Systems** (catalog incl. `requires_credentials` and price suggestions), **Team & Roles** (users, role assignment with fixed role descriptions, reset password, deactivate), **Operational Reference Data**, **Import**, **Settings**. Finance lists stay in Finance. No client operations here.

### 15.1 Operational Reference Data
Table `reference_options(id, list_key, value, label_ar, label_en, sort_order, is_active, timestamps)`, unique `(list_key, value)`. Lists:
- `client_category` — controlled select (with "Other" → free text stored as typed).
- `lead_source` — controlled select; seeded with the union of current values, de‑duplicated: Google Maps, Instagram, Referral, Direct / Field visit, Existing client, Other.
- `city_area` — **suggestions + free text** [FROZEN D-20].
`clients` columns remain strings (no FKs). Unknown historical values render as stored. `ClientController::leadSourceOptions` and `ClientLifecycle::SOURCE_TYPES` are replaced by this source.

### 15.2 Import
Owner‑only (`import_clients`), route‑enforced. Existing prospect/subscriber CSV preview→confirm flow retained for owner use. Production launch import is a separate controlled task (§33a).

---

## 16. Settings

**Hard rule:** every setting kept MUST change behavior; stored‑but‑unused settings are not allowed. No theme setting.

| Section | Key | Behavior it drives |
|---|---|---|
| Company & Contracts | `company_name_ar/en`, `company_logo`, `company_phone`, `company_email`, `company_address` | Captured into contract snapshot at Issue; logo on PDF header |
| | `authorized_signatory` | Notify signature block |
| | `registration_number`, `tax_number` (optional) | Printed only if set at Issue |
| | `default_contract_terms` | Terms section at draft creation |
| | `contract_prefix` | Contract number at Issue |
| | `invoice_prefix` | Invoice numbering (existing) |
| Operations | `timezone` (Asia/Amman only) | App/scheduler timezone (existing) |
| | `appointment_duration` | Default end time in appointment forms; Today timeline block length |
| | `free_installation_duration` | Default end time for installation scheduling |
| | `post_install_followup_days` | Auto follow‑up due date created on installation completion |
| | `workday_start`, `workday_end` | Today timeline bounds; default time suggestions; reminders outside hours suppressed to next start |
| Subscriptions & Contracts | `currency` (JOD) | Display/validation |
| | `default_billing_cycle` | Preselected cycle |
| | `auto_contract_on_paid_subscription` | Draft contract creation |
| | `allow_monthly`, `allow_annual_installments` | Options in subscription sheet + server validation |
| Optional Features | `feature_capital_financing` | §13 |

Page route `/administration/settings` (old `/settings` redirects). Gate `manage_company_settings`.

---

## 17. Staff work profile

Fields: Photo, Full name, Job title, Phone, Work email (= login email), Role (read‑only). Action: Change password. Summary: today's completed work count; next 3 appointments. Self‑editable: photo, name, job title, phone. Email and role: Owner via Team only. Schema M‑2. Avatar: image, ≤1 MB, stored on `public` disk, resized ≤256 px, old file deleted on replace. Not HR: no salaries, leave, documents, attendance.

---

## 18. Client system credentials [FROZEN D-09]

### 18.1 Capability
Credentials UI appears only for Systems with `products.requires_credentials = true` (Smart Link, E‑Menu, E‑Store). Auto SMS shows no credential fields. Credentials are shown per applicable System the client has access to (free or paid); a credential can also be added for an applicable System before access is granted.

### 18.2 Schema
`client_system_credentials`: `id`, `client_id` (fk restrictOnDelete), `product_id` (fk restrictOnDelete), `login_url` (nullable), `username` (email/username, nullable), `secret` (text, `encrypted` cast), `note` (text nullable, `encrypted` cast), `created_by`, `updated_by`, `last_revealed_at`, timestamps, soft deletes; unique `(client_id, product_id)` among non‑deleted rows (enforced in service).
`client_credential_access_logs` (append‑only): `id`, `credential_id`, `client_id`, `user_id`, `action` (`created|updated|revealed|copied|sent|deleted`), `channel` (`whatsapp|copy|null`), `recipient_masked` (e.g. `+9627•••••12`), `ip`, `user_agent`, `created_at`.

### 18.3 Behavior
- Page HTML never contains the secret; masked `••••••••`.
- **Reveal:** POST `/clients/{client}/credentials/{credential}/reveal` (`throttle:20,1`) → JSON secret for that credential only; log `revealed`; set `last_revealed_at`; UI re‑masks after 30 s.
- **Copy:** client‑side copy of the revealed value (or reveal+copy in one tap); log `copied` via POST.
- **Send:** explicit button → confirmation sheet (recipient = client's primary WhatsApp/phone, editable; message preview with URL, username, password) → on confirm, server logs `sent` and returns a `https://wa.me/{number}?text=…` URL that the browser opens. The secret is in the message only after this explicit action.
- Secret/note: in `$hidden`; excluded from activity logs (log "Smart Link credentials updated by X" only), notifications, timelines, exception context; the `secret` field is added to `dontFlash`.
- Encryption uses `APP_KEY`; rotation via `APP_PREVIOUS_KEYS` (§33).

---

## 19. Client Workspace

Vertical card stack (phone = single column in this order; desktop ≥1280 = two columns: left operational 1–4, 11; right commercial 5–10):

1. **Business Identity** — business name, category, city/area, address/location, business phone (tap to call/WhatsApp), stage badge.
2. **Primary Contact** — name (optional), role/title, phone, WhatsApp, email; "Add contact details" when empty. Visually separate from Business Identity.
3. **Next Action** — one computed next step.
4. **Quick Actions** — one contextual primary button (e.g. Record call for prospects; Payment received for subscribers with amount due) + "More actions" sheet (appointment, installation, follow‑up, note, start subscription, close/reopen).
5. **Systems / Access** — systems with Free/Paid chips; grant/revoke free access.
6. **Subscription** — systems, cycle, agreed value, current period, next renewal; empty state with "Start subscription".
7. **Contract** — number or "Draft", status; **Preview**, **Download PDF**; Owner: **Issue** on drafts.
8. **Amount Due / Latest Confirmed Payment** — amount due, overdue flag, latest confirmed payment; pending receipts listed as "بانتظار التأكيد"; primary action "Payment received".
9. **Referral** — referred by, note, commission % (Owner edits; Staff sees value read‑only).
10. **System Credentials** — per applicable System: URL, username, masked secret, Reveal / Copy / Send.
11. **Recent Activity** — last 10 human‑readable events (incl. "Linked to Custom Project P"), "View all".

Removed: embedded Custom Projects card [FROZEN D-14]; legacy partials `subscription-billing`, `history`, `details`, `management-finance`; duplicate contract blocks; raw translation keys; internal queue names; technical finance (allocation ids, cash movement ids, engine versions, account names).

---

## 20. Today and work scheduling

Modes `daily` and `work` retained. Today (phone‑first order): header with counts → **Now/Next appointments** (card: client, time, assigned staff, type/status, one primary action "Record result") → installations today → follow‑ups due → collections due (Staff: own; Owner: pending confirmations count) → daily notes. Timeline bounds from `workday_start/end`; default durations from settings. Scheduling forms are full‑screen sheets on phone.

## 20a. Custom Projects (top‑level) [FROZEN D-14]

- Route `/custom-projects` in sidebar (desktop/iPad‑landscape) and More sheet (phone).
- `client_id` becomes **nullable** (M‑6). A project may exist without a client.
- Linking/unlinking a client writes a human‑readable Client Activity event only (no card in the workspace).
- Invoicing a project requires a linked client (invoice needs a customer); UI disables "Create invoice" until linked. One‑time invoice via existing `BillingController::storeOneTimeInvoice`/`InvoiceService`; outside MRR/ARR.
- Staff: read‑only list and detail. Owner/Admin: create, edit, archive, invoice.

---

## 21. Table/list design system

- `x-notify.list` component: toolbar (search at start, filters, primary action at end) + rows defined once; renders `<table>` at ≥768 px and cards at <768 px.
- Desktop rows: ≤5 columns, one status badge, one primary action, secondary actions in `⋯` menu.
- Phone: cards; appointment cards show client, time, assigned staff, type/status, one action.
- Technical tables (journal lines, chart of accounts) may scroll horizontally inside their own wrapper only.
- Pagination: "Load more" on phone, numbered on desktop.
- No new inline `style=`; touched views replace inline styles with classes.

## 22. Phone architecture (390 px baseline)

Bottom nav (safe‑area aware); no sidebar; significant forms as full‑screen sheets with sticky primary action; touch targets ≥44×44 px; form input text ≥16 px; no horizontal page overflow; single‑column workspace per §19.

## 23. iPad architecture

- **Portrait (768–1023 px):** bottom/tablet navigation with labels; no sidebar; two‑column content only where it clearly helps.
- **Landscape (≥1024 px):** sidebar (full at ≥1280; compact icon+label ~88 px at 1024–1279); bottom nav hidden.
- Touch affordances via `@media (pointer: coarse)` in addition to width.
- Breakpoint tokens: 600 / 768 / 1024 / 1280. All existing ad‑hoc breakpoints migrated to these.

## 24. Desktop architecture (≥1280 px)

Full sidebar (272 px) per §3.1; content max‑width ~1280 px; two‑column workspace; tables per §21.

---

## 25. Arabic/English localization [FROZEN D-21]

Arabic default (RTL), English (LTR), header switch. All user‑facing strings in `lang/{ar,en}`; hard‑coded strings in controllers/services moved to keys; payment method labels per locale. Terminology: الحسابات والقيود المحاسبية (never دفتر الأستاذ), المركز المالي, حسابات الشركة, استلام دفعة, بانتظار التأكيد, تحويل داخلي, الأنظمة, عملاء محتملون, المشتركون, بانتظار التجديد / مشترك سابق. Latin digits in both locales; amounts `1,234.500 د.أ` / `JOD 1,234.500`, LTR‑isolated in RTL text. Tests enforce key parity and no raw keys.

## 26. Theme [FROZEN D-04]

Remove Dark Mode: delete `[data-theme="dark"]` block, head theme script, Alpine `theme`/`toggleTheme`, header toggle button, `notify.shell.toggle_theme` keys, theme icon usage; update `ApplicationShellNavigationTest`. Set `color-scheme: light`. Keep and extend the Light token set (`:root` variables) so a future dark theme can be rebuilt; replace hard‑coded colors with tokens in every touched view/CSS block.

## 27. Security and authorization

- Every controller action authorizes (Gate/Policy). Explicit additions: `CsvImportController` (`import_clients`), team actions (`manage_team`), `ClientSystemAccessController` (`manage_system_access`), `GuidedSubscriptionController::store` (`start_paid_subscription`), receipt submit/approve/reject, credentials, custom projects.
- `feature:capital` middleware; fixed‑asset routes removed.
- Financial POST routes keep `financial.idempotency` (incl. receipt approval and owner direct payment).
- Route‑sweep test: every named route exercised as Staff returns the expected status.
- Referral commission and other owner‑only fields are ignored server‑side for Staff, not merely hidden.
- Uploads validated (mime, size); CSV and avatars/logos stored per §17/§15.2.
- Credential rules §18.

---

## 28. Data model changes (final)

| # | Change | Type | Notes / risk |
|---|---|---|---|
| M‑1 | Bootstrap data migration: CASH-BOX, CLIQ accounts + chart mappings | data (idempotent) | Low; find‑by‑code, never duplicate |
| M‑2 | `users`: `phone`, `job_title`, `avatar_path` (nullable) | additive | Low |
| M‑3 | `reference_options` table + seed | additive | Low |
| M‑4 | `client_system_credentials`, `client_credential_access_logs` | additive | Low; APP_KEY dependency |
| M‑5 | `payment_receipt_confirmations`: `id`, `client_id` (fk restrict), `amount_minor` (bigint > 0), `currency` ('JOD'), `payment_method` (cash\|cliq), `received_at`, `reference`, `note`, `status` (pending\|approved\|rejected\|cancelled, indexed), `submitted_by`, `reviewed_by`, `reviewed_at`, `rejection_reason`, `payment_id` (fk nullable **unique**), `idempotency_key` (unique), timestamps | additive | Low |
| M‑6 | `custom_projects.client_id` → nullable (keep FK restrictOnDelete) | alter | Medium on SQLite (table rebuild via `->change()`, Laravel 11); test both engines |
| M‑7 | `contracts.contract_number` → nullable (keep unique) | alter | Medium on SQLite; existing rows unaffected |
| M‑8 | `products.requires_credentials` bool default false + data update for Smart Link, E‑Menu, E‑Store | additive + data | Low |
| M‑9 | `clients.primary_phone_type` string default `business` (business\|owner\|manager); `clients.address` nullable (if absent) | additive | Low |
| M‑10 | `client_contacts.name` → nullable; `client_contacts.email` nullable | alter + additive | Medium on SQLite |
| M‑11 | Data migration: legacy `clients.status` → `stage` where stage null | data | Low |
| M‑12 | Settings rows: `feature_capital_financing = 0` | settings | None |
| — | No changes to payments, allocations, invoices, cash movements, journals, subscriptions tables | — | — |
| — | No drops (legacy `status`, plans/plan_prices, fixed‑asset tables, legacy investments/capital_expenses retained) | — | — |

`former_subscriber` is a new string value in `clients.stage` (string column); no schema change.

### 28.1 Business vs contact model (reuse `client_contacts`)
Create minimum: business name, business category, **primary phone**, **primary phone type** (Business / Owner / Manager‑Responsible), city/area, lead source. Optional on create or later: contact name, role/title, contact phone, WhatsApp if different, contact email; business phone; address/location.
Rules:
- `clients.phone` always stores the primary phone (search/index/dedup). `clients.primary_phone_type` records whose it is.
- Type = Business → `business_phone = phone`; no contact row required.
- Type = Owner/Manager → create/update the `is_primary` `client_contacts` row with `primary_phone = phone`, `role = owner|manager`, `name` nullable; `business_phone` stays null until known.
- Owner name is never required. Contact details can be added/edited later from the workspace.
- CSV import and the production import (§33a) follow the same rules.

---

## 29. Existing code to reuse

`PaymentAllocationService`, `CashMovementService`, `AccountingEventPostingService`, `JournalPostingService`, `BillingAccountingService`, `ReceivableService`, `CollectionCorrectionService`, `CreditNoteService`, `RefundService`, `FinancialAccountService` (transfers), `FinancialAccountBalanceService`, `AccountingSetupService`, `RevenueRecognitionService`, `FinancialStatementService`, `AccountingReportService`, `AccountingReconciliationService`, `FinancialReportingReconciliationService`, `SaasMetricsService`, `SaasMetricEventService`, `SubscriptionBillingService` (agreed‑value path, renewals), `InvoiceService`, `PaymentScheduleService`, `ContractService` (refactored for numbering at Issue), `CapitalManagementService` (funding only), `OperatingExpenseService`, `RecurringExpenseService`, `ClientOperationalWorkflowService`, `FreeInstallationService`, `FollowUpService`, `MeetingOutcomeService`, `OperationalQueueService`, `UnifiedOperationalWorkProjection`, `DailyOperationalService`, `NotificationService`, `CsvImportService`; `ClientContact`; `EnsureFinancialIdempotency`; `Money`, `ReportingPeriod`, `ClientLifecycle`; `x-notify.*` components incl. `app-shell`, `mobile-nav`; workspace action sheets; view models; scheduler commands; `FinanceReportController` export row builders.

## 30. Existing code to remove or retire

| Item | Action |
|---|---|
| Type‑based `METHOD_ACCOUNT_TYPES` in resolver | Delete; fixed code map |
| Non‑V1 methods in forms and validation for new records | Remove (constants kept for history) |
| `contracts.print`, HTML `contracts.download` routes/actions, Print buttons, template action bar, witness block, old `contracts/template.blade.php` | Delete (legacy fallback partial kept for old snapshots) |
| `finance/partials/*`, section switch, monolithic `FinanceReportController::index` | Replace |
| `/executive` page and view | Delete after Overview (D‑16) |
| `/subscription-billing` page view | Delete; tools move to Accounting advanced panel |
| `/saas-metrics` standalone page | Redirect to Reports → Subscription Metrics |
| `asset-categories.*`, `fixed-assets.*` routes and UI | Remove from routes/views; engine retained |
| Personal‑paid expense UI, vendor UI | Remove from UI; engine retained |
| Workspace partials `subscription-billing`, `history`, `details`, `management-finance`, embedded Custom Projects card | Delete |
| Dark mode code | Delete |
| `leadSourceOptions`, `ClientLifecycle::SOURCE_TYPES` | Replace by reference data |
| Empty dirs `resources/views/partners`, `conflicts`, `commercial-catalog/partials` | Delete |
| Superseded docs | Move to `docs/archive/` with a header pointing here |
| `FinancialPermissions` | Alias → delete after migration |

---

## 31. Migration strategy

1. Additive/alter migrations guarded (`hasTable/hasColumn`), SQLite + MySQL 8 safe; column alters (M‑6, M‑7, M‑10) tested on both engines.
2. Data migrations idempotent; bootstrap logic in one service used by migration, seeder and `notify:bootstrap`.
3. `migrate:fresh --seed` yields: owner user, systems (with `requires_credentials`), chart of accounts, CASH-BOX + CLIQ, reference data, settings defaults → a Staff receipt can be submitted and an Owner approval posts correctly with zero manual setup.
4. `down()` drops only what it created; alters reversible where safe.
5. Development DB: no real client data is imported [FROZEN D-22]. Dev data may be reset freely with `migrate:fresh --seed`.

## 32. Automated testing strategy

Existing suites stay green each phase (tests asserting removed behavior are updated in the same phase, with the reason in the commit). New tests:
- **Bootstrap:** fresh install has exactly one CASH-BOX and one CLIQ; re‑running is a no‑op; resolver maps cash/cliq; other methods rejected.
- **Receipt workflow:** Staff submit creates zero Payment/Allocation/CashMovement/Journal and no receivable change; backdating >7 days or future rejected; approve creates exactly one Payment with correct account + allocation + journals; double approve idempotent; reject has zero financial effect; Staff cannot approve; owner direct payment posts immediately; notifications sent.
- **Role matrix:** route‑sweep as Staff and Owner; Staff allowed set per §2.3.
- **Subscriptions:** Staff start → owners notified; last subscription end → `former_subscriber` (not closed); renewal → `subscriber`.
- **Segmentation:** view filters; default view per role; status→stage migration.
- **Client model:** create with only minimum fields; phone type Business vs Owner/Manager placement; contact without name allowed.
- **Capital gate:** OFF → 404 + hidden; ON → funding available, fixed‑asset routes absent; reports identical ON/OFF.
- **Expenses:** one‑time and recurring monthly with Cash/CliQ; no personal/asset options in UI.
- **Contracts:** draft has null number + watermark; Issue assigns sequential number, captures settings, freezes snapshot; settings change after Issue does not alter contract; monthly text has no annual wording; annual full vs installments correct; installment sum mismatch blocks Issue; no witness; removed routes 404; PDF non‑empty with Arabic; empty registration prints nothing.
- **Credentials:** encrypted at rest; absent from HTML, logs, activity, JSON; reveal/copy/send logged; throttled; Auto SMS has no credential UI.
- **Custom Projects:** nullable client; link creates activity event; workspace has no projects card; Staff read‑only; invoice requires client.
- **Reports:** each renders and exports CSV with BOM; only selected report computed.
- **Finance Overview:** 4 primary KPIs, attention queue includes pending confirmations.
- **Settings:** every setting has a behavior test (durations, follow‑up days, workday bounds, logo, allow flags).
- **Localization:** ar/en key parity; no raw `notify.` keys on core pages.
- **Invariants:** existing Finance phase tests + zero‑financial‑effect tests (free access, referral, receipts pending/rejected, credentials).
- **Playwright smoke [FROZEN D-23]:** viewports 390×844, 820×1180, 1024×768 (landscape iPad), 1440×900; pages Today, Clients (each view), Client Workspace, Finance Overview, Collections, Reports, Administration; assert no horizontal overflow, correct nav (bottom vs sidebar), More sheet opens, primary action visible.
- Full suite runs on SQLite and on MySQL 8.

## 33. Deployment readiness

MySQL 8 migrations verified; `php artisan notify:bootstrap` in deploy script; `APP_KEY` set and backed up, `APP_PREVIOUS_KEYS` rotation procedure documented; scheduler (`schedule:run` every minute) running; queue `sync` acceptable for V1; `storage:link`; Arabic font bundled for PDF; `APP_TIMEZONE=Asia/Amman`; DB + `storage/app` backups; `/health` monitored; `optimize` + view cache; log redaction for secrets verified.

### 33a. Production launch client import [FROZEN D-22]
- Performed **only on production after V1 deployment**, as a controlled launch step with the owner's current tracker spreadsheet. Never on dev/local; never part of seed data. The spreadsheet is not stored in the repository.
- All clients in that tracker are existing **annual subscribers**; their provided dates are authoritative and are not invented or recalculated.
- The import establishes each as `stage = subscriber` with an annual agreed‑value subscription and systems/access per the sheet, **without** fabricating historical Payments, CashMovements or JournalEntries for money collected before V1, and **without** an opening receivable unless the owner explicitly supplies an outstanding balance for that client.
- Old tracker installment/"rest" notes are not converted into V1 receivables automatically; they may be stored as a client note.
- Exact column mapping, handling of the first V1 billing period/next renewal date, and contract handling for imported clients are confirmed with the owner during deployment using the real spreadsheet, then executed via a dedicated, dry‑run‑capable import command with a preview report before commit.

## 34. Explicit non‑goals

Partner portal/login/dashboard/links/conflict engine; packages or plan‑price sales UX; automatic referral commission posting; owner distributions; fixed assets/acquisition/depreciation; personal‑paid expense UX; payment methods other than Cash and CliQ; configurable payment‑method mapping; creating extra company accounts in UI; e‑signature; HR profiles; role editor; dark mode; multi‑currency; tax engine changes; customer portal; payment gateway; native apps; offline mode; accounting engine redesign; XLSX/PDF report export; importing real clients into development.

---

## 35. Implementation phases (not started)

| Phase | Name | Depends on | Scope | Exit criteria |
|---|---|---|---|---|
| P1 | Money foundation & payment approval | — | M‑1, M‑5, `CompanyAccountBootstrapService`, `notify:bootstrap`, fixed resolver, `PaymentMethods::v1()`, receipt entity/service/controller (submit, approve, reject, cancel), owner direct path, notifications | Bootstrap + receipt workflow + invariant tests green |
| P2 | Authorization matrix | P1 | `Permissions` + matrix, new permissions, controller gates, import/team authz, route‑sweep test | Role tests green |
| P3 | Core data‑model additions | P2 | M‑2, M‑3, M‑6, M‑7, M‑8, M‑9, M‑10, M‑11, M‑12; reference data service; `former_subscriber` transitions; Staff‑subscription owner notification; capital feature flag + middleware; fixed‑asset route removal | Migrations green on SQLite + MySQL; lifecycle tests |
| P4 | Client/contact model | P3 | Create/edit forms and service rules §28.1; CSV import alignment | Client model tests |
| P5 | System credential capability | P3 | M‑4 service/controller, reveal/copy/send, audit, capability flag | Credential security tests |
| P6 | Finance architecture & dashboards | P1–P3 | Finance routes/controllers, redirects, Overview dashboard, Collections tabs, Company Accounts, Accounting & Entries with advanced panel, Reports + CSV BOM, Staff Collections due | Finance/report tests |
| P7 | Expenses simplification | P6 | One‑time + monthly recurring with Cash/CliQ; hide personal/vendors/assets | Expense tests |
| P8 | Contracts & Arabic PDF | P3 | PDF spike → mPDF if needed; numbering at Issue; new document template; watermark; route removals | Contract tests + owner review of sample PDFs (AR) |
| P9 | Navigation/shell | P2, P6 | Remove dark mode, header, role sidebars, bottom nav/More, breakpoints, iPad modes | Shell tests; Playwright nav checks |
| P10 | Client segmentation & Workspace | P4, P5, P8, P9 | Clients views, workspace card stack §19, referral card | Workspace tests; overflow smoke |
| P11 | Custom Projects relocation | P3, P9 | Top‑level area, nullable client, activity events, Staff read‑only | Custom project tests |
| P12 | Today/tables/forms mobile‑first | P9 | Today cards, `x-notify.list` adoption, full‑screen sheets, appointment cards | Playwright smoke |
| P13 | Profile/Administration/Settings | P3, P9 | Profile fields/avatar, admin hub + reference data + systems flag UI, settings wiring (every key behavior‑tested) | Settings/profile tests |
| P14 | Localization & Light‑theme polish | P6–P13 | String moves, terminology, parity tests, token cleanup in touched views | Parity tests |
| P15 | SQLite + MySQL verification | P14 | Full suite on both engines; `migrate:fresh --seed` + bootstrap on clean MySQL; demo scenario prospect → subscriber → Staff receipt → approval → reports | All green |
| P16 | Owner manual QA | P15 | Scripted QA on iPhone, iPad portrait/landscape, desktop, AR/EN | Owner sign‑off |
| P17 | Production import validation | P16 | Build dry‑run import command; mapping confirmed with owner on real spreadsheet (production only) | Dry‑run report approved |
| P18 | Production deployment freeze | P17 | Deploy, bootstrap, import, verify §33, tag release, archive superseded docs | V1 live |

Rules: each phase is its own reviewed commit series; a phase starts only after its dependencies' exit criteria pass; accounting engine files change only where listed (resolver, bootstrap, contract numbering, subscription‑end stage transition).

---

## Code impact analysis

| Change | Affected code | Reuse | Refactor | Delete | Schema | Migration risk | Financial risk | Authz | Mobile/UI | Tests |
|---|---|---|---|---|---|---|---|---|---|---|
| Fixed Cash/CliQ + bootstrap | resolver, `CollectionsController`, payment/expense/capital forms, seeders | payment pipeline | resolver, method lists | type map | M‑1 | Low | Medium (account correctness) | — | simpler sheet | bootstrap tests; update Phase07Payments |
| Payment receipt approval | new model/service/controller, Collections, Overview, notifications | `recordV2Payment` | Collections tabs | — | M‑5 | Low | **High** (must not double‑post) → lock + unique `payment_id` + idempotency | new perms | pending states | receipt workflow tests |
| Role matrix | `FinancialPermissions`, `AppServiceProvider`, ~15 controllers, shell flags | Gate loop | `allows()` | alias later | — | — | Low | **High** | staff nav | route sweep; G3 tests |
| Client lifecycle `former_subscriber` | `ClientLifecycle`, `SubscriptionBillingService`, scheduler, views | workflow service | end‑of‑subscription hook | — | none | Low | None | — | new view | lifecycle tests |
| Client/contact model | `ClientController`, forms, `ClientContact`, import | contacts table | create/update rules | — | M‑9, M‑10 | Medium (SQLite alter) | None | — | workspace cards | client model tests |
| Credentials | new model/controller/service/views, `products` | activity infra | — | — | M‑4, M‑8 | Low | None | new perms | card + sheets | security tests |
| Finance IA + Overview + Reports | Finance controllers/views, SaaS, executive | services, exports | split controllers, dashboard | partials, executive, subscription‑billing view | — | — | Low (read‑only) | same gates | major | finance/report tests rewritten |
| Expenses simplification | `OperatingExpenseController`, views | expense/recurring services | forms | personal/vendor/asset UI | — | — | Low | owner only | simpler forms | expense tests |
| Capital gate | capital routes/controller/nav | capital service | middleware | fixed‑asset routes | M‑12 | — | None | 404 gate | nav | gate tests |
| Contracts | `ContractService`, `ContractPdfService`, `ContractController`, template, workspace | snapshot model | numbering at Issue, snapshot capture, PDF engine | print/html routes, witness | M‑7 | Medium | None | Issue owner‑only | single card | contract tests |
| Custom Projects relocation | `CustomProjectController`, views, workspace | model/invoice path | nullable client, activity | workspace card | M‑6 | Medium (SQLite alter) | None (invoice needs client) | staff read‑only | nav | project tests |
| Workspace/segmentation | `clients/show`, `ClientController::index`, view models | action sheets | card stack, views | 4 partials | M‑11 | Low | None | per card | major | workspace tests rewritten |
| Shell/phone/iPad/theme | `app-shell`, `mobile-nav`, `app.css` | bottom nav/sheet | breakpoints, nav | dark mode | — | — | None | nav flags | major | shell + Playwright |
| Profile/Admin/Settings | `ProfileController`, `AdministrationController`, `SettingsController`, forms consuming settings | existing | wiring of settings, reference data | unused‑setting code paths | M‑2, M‑3 | Low | None | new perms | pages | settings/profile tests |
| Localization | lang files, controllers | — | string moves | "دفتر الأستاذ" | — | — | None | — | text | parity tests |

---

## Highest‑risk areas

1. **Payment receipt approval** — must never double‑post or post on submit/reject; guarded by row lock, unique `payment_id`, idempotency key, and invariant tests.
2. **Arabic contract PDF** — dompdf likely inadequate; mPDF migration is on the release path.
3. **Role matrix** — broad authorization surface; mitigated by route‑sweep tests.
4. **SQLite column alters** (M‑6, M‑7, M‑10) — table rebuilds in tests; verify on MySQL 8 too.
5. **Contract numbering moved to Issue** — sequence integrity under concurrency; legacy numbered drafts.
6. **Client Workspace rewrite** — largest UI change; many markup‑bound tests.
7. **Credential secrecy** — leakage via logs/flash/serialization; APP_KEY rotation.
8. **Production launch import** — historical subscribers without fabricated money; dry‑run and owner‑confirmed mapping required.
9. **Finance route split** — redirects and links; covered by route tests.
