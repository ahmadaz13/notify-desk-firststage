# Notify Desk V1 Human Operations UX Architecture

## Document Control

| Item | Value |
| --- | --- |
| Phase | 01 - UX Architecture Freeze |
| Status | `FROZEN_WITH_EXPLICIT_IMPLEMENTATION_DEPENDENCIES` |
| Product | Notify Desk V1 |
| Scope | Human Operations Simplification |
| Code changes in this phase | None |
| Business timezone | `Asia/Amman` |
| Governing principle | Humans enter decisions and real-world facts only. The system derives, defaults, calculates, generates, and records everything else. |

This document is the implementation contract for the simplified human experience. It does not replace the V1 backend contracts. Where a simpler interaction is not supported by current code, the decision is marked `IMPLEMENTATION_DEPENDENCY`; the UI must keep the safe current input visible until that dependency is approved and implemented.

### Decision Labels

| Label | Meaning |
| --- | --- |
| `SAFE_UI_CHANGE` | Presentation can change while sending the same valid data to existing routes and services. |
| `SAFE_WITH_DEFAULT` | The field can be hidden because a safe default or derivation already exists in current code. |
| `IMPLEMENTATION_DEPENDENCY` | The target experience needs a small adapter, projection, validation rule, or approved domain addition before the field/action can be hidden. |
| `DO_NOT_CHANGE` | The value or behavior is an authoritative backend/domain rule and must not be moved into UI logic or bypassed. |

## Evidence Base

The freeze was validated against the current routes, controllers, ViewModels, services, policies, permission gates, models, migrations, Blade views, translations, and the following governing documents:

- `docs/backend/BACKEND_V1_FREEZE.md`
- `docs/backend/SOURCE_OF_TRUTH_V1.md`
- `docs/backend/FINAL_SOURCE_OF_TRUTH_MATRIX.md`
- `docs/backend/UI_BACKEND_CONTRACT_V1.md`
- `docs/backend/OPERATIONAL_WORKFLOW_V1.md`
- `docs/backend/IDENTITY_AND_PERMISSIONS_V1.md`
- `docs/backend/AUTOMATION_AND_DATA_INTEGRITY_V1.md`
- `docs/backend/V1_FINAL_ARCHITECTURE.md`
- `docs/ui-reference/NOTIFY_FINAL_UI_HANDOFF.json`
- `docs/audits/V1_HUMAN_OPERATIONS_SIMPLIFICATION_AUDIT.md`

## Executive UX Principles

1. **One next action, not six equal buttons.** Every operational surface identifies the most relevant next action from real client state and due work.
2. **Facts and decisions only.** A user records what happened, what was received, or what was agreed. IDs, pricing versions, tax basis points, invoice lines, allocations, journals, and metrics remain system concerns.
3. **Daily is not management.** Daily work is optimized for speed; configuration, reporting, recovery, and financial corrections remain deliberately separate.
4. **The backend remains authoritative.** Blade and JavaScript may present, filter, and submit. They must not calculate price, receivables, allocations, MRR/ARR, revenue, cash, accounting, or lifecycle state.
5. **Progressive disclosure is mandatory.** Rare options appear only when the selected outcome requires them or the authorized user deliberately opens Advanced.
6. **Permission is part of the architecture.** Hidden controls never substitute for server authorization. Existing gates, policies, and middleware remain decisive.
7. **No raw stage control in daily work.** Meaningful actions drive lifecycle transitions through existing services.
8. **Multi-Product is visible.** Each Product subscription is represented independently, including its Plan, term, balance, and contract.
9. **Notifications are signals, not work queues.** Operational work belongs in Today/Work. The bell is reserved for reassignment, system attention, recovery, and review notifications.
10. **Mobile is a first-class working surface.** Frequent actions need large touch targets, focused sheets, and no dense operational tables.

## Final Information Architecture

### Daily Layer

Daily answers only: what must I do, for which client, and what is the next action?

| Destination | Purpose | Default presentation |
| --- | --- | --- |
| Today | Work due now or today | Prioritized action feed grouped by time |
| Clients | Find a business and continue its workflow | Desktop operational table; mobile cards |
| Work | All open operational work | Filterable queues grouped by urgency |
| More | Permission-aware secondary entry point | Compact menu, not a second dashboard |

Global actions:

- Add Client is available from Today and Clients for users allowed by `ClientPolicy::create`.
- Notifications use the header bell with an unread badge.
- Search is contextual to Clients or the current management list; it is not a global command center in V1.

### Management Layer

Management is an intentional destination for Founder/Admin. It must not occupy the Staff daily path.

| Group | Destinations |
| --- | --- |
| Commercial | Subscription Management; Products & Pricing; Partners |
| Money | Collections; Finance; Operating Expenses; Capital Management |
| Reports | Executive; SaaS Metrics |
| Operations Admin | Import; Conflicts |
| System | Settings |

### Advanced Engine Layer

Advanced is visible only when existing gates allow it. It contains Financial Accounts, Accounting, Revenue Recognition, Credit Notes, Refunds, Reversals, manual Payment Allocation, PlanPrice history/version controls, journal internals, and legacy compatibility/recovery tools.

These items must never be promoted into Daily merely because they are currently rendered inside a client page.

## Mobile Navigation

The fixed bottom navigation is exactly:

1. **Today**
2. **Clients**
3. **Work**
4. **More**

Rules:

- The active destination remains visually stable while a sheet is open.
- More opens a bottom sheet containing only destinations the current user can access.
- Notifications are not a fifth tab; the bell remains in the header.
- Add Client is a focused action from Today/Clients, not a permanent center tab.
- Management and Advanced links appear in More only for users who pass their existing gates.
- Mobile navigation does not introduce new authorization logic.

## Desktop Navigation

The persistent Daily navigation is:

- Today
- Clients
- Work

The top bar contains Add Client when permitted, the notification bell, locale control, and user menu. Management is a separate labeled section or deliberate switch, grouped exactly as defined above. Advanced Accounting is visually subordinate to Management and never adjacent to Today as an equal daily destination.

The existing `dashboard?mode=daily` and `dashboard?mode=work` destinations may be retained during the shell phase. A new route is not required merely to achieve the navigation label.

## Role Visibility Matrix

| Capability / Surface | Staff | Founder/Admin | Finance or other roles |
| --- | --- | --- | --- |
| Sign in to active application | Yes when active internal | Yes when active internal | `employee` is inactive/reserved; Partner cannot authenticate |
| Today / Clients / Work | Yes | Yes | No new role is invented |
| Add/Edit Client | Yes through `ClientPolicy` | Yes | According to active internal policy only |
| Calls, appointments, follow-ups, installations, reviews | Yes | Yes | According to active internal policy only |
| Close/Reopen Client | Yes through current update policy | Yes | No separate authority exists |
| Start Subscription | Hidden; current gate denies | Yes through `MANAGE_SUBSCRIPTION_BILLING` | No V1 finance role exists |
| Record Payment | Hidden; current gate denies | Yes through `RECORD_PAYMENT` | No V1 finance role exists |
| View/Print/Download existing contract | Yes through `ContractPolicy::view/download` | Yes | Active internal only |
| Regenerate/Issue/Void/Supersede contract | Hidden | Yes through contract policy | No separate finance role exists |
| Collections / Finance / Reports / Catalog / Settings | Hidden unless current gate changes in a later approved phase | Yes according to `FinancialPermissions` | Do not expose based on label alone |
| Accounting and recovery tools | Hidden | Yes according to strict gates | Do not invent authority |
| Partner identity and source | Visible where operationally relevant | Visible | Referral metadata only |
| Partner commission and override | Hidden | Management only | No partner access |

`FinancialPermissions::allows()` currently grants all listed financial permissions only to owner-level users. Therefore Start Subscription and Record Payment are product actions, but they are not Staff actions in this frozen architecture.

## Today Specification

### Purpose

Today answers one question: **What should I do today?** It is not a KPI dashboard and must not show cash snapshots, MRR, expense totals, or historical charts.

### Time Groups

| Group | Inclusion rule | Sort |
| --- | --- | --- |
| Overdue | Open work whose authoritative due time is before the start of today | Oldest due first, then review severity |
| Next | Open work due now or within the next two hours, plus undated new prospects needing first contact | Due time first; undated prospects by creation time |
| Later Today | Open work due after the Next window and before end of today | Due time first |

The two-hour Next window is a presentation rule and may be configured without changing domain state.

### Work Item Types

Each card has: type, client, due time, short reason, one primary action, optional Call/WhatsApp icon, and an Open Client fallback.

| Type | Canonical source | Direct action |
| --- | --- | --- |
| Call | `active_contact_queue` or due non-installation follow-up | Record Call |
| Appointment | active `Appointment` | Record Result when due; otherwise Open/Reschedule |
| Installation | active installation appointment | Complete Installation when due |
| Follow-up | due `follow_ups` row or decision state | Record Follow-up |
| Collection | `ReceivableService` outstanding/overdue projection | Record Payment only when authorized |
| Review | pending `ClientReviewItem` | Review |

Completed items disappear only when the authoritative source marks them completed/resolved or a safe projection excludes them. The UI must not optimistically delete work from the feed before the server succeeds.

Current support status:

- Operational queues and next-action projection exist and are reusable.
- A unified Overdue/Next/Later Today projection does not yet exist: `IMPLEMENTATION_DEPENDENCY`.
- The current Today ViewModel has deferred renewal/collection queues: authoritative collections must come from `ReceivableService`: `IMPLEMENTATION_DEPENDENCY`.
- `follow_ups` has no completion/status field, and current queue queries can retain stale work and do not consistently exclude subscribers: domain-approved completion semantics are an `IMPLEMENTATION_DEPENDENCY`.

## Work Specification

Work shows all open operational work, not only today.

Filters are exactly: All, Calls, Appointments, Installations, Follow-ups, Collections.

Time groups are exactly: Overdue, Today, Upcoming.

Rules:

- All is selected by default.
- Filters change the list without changing state.
- Upcoming includes future callbacks, appointments, installations, and follow-ups.
- Collections appear only to users allowed to view/record them.
- Empty states name the selected filter and offer the nearest valid action.
- The current `OperationalQueueService` does not provide all future items or collections in one projection; the Work aggregate is an `IMPLEMENTATION_DEPENDENCY` built from existing authorities.

## Clients List Specification

Desktop columns are frozen as:

| Column | Source |
| --- | --- |
| Business | `Client.business_name` plus compact category/location metadata |
| Contact | `Client::preferredOperationalContact()` |
| Stage | normalized `ClientLifecycle` label |
| Next Action | `OperationalQueueService::nextActionFor()` or its approved richer adapter |
| Subscription | Product-aware V2 subscription summary |
| Due | `ReceivableService::clientSummary()` |

Desktop rows expose only the context action and an overflow menu. Mobile uses compact cards with Business, Contact, Stage, Next Action, Due, and one action button.

The current `ClientListViewModel` already supports Business, Contact, Stage, and Next Action. Product subscription and Due projections are `IMPLEMENTATION_DEPENDENCY`; they must be provided by a controller/ViewModel adapter and must not be calculated in Blade.

## Add Client Exact Fields

### Normal Form

| Order | Field | Requirement | Why a human must enter it |
| --- | --- | --- | --- |
| 1 | `business_name` | Required | Real-world identity of the prospect |
| 2 | `business_category` | Required | Real-world business classification |
| 3 | `contact_person` | Optional | Known person to contact; fallback remains available |
| 4 | `phone` | Required | Operational contact number |
| 5 | `city_area` | Required | Human-known operating location |
| 6 | `lead_source` | Required | Human-known acquisition source |

When `lead_source = Partner`, show `partner_id`. It should be required before submission to prevent ambiguous attribution; server-side `required_if` validation is an `IMPLEMENTATION_DEPENDENCY` because the current controller accepts it as nullable.

### Hidden or Deferred Fields

- `business_phone = phone`
- `primary_contact_role = owner`
- `city = city_area`
- `business_type = business_category`
- `number_of_branches = 1`
- `primary_owner_id = authenticated user`
- `stage/status = prospect`
- Partner commission uses `Partner::effectiveDefaultCommissionBps()` through `ClientPartnerAttributionService`.
- `source_reference`, `area`, `notes`, social links, maps URL, detailed location, branch count, and additional contacts move to Expandable Details/Edit Client.
- Commission percentage and attribution notes are Founder/Admin management fields only.

The current `ClientController::store()` already implements all listed defaults except conditional Partner validation. Add Client must never create a subscription, invoice, payment, schedule, or subscriber state.

## Client Workspace Exact Structure

The default workspace is one continuous operational page, not a tab hunt. Its order is frozen:

1. Client Identity
2. Preferred Contact with Call and WhatsApp
3. Current Stage
4. Next Action and due time
5. One Primary Context Action
6. Relevant Secondary Actions
7. Subscriptions by Product
8. Amount Due Summary
9. Contract Status and Access
10. Last 3 Activities
11. Expandable Full History
12. Expandable Details
13. Permissioned Management & Finance

Rules:

- Overview, Contacts, Timeline, Appointments, Installation, Billing, and Notes are content groups, not the normal daily navigation model.
- Full History and Details are collapsed by default.
- Each Product gets its own subscription card. Never merge two Product subscriptions into one status.
- A Product card shows Product, Plan, monthly/annual term, subscription status, next billing date, outstanding amount, and contract status from backend snapshots/projections.
- The total amount due is supplied by `ReceivableService`, not a sum in Blade.
- Technical invoice, allocation, refund, credit-note, journal, and revenue details live under permissioned Management & Finance.
- The existing workspace loads the necessary domain collections, but a richer context-action/Product-card ViewModel is an `IMPLEMENTATION_DEPENDENCY`.

## Context Action Visibility Matrix

| Operational state | Primary action | Relevant secondary actions | Must be hidden |
| --- | --- | --- | --- |
| New Prospect | Record Call | Call, WhatsApp, Edit Details | Complete Installation, Record Payment, raw stage |
| Contacting | Record Call or perform due callback | Create Appointment, Call, WhatsApp | Unrelated installation actions, raw stage |
| Appointment Scheduled | Record Result when due; otherwise Open Appointment | Reschedule, Cancel, Call | Complete Installation unless it is an installation appointment; raw stage |
| Appointment Completed | Perform the chosen next step | Schedule Installation, Follow-up, Start Subscription when authorized | Old meeting questionnaire, raw stage |
| Installation Scheduled | Complete Installation when due | Reschedule, Cancel, Call | Subscription side effects, raw stage |
| Free Installed | Record Follow-up | Call, WhatsApp, Start Subscription when authorized | Repeat completion without another appointment, automatic subscription |
| Follow-up Due | Record Follow-up | Call, WhatsApp, Create Appointment, Start Subscription when authorized | Raw stage, technical follow-up fields |
| Decision Pending | Record Follow-up | Start Subscription when authorized, Create Appointment, Close Client | Raw stage, installation completion without context |
| Active Subscriber Paid | View Product Subscription | View/Print/Download Contract; Add another Product subscription when authorized and eligible | Record Payment when no balance; prospect actions |
| Active Subscriber With Balance | Record Payment when authorized; otherwise View Balance | Contact Client, view invoice summary, contract access | Manual allocation by default, prospect actions |
| Not Interested Review | Review Decision | Resume Contact, Close Client with reason | Automatic close, deleting history |
| Wrong/Invalid Review | Review Contact Data | Edit Contact, Record Call, Close with reason | Automatic close, deleting history |
| Closed | Reopen Client | View History, existing Contracts | All normal daily actions until reopen |

When multiple signals exist, priority is: Closed > Review Required > Overdue Collection for authorized user > Due Installation > Due Appointment > Due Follow-up/Callback > New Contact > Product subscription administration. This priority is a presentation adapter; domain transitions remain service-owned.

## Record Call Flow

**Start state:** Client is open and the actor may update it.

**First question:** What happened?

| Human choice | Inputs shown | Backend mapping | Success and next action |
| --- | --- | --- | --- |
| No Answer | None | `no_answer_busy` | Keep Contacting; next action remains Call/Callback |
| Interested | Optional note | `answered` | Keep Contacting; expose Create Appointment |
| Appointment | Date, time, type | `appointment` | Create appointment; stage becomes Appointment |
| Call Later | Date and time | `callback_later` | Create follow-up; stage becomes Contacting |
| Not Interested | Required reason/note | `not_interested` | Create review item; do not close automatically |
| Wrong / Invalid | Optional note; placed under More outcomes | `wrong_invalid` | Create review item; do not close automatically |

Hidden defaults: `method = phone`; current actor is recorder; appointment attendee defaults to actor; workflow/stage transition remains `ClientOperationalWorkflowService` controlled.

`Interested` is a human label for the existing canonical `answered` result. Wrong/Invalid remains available despite not being one of the five primary choices because removing it would violate the frozen review semantics.

## Appointment Flow

**Start state:** Open client; actor may update it.

Normal fields:

- Date - required
- Time - required
- Type - required (`physical_visit`, `online_demo`, `phone_call`, or installation where context allows)

Advanced optional fields:

- Assigned staff override
- Different location
- Branch
- Notes

Defaults already supported:

- Attendee defaults to the current actor when none is submitted.
- Location may be prefilled from `client.location_text`; it remains editable and is not an authoritative server derivation.

**Success state:** scheduled appointment appears in Today/Work at its due time. Scheduling a normal appointment does not create billing or subscriber state.

## Appointment Result Flow

The first decision is exactly: Attended, No Show, Reschedule, Cancelled.

| Decision | Flow |
| --- | --- |
| No Show | Mark appointment `no_show`; offer Record Call or Reschedule |
| Reschedule | Ask date and time; use `appointments.reschedule` |
| Cancelled | Mark cancelled; keep client history and offer next operational action |
| Attended | Ask only the next step below, plus optional notes |

Attended next steps:

| Next step | Conditional input | Result |
| --- | --- | --- |
| Installation | Installation date and time | Schedule free installation; no financial effects |
| Follow-up | Follow-up date and time | Create follow-up |
| Start Subscription | Subscription flow inputs | Complete appointment, then launch the explicit paid subscription flow; never auto-subscribe |
| Not Interested | Required reason/note | Create Not Interested review; do not auto-close |

The current `MeetingOutcomeController` requires `meeting_type`, `interest_level`, and `next_action`, and `MeetingOutcomeService` does not directly support Start Subscription or Not Interested review as these human choices. A compact request adapter/orchestrator is an `IMPLEMENTATION_DEPENDENCY`. Until it exists, required backend fields cannot be silently omitted. Fields such as package discussed, demo performed, price discussed, customer needs, objections, and customer response are removed from the normal flow and may remain read-only history or an optional management note.

## Installation Flow

**Entry action:** Complete Installation.

Normal inputs:

- Installed item(s) - required only when not unambiguously available from the appointment/work context
- Optional note

Automatic values already supported:

- `installed_at = now` as a visible prefilled value or hidden only after server-side default exists
- `installed_by = current actor`
- `appointment_id` from the opened work item
- stage transition through `FreeInstallationService`
- follow-up at installation time + 3 days, 10:00

Current backend records installed `Service` items or custom item names, not a `Product` relation on the appointment. Therefore the immediate safe UI asks once for installed service/item when needed. Deriving a Product requires an approved appointment/work-context association and is an `IMPLEMENTATION_DEPENDENCY`; a global default Product is forbidden.

`no_follow_up` and `no_follow_up_reason` must not appear in the normal flow because `FreeInstallationService` always creates the three-day follow-up and currently ignores those submitted flags. Installation completion must never create a subscription.

## Follow-up Flow

**Start state:** A callback, post-installation follow-up, or decision follow-up is due.

First question: **What happened?**

| Outcome | Inputs | Success state / next action |
| --- | --- | --- |
| Subscribe | None before launch | Open Start Subscription; subscription is created only after explicit confirmation |
| Wait / Call Later | Date and time | Keep or move to Decision Pending/Contacting through service; create next follow-up |
| Appointment | Date, time, type | Create appointment |
| No Answer | Optional next callback date/time | Record attempt; retain an explicit next action |
| Not Interested | Required reason/note | Create review item; do not auto-close |

The current `FollowUpService` only appends a new follow-up and does not complete the old item or consistently transition lifecycle state. A service-level orchestration/completion rule is an `IMPLEMENTATION_DEPENDENCY`. The UI must not fake completion by removing a row locally.

## Start Subscription Flow

**Visibility:** Founder/Admin only under current `MANAGE_SUBSCRIPTION_BILLING` gate.

**Start state:** Client is not Closed; actor is authorized; selected Product has an eligible sellable Plan/PlanPrice; same-Product active/pending conflicts are rejected by `SubscriptionBillingService`.

Human flow:

1. Select Product.
2. Select Plan.
3. Select Monthly or Annual.
4. If Annual, select Full Payment or Installments.
5. If Installments, select installment count.
6. Start date defaults to today and remains editable.
7. Review the backend-generated commercial summary: price, setup fee, quantity, tax if authoritative, discount if authorized, total, period, invoice obligation, and installment schedule.
8. Confirm Start Subscription.

After confirmation, `SubscriptionBillingService` creates the subscription, first issued invoice, billing period, metric event, subscriber transition, and automatic contract draft. Payment is not created.

Important current constraints:

- Current controller accepts `plan_price_id`, not Product + Plan + interval. A server adapter that resolves the effective `PlanPrice` for the selected date is an `IMPLEMENTATION_DEPENDENCY`. The raw ID must never be shown as a human concept.
- `quantity` affects price and currently must be submitted. The target derives it from the client's branch quantity; server-side derivation/confirmation is an `IMPLEMENTATION_DEPENDENCY` before it can be safely hidden.
- Annual installments currently require `installment_due_day` from `[1, 5, 15, 30]`. No approved default exists. It remains a conditional human field until an approved deterministic policy is implemented.
- Normal staff receive no discount controls. Authorized Founder/Admin may open Advanced Discount; the service remains authoritative.
- `preview_only`, tax BPS, invoice lines, receivable internals, MRR/ARR, revenue schedules, and accounting are never shown as normal inputs.

## Contract Access Flow

Contract creation requires zero repeated business data entry.

1. A successful paid subscription commits.
2. `ContractService` creates an immutable Draft snapshot automatically.
3. The relevant Product subscription card shows contract status.
4. Active internal users may View, Print, or Download according to `ContractPolicy`.
5. Founder/Admin may deliberately regenerate, issue, void, or supersede through management/recovery controls.

Artifact generation failure must show: subscription and invoice succeeded; contract file needs recovery. It must never retry subscription creation.

Current artifacts are private HTML files and downloads use `Contract-{contract_number}.html`. The preferred base filename is `Notify-Contract-{Client}-{Product}-{Date}`. A `.pdf` extension is not frozen as available until PDF generation exists; PDF conversion and the exact recommended `.pdf` filename are an `IMPLEMENTATION_DEPENDENCY`.

## Record Payment Flow

**Visibility:** Founder/Admin only under current `RECORD_PAYMENT` gate.

Normal information and fields:

| Item | Behavior |
| --- | --- |
| Amount Due | Read-only from `ReceivableService` |
| Amount Received | Required; defaults to current outstanding when positive |
| Payment Method | Required human fact |
| Financial Account | Required unless a deterministic valid account can be resolved |

Hidden/defaulted values:

- `received_at = now`, editable only under More Options.
- `auto_allocate_oldest = true` for the normal path.
- Manual allocations, allocation amounts, credit notes, refunds, reversals, and technical references are Advanced only.
- Reference is optional and may be shown conditionally for bank transfer, CliQ, or wallet methods.

The current financial account model has no payment-method default mapping. Therefore Financial Account remains visible and required today. Hiding it requires a deterministic account resolver/configuration and is an `IMPLEMENTATION_DEPENDENCY`; backend validation and `CashMovementService` checks must remain.

On success, `PaymentAllocationService` records the V2 payment, cash movement, and oldest-invoice allocations inside authoritative transaction boundaries. The workspace refreshes the amount due from `ReceivableService`. A payment must never create a subscription.

## Close and Reopen Flow

### Close

- Available from overflow, review resolution context, or a relevant follow-up result.
- Ask for a reason code; ask for a note only when the selected reason needs explanation.
- Use `ClientOperationalWorkflowService::closeClient`.
- Preserve all history and set Closed through the service.
- Never present Closed as a raw stage dropdown value in daily UI.

### Reopen

- Closed workspace primary action is Reopen Client.
- Ask only for the required reason.
- Default reopen target is Prospect, matching the current controller, unless a future workflow deliberately supplies one of the allowed targets.
- Use the explicit `clients.reopen` route; ordinary stage update must not reopen.

## Daily vs Management vs Advanced Mapping

| Surface / Action | Layer | Notes |
| --- | --- | --- |
| Today, Clients, Work | Daily | All active internal users |
| Add Client, Record Call, Appointments, Installation, Follow-up, Close/Reopen | Daily | Policy controlled |
| Start Subscription, Record Payment | Daily context action for authorized Founder/Admin | Hidden for Staff; engine details remain hidden |
| Existing contract access | Daily context | Active internal users can view/download/print |
| Subscription Management, Collections | Management | Operational commercial/receivable review |
| Products & Pricing, Partners | Management | Founder/Admin |
| Finance, Expenses, Capital | Management | Strict financial gates |
| Executive, SaaS Metrics | Management | Reporting gates |
| Import, Conflicts, Settings | Management | Existing permissions only |
| Financial Accounts | Advanced | Configuration/cash integrity |
| Accounting, Revenue Recognition, journals | Advanced | Never daily |
| Credit Notes, Refunds, Reversals, manual allocations | Advanced | Correction workflows |
| PlanPrice versions and legacy compatibility | Advanced | Historical/configuration authority |

## Daily Operation Contracts

| Operation | Start state | Human inputs | Backend authority | Success state | Next action |
| --- | --- | --- | --- | --- | --- |
| Add Client | Actor can create a Client | Six normal fields; Partner only when relevant | `ClientController`, `ClientPartnerAttributionService` | Prospect and optional primary contact created | Open Client Workspace; primary action Record Call |
| Record Call | Open Client; actor can update | Outcome plus only its conditional facts | `ClientOperationalWorkflowService` | Attempt recorded; appointment/follow-up/review and stage handled transactionally | Use resulting appointment, callback, review, or Create Appointment action |
| Create Appointment | Open Client; actor can update | Date, time, type; optional overrides | Appointment controller or workflow service | Scheduled appointment with actor/default attendees | Show in Today/Work at due time; then Record Result |
| Record Appointment Result | Active appointment | Attendance; if attended, next-step decision and its conditional facts | `MeetingOutcomeService` plus approved adapter | Appointment/result recorded and selected workflow started | Installation, follow-up, explicit subscription flow, or review |
| Complete Installation | Due installation work; actor can update | Installed items when not derivable; optional note | `FreeInstallationService` | Installation recorded, stage Installed Free, three-day follow-up created | Record Follow-up when due |
| Record Follow-up | Due callback/trial/decision work | Outcome and only conditional date/reason | Approved orchestration over workflow/follow-up services | Current work completed/superseded and next state recorded | Subscription, appointment, next follow-up, review, or close decision |
| Start Subscription | Open Client; authorized actor; sellable non-conflicting Product | Product, Plan, term, annual terms, start date; read-only review confirmation | `PlanPriceService`, `CommercialPricingService`, `SubscriptionBillingService` | Subscription, invoice, period, metrics event, subscriber state, contract attempt | View contract; Record Payment if money is received |
| Record Payment | Authorized actor; Client has payment context and valid receiving account | Amount received, method, account when unresolved | `ReceivableService`, `PaymentAllocationService`, `CashMovementService` | V2 payment/cash movement recorded and safely allocated | Refresh balance; show remaining due or Paid |
| Close Client | Open Client; actor can update | Reason code and conditional note | `ClientOperationalWorkflowService::closeClient` | Closed with preserved history and audit log | Reopen is the only operational primary action |
| Reopen Client | Closed Client; actor can update | Required reason | `ClientOperationalWorkflowService::reopen` | Client explicitly reopened to approved active state | Record Call or the resolved context action |

## Field Classification Table

| workflow | field | current_status | final_visibility | required_or_optional | default_or_derivation | backend_authority | reason |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Add Client | `business_name` | Visible/required | Normal | Required | None | `ClientController::store` | Human-known identity |
| Add Client | `business_category` | Visible/required | Normal | Required | None | `ClientController::store` | Human-known category |
| Add Client | `contact_person` | Visible; Blade required/server optional | Normal | Optional | Preferred contact can fall back | Client model/contact creation | Name may be unknown at intake |
| Add Client | `phone` | Visible/required | Normal | Required | None | `ClientController::store` | Operational contact fact |
| Add Client | `city_area` | Visible/required | Normal | Required | None | `ClientController::store` | Location fact |
| Add Client | `lead_source` | Visible/required | Normal | Required | None | `ClientController::store` | Acquisition fact |
| Add Client | `partner_id` | Always visible/optional | Conditional | Required when Partner | Selected from active partners | `ClientPartnerAttributionService` | Prevent ambiguous attribution; dependency for server rule |
| Add Client | `source_reference` | Visible/optional | Details later | Optional | None | Client record | Not needed to create prospect |
| Add Client | `business_phone` | Visible/required in Blade | Hidden | Derived | `phone` | `ClientController::store` | Existing safe default |
| Add Client | `primary_contact_role` | Visible/required | Hidden | Derived | `owner` | `ClientController::store` | Existing safe default |
| Add Client | `city` | Visible/optional | Hidden | Derived | `city_area` | `ClientController::store` | Existing safe default |
| Add Client | `area` | Visible/optional | Details later | Optional | None | Client record | Avoid duplicate location entry |
| Add Client | `business_type` | Server optional | Hidden | Derived | `business_category` | `ClientController::store` | Existing safe default |
| Add Client | `number_of_branches` | Server optional | Details later | Optional | `1` | `ClientController::store` | Existing safe default |
| Add Client | `stage`, `status` | Service/controller controlled | Hidden | System | Prospect | `ClientOperationalWorkflowService` | Never human-set in create |
| Add Client | `primary_owner_id` | Controller controlled | Hidden | System | Authenticated user | `ClientController::store` | Existing safe default |
| Add Client | `partner_commission_percentage` | Visible/optional | Management only | Optional | Partner default snapshot | `ClientPartnerAttributionService` | Financial/referral agreement, not Staff input |
| Add Client | `partner_attribution_notes` | Visible/optional | Management only | Optional | None | Attribution service | Agreement metadata |
| Add Client | social/location/notes fields | Some visible or accepted | Expandable Details | Optional | None | Client record | Not required for intake decision |
| Record Call | `result` | Visible/required | Normal first question | Required | Human choice mapped to canonical result | `ClientOperationalWorkflowService` | Core real-world outcome |
| Record Call | `method` | Visible/required | Hidden | Derived | `phone` | `ContactAttemptController` | Action itself is Record Call |
| Record Call | `appointment_date` | Conditional | Conditional | Required for Appointment | None | Workflow service | Real appointment fact |
| Record Call | `appointment_time` | Conditional | Conditional | Required for Appointment | None | Workflow service | Real appointment fact |
| Record Call | `appointment_type` | Conditional | Conditional | Required by frozen UI | None | Appointment domain | Human choice |
| Record Call | `follow_up_date_time` | Conditional | Conditional | Required for Call Later | None | Workflow service | Human commitment |
| Record Call | `note` | Visible | Conditional | Required for Not Interested; otherwise optional | None | Workflow service | Preserves review semantics |
| Record Call | location/attendees/appointment notes | Visible conditional | Advanced conditional | Optional | Location prefill; attendee actor | Appointment service path | Rare overrides |
| Appointment | `appointment_date` | Required | Normal | Required | None | Appointment controller/service | Human commitment |
| Appointment | `appointment_time` | Required | Normal | Required | None | Appointment controller/service | Human commitment |
| Appointment | `appointment_type` | Required | Normal | Required | None | `AppointmentTypes` | Human choice |
| Appointment | `attendees[]` | Optional | Advanced | Optional | Current actor | Existing controllers/services | Existing safe default |
| Appointment | `location` | Optional | Advanced | Optional | Prefill client location when known | Appointment record | Editable real-world detail |
| Appointment | `branch_name`, `notes` | Optional | Advanced | Optional | None | Appointment record | Rare details |
| Appointment Result | `attendance_status` | Required | Normal first decision | Required | None | Appointment/meeting services | Real-world result |
| Appointment Result | `outcome_result` | Optional backend field | Conditional next step | Required when Attended | Human choice mapping | `MeetingOutcomeService` | Drives meaningful action |
| Appointment Result | installation date/time | Conditional | Conditional | Required for Installation | None | `MeetingOutcomeService` | Real-world schedule |
| Appointment Result | next follow-up date/time | Date supported; time not | Conditional | Required for Follow-up | Human chooses both | Follow-up authority | Time support needs adapter |
| Appointment Result | Not Interested reason | No direct meeting-review path | Conditional | Required | None | Review workflow adapter | Must create review, not auto-close |
| Appointment Result | meeting type | Required by controller | Hidden after adapter | Derived | Appointment type | `MeetingOutcomeService` | Already known by system |
| Appointment Result | interest level / next action | Required by controller | Hidden after adapter | Derived only from explicit next-step mapping | Adapter + service | Current technical requirement |
| Appointment Result | package/demo/price/needs/objections/response | Visible current form | Advanced note/history only | Optional | None | Meeting outcome record | Not needed for primary decision |
| Installation | `appointment_id` | Optional select | Hidden in context | Derived | Open work item | `FreeInstallationService` | Already known |
| Installation | `installed_at` | Visible/required | Defaulted; editable in More | Required | Now in UI; server default dependency if fully hidden | Controller/service | Audit time must remain accurate |
| Installation | `installed_by` | Visible/optional | Hidden | Derived | Current actor | Controller/service | Existing default |
| Installation | installed items | Required by service | Normal when not derivable | Required | Derive only from explicit context | `FreeInstallationService` | Backend requires at least one item |
| Installation | `branch_name` | Optional | Advanced | Optional | Appointment branch | Service | Existing safe derivation |
| Installation | `notes` | Optional | Normal | Optional | None | Service | Useful exception detail |
| Installation | next follow-up date/action | Visible | Hidden | System | +3 days at 10:00; service default action | `FreeInstallationService` | Existing automation |
| Installation | `no_follow_up*` | Accepted by controller but ignored by service | Hidden | N/A | Never bypass follow-up | `FreeInstallationService` | Frozen three-day follow-up rule |
| Follow-up | outcome | No canonical compact field | Normal first question | Required | Mapping adapter | Workflow services | Human decision |
| Follow-up | next date/time | Date required in current create | Conditional | Required for Wait/Call Later | None | Follow-up service | Human commitment |
| Follow-up | method/reason/next_action | Required current fields | Hidden after adapter | System | Derived from outcome/context | Follow-up/workflow adapter | Technical duplication |
| Follow-up | notes/result | Visible | Conditional | Required only for Not Interested reason | None | Workflow/review service | Preserve human context |
| Subscription | Product | Implied through selected PlanPrice | Normal | Required | Sellable catalog | Product/Plan models | Multi-Product clarity |
| Subscription | Plan | Implied through selected PlanPrice | Normal | Required | Sellable Product plans | Product/Plan models | Commercial choice |
| Subscription | billing interval | Implied by PlanPrice | Normal | Required | Monthly/Annual | `PlanPriceService` | Commercial choice |
| Subscription | `plan_price_id` | Required current input | Hidden technical value | System | Effective PlanPrice resolver | `PlanPriceService` | Never expose raw ID |
| Subscription | `quantity` | Required | Hidden after adapter | System | Client branch quantity, confirmed when exceptional | Billing service | Price-affecting derivation needs server ownership |
| Subscription | `start_date` | Required/defaulted in Blade | Normal | Required | Today, editable | Billing service | Commercial effective date |
| Subscription | `payment_terms` | Conditional | Conditional for Annual | Required for Annual | Full by default | Billing service | Human agreement |
| Subscription | `installments_count` | Conditional | Conditional | Required for installments | None | Billing/payment schedule services | Human agreement |
| Subscription | `installment_due_day` | Conditional/required by service | Conditional until policy exists | Required today | No approved default | Billing/payment schedule services | Cannot hide without policy |
| Subscription | price/tax/total/schedule | Previewed | Read-only summary | System | Authoritative backend calculation | Pricing/billing services | Never user-calculated |
| Subscription | discount fields | Visible current form | Founder/Admin Advanced | Optional | No global default | Pricing service | Authorized exception only |
| Subscription | `preview_only` | Visible submit control | Hidden implementation detail | System | Review action | Billing controller | Not a human data field |
| Payment | Amount Due | Available projection | Read-only context | System | `ReceivableService` | Receivable service | Authoritative balance |
| Payment | `amount` | Required | Normal | Required | Current outstanding when appropriate | Payment allocation service | Human confirms cash received |
| Payment | `payment_method` | Required | Normal | Required | None | `PaymentMethods` | Real-world fact |
| Payment | `financial_account_id` | Required | Normal until resolver exists | Required | No current default | Financial account/cash services | Financial integrity |
| Payment | `received_at` | Required/defaulted in Blade | More Options | Required | Now | Payment service | Existing safe UI default |
| Payment | `auto_allocate_oldest` | Optional/currently false when absent | Hidden normal | System | True | Payment allocation service | Existing safe allocation path |
| Payment | reference/notes | Optional | Conditional/Advanced | Optional | None | Payment record | Needed only in some methods/cases |
| Payment | manual allocations | Visible current workspace | Advanced | Optional | Oldest-first normal path | Payment allocation service | Correction/exception workflow |
| Close | reason code | Supported by service | Normal | Required | None | Workflow service | Explicit closure fact |
| Close | reason note | Supported | Conditional | Required for Other | None | Workflow service | Explain exceptional reason |
| Reopen | `reason` | Required | Normal | Required | None | Workflow service | Explicit audit trail |
| Reopen | target `stage` | Optional/current default Prospect | Hidden normal | System | Prospect | Reopen controller/service | Avoid raw stage selection |

## Defaults, Derivations, and Automations

| Value / effect | Source | Status |
| --- | --- | --- |
| Client owner | Authenticated user in `ClientController` | `SAFE_WITH_DEFAULT` |
| Prospect stage/status | `ClientController` / lifecycle service | `DO_NOT_CHANGE` |
| Business phone/type/city/branch count | Existing controller defaults | `SAFE_WITH_DEFAULT` |
| Partner commission snapshot | Partner default in attribution service | `SAFE_WITH_DEFAULT` |
| Appointment attendee | Current actor when absent | `SAFE_WITH_DEFAULT` |
| Preferred operational contact | `Client::preferredOperationalContact()` | `SAFE_WITH_DEFAULT` |
| Next client action | `OperationalQueueService` | `SAFE_UI_CHANGE`; richer subscriber/finance action needs adapter |
| Installation actor | Current actor | `SAFE_WITH_DEFAULT` |
| Post-install follow-up | +3 days at 10:00 in service | `DO_NOT_CHANGE` |
| Effective subscription price | PlanPrice authority | `DO_NOT_CHANGE`; human selector adapter required |
| Subscription invoice/period/metrics | `SubscriptionBillingService` and collaborators | `DO_NOT_CHANGE` |
| Contract draft | Automatic after subscription commit | `DO_NOT_CHANGE` |
| Amount due | `ReceivableService` | `DO_NOT_CHANGE` |
| Payment allocation | Oldest invoices through `PaymentAllocationService` | `SAFE_WITH_DEFAULT` when submitted true |
| Payment financial account | No deterministic current default | `IMPLEMENTATION_DEPENDENCY` |
| Follow-up completion | No current authoritative completion marker | `IMPLEMENTATION_DEPENDENCY` |
| Notification deduplication | Deterministic occurrence key | `DO_NOT_CHANGE` |

## Human-Readable Validation Rules

Validation appears beside the affected field and in a concise summary. Never show raw IDs, BPS terminology, table names, engine versions, or Laravel rule text.

| Technical condition | Human copy (AR) | Human copy (EN) |
| --- | --- | --- |
| missing business name | أدخل اسم النشاط. | Enter the business name. |
| missing phone | أدخل رقم هاتف العميل. | Enter the client's phone number. |
| missing partner after Partner source | اختر الشريك الذي أحال العميل. | Select the referring partner. |
| missing call outcome | اختر ما حدث في الاتصال. | Choose what happened on the call. |
| missing callback time | حدد موعد معاودة الاتصال. | Choose when to call back. |
| missing appointment date/time | حدد تاريخ ووقت الموعد. | Choose the appointment date and time. |
| not interested without reason | اكتب سبب عدم الاهتمام للمراجعة. | Add the reason for review. |
| installation without item | اختر ما تم تركيبه. | Select what was installed. |
| unavailable price | لا يوجد سعر فعال لهذه الباقة في التاريخ المحدد. | No active price is available for this plan on the selected date. |
| same-Product conflict | يوجد اشتراك نشط أو تغيير مجدول لهذا المنتج. | This product already has an active subscription or scheduled change. |
| invalid installments | اختر عدداً من 2 إلى 12 قسطاً. | Choose between 2 and 12 installments. |
| missing financial account | اختر الحساب الذي استلم الدفعة. | Select the account that received the payment. |
| invalid payment amount | أدخل مبلغاً أكبر من صفر وبحد أقصى 3 منازل عشرية. | Enter an amount greater than zero with up to 3 decimals. |
| closed subscription attempt | أعد فتح ملف العميل قبل بدء الاشتراك. | Reopen the client before starting a subscription. |
| reopen without reason | اكتب سبب إعادة فتح الملف. | Enter a reason for reopening the client. |

## AR/EN Terminology

| English | Arabic | Internal term not shown to humans |
| --- | --- | --- |
| Today | اليوم | `mode=daily` |
| Clients | العملاء | `clients.index` |
| Work | العمل | queue keys |
| More | المزيد | permission menu |
| Add Client | إضافة عميل | `clients.store` |
| Record Call | تسجيل اتصال | `contact_attempt` |
| Create Appointment | إنشاء موعد | `appointments.store` |
| Record Result | تسجيل النتيجة | `meeting_outcome` |
| Complete Installation | إكمال التركيب | `installed_free` transition |
| Follow-up | متابعة | `follow_ups` |
| Start Subscription | بدء الاشتراك | `plan_price_id`, billing engine |
| Record Payment | تسجيل دفعة | V2 payment engine |
| Amount Due | المبلغ المستحق | receivable projection |
| Product | المنتج | `product_id` |
| Plan | الباقة | `plan_id` |
| Monthly | شهري | `monthly` |
| Annual | سنوي | `annual` |
| Full Payment | دفعة كاملة | `full` |
| Installments | أقساط | `installments` |
| Contract | العقد | contract artifact internals |
| Needs Review | يحتاج مراجعة | review type/status keys |
| Decision Pending | بانتظار القرار | `decision_pending` |
| Closed | مغلق | legacy `archived` mapping |
| Reopen Client | إعادة فتح العميل | `allow_reopen` |

## Mobile Interaction Rules

- Frequent touch targets are at least approximately 44px.
- Daily forms use one column and persistent labels; placeholders are examples only.
- Record Call, Record Result, Complete Installation, and Record Payment open as focused bottom sheets or full-height sheets.
- Add Client uses a full-height sheet or focused page and keeps the submit action reachable above the keyboard.
- Operational tables become cards; financial/management tables may scroll only in their management context.
- One primary action is visually dominant. Secondary actions use icon buttons or overflow.
- Conditional fields appear immediately after the choice that caused them.
- Currency is displayed as JOD with three decimal places where the authoritative value requires it.
- Arabic uses RTL and Cairo; English uses LTR and Inter. Layout uses logical start/end properties.
- Error focus moves to the first invalid field; entered values remain intact.
- Loading disables only the submitted action and prevents duplicate financial/contract submissions.
- Success returns to the originating client/work item and displays the new next action.

## Permission-Sensitive Controls

| Control | Visibility condition | Server authority |
| --- | --- | --- |
| Add/Edit/Close/Reopen Client | Client policy allows create/update | `ClientPolicy` |
| Start Subscription | `MANAGE_SUBSCRIPTION_BILLING` | Gate + billing service |
| Record Payment | `RECORD_PAYMENT` | Gate + allocation/cash services |
| View/Print Contract | Contract view policy | `ContractPolicy` |
| Download Contract | Contract download policy | `ContractPolicy` |
| Regenerate/Issue/Void/Supersede | Contract create/action policy | `ContractPolicy` |
| Manual allocations | Financial permission and management context | Collection controller/services |
| Credits/Refunds/Reversals | Respective strict gate | Financial services |
| Partner commission override | Founder/Admin management context | Attribution service |
| Accounting/Revenue controls | Existing accounting gates | Accounting services |

Blade conditions are presentation only. Every write keeps its current middleware, policy, and gate enforcement.

## Risks and Non-Negotiable Backend Constraints

### Resolved Architecture Conflicts

1. **Wrong/Invalid cannot disappear.** It is retained under More outcomes because current domain semantics require a review item.
2. **Installed Product is not currently a backend fact.** Free installation records Services/custom items. The safe flow asks for installed items until an explicit Product context exists.
3. **Annual due day has no default.** It remains conditional until a business-approved resolver exists.
4. **Payment account has no default mapping.** The account remains visible until deterministic resolution exists.
5. **Contract artifact is HTML, not PDF.** PDF and its filename are a later artifact dependency.
6. **Follow-up completion is not represented.** Automatic disappearance needs an approved completion model/projection.
7. **Staff cannot perform owner-level finance actions.** Start Subscription and Record Payment remain hidden for Staff under current gates.

### Non-Negotiable Constraints

- Add Client creates a prospect only.
- Free installation never creates a subscription.
- Payment never creates a subscription.
- Only explicit `SubscriptionBillingService::startPaidSubscription()` creates paid subscriber state.
- No daily stage dropdown and no forced subscriber stage.
- PlanPrice remains recurring price authority.
- One Client may subscribe to multiple Products, but not conflicting active/pending Plans for the same Product.
- Annual installments remain one annual subscription and one annual invoice obligation.
- Invoice, receivable, allocation, cash, accounting, revenue, and SaaS metric authorities remain intact.
- Contract failure never rolls back a committed subscription or invoice.
- Wrong/Invalid and Not Interested retain review semantics.
- Closed history is preserved and reopen is explicit.
- `auth` plus `EnsureActiveInternalUser`, policies, gates, and `FinancialPermissions` remain intact.
- Legacy compatibility routes and data never become active authority again.

## Implementation Dependencies

| ID | Dependency | Needed for | Boundary |
| --- | --- | --- | --- |
| UX-D01 | Unified operational work projection with due timestamp/type/action | Today and Work grouping | Read adapter over existing sources |
| UX-D02 | Permissioned collection work projection from `ReceivableService` | Today/Work Collections | Read adapter; no duplicate AR math |
| UX-D03 | Approved follow-up completion/supersession semantics and subscriber exclusion | Completed work disappearance | Domain decision; may require additive backend change |
| UX-D04 | Product subscription and due summary in Client list ViewModel | Final Clients columns | Read adapter/eager loading |
| UX-D05 | Rich context-action resolver including receivables and Product state | One primary workspace action | Read adapter over authorities |
| UX-D06 | Conditional Partner server validation | Clean Add Client attribution | Narrow validation change |
| UX-D07 | Compact appointment-result adapter | Minimal Attended flow | Controller/orchestration adapter; no service bypass |
| UX-D08 | Follow-up result orchestration | Subscribe/Wait/Appointment/Review outcomes | Service adapter using existing authorities |
| UX-D09 | Installation appointment-to-Product/Service context | Derive installed item when unambiguous | Approved context association; no global default |
| UX-D10 | Human Product/Plan/interval resolver to effective PlanPrice | Subscription selector | Server adapter using `PlanPriceService` |
| UX-D11 | Server-owned branch quantity derivation/confirmation | Hide subscription quantity | Billing request adapter |
| UX-D12 | Approved annual installment due-day policy | Remove due-day question | Human business decision then service adapter |
| UX-D13 | Deterministic payment-method-to-account resolver/configuration | Hide financial account | Must preserve account validation |
| UX-D14 | PDF contract artifact generation | `.pdf` download and recommended filename | Contract artifact layer only |
| UX-D15 | AR/EN copy additions for new labels and validation | Complete localization | Translation files in implementation phase |

## Open Questions Requiring Human Decision

These questions do not block Phase 2 Application Shell because the safe fallback is specified.

1. Which allowed day (`1`, `5`, `15`, or `30`) should be the default installment due day, or should it remain a required annual-installment choice?
2. Should follow-up completion be represented by an additive status/completed timestamp, or by an approved supersession/event projection? Current rows have no completion state.
3. Is a PDF contract legally/operationally required for V1, or is the current private HTML download acceptable?
4. Should free-installation appointments gain an explicit Product/Service context, or should the installer always confirm installed items?

## Acceptance Criteria for Phase 2 Application Shell

Phase 2 is limited to the shell and navigation. It must not implement the workflow adapters listed above.

1. Desktop primary Daily navigation shows Today, Clients, and Work.
2. Mobile bottom navigation shows Today, Clients, Work, and More in that order.
3. Notifications remain a header bell with unread badge and are not a primary tab.
4. Add Client is available from Today/Clients only when `ClientPolicy::create` allows it.
5. Founder/Admin can deliberately enter the grouped Management destinations; Staff cannot see gated finance/system destinations.
6. Advanced Accounting and engine controls are visually subordinate and permission gated.
7. Existing route names may be reused; no public or weakened route is introduced.
8. Active states work for current query-mode destinations and nested management routes.
9. Arabic RTL and English LTR navigation labels fit without overlap on desktop and 390px mobile.
10. Touch targets meet the mobile interaction rule and More behaves as an accessible sheet/menu.
11. The shell contains no KPI calculations and does not move financial formulas into Blade or JavaScript.
12. Guest and legacy Partner access remain blocked by existing middleware and identity rules.
13. No migration, business service, lifecycle rule, financial authority, or permission definition changes in Phase 2.
14. Frontend implementation is verified with the project build and focused authorization/navigation tests; no server or database reset is required.
15. Phase 2 stops after the Application Shell checkpoint and waits for approval before forms/workflows are simplified.

## Frozen Outcome

Notify Desk V1 will present a small Daily product surface centered on Today, Clients, Work, and one context-aware action. Management remains available intentionally, and the financial/accounting engine remains protected and mostly invisible. Every hidden value in this architecture either has a proven current source or is explicitly blocked behind an implementation dependency. No UI simplification is allowed to weaken a V1 domain authority.
