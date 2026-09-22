<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Services\CommercialPricingService;
use App\Services\InvoiceService;
use App\Services\PlanPriceService;
use App\Services\SubscriptionBillingService;
use App\Support\FinancialPermissions;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function startPaidSubscription(
        Request $request,
        Client $client,
        PlanPriceService $priceService,
        SubscriptionBillingService $billingService
    ): RedirectResponse {
        Gate::authorize(FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING);

        if (! $request->filled('plan_price_id') && $request->filled('plan_id') && $request->filled('billing_interval')) {
            $matchedPrice = \App\Models\PlanPrice::query()
                ->where('plan_id', (int) $request->input('plan_id'))
                ->where('billing_interval', (string) $request->input('billing_interval'))
                ->where('is_active', true)
                ->first();
            if ($matchedPrice) {
                $request->merge(['plan_price_id' => $matchedPrice->id]);
            }
        }

        $validated = $request->validate([
            'plan_price_id' => 'required|exists:plan_prices,id',
            'quantity' => 'required|integer|min:1|max:999',
            'start_date' => 'required|date',
            'discount_jod' => $this->nullableMoneyRules(),
            'discount_reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'payment_terms' => ['nullable', Rule::in(['full', 'installments'])],
            'installments_count' => 'nullable|integer|min:2|max:12',
            'installment_due_day' => ['nullable', 'integer', Rule::in([1, 5, 15, 30])],
            'preview_only' => 'nullable|boolean',
        ], [
            'plan_price_id.required' => __('notify.subscriptions.plan_price_required') ?: 'يرجى اختيار خطة وسعر الاشتراك.',
            'plan_price_id.exists' => __('notify.subscriptions.plan_price_invalid') ?: 'السعر أو الخطة المختارة غير صالحة.',
        ], [
            'plan_price_id' => __('notify.subscriptions.plan_price') ?: 'سعر الخطة',
        ]);

        $price = $priceService->activeEffectivePrice((int) $validated['plan_price_id']);
        if ($request->boolean('preview_only')) {
            return back()
                ->withInput()
                ->with('billingTermsPreview', $billingService->previewPaidSubscriptionTerms($price, $validated));
        }

        [, , $contractResult] = $billingService->startPaidSubscription($client, $price, $validated, auth()->id());

        $response = back()->with('success', 'تم بدء الاشتراك المدفوع وإنشاء الفاتورة الأولى بدون تسجيل أي دفعة.');

        if ($contractResult['status'] !== 'ready') {
            $response->with('warning', 'تم حفظ الاشتراك والفاتورة، لكن مسودة العقد تحتاج إعادة توليد من قسم العقود دون إنشاء اشتراك أو فاتورة جديدة.');
        }

        return $response;
    }

    public function storeOneTimeInvoice(
        Request $request,
        Client $client,
        CommercialPricingService $pricingService,
        InvoiceService $invoiceService
    ): RedirectResponse {
        Gate::authorize(FinancialPermissions::MANAGE_INVOICES);

        $validated = $request->validate([
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'description' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:1',
            'lines.*.line_type' => ['nullable', Rule::in([InvoiceLine::TYPE_ONE_TIME_SERVICE, InvoiceLine::TYPE_CUSTOM])],
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.quantity' => 'nullable|integer|min:1|max:999',
            'lines.*.unit_price_jod' => $this->nullableMoneyRules(),
            'lines.*.discount_jod' => $this->nullableMoneyRules(),
            'lines.*.tax_rate_bps' => 'nullable|integer|min:0|max:10000',
            'lines.*.service_id' => 'nullable|exists:services,id',
        ]);

        $lines = [];
        $billableLines = array_values(array_filter($validated['lines'], fn ($line) => filled($line['description'] ?? null) || filled($line['unit_price_jod'] ?? null)));

        if ($billableLines === []) {
            throw ValidationException::withMessages(['lines' => 'يجب إدخال سطر فاتورة واحد على الأقل.']);
        }

        foreach ($billableLines as $index => $line) {
            if (! filled($line['description'] ?? null) || ! filled($line['unit_price_jod'] ?? null)) {
                throw ValidationException::withMessages(['lines' => 'كل سطر فاتورة مستخدم يحتاج وصفاً وسعر وحدة.']);
            }
            $line['quantity'] = $line['quantity'] ?? 1;
            $lines[] = $pricingService->calculateOneTimeLine($line, ($index + 1) * 10);
        }

        $invoiceService->createIssuedOneTime(
            $client,
            $lines,
            Carbon::parse($validated['issue_date']),
            Carbon::parse($validated['due_date']),
            $validated['description'] ?? null,
            auth()->id()
        );

        return back()->with('success', 'تم إنشاء وإصدار فاتورة العمل الإضافي بدون إنشاء اشتراك أو دفعة.');
    }

    public function voidInvoice(Request $request, Invoice $invoice, InvoiceService $invoiceService): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_INVOICES);

        $validated = $request->validate([
            'void_reason' => 'required|string|max:1000',
        ]);

        $invoiceService->void($invoice, $validated['void_reason'], auth()->id());

        return back()->with('success', 'تم إلغاء الفاتورة مع الحفاظ على سطورها وسجلها.');
    }

    private function moneyRules(): array
    {
        return ['required', 'string', 'regex:/^\\d+(\\.\\d{1,3})?$/'];
    }

    private function nullableMoneyRules(): array
    {
        return ['nullable', 'string', 'regex:/^\\d+(\\.\\d{1,3})?$/'];
    }
}
