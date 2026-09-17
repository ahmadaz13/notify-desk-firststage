<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\InvoiceLine;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\RevenueRecognitionPeriod;
use App\Models\RevenueRecognitionSchedule;
use App\Models\Subscription;
use App\Models\SubscriptionBillingPeriod;
use App\Models\SubscriptionMetricEvent;
use App\Support\Money;
use App\Support\ReportingPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SaasMetricsService
{
    public function __construct(
        private readonly SaasMetricEventService $events,
        private readonly SubscriptionBillingService $billingService
    ) {
    }

    public function dashboard(ReportingPeriod $period): array
    {
        $asOf = $period->end;
        $active = $this->activePeriods($asOf);
        $endingArr = (int) $active->sum(fn (SubscriptionBillingPeriod $period) => $this->events->normalizedArrMinorForPeriod($period));
        $startingAsOf = $period->start->copy()->subSecond();
        $startingArr = $this->endingArrAt($startingAsOf);
        $movement = $this->movementSummary($period);
        $activeSubscriptions = $active->pluck('subscription_id')->unique()->count();
        $activeClients = $active->map(fn (SubscriptionBillingPeriod $period) => $period->subscription?->client_id)->filter()->unique()->count();
        $startingSubscriptions = $this->activePeriods($startingAsOf)->pluck('subscription_id')->unique()->count();

        return [
            'period' => $period,
            'as_of' => $asOf,
            'ending_arr_minor' => $endingArr,
            'ending_mrr_minor' => $this->events->mrrMinorFromArr($endingArr),
            'starting_arr_minor' => $startingArr,
            'starting_mrr_minor' => $this->events->mrrMinorFromArr($startingArr),
            'active_subscriptions' => $activeSubscriptions,
            'active_subscribing_clients' => $activeClients,
            'movements' => $movement,
            'net_new_arr_minor' => $movement['new_arr_minor'] + $movement['expansion_arr_minor'] + $movement['reactivation_arr_minor'] - $movement['contraction_arr_minor'] - $movement['churn_arr_minor'],
            'net_new_mrr_minor' => $this->events->mrrMinorFromArr($movement['new_arr_minor'] + $movement['expansion_arr_minor'] + $movement['reactivation_arr_minor'] - $movement['contraction_arr_minor'] - $movement['churn_arr_minor']),
            'logo_churn_rate_bps' => $startingSubscriptions > 0 ? $this->ratioBps($movement['churned_subscriptions'], $startingSubscriptions) : null,
            'gross_mrr_churn_rate_bps' => $startingArr > 0 ? $this->ratioBps($movement['churn_arr_minor'], $startingArr) : null,
            'contraction_rate_bps' => $startingArr > 0 ? $this->ratioBps($movement['contraction_arr_minor'], $startingArr) : null,
            'nrr_bps' => $startingArr > 0 ? $this->ratioBps($startingArr + $movement['expansion_arr_minor'] - $movement['contraction_arr_minor'] - $movement['churn_arr_minor'], $startingArr) : null,
            'grr_bps' => $startingArr > 0 ? min(10000, $this->ratioBps(max(0, $startingArr - $movement['contraction_arr_minor'] - $movement['churn_arr_minor']), $startingArr)) : null,
            'active_periods' => $active,
            'plan_metrics' => $this->planMetrics($active, $period),
            'billing_interval_metrics' => $this->billingIntervalMetrics($active),
            'renewal_metrics' => $this->renewalMetrics(),
            'trend' => $this->trend($period->end),
            'movement_events' => $this->movementEvents($period),
            'cash_collected_minor' => $this->cashCollectedMinor($period),
        ];
    }

    public function endingArrAt(Carbon|string $asOf): int
    {
        return (int) $this->activePeriods($asOf)
            ->sum(fn (SubscriptionBillingPeriod $period) => $this->events->normalizedArrMinorForPeriod($period));
    }

    public function activePeriods(Carbon|string $asOf): Collection
    {
        $date = Carbon::parse($asOf);

        return SubscriptionBillingPeriod::query()
            ->with(['subscription.client', 'plan', 'invoice.lines'])
            ->whereDate('period_start', '<=', $date->toDateString())
            ->whereDate('period_end', '>=', $date->toDateString())
            ->whereHas('subscription', function ($query) use ($date) {
                $query->where('billing_engine_version', 'v2')
                    ->whereDate('start_date', '<=', $date->toDateString())
                    ->where(function ($query) use ($date) {
                        $query->whereNull('ended_at')->orWhere('ended_at', '>', $date);
                    });
            })
            ->orderBy('subscription_id')
            ->get();
    }

    public function movementEvents(ReportingPeriod $period): Collection
    {
        return SubscriptionMetricEvent::with(['subscription.client'])
            ->whereBetween('effective_at', [$period->start, $period->end])
            ->orderBy('effective_at')
            ->orderBy('id')
            ->get();
    }

    public function movementSummary(ReportingPeriod $period): array
    {
        $events = $this->movementEvents($period);
        $sum = fn (string $type) => (int) $events
            ->where('movement_type', $type)
            ->sum(fn (SubscriptionMetricEvent $event) => abs((int) $event->arr_delta_minor));

        $newArr = $sum(SubscriptionMetricEvent::TYPE_NEW);
        $expansionArr = $sum(SubscriptionMetricEvent::TYPE_EXPANSION);
        $contractionArr = $sum(SubscriptionMetricEvent::TYPE_CONTRACTION);
        $churnArr = $sum(SubscriptionMetricEvent::TYPE_CHURN);
        $reactivationArr = $sum(SubscriptionMetricEvent::TYPE_REACTIVATION);

        return [
            'new_arr_minor' => $newArr,
            'new_mrr_minor' => $this->events->mrrMinorFromArr($newArr),
            'expansion_arr_minor' => $expansionArr,
            'expansion_mrr_minor' => $this->events->mrrMinorFromArr($expansionArr),
            'contraction_arr_minor' => $contractionArr,
            'contraction_mrr_minor' => $this->events->mrrMinorFromArr($contractionArr),
            'churn_arr_minor' => $churnArr,
            'churn_mrr_minor' => $this->events->mrrMinorFromArr($churnArr),
            'reactivation_arr_minor' => $reactivationArr,
            'reactivation_mrr_minor' => $this->events->mrrMinorFromArr($reactivationArr),
            'churned_subscriptions' => $events->where('movement_type', SubscriptionMetricEvent::TYPE_CHURN)->pluck('subscription_id')->unique()->count(),
        ];
    }

    public function planMetrics(Collection $activePeriods, ReportingPeriod $period): Collection
    {
        $recognized = $this->recognizedRevenueByPlan($period)->keyBy('plan_id');

        return $activePeriods
            ->groupBy('plan_id')
            ->map(function (Collection $periods, $planId) use ($recognized) {
                $arr = (int) $periods->sum(fn (SubscriptionBillingPeriod $period) => $this->events->normalizedArrMinorForPeriod($period));
                $plan = $periods->first()->plan;

                return [
                    'plan_id' => $planId,
                    'plan_name' => $plan?->name_ar ?: $periods->first()->plan_name_snapshot ?: 'Unattributed',
                    'active_subscriptions' => $periods->pluck('subscription_id')->unique()->count(),
                    'arr_minor' => $arr,
                    'mrr_minor' => $this->events->mrrMinorFromArr($arr),
                    'recognized_revenue_minor' => (int) ($recognized[$planId]['amount_minor'] ?? 0),
                ];
            })
            ->values();
    }

    public function billingIntervalMetrics(Collection $activePeriods): array
    {
        $monthly = $activePeriods->where('billing_interval', PlanPrice::MONTHLY);
        $annual = $activePeriods->where('billing_interval', PlanPrice::ANNUAL);
        $monthlyArr = (int) $monthly->sum(fn (SubscriptionBillingPeriod $period) => $this->events->normalizedArrMinorForPeriod($period));
        $annualArr = (int) $annual->sum(fn (SubscriptionBillingPeriod $period) => $this->events->normalizedArrMinorForPeriod($period));
        $count = max(1, $activePeriods->count());

        return [
            'monthly_count' => $monthly->count(),
            'annual_count' => $annual->count(),
            'monthly_arr_minor' => $monthlyArr,
            'annual_arr_minor' => $annualArr,
            'monthly_mix_bps' => $this->ratioBps($monthly->count(), $count),
            'annual_mix_bps' => $this->ratioBps($annual->count(), $count),
        ];
    }

    public function renewalMetrics(): array
    {
        $snapshot = $this->billingService->operationsSnapshot();

        return [
            'due_7_count' => $snapshot['due_soon_7']->count(),
            'due_30_count' => $snapshot['due_soon_30']->count(),
            'annual_due_count' => $snapshot['due_soon_30']->where('billing_interval_v2', PlanPrice::ANNUAL)->count(),
            'monthly_due_count' => $snapshot['due_soon_30']->where('billing_interval_v2', PlanPrice::MONTHLY)->count(),
            'pending_cancellations' => $snapshot['pending_cancellation']->count(),
            'billing_review_count' => $snapshot['review_items']->count(),
            'items' => $snapshot,
        ];
    }

    public function trend(Carbon|string $endingAt, int $months = 12): Collection
    {
        $end = Carbon::parse($endingAt)->endOfMonth();
        $start = $end->copy()->subMonths($months - 1)->startOfMonth();
        $rows = collect();
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $period = new ReportingPeriod($cursor->copy()->startOfMonth(), $cursor->copy()->endOfMonth(), 'custom_date_range');
            $summary = $this->movementSummary($period);
            $endingArr = $this->endingArrAt($period->end);
            $startingArr = $this->endingArrAt($period->start);

            $rows->push([
                'month' => $cursor->format('Y-m'),
                'ending_arr_minor' => $endingArr,
                'ending_mrr_minor' => $this->events->mrrMinorFromArr($endingArr),
                'new_mrr_minor' => $summary['new_mrr_minor'],
                'expansion_mrr_minor' => $summary['expansion_mrr_minor'],
                'contraction_mrr_minor' => $summary['contraction_mrr_minor'],
                'churn_mrr_minor' => $summary['churn_mrr_minor'],
                'reactivation_mrr_minor' => $summary['reactivation_mrr_minor'],
                'net_new_mrr_minor' => $this->events->mrrMinorFromArr($summary['new_arr_minor'] + $summary['expansion_arr_minor'] + $summary['reactivation_arr_minor'] - $summary['contraction_arr_minor'] - $summary['churn_arr_minor']),
                'active_subscribers' => $this->activePeriods($period->end)->pluck('subscription.client_id')->filter()->unique()->count(),
                'nrr_bps' => $startingArr > 0 ? $this->ratioBps($startingArr + $summary['expansion_arr_minor'] - $summary['contraction_arr_minor'] - $summary['churn_arr_minor'], $startingArr) : null,
                'grr_bps' => $startingArr > 0 ? min(10000, $this->ratioBps(max(0, $startingArr - $summary['contraction_arr_minor'] - $summary['churn_arr_minor']), $startingArr)) : null,
            ]);
            $cursor->addMonthNoOverflow();
        }

        return $rows;
    }

    public function exportRows(string $report, ReportingPeriod $period): array
    {
        $dashboard = $this->dashboard($period);

        return match ($report) {
            'summary' => [
                ['Metric', 'JOD'],
                ['MRR', $this->jod($dashboard['ending_mrr_minor'])],
                ['ARR', $this->jod($dashboard['ending_arr_minor'])],
                ['Net New MRR', $this->jod($dashboard['net_new_mrr_minor'])],
            ],
            'monthly-movement' => $dashboard['trend']->prepend(['Month', 'Ending MRR', 'New MRR', 'Expansion MRR', 'Contraction MRR', 'Churn MRR', 'Reactivation MRR', 'Net New MRR'])->map(function ($row) {
                return is_array($row) && isset($row['month'])
                    ? [$row['month'], $this->jod($row['ending_mrr_minor']), $this->jod($row['new_mrr_minor']), $this->jod($row['expansion_mrr_minor']), $this->jod($row['contraction_mrr_minor']), $this->jod($row['churn_mrr_minor']), $this->jod($row['reactivation_mrr_minor']), $this->jod($row['net_new_mrr_minor'])]
                    : $row;
            })->values()->all(),
            'active-subscriptions' => $dashboard['active_periods']->map(fn (SubscriptionBillingPeriod $period) => [
                $period->subscription?->client?->business_name,
                $period->subscription_id,
                $period->plan_name_snapshot,
                $period->billing_interval,
                $this->jod($this->events->mrrMinorFromArr($this->events->normalizedArrMinorForPeriod($period))),
                $this->jod($this->events->normalizedArrMinorForPeriod($period)),
            ])->prepend(['Client', 'Subscription ID', 'Plan', 'Interval', 'MRR JOD', 'ARR JOD'])->values()->all(),
            'churned-subscriptions' => $dashboard['movement_events']->where('movement_type', SubscriptionMetricEvent::TYPE_CHURN)->map(fn (SubscriptionMetricEvent $event) => [
                $event->subscription?->client?->business_name,
                $event->subscription_id,
                $event->effective_at->toDateString(),
                $this->jod(abs($this->events->mrrMinorFromArr($event->arr_delta_minor))),
            ])->prepend(['Client', 'Subscription ID', 'Effective Date', 'Churn MRR JOD'])->values()->all(),
            'mrr-by-plan' => $dashboard['plan_metrics']->map(fn (array $row) => [$row['plan_name'], $row['active_subscriptions'], $this->jod($row['mrr_minor']), $this->jod($row['arr_minor'])])->prepend(['Plan', 'Active Subscriptions', 'MRR JOD', 'ARR JOD'])->values()->all(),
            default => abort(404),
        };
    }

    public function cashCollectedMinor(ReportingPeriod $period): int
    {
        $payments = (int) CashMovement::where('event_type', CashMovement::EVENT_PAYMENT_RECEIVED)
            ->whereBetween('occurred_at', [$period->start, $period->end])
            ->sum('amount_minor');
        $reversals = (int) CashMovement::where('event_type', CashMovement::EVENT_PAYMENT_REVERSAL)
            ->whereBetween('occurred_at', [$period->start, $period->end])
            ->sum('amount_minor');

        return $payments - $reversals;
    }

    public function jod(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';

        return $sign.Money::fromMinorUnits(abs($minor))->format();
    }

    public function bps(?int $bps): string
    {
        if ($bps === null) {
            return 'N/A';
        }
        $major = intdiv($bps, 100);
        $fraction = $bps % 100;

        return sprintf('%d.%02d%%', $major, $fraction);
    }

    private function ratioBps(int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            return 0;
        }

        $numerator = (int) $numerator;
        $denominator = (int) $denominator;
        $whole = intdiv($numerator, $denominator) * 10000;
        $remainder = $numerator % $denominator;

        return $whole + $this->scaledRemainderBps($remainder, $denominator);
    }

    private function scaledRemainderBps(int $remainder, int $denominator): int
    {
        $result = 0;
        $carry = intdiv($denominator, 2);
        for ($i = 0; $i < 10000; $i++) {
            $carry += $remainder;
            if ($carry >= $denominator) {
                $step = intdiv($carry, $denominator);
                $result += $step;
                $carry -= $step * $denominator;
            }
        }

        return $result;
    }

    private function recognizedRevenueByPlan(ReportingPeriod $period): Collection
    {
        return RevenueRecognitionSchedule::query()
            ->with('invoiceLine.plan')
            ->whereHas('periods', fn ($query) => $query
                ->where('status', RevenueRecognitionPeriod::STATUS_RECOGNIZED)
                ->whereBetween('recognized_at', [$period->start, $period->end]))
            ->get()
            ->groupBy(fn (RevenueRecognitionSchedule $schedule) => $schedule->invoiceLine?->plan_id ?: 'unattributed')
            ->map(fn (Collection $schedules, string $planId) => [
                'plan_id' => $planId === 'unattributed' ? null : (int) $planId,
                'plan_name' => $schedules->first()->invoiceLine?->plan?->name_ar ?? 'Unattributed',
                'amount_minor' => (int) $schedules->sum(fn (RevenueRecognitionSchedule $schedule) => $schedule->periods()
                    ->where('status', RevenueRecognitionPeriod::STATUS_RECOGNIZED)
                    ->whereBetween('recognized_at', [$period->start, $period->end])
                    ->sum('recognized_minor')),
            ])
            ->values();
    }
}
