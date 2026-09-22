<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\PlanPrice;
use App\Models\Product;
use App\Services\PaymentScheduleService;
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
        protected SubscriptionBillingService $billingService,
        protected PaymentScheduleService $paymentSchedules
    ) {}

    public function catalog(Request $request, Client $client): JsonResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING);

        return response()->json([
            'success' => true,
            'systems' => Product::sellable()->orderBy('name_ar')->get()->map(fn (Product $system) => [
                'id' => $system->id,
                'name_ar' => $system->name_ar,
                'name_en' => $system->name_en,
                'default_monthly_price' => $system->default_monthly_price_minor === null ? null : Money::fromMinorUnits($system->default_monthly_price_minor)->format(),
                'default_annual_price' => $system->default_annual_price_minor === null ? null : Money::fromMinorUnits($system->default_annual_price_minor)->format(),
            ])->values(),
        ]);
    }

    public function preview(Request $request, Client $client): JsonResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING);
        $this->assertClientCanSubscribe($client);
        $data = $this->validateInputs($request);
        $minor = Money::fromJod($data['agreed_value_jod'])->minorUnits();
        $start = Carbon::parse($data['start_date'] ?? now('Asia/Amman'))->startOfDay();
        $terms = $this->paymentTerms($data);
        $systems = Product::sellable()->whereIn('id', $data['system_ids'])->orderBy('name_ar')->get();
        $schedule = $terms['type'] === 'installments'
            ? $this->paymentSchedules->previewAnnualInstallments($minor, $terms['count'], $start->toDateString(), $terms['due_day'])
            : [];

        return response()->json([
            'success' => true,
            'systems' => $systems->map->only(['id', 'name_ar', 'name_en'])->values(),
            'billing_interval' => $data['billing_interval'],
            'agreed_value_formatted' => Money::fromMinorUnits($minor)->format(),
            'payment_terms' => $terms['type'],
            'schedule' => collect($schedule)->map(fn (array $item) => $item + [
                'amount_due_formatted' => Money::fromMinorUnits($item['amount_due_minor'])->format(),
            ])->values(),
        ]);
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING);
        $this->assertClientCanSubscribe($client);
        $data = $this->validateInputs($request);
        $systems = Product::sellable()->whereIn('id', $data['system_ids'])->get();
        if ($systems->count() !== count(array_unique($data['system_ids']))) {
            throw ValidationException::withMessages(['system_ids' => __('notify.client_workspace.validation.systems')]);
        }
        $terms = $this->paymentTerms($data);

        [$subscription, $invoice, $contractResult] = $this->billingService->startAgreedSubscription($client, $systems, [
            'billing_interval' => $data['billing_interval'],
            'agreed_value_minor' => Money::fromJod($data['agreed_value_jod'])->minorUnits(),
            'start_date' => Carbon::parse($data['start_date'] ?? now('Asia/Amman'))->toDateString(),
            'payment_terms' => $terms['type'],
            'installments_count' => $terms['count'],
            'installment_due_day' => $terms['due_day'],
            'notes' => $data['notes'] ?? null,
        ], $request->user()->id);

        $response = redirect()->route('clients.show', $client)->with('success', __('notify.subscriptions.converted'));
        if ($contractResult['status'] !== 'ready') {
            $response->with('warning', __('notify.subscriptions.contract_recovery'));
        }

        return $response->with('lastStartedSubscriptionId', $subscription->id);
    }

    private function validateInputs(Request $request): array
    {
        return $request->validate([
            'system_ids' => ['required', 'array', 'min:1'],
            'system_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'billing_interval' => ['required', Rule::in([PlanPrice::MONTHLY, PlanPrice::ANNUAL])],
            'agreed_value_jod' => ['required', 'string', 'regex:/^\d+(\.\d{1,3})?$/', 'not_in:0,0.0,0.00,0.000'],
            'start_date' => ['nullable', 'date'],
            'payment_terms' => ['nullable', Rule::in(['full', 'installments'])],
            'installments_count' => ['nullable', 'integer', 'min:2', 'max:12'],
            'installment_due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'system_ids.required' => __('notify.client_workspace.validation.systems'),
            'system_ids.min' => __('notify.client_workspace.validation.systems'),
            'agreed_value_jod.required' => __('notify.client_workspace.validation.agreed_value'),
            'agreed_value_jod.regex' => __('notify.client_workspace.validation.agreed_value'),
        ]);
    }

    private function paymentTerms(array $data): array
    {
        if ($data['billing_interval'] !== PlanPrice::ANNUAL || ($data['payment_terms'] ?? 'full') !== 'installments') {
            return ['type' => 'full', 'count' => 1, 'due_day' => 1];
        }

        $count = (int) ($data['installments_count'] ?? 0);
        $dueDay = (int) ($data['installment_due_day'] ?? 0);
        if ($count < 2 || $count > 12) {
            throw ValidationException::withMessages(['installments_count' => __('notify.client_workspace.validation.installment_count')]);
        }
        if ($dueDay < 1 || $dueDay > 31) {
            throw ValidationException::withMessages(['installment_due_day' => __('notify.client_workspace.validation.due_day')]);
        }

        return [
            'type' => 'installments',
            'count' => $count,
            'due_day' => $dueDay,
        ];
    }

    private function assertClientCanSubscribe(Client $client): void
    {
        if (ClientLifecycle::normalizeStage($client->stage, $client->status) === ClientLifecycle::CLOSED) {
            throw ValidationException::withMessages(['client' => __('notify.subscriptions.closed_client')]);
        }
    }
}
