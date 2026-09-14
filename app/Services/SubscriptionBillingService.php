<?php

namespace App\Services;

use App\Models\Client;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Support\ClientLifecycle;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionBillingService
{
    public function __construct(
        private CommercialPricingService $pricingService,
        private InvoiceService $invoiceService
    ) {}

    public function startPaidSubscription(Client $client, PlanPrice $price, array $data, ?int $userId): array
    {
        $stage = ClientLifecycle::normalizeStage($client->stage ?? null, $client->status ?? null);
        if ($stage === ClientLifecycle::CLOSED) {
            throw ValidationException::withMessages(['client_id' => 'لا يمكن بدء اشتراك مدفوع لعميل مغلق قبل إعادة فتحه.']);
        }

        $activeDuplicate = Subscription::where('client_id', $client->id)
            ->where('plan_id', $price->plan_id)
            ->where('billing_engine_version', 'v2')
            ->where('status', 'active')
            ->exists();

        if ($activeDuplicate) {
            throw ValidationException::withMessages(['plan_price_id' => 'يوجد اشتراك V2 نشط لهذه الباقة بالفعل.']);
        }

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $periodEnd = $price->billing_interval === PlanPrice::ANNUAL
            ? $start->copy()->addYear()->subDay()
            : $start->copy()->addMonthNoOverflow()->subDay();
        $nextBilling = $price->billing_interval === PlanPrice::ANNUAL
            ? $start->copy()->addYear()
            : $start->copy()->addMonthNoOverflow();

        $pricing = $this->pricingService->calculateSubscription(
            $price,
            (int) $data['quantity'],
            $data['discount_jod'] ?? null
        );

        return DB::transaction(function () use ($client, $price, $data, $userId, $start, $periodEnd, $nextBilling, $pricing) {
            $subscription = Subscription::create([
                'client_id' => $client->id,
                'plan_id' => $price->plan_id,
                'plan_price_id' => $price->id,
                'user_id' => $userId,
                'billing_engine_version' => 'v2',
                'billing_interval_v2' => $price->billing_interval,
                'currency' => $price->currency,
                'quantity' => $pricing['quantity'],
                'plan_code_snapshot' => $price->plan->code,
                'plan_name_snapshot' => $price->plan->name_ar,
                'unit_price_minor' => $pricing['unit_price_minor'],
                'setup_fee_minor_v2' => $pricing['setup_fee_minor'],
                'subtotal_minor' => $pricing['subtotal_minor'],
                'discount_minor' => $pricing['discount_minor'],
                'tax_rate_bps' => $pricing['tax_rate_bps'],
                'tax_minor_v2' => $pricing['tax_minor'],
                'total_minor' => $pricing['total_minor'],
                'current_period_start' => $start->toDateString(),
                'current_period_end' => $periodEnd->toDateString(),
                'next_billing_date' => $nextBilling->toDateString(),
                'billing_type' => $price->billing_interval,
                'total_price' => Money::fromMinorUnits($pricing['total_minor'])->format(),
                'grand_total' => Money::fromMinorUnits($pricing['total_minor'])->format(),
                'setup_fee' => Money::fromMinorUnits($pricing['setup_fee_minor'])->format(),
                'start_date' => $start->toDateString(),
                'renewal_date' => $nextBilling->toDateString(),
                'installments_count' => 1,
                'status' => 'active',
                'version' => 1,
            ]);

            $invoice = $this->invoiceService->createIssuedForSubscription(
                $client,
                $subscription,
                $pricing,
                $start,
                $start,
                $data['notes'] ?? null,
                $userId
            );

            $client->update([
                'stage' => ClientLifecycle::SUBSCRIBER,
                'status' => 'subscriber',
                'closed_at' => null,
                'closed_reason' => null,
            ]);

            DB::table('activity_logs')->insert([
                'client_id' => $client->id,
                'user_id' => $userId,
                'type' => 'subscription_started',
                'description' => 'تم بدء اشتراك مدفوع للباقة '.$price->plan->name_ar,
                'metadata' => json_encode([
                    'subscription_id' => $subscription->id,
                    'plan_id' => $price->plan_id,
                    'plan_price_id' => $price->id,
                    'quantity' => $pricing['quantity'],
                    'total_minor' => $pricing['total_minor'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [$subscription->fresh(), $invoice];
        });
    }
}
