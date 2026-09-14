<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionBillingPeriod;
use App\Models\SubscriptionEvent;
use App\Support\ClientLifecycle;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubscriptionBillingService
{
    public function __construct(
        private readonly CommercialPricingService $pricingService,
        private readonly InvoiceService $invoiceService,
        private readonly SaasMetricEventService $saasMetricEvents
    ) {
    }

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
        $periodEnd = $this->periodEnd($start, $price->billing_interval);
        $nextBilling = $this->nextPeriodStart($start, $price->billing_interval);
        $pricing = $this->pricingService->calculateSubscription($price->loadMissing('plan'), (int) $data['quantity'], $data['discount_jod'] ?? null);

        return DB::transaction(function () use ($client, $price, $data, $userId, $start, $periodEnd, $nextBilling, $pricing) {
            $subscription = Subscription::create($this->subscriptionSnapshotAttributes(
                client: $client,
                price: $price,
                pricing: $pricing,
                start: $start,
                end: $periodEnd,
                nextBilling: $nextBilling,
                userId: $userId,
                status: 'active',
                version: 1
            ));

            $invoice = $this->invoiceService->createIssuedForSubscription(
                $client,
                $subscription,
                $pricing,
                $start,
                $start,
                $data['notes'] ?? null,
                $userId
            );

            $period = $this->createOrLinkPeriod($subscription->fresh(), $price, $pricing, $start, $periodEnd, $invoice, 1);
            $event = $this->recordEvent($subscription, SubscriptionEvent::TYPE_STARTED, $start, [
                'to_plan_id' => $price->plan_id,
                'to_price_minor' => $pricing['unit_price_minor'],
                'billing_interval' => $price->billing_interval,
                'quantity' => $pricing['quantity'],
                'created_by' => $userId,
                'metadata' => ['invoice_id' => $invoice->id],
            ]);
            $this->saasMetricEvents->recordNew($subscription, $event, $period);

            $client->update([
                'stage' => ClientLifecycle::SUBSCRIBER,
                'status' => 'subscriber',
                'closed_at' => null,
                'closed_reason' => null,
            ]);

            $this->log($client->id, $userId, 'subscription_started', 'تم بدء اشتراك مدفوع للباقة '.$price->plan->name_ar, [
                'subscription_id' => $subscription->id,
                'plan_id' => $price->plan_id,
                'plan_price_id' => $price->id,
                'quantity' => $pricing['quantity'],
                'total_minor' => $pricing['total_minor'],
            ]);

            return [$subscription->fresh(['billingPeriods', 'lifecycleEvents']), $invoice];
        });
    }

    public function generateRenewals(Carbon|string|null $through = null, bool $dryRun = false, ?int $userId = null): array
    {
        $throughDate = $through === null ? now()->startOfDay() : Carbon::parse($through)->startOfDay();
        $counts = [
            'renewed' => 0,
            'cancelled' => 0,
            'already_processed' => 0,
            'review' => 0,
            'skipped' => 0,
            'dry_run' => $dryRun,
        ];

        Subscription::query()
            ->where('billing_engine_version', 'v2')
            ->where('status', 'active')
            ->where('cancel_at_period_end', true)
            ->whereNotNull('current_period_end')
            ->whereDate('current_period_end', '<=', $throughDate->toDateString())
            ->orderBy('id')
            ->each(function (Subscription $subscription) use (&$counts, $dryRun, $userId) {
                if ($dryRun) {
                    $counts['cancelled']++;
                    return;
                }

                $this->applyScheduledCancellation($subscription, $userId);
                $counts['cancelled']++;
            });

        Subscription::query()
            ->with(['plan', 'planPrice', 'pendingPlanPrice.plan'])
            ->where('billing_engine_version', 'v2')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->where('cancel_at_period_end', false)->orWhereNull('cancel_at_period_end');
            })
            ->whereNotNull('next_billing_date')
            ->whereDate('next_billing_date', '<=', $throughDate->toDateString())
            ->orderBy('next_billing_date')
            ->orderBy('id')
            ->each(function (Subscription $subscription) use (&$counts, $dryRun, $userId) {
                $result = $this->renewOne($subscription, $dryRun, $userId);
                $counts[$result]++;
            });

        return $counts;
    }

    public function backfillInitialPeriods(bool $dryRun = false): array
    {
        $counts = [
            'created' => 0,
            'existing' => 0,
            'review' => 0,
            'skipped' => 0,
            'dry_run' => $dryRun,
        ];

        Subscription::query()
            ->with(['planPrice.plan', 'invoices'])
            ->where('billing_engine_version', 'v2')
            ->orderBy('id')
            ->each(function (Subscription $subscription) use (&$counts, $dryRun) {
                if ($subscription->billingPeriods()->exists()) {
                    $counts['existing']++;
                    return;
                }

                $invoice = $subscription->invoices()
                    ->whereNotNull('billing_period_start')
                    ->whereNotNull('billing_period_end')
                    ->orderBy('issue_date')
                    ->orderBy('id')
                    ->first();

                if (
                    $invoice === null
                    || $subscription->current_period_start === null
                    || $subscription->current_period_end === null
                    || $subscription->planPrice === null
                ) {
                    $counts['review']++;
                    return;
                }

                if ($dryRun) {
                    $counts['created']++;
                    return;
                }

                $pricing = [
                    'quantity' => (int) ($subscription->quantity ?: 1),
                    'unit_price_minor' => (int) ($subscription->unit_price_minor ?: $subscription->planPrice->amount_minor),
                    'setup_fee_minor' => (int) ($subscription->setup_fee_minor_v2 ?: 0),
                    'subtotal_minor' => (int) ($subscription->subtotal_minor ?: $invoice->subtotal_minor),
                    'discount_minor' => (int) ($subscription->discount_minor ?: $invoice->discount_minor),
                    'tax_rate_bps' => $subscription->tax_rate_bps,
                    'tax_minor' => (int) ($subscription->tax_minor_v2 ?: $invoice->tax_minor),
                    'total_minor' => (int) ($subscription->total_minor ?: $invoice->total_minor),
                ];

                $period = $this->createOrLinkPeriod(
                    $subscription,
                    $subscription->planPrice,
                    $pricing,
                    $subscription->current_period_start,
                    $subscription->current_period_end,
                    $invoice,
                    1
                );
                $event = $this->recordEvent($subscription, SubscriptionEvent::TYPE_STARTED, $subscription->current_period_start, [
                    'to_plan_id' => $subscription->plan_id,
                    'to_price_minor' => $pricing['unit_price_minor'],
                    'billing_interval' => $subscription->billing_interval_v2,
                    'quantity' => $pricing['quantity'],
                    'created_by' => $subscription->user_id,
                    'metadata' => ['backfilled' => true, 'invoice_id' => $invoice->id],
                ]);
                $this->saasMetricEvents->recordNew($subscription, $event, $period);
                $counts['created']++;
            });

        return $counts;
    }

    public function schedulePlanChange(Subscription $subscription, PlanPrice $price, int $quantity, ?int $userId): Subscription
    {
        $this->assertV2($subscription);
        if ($subscription->status !== 'active') {
            throw ValidationException::withMessages(['subscription_id' => 'يمكن جدولة تغيير خطة لاشتراك نشط فقط.']);
        }
        $price->loadMissing('plan');
        $effectiveAt = $subscription->next_billing_date ?: $this->nextPeriodStart($subscription->current_period_start ?: now(), $price->billing_interval);

        $subscription->update([
            'pending_plan_id' => $price->plan_id,
            'pending_plan_price_id' => $price->id,
            'pending_quantity' => max(1, $quantity),
            'pending_change_effective_at' => Carbon::parse($effectiveAt)->startOfDay(),
        ]);

        $this->recordEvent($subscription, SubscriptionEvent::TYPE_PLAN_CHANGE_SCHEDULED, Carbon::parse($effectiveAt), [
            'from_plan_id' => $subscription->plan_id,
            'to_plan_id' => $price->plan_id,
            'from_price_minor' => $subscription->unit_price_minor,
            'to_price_minor' => $price->amount_minor,
            'billing_interval' => $price->billing_interval,
            'quantity' => max(1, $quantity),
            'created_by' => $userId,
        ]);

        $this->log($subscription->client_id, $userId, 'subscription_plan_change_scheduled', 'تمت جدولة تغيير خطة الاشتراك عند حد الفترة القادمة', [
            'subscription_id' => $subscription->id,
            'pending_plan_price_id' => $price->id,
        ]);

        return $subscription->fresh(['pendingPlanPrice']);
    }

    public function scheduleCancellation(Subscription $subscription, ?string $reason, ?int $userId): Subscription
    {
        $this->assertV2($subscription);
        if ($subscription->status !== 'active') {
            throw ValidationException::withMessages(['subscription_id' => 'يمكن جدولة إلغاء اشتراك نشط فقط.']);
        }

        $effective = $subscription->current_period_end ?: now();
        $subscription->update([
            'cancel_at_period_end' => true,
            'cancellation_requested_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        $this->recordEvent($subscription, SubscriptionEvent::TYPE_CANCELLATION_SCHEDULED, Carbon::parse($effective)->endOfDay(), [
            'from_plan_id' => $subscription->plan_id,
            'from_price_minor' => $subscription->unit_price_minor,
            'billing_interval' => $subscription->billing_interval_v2,
            'quantity' => $subscription->quantity,
            'created_by' => $userId,
            'metadata' => ['reason' => $reason],
        ]);
        $this->log($subscription->client_id, $userId, 'subscription_cancellation_scheduled', 'تمت جدولة إلغاء الاشتراك في نهاية الفترة الحالية', [
            'subscription_id' => $subscription->id,
            'effective_at' => Carbon::parse($effective)->toDateString(),
        ]);

        return $subscription->fresh();
    }

    public function undoCancellation(Subscription $subscription, ?int $userId): Subscription
    {
        $this->assertV2($subscription);
        if (! $subscription->cancel_at_period_end || $subscription->status !== 'active') {
            throw ValidationException::withMessages(['subscription_id' => 'لا توجد جدولة إلغاء نشطة لهذا الاشتراك.']);
        }

        $subscription->update([
            'cancel_at_period_end' => false,
            'cancellation_requested_at' => null,
            'cancellation_reason' => null,
        ]);

        $this->recordEvent($subscription, SubscriptionEvent::TYPE_CANCELLATION_CANCELLED, now(), [
            'from_plan_id' => $subscription->plan_id,
            'from_price_minor' => $subscription->unit_price_minor,
            'billing_interval' => $subscription->billing_interval_v2,
            'quantity' => $subscription->quantity,
            'created_by' => $userId,
        ]);
        $this->log($subscription->client_id, $userId, 'subscription_cancellation_cancelled', 'تم إلغاء جدولة إلغاء الاشتراك', [
            'subscription_id' => $subscription->id,
        ]);

        return $subscription->fresh();
    }

    public function reactivate(Subscription $subscription, PlanPrice $price, array $data, ?int $userId): array
    {
        $this->assertV2($subscription);
        if ($subscription->status !== 'cancelled') {
            throw ValidationException::withMessages(['subscription_id' => 'يمكن إعادة تفعيل الاشتراكات الملغاة فقط.']);
        }

        $start = Carbon::parse($data['start_date'] ?? now())->startOfDay();
        $periodEnd = $this->periodEnd($start, $price->billing_interval);
        $nextBilling = $this->nextPeriodStart($start, $price->billing_interval);
        $pricing = $this->pricingService->calculateSubscription($price->loadMissing('plan'), (int) ($data['quantity'] ?? 1), null);

        return DB::transaction(function () use ($subscription, $price, $pricing, $start, $periodEnd, $nextBilling, $userId) {
            $subscription->update($this->subscriptionUpdateSnapshot($price, $pricing, $start, $periodEnd, $nextBilling) + [
                'status' => 'active',
                'cancel_at_period_end' => false,
                'cancellation_requested_at' => null,
                'cancelled_at' => null,
                'ended_at' => null,
                'cancellation_reason' => null,
                'cancelled_by' => null,
                'version' => (int) $subscription->version + 1,
            ]);

            $invoice = $this->invoiceService->createIssuedForSubscription(
                $subscription->client,
                $subscription->fresh(),
                $pricing,
                $start,
                $start,
                'Subscription reactivation',
                $userId
            );
            $period = $this->createOrLinkPeriod($subscription->fresh(), $price, $pricing, $start, $periodEnd, $invoice, $this->nextPeriodNumber($subscription));
            $event = $this->recordEvent($subscription, SubscriptionEvent::TYPE_REACTIVATED, $start, [
                'to_plan_id' => $price->plan_id,
                'to_price_minor' => $pricing['unit_price_minor'],
                'billing_interval' => $price->billing_interval,
                'quantity' => $pricing['quantity'],
                'created_by' => $userId,
                'metadata' => ['invoice_id' => $invoice->id, 'billing_period_id' => $period->id],
            ]);
            $this->saasMetricEvents->recordReactivation($subscription->fresh(), $event, $period);
            $this->log($subscription->client_id, $userId, 'subscription_reactivated', 'تمت إعادة تفعيل الاشتراك وإنشاء فترة وفاتورة جديدتين', [
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice->id,
            ]);

            return [$subscription->fresh(['billingPeriods', 'lifecycleEvents']), $invoice];
        });
    }

    public function operationsSnapshot(Carbon|string|null $through = null): array
    {
        $today = Carbon::today();
        $throughDate = $through === null ? $today->copy()->addDays(30) : Carbon::parse($through)->startOfDay();

        return [
            'due_today' => Subscription::with('client')->where('billing_engine_version', 'v2')->where('status', 'active')->whereDate('next_billing_date', '<=', $today->toDateString())->get(),
            'due_soon_7' => Subscription::with('client')->where('billing_engine_version', 'v2')->where('status', 'active')->whereBetween('next_billing_date', [$today->copy()->addDay(), $today->copy()->addDays(7)])->get(),
            'due_soon_30' => Subscription::with('client')->where('billing_engine_version', 'v2')->where('status', 'active')->whereBetween('next_billing_date', [$today->copy()->addDay(), $throughDate])->get(),
            'pending_cancellation' => Subscription::with('client')->where('billing_engine_version', 'v2')->where('status', 'active')->where('cancel_at_period_end', true)->get(),
            'generated_periods' => SubscriptionBillingPeriod::with(['subscription.client', 'invoice'])->where('status', SubscriptionBillingPeriod::STATUS_INVOICED)->orderByDesc('generated_at')->limit(20)->get(),
            'review_items' => $this->billingReviewItems($throughDate),
        ];
    }

    public function billingReviewItems(Carbon|string|null $through = null): Collection
    {
        $throughDate = $through === null ? Carbon::today() : Carbon::parse($through)->startOfDay();

        return Subscription::with(['client', 'pendingPlanPrice.plan', 'plan'])
            ->where('billing_engine_version', 'v2')
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->flatMap(function (Subscription $subscription) use ($throughDate) {
                $items = collect();
                if ($subscription->current_period_start === null || $subscription->current_period_end === null || $subscription->next_billing_date === null) {
                    $items->push($this->reviewItem($subscription, 'invalid_period_dates', 'Missing or invalid service-period dates.'));
                    return $items;
                }
                if ($subscription->next_billing_date->lte($throughDate)) {
                    $price = $subscription->pending_plan_price_id
                        ? $this->validPlanPrice($subscription->pendingPlanPrice, $subscription->next_billing_date)
                        : $this->resolveFuturePrice($subscription, $subscription->next_billing_date);
                    if ($price === null) {
                        $items->push($this->reviewItem($subscription, 'missing_future_price', 'No valid active PlanPrice for the next billing period.'));
                    }
                }
                if ($subscription->pending_plan_price_id && $subscription->pendingPlanPrice !== null && ! $this->validPlanPrice($subscription->pendingPlanPrice, $subscription->pending_change_effective_at ?: $subscription->next_billing_date)) {
                    $items->push($this->reviewItem($subscription, 'invalid_pending_plan_change', 'Pending plan change points to a price that is not valid at the billing boundary.'));
                }

                return $items;
            })
            ->values();
    }

    private function renewOne(Subscription $subscription, bool $dryRun, ?int $userId): string
    {
        if (! $this->subscriptionHasDates($subscription)) {
            return 'review';
        }

        $start = $subscription->next_billing_date->copy()->startOfDay();
        $price = $subscription->pending_plan_price_id
            ? $this->validPlanPrice($subscription->pendingPlanPrice, $start)
            : $this->resolveFuturePrice($subscription, $start);

        if ($price === null) {
            return 'review';
        }

        $periodEnd = $this->periodEnd($start, $price->billing_interval);
        $existing = SubscriptionBillingPeriod::where('subscription_id', $subscription->id)
            ->whereDate('period_start', $start->toDateString())
            ->whereDate('period_end', $periodEnd->toDateString())
            ->first();
        if ($existing?->invoice_id !== null) {
            return 'already_processed';
        }
        if ($dryRun) {
            return 'renewed';
        }

        return DB::transaction(function () use ($subscription, $price, $start, $periodEnd, $existing, $userId) {
            $subscription = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            if ($subscription->next_billing_date === null || ! $subscription->next_billing_date->isSameDay($start)) {
                return 'already_processed';
            }

            $oldPlanId = $subscription->plan_id;
            $oldPriceMinor = $subscription->unit_price_minor;
            $beforePeriod = $this->periodCoveringDate($subscription, $start->copy()->subDay());
            $pricing = $this->pricingService->calculateSubscription($price->loadMissing('plan'), (int) ($subscription->pending_quantity ?: $subscription->quantity ?: 1), null);
            $nextBilling = $this->nextPeriodStart($start, $price->billing_interval);
            $hadPlanChange = $subscription->pending_plan_price_id !== null;

            $subscription->update($this->subscriptionUpdateSnapshot($price, $pricing, $start, $periodEnd, $nextBilling) + [
                'pending_plan_id' => null,
                'pending_plan_price_id' => null,
                'pending_quantity' => null,
                'pending_change_effective_at' => null,
                'renewal_date' => $nextBilling->toDateString(),
                'version' => (int) $subscription->version + 1,
            ]);

            $invoice = $this->invoiceService->createIssuedForSubscription(
                $subscription->client,
                $subscription->fresh(),
                $pricing,
                $start,
                $start,
                'Subscription renewal',
                $userId
            );

            $period = $existing ?: $this->createOrLinkPeriod($subscription->fresh(), $price, $pricing, $start, $periodEnd, null, $this->nextPeriodNumber($subscription));
            $period->update([
                'invoice_id' => $invoice->id,
                'status' => SubscriptionBillingPeriod::STATUS_INVOICED,
                'generated_at' => now(),
            ]);

            $movementSourceEvent = null;
            if ($hadPlanChange) {
                $movementSourceEvent = $this->recordEvent($subscription, SubscriptionEvent::TYPE_PLAN_CHANGE_APPLIED, $start, [
                    'from_plan_id' => $oldPlanId,
                    'to_plan_id' => $price->plan_id,
                    'from_price_minor' => $oldPriceMinor,
                    'to_price_minor' => $pricing['unit_price_minor'],
                    'billing_interval' => $price->billing_interval,
                    'quantity' => $pricing['quantity'],
                    'created_by' => $userId,
                    'metadata' => ['billing_period_id' => $period->id],
                ]);
            }

            $renewedEvent = $this->recordEvent($subscription, SubscriptionEvent::TYPE_RENEWED, $start, [
                'to_plan_id' => $price->plan_id,
                'to_price_minor' => $pricing['unit_price_minor'],
                'billing_interval' => $price->billing_interval,
                'quantity' => $pricing['quantity'],
                'created_by' => $userId,
                'metadata' => ['invoice_id' => $invoice->id, 'billing_period_id' => $period->id],
            ]);
            $this->saasMetricEvents->recordEffectivePeriodChange($subscription->fresh(), $movementSourceEvent ?: $renewedEvent, $beforePeriod, $period->fresh());
            $this->log($subscription->client_id, $userId, 'subscription_renewed', 'تم توليد فاتورة تجديد اشتراك', [
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice->id,
                'billing_period_id' => $period->id,
            ]);

            return 'renewed';
        });
    }

    private function applyScheduledCancellation(Subscription $subscription, ?int $userId): void
    {
        DB::transaction(function () use ($subscription, $userId) {
            $subscription = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            if ($subscription->status !== 'active' || ! $subscription->cancel_at_period_end) {
                return;
            }

            $endedAt = Carbon::parse($subscription->current_period_end ?: now())->endOfDay();
            $subscription->update([
                'status' => 'cancelled',
                'cancel_at_period_end' => false,
                'cancelled_at' => $endedAt,
                'ended_at' => $endedAt,
                'cancelled_by' => $userId,
                'pending_plan_id' => null,
                'pending_plan_price_id' => null,
                'pending_quantity' => null,
                'pending_change_effective_at' => null,
            ]);
            $event = $this->recordEvent($subscription, SubscriptionEvent::TYPE_CANCELLED, $endedAt, [
                'from_plan_id' => $subscription->plan_id,
                'from_price_minor' => $subscription->unit_price_minor,
                'billing_interval' => $subscription->billing_interval_v2,
                'quantity' => $subscription->quantity,
                'created_by' => $userId,
            ]);
            $period = $this->periodCoveringDate($subscription, $endedAt);
            $this->saasMetricEvents->recordChurn($subscription->fresh(), $event, $period);
            $this->log($subscription->client_id, $userId, 'subscription_cancelled', 'تم إلغاء الاشتراك في نهاية الفترة الحالية', [
                'subscription_id' => $subscription->id,
            ]);
        });
    }

    private function resolveFuturePrice(Subscription $subscription, Carbon $periodStart): ?PlanPrice
    {
        if ($subscription->plan_id === null || $subscription->billing_interval_v2 === null) {
            return null;
        }

        $price = PlanPrice::with('plan')
            ->where('plan_id', $subscription->plan_id)
            ->where('billing_interval', $subscription->billing_interval_v2)
            ->effective($periodStart)
            ->orderByDesc('effective_from')
            ->first();

        return $this->validPlanPrice($price, $periodStart);
    }

    private function validPlanPrice(?PlanPrice $price, Carbon|string|null $at): ?PlanPrice
    {
        if ($price === null || $at === null) {
            return null;
        }
        $at = Carbon::parse($at);
        $price->loadMissing('plan');
        if (! $price->is_active || ! $price->plan?->is_active || $price->plan?->archived_at !== null) {
            return null;
        }
        if ($price->effective_from !== null && $price->effective_from->gt($at)) {
            return null;
        }
        if ($price->effective_until !== null && $price->effective_until->lte($at)) {
            return null;
        }

        return $price;
    }

    private function createOrLinkPeriod(Subscription $subscription, PlanPrice $price, array $pricing, Carbon|string $start, Carbon|string $end, ?Invoice $invoice, int $periodNumber): SubscriptionBillingPeriod
    {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();

        $period = SubscriptionBillingPeriod::where('subscription_id', $subscription->id)
            ->whereDate('period_start', $start->toDateString())
            ->whereDate('period_end', $end->toDateString())
            ->first();

        if ($period) {
            if ($invoice && $period->invoice_id === null) {
                $period->update([
                    'invoice_id' => $invoice->id,
                    'status' => SubscriptionBillingPeriod::STATUS_INVOICED,
                    'generated_at' => now(),
                ]);
            }

            return $period->fresh();
        }

        return SubscriptionBillingPeriod::create([
            'subscription_id' => $subscription->id,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'period_number' => $periodNumber,
            'billing_interval' => $price->billing_interval,
            'plan_id' => $price->plan_id,
            'plan_price_id' => $price->id,
            'plan_name_snapshot' => $price->plan?->name_ar ?: $subscription->plan_name_snapshot,
            'price_snapshot_minor' => (int) $pricing['unit_price_minor'],
            'quantity' => (int) $pricing['quantity'],
            'subtotal_minor' => (int) $pricing['subtotal_minor'],
            'discount_minor' => (int) $pricing['discount_minor'],
            'tax_minor' => (int) $pricing['tax_minor'],
            'total_minor' => (int) $pricing['total_minor'],
            'currency' => $price->currency ?: 'JOD',
            'invoice_id' => $invoice?->id,
            'status' => $invoice ? SubscriptionBillingPeriod::STATUS_INVOICED : SubscriptionBillingPeriod::STATUS_SCHEDULED,
            'generated_at' => $invoice ? now() : null,
        ]);
    }

    private function recordEvent(Subscription $subscription, string $type, Carbon|string $effectiveAt, array $data = []): SubscriptionEvent
    {
        return SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'event_type' => $type,
            'effective_at' => Carbon::parse($effectiveAt),
            'from_plan_id' => $data['from_plan_id'] ?? null,
            'to_plan_id' => $data['to_plan_id'] ?? null,
            'from_price_minor' => $data['from_price_minor'] ?? null,
            'to_price_minor' => $data['to_price_minor'] ?? null,
            'billing_interval' => $data['billing_interval'] ?? null,
            'quantity' => $data['quantity'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'created_by' => $data['created_by'] ?? null,
            'created_at' => now(),
        ]);
    }

    private function subscriptionSnapshotAttributes(Client $client, PlanPrice $price, array $pricing, Carbon $start, Carbon $end, Carbon $nextBilling, ?int $userId, string $status, int $version): array
    {
        return [
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
            'current_period_end' => $end->toDateString(),
            'next_billing_date' => $nextBilling->toDateString(),
            'billing_type' => $price->billing_interval,
            'total_price' => Money::fromMinorUnits($pricing['total_minor'])->format(),
            'grand_total' => Money::fromMinorUnits($pricing['total_minor'])->format(),
            'setup_fee' => Money::fromMinorUnits($pricing['setup_fee_minor'])->format(),
            'start_date' => $start->toDateString(),
            'renewal_date' => $nextBilling->toDateString(),
            'installments_count' => 1,
            'status' => $status,
            'version' => $version,
        ];
    }

    private function subscriptionUpdateSnapshot(PlanPrice $price, array $pricing, Carbon $start, Carbon $end, Carbon $nextBilling): array
    {
        return [
            'plan_id' => $price->plan_id,
            'plan_price_id' => $price->id,
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
            'current_period_end' => $end->toDateString(),
            'next_billing_date' => $nextBilling->toDateString(),
            'billing_type' => $price->billing_interval,
            'total_price' => Money::fromMinorUnits($pricing['total_minor'])->format(),
            'grand_total' => Money::fromMinorUnits($pricing['total_minor'])->format(),
            'setup_fee' => Money::fromMinorUnits($pricing['setup_fee_minor'])->format(),
        ];
    }

    private function periodEnd(Carbon $start, string $interval): Carbon
    {
        return $interval === PlanPrice::ANNUAL
            ? $start->copy()->addYear()->subDay()
            : $start->copy()->addMonthNoOverflow()->subDay();
    }

    private function nextPeriodStart(Carbon $start, string $interval): Carbon
    {
        return $interval === PlanPrice::ANNUAL
            ? $start->copy()->addYear()
            : $start->copy()->addMonthNoOverflow();
    }

    private function nextPeriodNumber(Subscription $subscription): int
    {
        return ((int) $subscription->billingPeriods()->max('period_number')) + 1;
    }

    private function periodCoveringDate(Subscription $subscription, Carbon|string $date): ?SubscriptionBillingPeriod
    {
        $date = Carbon::parse($date)->startOfDay();

        return SubscriptionBillingPeriod::where('subscription_id', $subscription->id)
            ->whereDate('period_start', '<=', $date->toDateString())
            ->whereDate('period_end', '>=', $date->toDateString())
            ->orderByDesc('period_start')
            ->first();
    }

    private function subscriptionHasDates(Subscription $subscription): bool
    {
        return $subscription->current_period_start !== null
            && $subscription->current_period_end !== null
            && $subscription->next_billing_date !== null;
    }

    private function assertV2(Subscription $subscription): void
    {
        if ($subscription->billing_engine_version !== 'v2') {
            throw ValidationException::withMessages(['subscription_id' => 'هذا الإجراء متاح فقط لاشتراكات V2.']);
        }
    }

    private function reviewItem(Subscription $subscription, string $reason, string $message): array
    {
        return [
            'subscription' => $subscription,
            'client' => $subscription->client,
            'reason' => $reason,
            'message' => $message,
            'resolution' => 'Review subscription dates and assign a valid active plan price before renewal.',
        ];
    }

    private function log(?int $clientId, ?int $userId, string $type, string $description, array $metadata): void
    {
        DB::table('activity_logs')->insert([
            'client_id' => $clientId,
            'user_id' => $userId,
            'type' => $type,
            'description' => $description,
            'metadata' => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
