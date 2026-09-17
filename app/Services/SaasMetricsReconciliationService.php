<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionMetricEvent;
use App\Support\ReportingPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SaasMetricsReconciliationService
{
    public function __construct(
        private readonly SaasMetricsService $metrics,
        private readonly SaasMetricEventService $events
    ) {
    }

    public function run(?ReportingPeriod $period = null): array
    {
        $period ??= new ReportingPeriod(now()->copy()->startOfMonth(), now()->copy()->endOfMonth(), 'current_month');
        $failures = [];
        $dashboard = $this->metrics->dashboard($period);
        $endingArr = $this->metrics->endingArrAt($period->end);

        if ($dashboard['ending_arr_minor'] !== $endingArr) {
            $failures[] = ['check' => 'ending_arr_matches_active_periods', 'expected' => $endingArr, 'actual' => $dashboard['ending_arr_minor']];
        }
        if ($dashboard['ending_mrr_minor'] !== $this->events->mrrMinorFromArr($dashboard['ending_arr_minor'])) {
            $failures[] = ['check' => 'ending_mrr_derives_from_aggregate_arr'];
        }

        $equationEnding = (int) $dashboard['starting_arr_minor']
            + $dashboard['movements']['new_arr_minor']
            + $dashboard['movements']['expansion_arr_minor']
            + $dashboard['movements']['reactivation_arr_minor']
            - $dashboard['movements']['contraction_arr_minor']
            - $dashboard['movements']['churn_arr_minor'];
        if ($dashboard['ending_arr_minor'] !== $equationEnding) {
            $failures[] = ['check' => 'period_arr_movement_equation', 'expected' => $equationEnding, 'actual' => $dashboard['ending_arr_minor']];
        }

        $duplicateEventSources = SubscriptionMetricEvent::whereNotNull('subscription_event_id')
            ->select('subscription_event_id', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('subscription_event_id')
            ->having('aggregate', '>', 1)
            ->count();
        if ($duplicateEventSources > 0) {
            $failures[] = ['check' => 'no_duplicate_subscription_event_sources', 'count' => $duplicateEventSources];
        }

        $scheduledChurn = SubscriptionMetricEvent::query()
            ->join('subscription_events', 'subscription_events.id', '=', 'subscription_metric_events.subscription_event_id')
            ->where('subscription_metric_events.movement_type', SubscriptionMetricEvent::TYPE_CHURN)
            ->where('subscription_events.event_type', SubscriptionEvent::TYPE_CANCELLATION_SCHEDULED)
            ->count();
        if ($scheduledChurn > 0) {
            $failures[] = ['check' => 'scheduled_cancellation_not_churn', 'count' => $scheduledChurn];
        }

        $scheduledPlanMovements = SubscriptionMetricEvent::query()
            ->join('subscription_events', 'subscription_events.id', '=', 'subscription_metric_events.subscription_event_id')
            ->where('subscription_events.event_type', SubscriptionEvent::TYPE_PLAN_CHANGE_SCHEDULED)
            ->count();
        if ($scheduledPlanMovements > 0) {
            $failures[] = ['check' => 'scheduled_plan_change_not_movement', 'count' => $scheduledPlanMovements];
        }

        $cancelledActive = Subscription::where('billing_engine_version', 'v2')
            ->whereNotNull('ended_at')
            ->get()
            ->filter(fn (Subscription $subscription) => $this->events->activePeriodForSubscription($subscription, Carbon::parse($subscription->ended_at)->addDay()) !== null)
            ->count();
        if ($cancelledActive > 0) {
            $failures[] = ['check' => 'cancelled_subscription_zero_after_effective_end', 'count' => $cancelledActive];
        }

        return [
            'ok' => empty($failures),
            'failures' => $failures,
            'checks' => [
                'ending_arr_minor' => $dashboard['ending_arr_minor'],
                'ending_mrr_minor' => $dashboard['ending_mrr_minor'],
                'movement_equation_arr_minor' => $equationEnding,
                'duplicate_subscription_event_sources' => $duplicateEventSources,
                'scheduled_churn_events' => $scheduledChurn,
                'scheduled_plan_change_movements' => $scheduledPlanMovements,
                'cancelled_active_after_end' => $cancelledActive,
            ],
        ];
    }
}
