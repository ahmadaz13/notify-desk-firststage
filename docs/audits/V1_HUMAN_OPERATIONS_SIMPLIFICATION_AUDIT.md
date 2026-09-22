# Notify Desk V1 - Human Operations Simplification Audit

Task ID: `NOTIFY_V1_HUMAN_OPERATIONS_SIMPLIFICATION_AUDIT`  
Repository: `C:\notifydesk-laravel`  
Mode: read-only application audit. No PHP, Blade, CSS, JS, translation, route, migration, test, or data changes were made.

## Executive Summary

التطبيق قوي وظيفياً، لكن سطح الاستخدام اليومي يخلط بين عمل الميدان السريع وبين إدارة مالية/محاسبية متقدمة. المشكلة ليست ضعف backend؛ بالعكس، أغلب التعقيد الذي يظهر للمستخدم له خدمة خلفية موجودة وقادرة على توليده أو حمايته: `CommercialPricingService`, `SubscriptionBillingService`, `InvoiceService`, `ReceivableService`, `PaymentAllocationService`, `FreeInstallationService`, `ClientOperationalWorkflowService`, `ContractService`, `RevenueRecognitionService`, `SaasMetricEventService`.

النتيجة الأساسية: موظف يومي يفترض أن يعمل من `Today`, `Clients`, و `Client Workspace` فقط، لكن `Client Workspace` خصوصاً تبويب `subscription-billing` يعرض اشتراكات، فواتير، دفعات، تخصيصات، إشعارات دائن، استردادات، عقود، أثر محاسبي، وعروض تجارية داخل نفس المساحة. هذا مناسب للمدير أو المؤسس، لكنه ثقيل على شخص يسجل مكالمة أو تركيب أو دفعة أثناء العمل.

أكبر فرص التبسيط الآمنة:

- `Add Client` يمكن تقليصه من 15 حقل ظاهر إلى 6-7 حقول أساسية: `business_name`, `business_category`, `contact_person`, `phone`, `city_area`, `lead_source`, وربما `source_reference`.
- `Start Paid Subscription` يمكن أن يصبح اختيار `Product/Plan/Billing interval/Payment terms/Start date/Confirm` مع عرض السعر للقراءة فقط؛ السعر والضريبة والفاتورة والذمم والعقد والجدول والـ MRR/ARR كلها backend outputs.
- `Record Payment` يمكن أن يبدأ من "المبلغ المستحق الآن" + `amount` + `payment_method` + الحساب عند الحاجة؛ `PaymentAllocationService::autoAllocateOldest()` يسمح بإخفاء التخصيص في الحالة الشائعة.
- `Complete Installation` يطلب اليوم عناصر مركبة وموعد ومنفذ ومتابعة؛ الخدمة أصلاً تولد متابعة بعد 3 أيام افتراضياً، لذلك يمكن جعلها قراراً واحداً: "تم التركيب" + المنتج/النظام + ملاحظة قصيرة.
- الجداول الكبيرة يجب أن تبقى للإدارة والتحليل. اليوم اليومي يجب أن يكون queues/cards/actions.

## Current Human Experience

المستخدم يرى بنية جيدة لكنها مزدحمة:

- شريط جانبي: `Today`, `Clients`, `Sales`, `Finance`, `Reports`, `Administration`, `Advanced Accounting`.
- موبايل: `Today`, `Clients`, `Work`, و `More` يضم `Notifications`, `Import`, `Collections`, `Catalog`, `Review Work`, `Settings`, `Accounting`.
- `Today` يستخدم queues مناسبة: callbacks, appointments, installations, trial follow-ups, decision pending, client reviews, new prospects.
- `Clients` يعرض جدول desktop وبطاقات mobile، مع next action.
- `Client Workspace` يعرض header جيد و next action، لكنه يفتح 7 tabs: overview, contacts, timeline, appointments, installation, billing, notes.
- تبويب `billing` وحده أصبح mini-ERP داخل ملف العميل.

المبدأ المقترح: اليومي لا يرى engine. المدير يرى engine عند الحاجة.

## Navigation Inventory

| Navigation | Route | Category | Intended user | Daily visible? | Notes |
|---|---|---:|---|---:|---|
| Today | `/` `dashboard` | DAILY | Staff/founder | Yes | أفضل نقطة بداية يومية، queues موجودة. |
| Clients | `/clients` | DAILY | Staff/founder | Yes | يجب أن يبقى، لكن table desktop فقط. |
| Add Client FAB | `/clients/create` | DAILY | Staff | Yes | جيد كاختصار، لكن form طويل. |
| Work tab | `/?mode=work` | DAILY | Staff | Yes | صالح كتصنيف queues. |
| Sales/Billing | `/subscription-billing` | MANAGEMENT | Founder/admin | No for normal daily | Renewal operations, not field entry. |
| Collections | `/collections` | MANAGEMENT | Finance/founder | No | يجب أن يظهر كإدارة تحصيل، لا workflow يومي. |
| Finance | `/finance` | MANAGEMENT | Founder/finance | No | Reports. |
| Financial Accounts | `/financial-accounts` | ENGINE/MANAGEMENT | Finance | No | Cash subledger. |
| Operating Expenses | `/operating-expenses` | MANAGEMENT | Finance/founder | No | Expense ops, not core field workflow. |
| Capital Management | `/capital-management` | MANAGEMENT | Founder/finance | No | Capital/fixed assets. |
| Executive | `/executive` | MANAGEMENT | Founder | No | Management dashboard. |
| SaaS Metrics | `/saas-metrics` | MANAGEMENT/ENGINE | Founder | No | SaaS reporting engine. |
| Accounting | `/accounting` | ENGINE | Finance/admin | No | Journal/revenue recognition engine. |
| Settings | `/settings` | MANAGEMENT/ENGINE | Admin | No | Financial settings. |
| Commercial Catalog | `/commercial-catalog` | MANAGEMENT/ENGINE | Admin | No | Product/plan/price authority. |
| Partners | `/partners` | MANAGEMENT | Admin | No for field staff | Referral admin. |
| Partner Dashboard | `/partner/dashboard` | MANAGEMENT | Partner/internal | No for internal daily | Isolated referral view. |
| Conflicts | `/conflicts` | MANAGEMENT | Admin | No | Review/resolve conflicts. |
| CSV Import | `/clients-import` | MANAGEMENT | Admin/staff batch import | No normal daily | Useful batch tool. |
| Notifications | `/notifications` | DAILY SUPPORT | Staff/founder | Secondary | Activity inbox, not primary work surface. |
| Public Client Form | `/p/{uuid}/client/create` | PARTNER/LEGACY INPUT | External partner | No internal nav | Simple 4-field public capture. |
| Contracts preview/print/download | `/contracts/{contract}/...` | MANAGEMENT/LEGAL | Admin/founder | No | Contract artifact operations. |

## Screen Inventory

Scanned: 59 Blade views, 39 controllers, 41 services, 60 models, 42 migrations, 57 feature tests.

| Screen | View | Controller | Category | Purpose | Daily frequency | Normal nav? |
|---|---|---|---:|---|---:|---:|
| Login | `auth.login` | `AuthController` | DAILY SUPPORT | Internal access | Daily | Yes before auth |
| Today dashboard | `dashboard` | `DashboardController@index` | DAILY | Queues, notes, appointments, activity | High | Yes |
| Clients list | `clients.index` | `ClientController@index` | DAILY | Find/open clients | High | Yes |
| Add client | `clients.create` | `ClientController@create/store` | DAILY | New prospect capture | High | Yes |
| Edit client | `clients.edit` | `ClientController@edit/update` | DAILY/MANAGEMENT | Correct details | Medium | From client |
| Client workspace | `clients.show` + partials | `ClientController@show` | DAILY + MANAGEMENT + ENGINE | Operational file and financial file | High | Yes, but sections need separation |
| Contact outcome modal | `components.notify.contact-outcome` | `ContactAttemptController@store` | DAILY | Log call result | High | Yes |
| Appointment outcome | `appointments.outcome` | `MeetingOutcomeController` | DAILY | Meeting result | Medium | Yes from appointment |
| Collections | `collections.index` | `CollectionsController@index` | MANAGEMENT | Outstanding invoices/credits | Medium | No daily |
| Subscription billing | `subscription-billing.index` | `SubscriptionBillingController` | MANAGEMENT/ENGINE | renewals/reviews | Periodic | No daily |
| Commercial catalog | `commercial-catalog.index` + partial | `CommercialCatalogController` | ENGINE | Product/Plan/PlanPrice source | Rare | No |
| Finance reports | `finance.index` | `FinanceReportController` | MANAGEMENT | Financial reporting | Weekly/monthly | No |
| Executive | `executive.index` | `ExecutiveDashboardController` | MANAGEMENT | Executive KPIs | Weekly | No |
| SaaS metrics | `saas-metrics.index` | `SaasMetricsController` | ENGINE/MANAGEMENT | MRR/ARR metrics | Weekly/monthly | No |
| Financial accounts | `financial-accounts.index` | `FinancialAccountController` | ENGINE/MANAGEMENT | Cash accounts/transfers | Periodic | No |
| Operating expenses | `operating-expenses.index` | `OperatingExpenseController` | MANAGEMENT | Expense and recurring obligations | Medium | No for sales staff |
| Capital management | `capital-management.index` | `CapitalManagementController` | MANAGEMENT | Funding/assets | Rare | No |
| Accounting | `accounting.index` | `AccountingController` | ENGINE | Ledger/revenue recognition | Rare | No |
| Settings | `settings.index` | `SettingsController` | ENGINE | V1 settings/activity logs | Rare | No |
| Partners CRUD | `partners.*` | `PartnerController` | MANAGEMENT | Partner records | Medium | Admin only |
| Partner dashboard | `partners.dashboard` | `PartnerDashboardController` | MANAGEMENT | Partner client view | Medium | Separate role |
| Conflict resolution | `conflicts.*` | `ConflictResolutionController` | MANAGEMENT | Resolve referral conflicts | Low | Admin only |
| Client import | `clients.import` | `CsvImportController` | MANAGEMENT | CSV bulk import | Low | No daily |
| Notifications | `notifications.index` | `NotificationController` | DAILY SUPPORT | Notification inbox | Medium | Secondary |
| Contract template | `contracts.template` | `ContractController` | LEGAL/MANAGEMENT | Contract preview/print/download | Medium after subscription | Not daily |
| Public client form | `public-client-form` | `PublicClientController` | PARTNER INPUT | External referral prospect | As needed | Public link only |

## Daily Workflow Inventory

| Operation | Entry point | Current visible fields | Required fields | Records/services | Side effects | Minimum necessary |
|---|---|---:|---|---|---|---|
| Add Client | `/clients/create` | 15 | `business_name`, `business_category`, `business_phone`, `city_area`, `contact_person`, `phone`, `primary_contact_role`, `lead_source` by UI; controller also requires `phone` not `business_phone` | `Client`, optional `ClientContact`, activity log, `ClientPartnerAttributionService` | defaults `business_type`, `city`, branches, stage/status, owner | business name, type, preferred contact name/mobile, city/area, optional source |
| Edit Client | `/clients/{client}/edit` | 21 | `business_name`, `phone`, `city_area`, `business_category`, `lead_source` | updates `Client`, primary contact, attribution | activity log | only changed facts; advanced details collapsed |
| Record Contact Attempt | Client workspace contacts/modal | 11 | `method`, `result`; conditional date/time/note | `ContactAttempt`, maybe `Appointment`, `follow_ups`, `ClientReviewItem` | stage transition, activity logs | result + note/date only when required |
| Schedule Callback | Contact outcome `callback_later` | `method`, `result`, `next_action`, `follow_up_date_time`, note | `method`, `result`, follow-up time | `ClientOperationalWorkflowService::scheduleFollowUp` | client stage `contacting` | callback time; default method/next_action |
| Create Appointment | Workspace appointments or contact outcome | 10-11 | `client_id`, `appointment_date`, `appointment_time`, `appointment_type` | `Appointment`, attendees pivot | activity log; from contact outcome changes stage | date, time, type |
| Reschedule Appointment | inline appointment form | 4 | `appointment_date`, `appointment_time` | `FreeInstallationService::rescheduleAppointment` | status scheduled, optional stage update for installation | date/time only; retain previous location/branch |
| Complete Appointment | `/appointments/{appointment}/outcome` | 12 | `attendance_status`, `meeting_type`, `interest_level`, `next_action` | `MeetingOutcomeService` | complete appointment, follow-up/stage effects | outcome decision + next action |
| Complete Free Installation | installation tab | 11+ | `installed_at`; service requires at least one `service_ids[]` or `custom_item_names` | `FreeInstallationService::completeInstallation` | installation item snapshots, appointment completed, stage `installed_free`, follow-up auto-created | installed successfully + product/system + short note |
| Record Follow-up | installation/follow-up tab | 6 | `method`, `reason`, `next_action`, `next_follow_up_date` | `FollowUpService` | follow-up row | result/next action; defaults for method/reason/date |
| Mark Not Interested | Contact outcome | `result`, note | note required by service | `ClientReviewItem` | does not auto-close | one decision + reason |
| Mark Wrong/Invalid | Contact outcome | `result`, optional note | result | `ClientReviewItem` | stays active for review | one decision; review queue handles rest |
| Close Client | stage form / destroy maps to close | 2 | stage `closed`; if reason `other`, note required by service | `ClientOperationalWorkflowService::closeClient` | status archived, closed_at, log | reason code + optional note |
| Reopen Client | route exists | 2 | `reason`, optional stage | `ClientOperationalWorkflowService::reopen` | stage/status restored, log | reason only; default stage prospect |
| Start Paid Subscription | billing tab `convert-section` | 9 | `plan_price_id`, `quantity`, `start_date` | `SubscriptionBillingService`, `CommercialPricingService`, `InvoiceService`, `PaymentScheduleService`, `ContractService`, `SaasMetricEventService` | subscription active, invoice issued, receivable, revenue schedules, contract draft, metrics, logs | product/plan, monthly/annual, full/installments, start date |
| Record Payment | billing tab payment forms | common payment: 6-8; quick invoice payment: 4 hidden allocation fields | `amount`, `financial_account_id`, `payment_method`, `received_at` | `PaymentAllocationService::recordV2Payment` | cash movement, optional allocation, logs, accounting posting | amount received, method/account only if not defaultable |

## Complete Form Field Audit

Classification legend: `ESSENTIAL_HUMAN_INPUT`, `OPTIONAL_HUMAN_INPUT`, `AUTO_DERIVABLE`, `DEFAULTABLE`, `ADVANCED_ONLY`, `MANAGEMENT_ONLY`, `LEGACY_ONLY`, `REMOVE_FROM_NORMAL_FLOW`.

| Screen | Fields exposed | Classification summary | Normal-flow recommendation |
|---|---|---|---|
| `clients.create` | `business_name`, `business_category`, `business_phone`, `city_area`, `contact_person`, `phone`, `primary_contact_role`, `lead_source`, `source_reference`, `partner_id`, `partner_commission_percentage`, `partner_attribution_notes`, `city`, `area`, `notes` | Essential: `business_name`, `business_category`, `contact_person`, `phone`, `city_area`; Defaultable: `primary_contact_role`, `lead_source`; Optional: `source_reference`, `notes`; Advanced/conditional: partner fields; Auto-derivable: `city`, `area`, `business_phone` fallback | Move partner block behind `lead_source = Partner`; hide commission by default; merge city/area. |
| `clients.edit` | `business_name`, `phone`, `business_phone`, `city_area`, `business_category`, `business_type`, `city`, `area`, `number_of_branches`, `lead_source`, `contact_person`, `primary_contact_role`, `source_reference`, `instagram`, `website`, `maps_url`, `location_text`, `partner_id`, `partner_commission_percentage`, `partner_attribution_notes`, `notes` | Essential only when changed; Advanced: `instagram`, `website`, `maps_url`, `number_of_branches`, partner fields; Auto-derivable/defaultable: `business_type`, `city`, branch count | Use "Basic" and "Details" sections; edit later, not Add Client. |
| `components.notify.contact-outcome` | `method`, `next_action`, `result`, `appointment_date`, `appointment_time`, `appointment_type`, `location`, `attendees[]`, `appointment_notes`, `follow_up_date_time`, `note` | Essential: `result`; Defaultable: `method`, `next_action`, `appointment_time`, `appointment_type`, `location`, `attendees[]`; Conditional essential: appointment date/time, callback time, not-interested note | Keep modal, but show only conditional fields after result selection. |
| `clients.workspace.overview` | `stage`, `closed_reason` | Management/daily-sensitive. Stage can be dangerous if exposed broadly. | Replace with guided actions: close/reopen/review; hide raw stage select from daily. |
| `clients.workspace.contacts` | `name`, `role`, `primary_phone`, `whatsapp_number`, `preferred_contact_method`, `is_primary` | Essential: `name`; Optional: phones/method; Defaultable: `is_primary` first contact | Keep under details, not primary daily action. |
| `clients.workspace.appointments` | `client_id`, `appointment_date`, `appointment_time`, `appointment_type`, `location`, `branch_name`, `attendees[]`, `notes`, `status`, `next_stage` | Essential: date/time/type; Defaultable: attendees, location; Advanced/conditional: cancellation `next_stage` | Quick action card: date/time/type/save. |
| `appointments.outcome` | `attendance_status`, `meeting_type`, `interest_level`, `package_discussed`, `demo_performed`, `price_discussed`, `customer_needs`, `main_objections`, `customer_response`, `next_action`, `next_follow_up_date`, `meeting_notes` | Essential: attendance, interest, next_action; Optional: notes/needs/objections; Defaultable: meeting type, checkboxes, follow-up date | Convert to outcome choices first, notes second. |
| `clients.workspace.installation-followup` | `appointment_date`, `appointment_time`, `attendees[]`, `branch_name`, `location`, `notes`, `appointment_id`, `installed_at`, `installed_by`, `service_ids[]`, `custom_item_names`, `next_follow_up_date`, `next_action`, `no_follow_up`, `no_follow_up_reason`, `method`, `reason`, `result`, `next_stage`, `status` | Essential: install decision + installed item; Defaultable: installed_at/by, next follow-up date/action, attendees; Conditional: no follow-up reason; Advanced: raw status/next_stage | Split: "Schedule install" and "Complete install"; complete install should be 3 fields. |
| `clients.workspace.subscription-billing` | `plan_price_id`, `quantity`, `start_date`, `payment_terms`, `installments_count`, `installment_due_day`, `discount_jod`, `discount_reason`, `notes`, `preview_only`, `amount`, `financial_account_id`, `received_at`, `payment_method`, `reference`, `auto_allocate_oldest`, `allocations[0][invoice_id]`, `allocations[0][amount]`, `invoice_id`, `issue_date`, `due_date`, invoice lines, credit note lines, refund fields, void/reverse reasons, offer fields, contract actions | Normal subscription essentials: `plan_price_id`, `start_date`, maybe `quantity`; Payment essentials: `amount`, `payment_method`; Management/advanced: allocation, credit notes, refunds, void/reversal, accounting trace, one-time invoices, offers | Break into normal quick actions and advanced finance drawer. |
| `collections.index` | `client_id`, `due_state`, `settlement_state`, `date_from`, `date_to` | Management filters | Keep management only. |
| `subscription-billing.index` | `dry_run` | Engine operation | Management/engine only. |
| `commercial-catalog.index` + partial | `code`, `name_ar`, `name_en`, `description_ar`, `is_active`, `product_id`, `tier`, `offer_type`, `services[]`, `billing_interval`, `amount_jod`, `setup_fee_jod`, `included_branch_quantity`, `additional_branch_price_jod`, `default_tax_rate_bps`, `effective_from` | Engine source of truth | Hide from daily. |
| `settings.index` | `allow_auto_transfer_clients`, `annual_discount_percentage`, `sales_tax_percentage`, `monthly_due_day`, `type` | Engine settings | Hide from daily. |
| `financial-accounts.index` | `code`, `name_ar`, `type`, `opening_balance`, `opening_date`, `notes`, transfer fields, historical cash assignment fields | Engine/finance | Hide from daily. |
| `operating-expenses.index` | 36 fields including expense, recurring template, vendor, category | Management finance | Separate from CRM daily. |
| `capital-management.index` | 32 fields including funding and fixed asset records | Management finance | Hide from daily. |
| `accounting.index` | `dry_run`, `through`, `recognition_date`, `note`, `account_id`, `notes`, `reason` | Engine | Advanced only. |
| `partners.create/edit/dashboard` | partner company/contact/email/phone/commission/status/filter fields | Management | Admin only. |
| `public-client-form` | `name`, `phone`, `area`, `source` | Essential external intake | Good simple model for Add Client. |

## Add Client Deep Audit

Current form: 15 visible fields. Controller accepts 18+ fields and defaults some server-side:

- Current visible: `business_name`, `business_category`, `business_phone`, `city_area`, `contact_person`, `phone`, `primary_contact_role`, `lead_source`, `source_reference`, `partner_id`, `partner_commission_percentage`, `partner_attribution_notes`, `city`, `area`, `notes`.
- Server-derived/defaulted today: `business_phone = phone` if null, `business_type = business_category`, `city = city_area`, `number_of_branches = 1`, `stage = prospect`, `status = prospect`, `primary_owner_id = auth()->id()`.
- Current mismatch: UI marks `business_phone` required, while controller makes `business_phone` nullable and requires `phone`. This is friction: business phone should be fallback, not equal priority with decision-maker mobile.

Minimum recommended form:

1. `business_name`
2. `business_category`
3. `contact_person`
4. `phone`
5. `city_area`
6. `lead_source` defaulted to most common source
7. optional `source_reference`

Move under optional details: `business_phone`, `notes`, `primary_contact_role`, `instagram`, `website`, `maps_url`, `location_text`, `number_of_branches`.

Move to edit later: `city`, `area`, `business_type`, social/web/location details.

Remove from normal add flow: `partner_commission_percentage`, `partner_attribution_notes` unless source/lead is Partner.

Safe automation: partner commission can default from `Partner::effectiveDefaultCommissionBps()` through `ClientPartnerAttributionService`; no daily user needs to enter BPS/percentage on first capture.

## Client Workspace Deep Audit

Above the fold today:

- business name, stage badge, subtitle with type/location/phone
- next action
- call/WhatsApp buttons
- convert button
- metrics: contact, operations, billing
- tabs for overview, contacts, timeline, appointments, installation, billing, notes

Useful for field work:

- identity and preferred contact
- current lifecycle stage
- next action
- quick actions: record call, create appointment, complete install, start subscription, record payment
- last activity and short history

History-only:

- timeline
- appointments history
- follow-up list
- contact attempts
- offers
- contract history

Management/financial only:

- invoice list and line details
- payment allocations and reversals
- credit notes/refunds
- accounting trace
- billing periods and lifecycle events
- contract issue/void/supersede controls

Recommendation: one primary "Operating Summary" tab should replace daily tab-hopping:

- Client identity
- Preferred contact
- Stage
- Next action
- Quick actions
- Subscription status and amount due summary
- Last activity
- Expandable history

Keep `billing`, `accounting trace`, `credits/refunds`, and contract administration under management/advanced sections.

## Subscription Start Deep Audit

Current visible inputs in normal start area: `plan_price_id`, `quantity`, `start_date`, `payment_terms`, `installments_count`, `installment_due_day`, `discount_jod`, `discount_reason`, `notes`, `preview_only`.

Actually required by controller: `plan_price_id`, `quantity`, `start_date`.

Values already owned by catalog/backend:

- Product/plan/price from `Product -> Plan -> PlanPrice`
- billing interval from `PlanPrice::billing_interval`
- amount, setup fee, included branches, additional branch price, tax from `PlanPrice`
- totals from `CommercialPricingService::calculateSubscription`
- invoice lines from `InvoiceService::createIssuedForSubscription`
- revenue schedules from `RevenueRecognitionService`
- MRR/ARR events from `SaasMetricEventService`
- contract draft from `ContractService`

Inputs that can disappear from normal flow:

- `discount_jod`, `discount_reason` unless manager override
- `quantity` if branch count can default from client `number_of_branches`
- `installment_due_day` can default to 1 or configured setting
- `notes` can be optional details
- `preview_only` can be automatic inline preview, not a submit mode

Inputs that must remain:

- selected offer/product/plan
- monthly vs annual
- full annual vs installments
- start date
- explicit confirm

Recommended click count: from client workspace, 2-4 clicks: Start Subscription -> choose plan terms -> Confirm. Current flow is closer to 1 tab switch + scroll + 9 fields + preview/submit.

## Payment Entry Deep Audit

Current payment entry in client billing includes:

- `amount`
- `financial_account_id`
- `payment_method`
- `received_at`
- `reference`
- `auto_allocate_oldest`
- optional/manual `allocations[0][invoice_id]`, `allocations[0][amount]`

Controller requires: amount, account, method, received_at. Service additionally enforces positive amount, account can receive ordinary movement, and allocation cannot exceed invoice outstanding/unallocated payment.

Backend authority that can remain invisible:

- `PaymentAllocationService::autoAllocateOldest()` allocates against issued invoices by due date.
- `ReceivableService` computes outstanding, credits, settlement status, aging.
- `CashMovementService::recordPaymentReceipt()` posts cash movement.
- `BillingAccountingService::postPaymentAllocation()` posts accounting on allocation.

Recommended normal UX:

1. Show current amount due.
2. Default amount to outstanding balance.
3. Enter/confirm amount received.
4. Select method; default date to now.
5. Account selection only if multiple active cash accounts or method requires it.
6. Optional reference/note.
7. Auto allocate oldest by default; show allocation in details after saving.

Do not simplify away payment integrity: amount, account, received_at, and method must still be validated server-side.

## Calls / Appointments / Installation Audit

Calls:

- Current modal is close to target, but starts with `method` and `next_action` before the decision.
- Target should be outcome-first: No Answer, Interested, Appointment Set, Call Later, Not Interested.
- Conditional fields appear only after outcome.

Appointments:

- Current fields: date, time, type, location, branch, attendees, notes.
- Minimum: date, time, type, save. Location defaults from client; attendee defaults to actor.

Installation:

- Current completion asks for appointment, installed_at, installed_by, branch, service_ids, custom items, follow-up date, next_action, no_follow_up/no reason, notes.
- `FreeInstallationService::completeInstallation()` already defaults installed_by and post-install follow-up at 3 days 10:00 when not provided.
- Minimum: installed successfully, product/system installed, short note. Follow-up date/action generated automatically.

## Tables and Lists Audit

| Surface | Current presentation | Usage | Recommendation |
|---|---|---|---|
| Clients desktop | Table with 8 columns | Daily and management | Desktop keep table; mobile cards are good. |
| Today queues | Cards | Daily | Keep queue cards. Add direct quick actions. |
| Client workspace history | Lists/cards | Daily detail/history | Collapse by default. |
| Subscription billing tab | Large mixed lists/cards | Management/engine | Split normal quick actions from management sections. |
| Collections | Filtered lists/cards | Management | Keep management queue. |
| Subscription billing renewals | Collapsible lists | Engine/management | Keep outside daily nav. |
| Finance reports | 8 tables | Management | Keep tables. |
| Executive | KPI tables | Management | Keep. |
| SaaS metrics | tables | Management/engine | Keep. |
| Accounting | 7 tables | Engine | Hide under Advanced. |
| Settings activity logs | tables | Management/engine | Hide. |
| Partners dashboard | table | Partner/admin | Keep role-specific. |
| Conflicts | table | Admin review | Keep. |
| Import preview | tables | Batch management | Keep. |

Tables scanned by static Blade extraction: 31. Recommended: keep most financial/accounting tables, but keep them out of normal daily navigation.

## Automation Opportunity Map

| Current human action | Existing backend capability | Safe to automate | Risk | Recommended UX |
|---|---|---:|---|---|
| Enter current date/time | `now()` defaults already used in Blade/controllers | Yes | Low | Default silently, allow edit. |
| Pick primary owner | `auth()->id()` in `ClientController@store` | Yes | Low | Hide from form. |
| Duplicate city/area | `city = city_area` default exists | Yes | Low | One location field. |
| Enter business type and category separately | `business_type = business_category` default exists | Yes | Low | One business type field. |
| Enter branch count on add | default `1` exists | Yes | Low | Edit later. |
| Partner commission | Partner default commission + attribution service | Yes, conditional | Medium | Only show override to admin/advanced. |
| Select appointment attendee | defaults to actor | Yes | Low | Default actor, add "assign team" optional. |
| Post-install follow-up | service defaults +3 days 10:00 | Yes | Low | Show generated follow-up after completion. |
| Resolve price/tax/setup fee | `PlanPriceService`, `CommercialPricingService` | Yes | Low if PlanPrice selected | Display read-only price. |
| Create invoice/receivable | `InvoiceService` and `ReceivableService` | Yes | Low | Confirm subscription only. |
| Contract draft | `SubscriptionBillingService::createInitialContractDraft` | Yes | Low/medium recovery path exists | Show generated contract status. |
| Installment schedule | `PaymentScheduleService` | Yes | Low | Ask count only if annual installments. |
| Payment allocation | `autoAllocateOldest` | Yes for common case | Medium if special allocation needed | Auto allocate; advanced manual allocation. |
| MRR/ARR events | `SaasMetricEventService` | Yes | Low | Never manual in daily UI. |
| Accounting posting | `BillingAccountingService`, `JournalPostingService` | Yes | High if bypassed | Engine only, no daily controls. |
| Revenue recognition | `RevenueRecognitionService` | Yes/managed | High | Advanced accounting only. |

## Information Density Audit

Classify as `SHOW_DAILY`:

- client name, stage label, preferred contact, next action, amount due summary, last activity, quick actions.

Classify as `SHOW_ON_DETAILS`:

- contact list, appointment history, follow-up history, timeline, location links, social/web info, contract status.

Classify as `MANAGEMENT_ONLY`:

- partner commission, offers, subscription lifecycle change/cancel/reactivate, collections queue, finance reports, import, conflicts, partners, settings.

Classify as `ADVANCED_ONLY`:

- financial accounts, cash movements, invoice void/reversal, refunds, credit notes, revenue recognition review, accounting periods, chart accounts.

Classify as `HIDE_FROM_UI` in normal daily workflows:

- `billing_engine_version`, `payment_engine_version`, PlanPrice version internals, `tax_rate_bps`, journal source types/IDs, allocation internals, legacy payment schedules, deprecated financial dashboard mode.

## Daily vs Management vs Engine Classification

Daily candidates confirmed:

- `dashboard` daily/work queues
- `clients.index`
- `clients.create`
- `clients.show`
- contact outcome modal
- appointment create/outcome
- installation schedule/complete
- record follow-up
- start subscription as quick action
- record payment as quick action

Management candidates confirmed:

- products/plans/catalog
- partners
- collections
- subscription renewals
- finance/executive/SaaS reports
- operating expenses
- capital management
- settings
- conflicts/import

Engine candidates confirmed:

- PlanPrice mechanics
- pricing/tax/receivable/payment allocation
- accounting/cash subledger
- revenue recognition
- SaaS metrics events
- compatibility/deprecated dashboard payment/expense routes

Legacy compatibility:

- `DashboardController::clients`, `showClient`, `storeClient`, `convert`
- `DashboardController::storePayment` aborts 410
- `DashboardController::storeExpense` aborts 410
- `ClientController::convert` aborts 410
- deprecated `?mode=financial` dashboard links to authoritative finance/exec/SaaS/accounting pages
- legacy schedules filtered by `schedule_engine_version` null

## Current Click / Field Counts

| Workflow | Current clicks/steps | Current visible fields | Minimum target clicks | Minimum fields |
|---|---:|---:|---:|---:|
| Today to Add Client completion | Today -> FAB/Add Client -> submit = 2-3 | 15 | 2 | 6-7 |
| Log a Call | Open client -> Contacts tab -> modal -> save = 4-5 | 11 conditional | 2-3 | 1-4 conditional |
| Create Appointment | Open client -> appointment form/modal -> save = 3-4 | 10-11 | 2-3 | 3 |
| Complete Installation | Open client -> installation tab -> complete form -> save = 3-4 | 11+ | 2 | 2-3 |
| Start Subscription | Open client -> billing tab -> scroll convert -> choose values -> submit/preview = 4-7 | 9 | 3-4 | 4-5 |
| Record Payment | Open client -> billing tab -> payment section -> save = 4-6 | 6-8 | 2-3 | 2-4 |

## Backend Constraints

Preserve these constraints during any later simplification:

- Authorization: all internal routes are behind `auth` + `EnsureActiveInternalUser`; policies/gates protect client/financial actions.
- `ClientController@store/update` validation and activity log creation must remain.
- `ClientOperationalWorkflowService` remains authoritative for stage transitions, close/reopen, contact outcomes, follow-ups, review items.
- `FreeInstallationService` remains authoritative for scheduling/rescheduling/cancel/completion and install follow-up creation.
- `BillingController@startPaidSubscription` must keep `PlanPriceService::activeEffectivePrice` and `SubscriptionBillingService::startPaidSubscription`.
- Price must come from `PlanPrice` + `CommercialPricingService`, not user-entered price.
- Invoice totals must be created and checked by `InvoiceService`.
- Payment amount and allocation integrity must remain in `PaymentAllocationService` and `ReceivableService`.
- Cash account rules must remain in `CashMovementService`.
- Contract historical snapshots and artifacts must remain generated by `ContractService`.
- Accounting, revenue recognition, and SaaS metrics must remain engine-generated.
- Legacy/deprecated writes should stay blocked with 410 unless deliberately retired.

## Simplification Risk Map

| Area | Risk category | Reason |
|---|---|---|
| Hide partner commission on Add Client | SAFE_WITH_SERVER_DEFAULT | partner default commission exists; override can be advanced. |
| Merge `city`, `area`, `city_area` in normal add | SAFE_UI_SIMPLIFICATION | server already maps `city` from `city_area`; details can be edited later. |
| Hide `business_type` on Add Client | SAFE_WITH_SERVER_DEFAULT | defaults to `business_category`. |
| Hide PlanPrice amount/tax inputs | SAFE_UI_SIMPLIFICATION | they are not normal inputs; backend owns them. |
| Default quantity from branch count | SAFE_WITH_SERVER_DEFAULT | still validated server-side. |
| Auto-generate installment schedule | SAFE_UI_SIMPLIFICATION | `PaymentScheduleService` exists. |
| Auto-allocate oldest payment | SAFE_WITH_SERVER_DEFAULT | service enforces constraints; manual remains advanced. |
| Hide payment allocation details | SAFE_UI_SIMPLIFICATION | preserve backend allocation records. |
| Hide accounting trace from daily | SAFE_UI_SIMPLIFICATION | accounting page remains advanced. |
| Remove financial account from all payments | REQUIRES_DOMAIN_CHANGE | service currently requires `financial_account_id`; can default only if a configured default exists. |
| Remove service/item requirement from installation | REQUIRES_DOMAIN_CHANGE | service requires at least one installed item. |
| Let daily user manually set subscriber stage | DO_NOT_SIMPLIFY | service blocks subscriber stage outside paid subscription workflow. |
| Allow user-entered subscription price | DO_NOT_SIMPLIFY | breaks PlanPrice authority. |
| Skip invoice/receivable on subscription | DO_NOT_SIMPLIFY | breaks source of truth and accounting. |
| Hide payment amount validation | DO_NOT_SIMPLIFY | financial integrity. |
| Hide contract snapshot generation | DO_NOT_SIMPLIFY | legal/historical record. |

## Proposed Minimal Human Surface

Daily navigation:

- Today
- Clients
- Work
- Notifications secondary

Client workspace primary sections:

- Summary
- Quick Actions
- Activity
- Details
- Management/Finance collapsed

Six core quick actions:

- Add Client
- Record Call
- Create Appointment
- Complete Installation
- Start Subscription
- Record Payment

Management navigation:

- Collections
- Subscription Billing
- Products/Catalog
- Partners
- Finance
- Executive
- SaaS Metrics
- Operating Expenses
- Capital Management
- Settings
- Import
- Conflicts

Advanced navigation:

- Accounting
- Financial Accounts
- Revenue recognition
- Cash assignment/backfills
- Credit notes/refunds/reversals

Hidden engine surfaces:

- PlanPrice versioning internals
- payment allocation internals
- journal entries from daily users
- revenue recognition schedules
- legacy payment schedules
- engine version fields

## Recommended Simplification Priority

1. Simplify `Add Client`: reduce to the 6-7 field operational form and move partner/commission/location/social fields to optional details.
2. Restructure `Client Workspace`: first screen becomes summary + next action + six quick actions; collapse history and management sections.
3. Split `billing` tab: normal `Start Subscription` and `Record Payment` quick actions first; advanced finance/credits/refunds/contracts/accounting after permissioned expansion.
4. Make `Record Payment` amount-due driven and auto-allocate by default.
5. Make `Complete Installation` a short confirmation flow that relies on the existing 3-day follow-up automation.
6. Move management/engine pages out of normal mobile `More` for non-management roles.
7. Replace raw stage dropdown with guided lifecycle actions.

## Open Questions

- Should normal staff ever see partner commission percentage, or only partner identity/source?
- Is there one default cash account per payment method? If yes, `financial_account_id` can be hidden in most payment entries.
- Should `business_phone` be collected at Add Client or only if different from owner/manager mobile?
- Which product/system is the default free installation item? If a default exists, installation completion can become one-click plus optional notes.
- Should `Start Subscription` allow discounts to staff, or require manager permission?
- Should `Today` money queues expose only "amount due" and "record payment", with collections reports management-only?

## Final JSON Summary

```json
{
  "status": "AUDIT_COMPLETE",
  "screens_scanned": 26,
  "daily_operations_scanned": 15,
  "forms_scanned": 100,
  "form_fields_scanned": 358,
  "tables_scanned": 31,
  "daily_surfaces": ["dashboard", "clients.index", "clients.create", "clients.show", "contact-outcome", "appointments.outcome", "installation-followup", "notifications.index"],
  "management_surfaces": ["collections.index", "subscription-billing.index", "finance.index", "executive.index", "saas-metrics.index", "partners.*", "conflicts.*", "clients.import", "operating-expenses.index", "capital-management.index", "settings.index"],
  "engine_surfaces": ["commercial-catalog.index", "financial-accounts.index", "accounting.index", "contracts.template", "subscription billing internals", "payment allocation internals", "revenue recognition"],
  "legacy_surfaces": ["DashboardController::storePayment", "DashboardController::storeExpense", "DashboardController::convert", "ClientController::convert", "dashboard financial mode", "legacy payment schedules"],
  "fields_essential": ["business_name", "business_category", "contact_person", "phone", "city_area", "result", "appointment_date", "appointment_time", "plan_price_id", "start_date", "amount", "payment_method"],
  "fields_defaultable": ["primary_contact_role", "lead_source", "business_phone", "business_type", "city", "number_of_branches", "method", "attendees[]", "installed_at", "installed_by", "next_follow_up_date", "next_action", "received_at", "installment_due_day"],
  "fields_auto_derivable": ["subscription price", "tax_rate_bps", "invoice lines", "receivable balance", "renewal date", "contract draft", "MRR/ARR events", "payment allocation", "post-install follow-up", "accounting entries"],
  "fields_advanced_only": ["partner_commission_percentage", "partner_attribution_notes", "discount_jod", "discount_reason", "allocations[0][invoice_id]", "allocations[0][amount]", "invoice_id", "credit note fields", "refund fields", "void_reason", "reversal reason", "accounting recognition fields"],
  "fields_management_only": ["Product/Plan/PlanPrice fields", "settings fields", "financial account fields", "operating expense fields", "capital management fields", "partner CRUD fields"],
  "fields_legacy_only": ["legacy payment schedule fields", "dashboard financial compatibility fields"],
  "automation_opportunities": ["default current date/time", "default primary owner", "default contact role", "default business_type/city/branch count", "conditional partner attribution", "default attendees", "post-install follow-up", "PlanPrice resolution", "tax calculation", "invoice/receivable creation", "contract draft creation", "installment schedule generation", "auto allocate oldest payment", "MRR/ARR events", "accounting posting", "revenue recognition"],
  "tables_to_keep": ["finance reports", "executive KPI tables", "saas metrics", "accounting tables", "conflicts table", "import preview tables"],
  "tables_to_simplify": ["clients desktop table", "partners dashboard table", "settings activity table"],
  "tables_to_replace_with_cards_or_queues": ["daily operations", "client workspace normal history", "collections daily-facing items"],
  "screens_to_hide_from_daily_navigation": ["subscription-billing.index", "collections.index", "commercial-catalog.index", "finance.index", "executive.index", "saas-metrics.index", "financial-accounts.index", "operating-expenses.index", "capital-management.index", "accounting.index", "settings.index", "partners.*", "conflicts.*", "clients.import"],
  "current_friction_metrics": {
    "add_client_fields": 15,
    "record_call_fields": 11,
    "create_appointment_fields": 10,
    "complete_installation_fields": 11,
    "start_subscription_fields": 9,
    "record_payment_fields": 6
  },
  "minimum_practical_friction_targets": {
    "add_client_fields": 6,
    "record_call_fields": 1,
    "create_appointment_fields": 3,
    "complete_installation_fields": 3,
    "start_subscription_fields": 4,
    "record_payment_fields": 3
  },
  "recommended_daily_navigation": ["Today", "Clients", "Work", "Notifications"],
  "recommended_quick_actions": ["Add Client", "Record Call", "Create Appointment", "Complete Installation", "Start Subscription", "Record Payment"],
  "recommended_client_workspace_structure": ["Summary", "Next Action", "Quick Actions", "Subscription/Amount Due Summary", "Last Activity", "Expandable History", "Management/Advanced"],
  "backend_constraints_that_must_be_preserved": ["auth + EnsureActiveInternalUser", "client policies", "FinancialPermissions gates", "ClientOperationalWorkflowService transitions", "FreeInstallationService install workflow", "PlanPrice authority", "CommercialPricingService totals", "InvoiceService issuance", "PaymentAllocationService integrity", "CashMovementService account rules", "ContractService snapshots", "RevenueRecognitionService schedules", "SaasMetricEventService metrics"],
  "simplification_risks": ["do not allow manual subscriber stage", "do not allow user-entered subscription price", "do not bypass payment amount/account validation", "do not hide contract/revenue/accounting source-of-truth logic from backend", "do not remove required installation item without domain decision"],
  "files_reviewed": ["routes/web.php", "resources/views/**/*.blade.php", "resources/views/components/**/*.blade.php", "app/Http/Controllers/**/*.php", "app/ViewModels/**/*.php", "app/Services/**/*.php", "app/Models/**/*.php", "app/Http/Middleware/**/*.php", "lang/ar/notify.php", "lang/en/notify.php", "tests/Feature/**/*.php", "database/migrations/**/*.php"],
  "open_questions": ["Should normal staff see partner commission percentage?", "Is there a default financial account per payment method?", "Should business_phone remain on Add Client?", "What is the default installed product/system?", "Who can approve discounts?", "Should Today expose money queues or only record-payment actions?"]
}
```
