<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\PlanPrice;
use App\Services\CommercialPricingService;
use App\Services\PaymentScheduleService;
use App\Services\PlanPriceService;
use App\Services\SubscriptionBillingService;
use App\Support\ClientLifecycle;
use App\Support\FinancialPermissions;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GuidedSubscriptionController extends Controller
{
    public function __construct(
        protected PlanPriceService $priceService,
        protected CommercialPricingService $pricingService,
        protected SubscriptionBillingService $billingService,
        protected PaymentScheduleService $paymentSchedules
    ) {}

    /**
     * Get sellable catalog options (products, plans, and available intervals).
     */
    public function catalog(Request $request, Client $client): JsonResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING);

        $date = $request->query('date') ? Carbon::parse($request->query('date')) : now();
        $catalog = $this->priceService->getSellableCatalogTree($date);

        return response()->json([
            'success' => true,
            'catalog' => $catalog,
            'client_branches' => max(1, (int) ($client->number_of_branches ?: 1)),
        ]);
    }

    /**
     * Server-side authoritative commercial preview.
     */
    public function preview(Request $request, Client $client): JsonResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING);

        $this->assertClientCanSubscribe($client);

        $validated = $this->validateGuidedInputs($request);
        $startDate = filled($validated['start_date'] ?? null)
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : now()->startOfDay();

        // Quantity is strictly server-derived from Client branches
        $quantity = max(1, (int) ($client->number_of_branches ?: 1));

        // Authoritatively resolve effective PlanPrice
        $price = $this->priceService->resolveEffectivePrice(
            (int) $validated['product_id'],
            (int) $validated['plan_id'],
            $validated['billing_interval'],
            $startDate
        );

        $paymentTerms = $this->normalizePaymentTerms($price, $validated);

        $pricing = $this->pricingService->calculateSubscription(
            $price,
            $quantity,
            null // No discounts in normal flow
        );

        $schedule = ($paymentTerms['type'] === 'installments' && $paymentTerms['installments_count'] > 1)
            ? $this->paymentSchedules->previewAnnualInstallments(
                $pricing['total_minor'],
                $paymentTerms['installments_count'],
                $startDate->toDateString(),
                $paymentTerms['due_day']
            )
            : [];

        $formattedSchedule = array_map(function ($item) {
            return [
                'sequence' => $item['sequence'],
                'due_date' => $item['due_date'],
                'amount_due_formatted' => Money::fromMinorUnits($item['amount_due_minor'])->format(),
                'amount_due_minor' => $item['amount_due_minor'],
            ];
        }, $schedule);

        return response()->json([
            'success' => true,
            'product_id' => $price->plan->product_id,
            'product_name' => $price->plan->product?->name_ar ?? $price->plan->product?->name_en,
            'plan_id' => $price->plan_id,
            'plan_name' => $price->plan->name_ar,
            'billing_interval' => $price->billing_interval,
            'billing_interval_label' => $price->billing_interval === PlanPrice::ANNUAL ? 'سنوي' : 'شهري',
            'payment_terms' => $paymentTerms['type'],
            'payment_terms_label' => $paymentTerms['type'] === 'installments' ? 'أقساط سنوية' : 'دفعة كاملة',
            'quantity' => $quantity,
            'quantity_label' => $quantity.' '.($quantity === 1 ? 'فرع' : 'فروع'),
            'unit_price_formatted' => Money::fromMinorUnits($price->amount_minor)->format(),
            'setup_fee_formatted' => Money::fromMinorUnits($pricing['setup_fee_minor'])->format(),
            'tax_formatted' => Money::fromMinorUnits($pricing['tax_minor'])->format(),
            'total_formatted' => Money::fromMinorUnits($pricing['total_minor'])->format(),
            'total_minor' => $pricing['total_minor'],
            'installments_count' => $paymentTerms['installments_count'],
            'installment_due_day' => $paymentTerms['due_day'],
            'schedule' => $formattedSchedule,
        ]);
    }

    /**
     * Final confirmation of guided subscription. Re-resolves and calculates server-side.
     */
    public function store(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING);

        $this->assertClientCanSubscribe($client);

        $validated = $this->validateGuidedInputs($request);
        $startDate = filled($validated['start_date'] ?? null)
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : now()->startOfDay();

        // Strictly server-derived quantity
        $quantity = max(1, (int) ($client->number_of_branches ?: 1));

        // Re-resolve authoritative PlanPrice
        $price = $this->priceService->resolveEffectivePrice(
            (int) $validated['product_id'],
            (int) $validated['plan_id'],
            $validated['billing_interval'],
            $startDate
        );

        $paymentTerms = $this->normalizePaymentTerms($price, $validated);

        $billingData = [
            'quantity' => $quantity,
            'start_date' => $startDate->toDateString(),
            'payment_terms' => $paymentTerms['type'],
            'installments_count' => $paymentTerms['installments_count'],
            'installment_due_day' => $paymentTerms['due_day'],
            'discount_jod' => null,
            'notes' => $validated['notes'] ?? null,
        ];

        try {
            [$subscription, $invoice, $contractResult] = $this->billingService->startPaidSubscription(
                $client,
                $price,
                $billingData,
                auth()->id()
            );
        } catch (ValidationException $e) {
            $errors = $e->errors();
            if (isset($errors['plan_price_id'])) {
                $firstError = $errors['plan_price_id'][0] ?? '';
                if (str_contains($firstError, 'نشط') || str_contains($firstError, 'المنتج') || str_contains($firstError, 'خطة مجدول')) {
                    throw ValidationException::withMessages([
                        'product_id' => __('notify.subscriptions.validation.same_product_conflict') ?: ($firstError ?: 'يوجد اشتراك نشط أو تغيير مجدول لهذا المنتج.'),
                    ]);
                }

                throw ValidationException::withMessages([
                    'plan_id' => __('notify.subscriptions.validation.plan_price_invalid') ?: 'السعر أو الخطة المختارة غير صالحة أو غير متاح للبيع حالياً.',
                ]);
            }
            throw $e;
        }

        $message = "تم بدء الاشتراك المدفوع للمنتج {$price->plan->product?->name_ar} بنجاح وإنشاء الفاتورة الأولى ومسودة العقد.";
        $response = redirect()->route('clients.show', $client->id)
            ->with('success', $message)
            ->with('lastStartedSubscriptionId', $subscription->id);

        if ($contractResult['status'] !== 'ready') {
            $response->with('warning', 'تم إنشاء الاشتراك والفاتورة، لكن ملف العقد يحتاج إعادة توليد لاحقاً.');
        }

        return $response;
    }

    private function validateGuidedInputs(Request $request): array
    {
        return $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'plan_id' => 'required|integer|exists:plans,id',
            'billing_interval' => ['required', 'string', Rule::in([PlanPrice::MONTHLY, PlanPrice::ANNUAL])],
            'start_date' => 'nullable|date',
            'payment_terms' => ['nullable', 'string', Rule::in(['full', 'installments'])],
            'installments_count' => 'nullable|integer',
            'installment_due_day' => 'nullable|integer',
            'notes' => 'nullable|string|max:1000',
        ], [
            'product_id.required' => __('notify.client_workspace.validation.product') ?: 'اختر المنتج.',
            'plan_id.required' => __('notify.client_workspace.validation.plan') ?: 'اختر الباقة.',
            'billing_interval.required' => __('notify.client_workspace.validation.billing_interval') ?: 'اختر مدة الاشتراك.',
            'billing_interval.in' => __('notify.client_workspace.validation.billing_interval') ?: 'اختر مدة الاشتراك.',
        ]);
    }

    private function normalizePaymentTerms(PlanPrice $price, array $data): array
    {
        if ($price->billing_interval !== PlanPrice::ANNUAL) {
            return ['type' => 'full', 'installments_count' => 1, 'due_day' => 1];
        }

        $terms = $data['payment_terms'] ?? 'full';
        if ($terms !== 'installments') {
            return ['type' => 'full', 'installments_count' => 1, 'due_day' => 1];
        }

        $count = (int) ($data['installments_count'] ?? 0);
        $dueDay = (int) ($data['installment_due_day'] ?? 0);

        if ($count < 2 || $count > 12) {
            throw ValidationException::withMessages([
                'installments_count' => __('notify.client_workspace.validation.installment_count') ?: 'اختر عدد الأقساط بين 2 و 12.',
            ]);
        }

        if (! in_array($dueDay, [1, 5, 15, 30], true)) {
            throw ValidationException::withMessages([
                'installment_due_day' => __('notify.client_workspace.validation.due_day') ?: 'اختر يوم استحقاق القسط (1، 5، 15، 30).',
            ]);
        }

        return [
            'type' => 'installments',
            'installments_count' => $count,
            'due_day' => $dueDay,
        ];
    }

    private function assertClientCanSubscribe(Client $client): void
    {
        $stage = ClientLifecycle::normalizeStage($client->stage ?? null, $client->status ?? null);
        if ($stage === ClientLifecycle::CLOSED) {
            throw ValidationException::withMessages([
                'client' => 'لا يمكن بدء اشتراك مدفوع لعميل مغلق قبل إعادة فتحه.',
            ]);
        }
    }
}
