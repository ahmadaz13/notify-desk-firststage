# Human Operations Simplification - Phase 06: Subscription & Contracts

## 1. Preflight Commercial Findings
Before implementing Phase 06, code inspection established the following foundational facts:
- **Pricing and Billing Authorities**:
  - `SubscriptionBillingService` and `CommercialPricingService` own canonical calculations.
  - `PlanPrice` is the sole recurring price authority for subscription tiers and billing intervals.
  - Tax calculation is governed by `CommercialPricingService::taxMinor(int $taxableMinor, ?int $taxRateBps): int`, computing exact half-up rounded minor units from basis points using integer arithmetic: `intdiv(($taxableMinor * $taxRateBps) + 5000, 10000)`.
- **Transaction Boundaries**:
  - Subscriptions, Invoices, Payment Schedules, and SaaS metric events are committed atomically inside `DB::transaction()` in `SubscriptionBillingService::startPaidSubscription`.
  - Contract draft creation occurs **after** the database transaction commits via `createInitialContractDraft`.
  - Contract artifact generation is completely isolated inside a `try / catch (Throwable)` block; errors during contract artifact or PDF generation never roll back the committed subscription or invoice.
- **Dependency Inspection**:
  - Zero PDF libraries existed in `composer.json` at the baseline.
  - `barryvdh/laravel-dompdf` (`v3.1.2`, wrapping `dompdf/dompdf v3.1.6`) was installed as the single, lightweight, PHP-native PDF engine compatible with Laravel 11 and PHP 8.2+.

---

## 2. Quantity Semantics Conclusion
- **Finding**: In all V1 commercial and billing paths, subscription quantity represents the number of business branches or operational units (`عدد الفروع / الوحدات`).
- **Proof in Code**:
  - `CommercialPricingService::calculateSubscription` calculates extra branch fees using `$extraBranches = max(0, $quantity - $included)` and sets line item metadata: `branch_quantity => $quantity, included_branch_quantity => $included, extra_branch_quantity => $extraBranches`.
  - `Client.number_of_branches` is an integer column defaulting to `1`.
  - The legacy form pre-populated the quantity field with `old('quantity', $client->number_of_branches ?? 1)`.
- **Decision & Implementation**:
  - Subscription quantity is derived server-side from `max(1, (int) ($client->number_of_branches ?: 1))`.
  - The browser is never trusted for quantity. If `quantity` is passed in a request, the server overrides it with the client's authoritative branch count.
  - The UI displays a clear, read-only summary (`3 فروع` / `3 branches`) with a link to update the client profile if branch count changes.

---

## 3. Guided Subscription Flow
The new commercial experience eliminates raw database concepts (like `plan_price_id` and raw taxes) and replaces them with a progressive disclosure modal (`resources/views/clients/workspace/actions/start-subscription.blade.php`):
1. **Product Selection**: The operator chooses an active sellable Product from the catalog.
2. **Plan Selection**: Dynamically populated with active plans belonging to that specific product.
3. **Billing Interval**: Dropdown offers only terms (`monthly` / `annual`) that currently possess an effective active `PlanPrice`.
4. **Annual Payment Terms (Conditional)**: If Annual is chosen, terms can be `full` (Full Payment) or `installments` (Installments).
5. **Installments Configuration**:
   - Number of installments: 2 to 12.
   - Monthly due day: 1, 5, 15, or 30 (kept as an explicit operational field per V1 policy).
6. **Start Date**: Defaults to the current date, editable.
7. **Read-only Authoritative Preview**: Dynamically fetches the server's calculation of the recurring price, setup fee, tax, total invoice obligation, and installment schedule.
8. **Confirm Subscription**: Double-submit protected action submitting to the server.

---

## 4. PlanPrice Resolution Contract
- **Endpoint**: `GuidedSubscriptionController`
- **Resolution Method**: `PlanPriceService::resolveEffectivePrice(int $productId, int $planId, string $billingInterval, Carbon|string|null $startDate = null): PlanPrice`
- **Contract Rules**:
  - Validates that the Product exists, is active, and is not archived.
  - Validates that the Plan belongs to the Product, is active, and is not archived.
  - Validates that `billing_interval` is either `monthly` or `annual`.
  - Queries `PlanPrice` scoped to `where('plan_id', $planId)->where('billing_interval', $billingInterval)->effective($date)`.
  - If 0 prices exist: throws a human-readable validation error (`لا يوجد سعر فعال لهذه الباقة في التاريخ المحدد.` / `No active price is available for this plan on the selected date.`).
  - If >1 prices exist: throws an error reporting catalog inconsistency.
  - Final confirmation strictly re-resolves and recalculates server-side; browser amounts, taxes, or line item fields are never accepted.

---

## 5. Preview Architecture
- **Route**: `POST /clients/{client}/guided-subscription/preview`
- **Controller**: `GuidedSubscriptionController::preview`
- Validates human selections, resolves authoritative `PlanPrice`, calculates amounts via `CommercialPricingService::calculateSubscription`, and generates payment schedule preview via `PaymentScheduleService::previewAnnualInstallments` when installments are selected.
- Returns clean JSON formatting read-only amounts with zero exposure of internal billing engine fields.

---

## 6. Annual Full vs. Annual Installments Behavior
- **Annual Full**:
  - Uses annual `PlanPrice`.
  - Creates 1 annual subscription (`billing_interval_v2 = 'annual'`, `installments_count = 1`).
  - Creates 1 annual invoice obligation.
  - Generates zero payment schedules.
- **Annual Installments**:
  - Economic subscription remains 1 annual subscription.
  - Total invoice obligation remains identical to Annual Full.
  - `PaymentScheduleService` generates installment records summing exactly to the annual invoice obligation.
  - MRR and ARR remain identical between Full and Installments.
  - Due days are restricted to `1`, `5`, `15`, `30`.

---

## 7. Multi-Product Behavior
- Clients can hold multiple active subscriptions simultaneously as long as they belong to **different** products (e.g., Restaurant System and Auto SMS System).
- Each product subscription generates its own independent subscription card, invoice, and contract draft.
- Subscribing to the same product when an active base subscription already exists is prevented and returns a human-readable error: `يوجد اشتراك نشط أو تغيير مجدول لهذا المنتج.` / `This product already has an active subscription or scheduled change.`.

---

## 8. Contract Architecture & PDF Isolation
- **Snapshot Creation**:
  - `ContractService::buildSnapshot` freezes commercial data (Product, Plan, PlanPrice, pricing breakdown, tax, invoice total, services, and schedules) into an immutable JSON document.
  - Contract draft is created automatically after transaction commit.
  - Zero repeated data entry is required.
- **PDF Generation**:
  - Handled by `ContractPdfService` using `Barryvdh\DomPDF\Facade\Pdf`.
  - Filename follows the approved pattern: `Notify-Contract-{sanitized-client}-{sanitized-product}-{contract-number}.pdf`.
  - Content is generated purely from the immutable `snapshot_data`, never querying mutable catalog models.
- **Artifact Failure Isolation**:
  - PDF generation is strictly on-demand and decoupled from database transactions.
  - If PDF generation fails, the exception is caught, logged, and a user-friendly warning is returned (`تعذر إنشاء ملف PDF للعقد في الوقت الحالي؛ لا يزال بإمكانك استعراض العقد وطباعته عبر المتصفح.`).
  - Subscription, invoice, payment schedules, and contract snapshot remain 100% committed and intact.
  - Legacy HTML View (`contracts.preview`) and HTML Print (`contracts.print`) remain fully functional as fallbacks.

---

## 9. Permissions & Security
- `FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING` gate is strictly enforced.
- Staff users without this permission cannot view or execute guided subscription routes (`403 Forbidden`).
- Direct routes (`clients.guided-subscription.store`, `clients.guided-subscription.preview`, `clients.guided-subscription.catalog`) are protected by middleware and explicit gate checks.
- Contract viewing and downloading enforce existing `ContractPolicy` (`view`, `download`).

---

## 10. Confirmation of Non-Alteration
The following core financial and operational authorities were **strictly preserved without alteration**:
- `CommercialPricingService` formulas and line item math were NOT changed.
- `InvoiceService` and invoice line calculations were NOT changed.
- `ReceivableService` and payment allocation rules were NOT changed.
- `PaymentScheduleService` installment generation was NOT changed.
- SaaS metrics and Revenue Recognition schedules were NOT changed.
- Accounting foundations, journal entries, and chart of accounts were NOT changed.
- No automatic payments were created.
- No new database migrations were introduced.

---

## 11. Verification Summary
- **Unit & Feature Tests**: 17 dedicated feature tests in `tests/Feature/GuidedSubscriptionPhase06Test.php` (106 assertions, 100% PASS).
- **Full Suite**: 372 tests, 2446 assertions (100% PASS, 0 errors, 0 failures).
- **View Cache**: `php artisan view:cache` (SUCCESS).
- **Asset Compilation**: `npm run build` (Vite build SUCCESS in 4.68s).
- **PHP Linting**: `php -l` passed with zero errors on all modified/created files.
- **Git Diff**: `git diff --check` passed with zero issues.
