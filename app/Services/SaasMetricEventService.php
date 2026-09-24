<?php

namespace App\Services;

use App\Models\InvoiceLine;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionBillingPeriod;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionMetricEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SaasMetricEventService
{
    public function normalizedArrMinorForPeriod(SubscriptionBillingPeriod $period): int
    {
        $recurringMinor = $this->recurringContractMinor($period);

        return $period->billing_interval === PlanPrice::ANNUAL
            ? $recurringMinor
            : $recurringMinor * 12;
    }

    public function mrrMinorFromArr(int $arrMinor): int
    {
        $sign = $arrMinor < 0 ? -1 : 1;

        return $sign * intdiv(abs($arrMinor) + 6, 12);
    }

    public function recordNew(Subscription $subscription, SubscriptionEvent $event, SubscriptionBillingPeriod $period): ?SubscriptionMetricEvent
    {
        return $this->recordTransition(
            $subscription,
            $event,
            $event->effective_at,
            0,
            $this->normalizedArrMinorForPeriod($period),
            [
                'plan_before_id' => null,
                'plan_after_id' => $period->plan_id,
                'billing_interval_before' => null,
                'billing_interval_after' => $period->billing_interval,
                'quantity_before' => null,
                'quantity_after' => $period->quantity,
                'source_type' => 'subscription_billing_period',
                'source_id' => $period->id,
                'metadata' => ['billing_period_id' => $period->id, 'invoice_id' => $period->invoice_id],
            ],
            SubscriptionMetricEvent::TYPE_NEW
        );
    }

    public function recordReactivation(Subscription $subscription, SubscriptionEvent $event, SubscriptionBillingPeriod $period): ?SubscriptionMetricEvent
    {
        return $this->recordTransition(
            $subscription,
            $event,
            $event->effective_at,
            0,
            $this->normalizedArrMinorForPeriod($period),
            [
                'plan_before_id' => null,
                'plan_after_id' => $period->plan_id,
                'billing_interval_before' => null,
                'billing_interval_after' => $period->billing_interval,
                'quantity_before' => null,
                'quantity_after' => $period->quantity,
                'source_type' => 'subscription_billing_period',
                'source_id' => $period->id,
                'metadata' => ['billing_period_id' => $period->id, 'invoice_id' => $period->invoice_id],
            ],
            SubscriptionMetricEvent::TYPE_REACTIVATION
        );
    }

    public function recordEffectivePeriodChange(
        Subscription $subscription,
        SubscriptionEvent $event,
        ?SubscriptionBillingPeriod $beforePeriod,
        SubscriptionBillingPeriod $afterPeriod
    ): ?SubscriptionMetricEvent {
        $beforeArr = $beforePeriod ? $this->normalizedArrMinorForPeriod($beforePeriod) : 0;
        $afterArr = $this->normalizedArrMinorForPeriod($afterPeriod);

        return $this->recordTransition($subscription, $event, $event->effective_at, $beforeArr, $afterArr, [
            'plan_before_id' => $beforePeriod?->plan_id,
            'plan_after_id' => $afterPeriod->plan_id,
            'billing_interval_before' => $beforePeriod?->billing_interval,
            'billing_interval_after' => $afterPeriod->billing_interval,
            'quantity_before' => $beforePeriod?->quantity,
            'quantity_after' => $afterPeriod->quantity,
            'source_type' => 'subscription_billing_period',
            'source_id' => $afterPeriod->id,
            'metadata' => [
                'before_billing_period_id' => $beforePeriod?->id,
                'after_billing_period_id' => $afterPeriod->id,
                'invoice_id' => $afterPeriod->invoice_id,
            ],
        ]);
    }

    public function recordChurn(Subscription $subscription, SubscriptionEvent $event, ?SubscriptionBillingPeriod $period): ?SubscriptionMetricEvent
    {
        $beforeArr = $period ? $this->normalizedArrMinorForPeriod($period) : 0;

        return $this->recordTransition(
            $subscription,
            $event,
            $event->effective_at,
            $beforeArr,
            0,
            [
                'plan_before_id' => $period?->plan_id ?: $subscription->plan_id,
                'plan_after_id' => null,
                'billing_interval_before' => $period?->billing_interval ?: $subscription->billing_interval_v2,
                'billing_interval_after' => null,
                'quantity_before' => $period?->quantity ?: $subscription->quantity,
                'quantity_after' => null,
                'source_type' => 'subscription_event',
                'source_id' => $event->id,
                'metadata' => ['billing_period_id' => $period?->id],
            ],
            SubscriptionMetricEvent::TYPE_CHURN
        );
    }

    public function backfill(bool $dryRun = false): array
    {
        $counts = ['created' => 0, 'existing' => 0, 'review' => 0, 'skipped' => 0, 'dry_run' => $dryRun];

        Subscription::query()
            ->with(['billingPeriods.invoice.lines', 'lifecycleEvents'])
            ->where('billing_engine_version', 'v2')
            ->orderBy('id')
            ->each(function (Subscription $subscription) use (&$counts, $dryRun) {
                $periods = $subscription->billingPeriods->sortBy(['period_start', 'id'])->values();
                if ($periods->isEmpty()) {
                    $counts['review']++;
                    return;
                }

                $previous = null;
                foreach ($periods as $index => $period) {
                    $event = $this->eventForPeriod($subscription->lifecycleEvents, $period, $index === 0);
                    if ($event === null && $index === 0) {
                        $counts['review']++;
                        $previous = $period;
                        continue;
                    }

                    if ($dryRun) {
                        if ($index === 0 || $this->normalizedArrMinorForPeriod($previous) !== $this->normalizedArrMinorForPeriod($period)) {
                            $counts['created']++;
                        }
                        $previous = $period;
                        continue;
                    }

                    $created = $index === 0
                        ? $this->recordNew($subscription, $event, $period)
                        : $this->recordEffectivePeriodChange($subscription, $event ?: $this->syntheticPeriodEvent($subscription, $period), $previous, $period);
                    $created ? $counts['created']++ : $counts['existing']++;
                    $previous = $period;
                }

                $subscription->lifecycleEvents
                    ->where('event_type', SubscriptionEvent::TYPE_CANCELLED)
                    ->each(function (SubscriptionEvent $event) use ($subscription, $periods, &$counts, $dryRun) {
                        $period = $this->periodCovering($periods, $event->effective_at);
                        if ($period === null) {
                            $counts['review']++;
                            return;
                        }
                        if ($dryRun) {
                            $counts['created']++;
                            return;
                        }
                        $created = $this->recordChurn($subscription, $event, $period);
                        $created ? $counts['created']++ : $counts['existing']++;
                    });
            });

        return $counts;
    }

    public function activePeriodForSubscription(Subscription $subscription, Carbon|string $asOf): ?SubscriptionBillingPeriod
    {
        $asOf = Carbon::parse($asOf);

        if (! in_array($subscription->billing_engine_version, Subscription::RECURRING_BILLING_ENGINES, true)) {
            return null;
        }
        if ($subscription->ended_at !== null && $subscription->ended_at->lte($asOf)) {
            return null;
        }

        return $subscription->billingPeriods()
            ->whereDate('period_start', '<=', $asOf->toDateString())
            ->whereDate('period_end', '>=', $asOf->toDateString())
            ->orderByDesc('period_start')
            ->first();
    }

    public function recurringContractMinor(SubscriptionBillingPeriod $period): int
    {
        $period->loadMissing('invoice.lines');
        if ($period->invoice) {
            $lineTotal = (int) $period->invoice->lines
                ->where('line_type', InvoiceLine::TYPE_SUBSCRIPTION)
                ->sum(fn (InvoiceLine $line) => (int) $line->subtotal_minor - (int) $line->discount_minor);
            if ($lineTotal > 0) {
                return $lineTotal;
            }
        }

        return max(0, (int) $period->subtotal_minor - (int) $period->discount_minor - (int) $period->tax_minor);
    }

    private function recordTransition(
        Subscription $subscription,
        ?SubscriptionEvent $event,
        Carbon|string $effectiveAt,
        int $beforeArr,
        int $afterArr,
        array $attributes,
        ?string $forcedType = null
    ): ?SubscriptionMetricEvent {
        $movement = $forcedType ?: $this->classify($beforeArr, $afterArr);
        if ($movement === null) {
            return null;
        }

        if ($event && SubscriptionMetricEvent::where('subscription_event_id', $event->id)->exists()) {
            return null;
        }

        if (($attributes['source_id'] ?? null) !== null && SubscriptionMetricEvent::where('source_type', $attributes['source_type'])->where('source_id', $attributes['source_id'])->where('movement_type', $movement)->exists()) {
            return null;
        }

        return SubscriptionMetricEvent::create([
            'subscription_id' => $subscription->id,
            'subscription_event_id' => $event?->id,
            'effective_at' => Carbon::parse($effectiveAt),
            'movement_type' => $movement,
            'arr_before_minor' => $beforeArr,
            'arr_after_minor' => $afterArr,
            'arr_delta_minor' => $afterArr - $beforeArr,
            'plan_before_id' => $attributes['plan_before_id'] ?? null,
            'plan_after_id' => $attributes['plan_after_id'] ?? null,
            'billing_interval_before' => $attributes['billing_interval_before'] ?? null,
            'billing_interval_after' => $attributes['billing_interval_after'] ?? null,
            'quantity_before' => $attributes['quantity_before'] ?? null,
            'quantity_after' => $attributes['quantity_after'] ?? null,
            'source_type' => $attributes['source_type'],
            'source_id' => $attributes['source_id'] ?? null,
            'metadata' => $attributes['metadata'] ?? null,
            'created_at' => now(),
        ]);
    }

    private function classify(int $beforeArr, int $afterArr): ?string
    {
        if ($beforeArr === $afterArr) {
            return null;
        }
        if ($beforeArr === 0 && $afterArr > 0) {
            return SubscriptionMetricEvent::TYPE_NEW;
        }
        if ($beforeArr > 0 && $afterArr === 0) {
            return SubscriptionMetricEvent::TYPE_CHURN;
        }

        return $afterArr > $beforeArr ? SubscriptionMetricEvent::TYPE_EXPANSION : SubscriptionMetricEvent::TYPE_CONTRACTION;
    }

    private function eventForPeriod(Collection $events, SubscriptionBillingPeriod $period, bool $first): ?SubscriptionEvent
    {
        $wanted = $first
            ? [SubscriptionEvent::TYPE_STARTED, SubscriptionEvent::TYPE_REACTIVATED]
            : [SubscriptionEvent::TYPE_PLAN_CHANGE_APPLIED, SubscriptionEvent::TYPE_RENEWED, SubscriptionEvent::TYPE_REACTIVATED];

        return $events
            ->whereIn('event_type', $wanted)
            ->first(fn (SubscriptionEvent $event) => $event->effective_at->isSameDay($period->period_start));
    }

    private function syntheticPeriodEvent(Subscription $subscription, SubscriptionBillingPeriod $period): SubscriptionEvent
    {
        return new SubscriptionEvent([
            'subscription_id' => $subscription->id,
            'event_type' => SubscriptionEvent::TYPE_RENEWED,
            'effective_at' => $period->period_start,
        ]);
    }

    private function periodCovering(Collection $periods, Carbon|string $at): ?SubscriptionBillingPeriod
    {
        $date = Carbon::parse($at)->startOfDay();

        return $periods->first(fn (SubscriptionBillingPeriod $period) => $period->period_start->lte($date) && $period->period_end->gte($date));
    }
}
