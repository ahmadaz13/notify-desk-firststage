<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\FinancialAccount;
use App\Models\PaymentReceiptConfirmation;
use App\Models\RecurringExpenseObligation;
use App\Support\ReportingPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Finance Overview decision dashboard (§12.1, D-16). Read-only projection: every number comes from
 * the authoritative engine (CashMovement-derived balances, ReceivableService, GL via
 * FinancialStatementService, SaaS metric events). Nothing here posts, allocates or recognizes.
 */
class FinanceOverviewService
{
    public const TREND_MONTHS = 6;

    public function __construct(
        private readonly FinancialAccountBalanceService $balances,
        private readonly ReceivableService $receivables,
        private readonly FinancialStatementService $statements,
        private readonly SaasMetricsService $saas,
        private readonly SaasMetricEventService $saasEvents,
        private readonly SubscriptionBillingService $billing
    ) {
    }

    public function build(?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $month = $this->monthPeriod($today);
        $previousMonth = $this->monthPeriod($today->copy()->subMonthNoOverflow());

        $outstanding = $this->receivables->outstandingInvoices([], $today);
        $overdue = $outstanding->filter(fn (array $item) => $item['projection']['is_overdue']);
        $pnl = $this->statements->profitAndLoss($month);

        return [
            'as_of' => $today,
            'month' => $month,
            'kpis' => [
                'available_cash' => $this->availableCash(),
                'receivables' => [
                    'total_minor' => (int) $outstanding->sum(fn (array $item) => $item['projection']['outstanding_minor']),
                    'overdue_minor' => (int) $overdue->sum(fn (array $item) => $item['projection']['outstanding_minor']),
                    'invoice_count' => $outstanding->count(),
                ],
                'collections' => $this->comparison(
                    $this->saas->cashCollectedMinor($month),
                    $this->saas->cashCollectedMinor($previousMonth)
                ),
                'expenses' => $this->comparison(
                    $this->companyExpenseCashOutMinor($month),
                    $this->companyExpenseCashOutMinor($previousMonth)
                ),
            ],
            'trend' => $this->trend($today),
            'attention' => [
                'pending_receipts' => $this->pendingReceipts(),
                'overdue' => $overdue
                    ->sortBy([
                        fn (array $a, array $b) => $b['projection']['outstanding_minor'] <=> $a['projection']['outstanding_minor'],
                        fn (array $a, array $b) => $a['invoice']->due_date <=> $b['invoice']->due_date,
                    ])
                    ->take(5)
                    ->map(fn (array $item) => [
                        'invoice' => $item['invoice'],
                        'client' => $item['invoice']->client,
                        'outstanding_minor' => (int) $item['projection']['outstanding_minor'],
                        'days_overdue' => $item['invoice']->due_date ? (int) $item['invoice']->due_date->diffInDays($today) : 0,
                    ])
                    ->values(),
                'overdue_count' => $overdue->count(),
                'renewals' => $this->renewals($today),
                'recurring_expenses' => $this->recurringExpensesDue($today),
            ],
            'result' => [
                'recognized_revenue_minor' => (int) $pnl['total_revenue_minor'],
                'expenses_minor' => (int) $pnl['total_expenses_minor'],
                'net_minor' => (int) $pnl['net_income_minor'],
            ],
            'saas' => $this->saasStrip($today),
        ];
    }

    /** CASH-BOX + CLIQ derived balances (§10.1); other historical accounts are not V1 available cash. */
    public function availableCash(): array
    {
        $accounts = FinancialAccount::query()
            ->whereIn('code', [CompanyAccountBootstrapService::CASH_BOX_CODE, CompanyAccountBootstrapService::CLIQ_CODE])
            ->get()
            ->keyBy('code');

        $cash = isset($accounts[CompanyAccountBootstrapService::CASH_BOX_CODE])
            ? $this->balances->currentBalanceMinor($accounts[CompanyAccountBootstrapService::CASH_BOX_CODE])
            : 0;
        $cliq = isset($accounts[CompanyAccountBootstrapService::CLIQ_CODE])
            ? $this->balances->currentBalanceMinor($accounts[CompanyAccountBootstrapService::CLIQ_CODE])
            : 0;

        return [
            'total_minor' => $cash + $cliq,
            'cash_box_minor' => $cash,
            'cliq_minor' => $cliq,
        ];
    }

    /** Company-paid expenses leaving Cash Box / CliQ in the period (expense payments net of reversals). */
    public function companyExpenseCashOutMinor(ReportingPeriod $period): int
    {
        $paid = (int) CashMovement::where('event_type', CashMovement::EVENT_EXPENSE_PAID)
            ->whereBetween('occurred_at', [$period->start, $period->end])
            ->sum('amount_minor');
        $reversed = (int) CashMovement::where('event_type', CashMovement::EVENT_EXPENSE_REVERSAL)
            ->whereBetween('occurred_at', [$period->start, $period->end])
            ->sum('amount_minor');

        return $paid - $reversed;
    }

    /**
     * @return Collection<int, array{month: string, start: Carbon, collections_minor: int, expenses_minor: int}>
     */
    public function trend(Carbon $today): Collection
    {
        return collect(range(self::TREND_MONTHS - 1, 0))->map(function (int $monthsAgo) use ($today) {
            $period = $this->monthPeriod($today->copy()->startOfMonth()->subMonthsNoOverflow($monthsAgo));

            return [
                'month' => $period->start->format('Y-m'),
                'start' => $period->start,
                'collections_minor' => $this->saas->cashCollectedMinor($period),
                'expenses_minor' => $this->companyExpenseCashOutMinor($period),
            ];
        })->values();
    }

    private function pendingReceipts(): array
    {
        $pending = PaymentReceiptConfirmation::query()->pending();

        return [
            'count' => (clone $pending)->count(),
            'total_minor' => (int) (clone $pending)->sum('amount_minor'),
            'oldest' => (clone $pending)->with(['client:id,business_name', 'submitter:id,name'])->orderBy('created_at')->orderBy('id')->first(),
        ];
    }

    private function renewals(Carbon $today): array
    {
        $due = $this->billing->renewalsDueBy($today->copy()->addDays(30));

        return [
            'count' => $due->count(),
            'items' => $due->take(5)->values(),
        ];
    }

    private function recurringExpensesDue(Carbon $today): array
    {
        $query = RecurringExpenseObligation::query()
            ->pending()
            ->whereDate('due_date', '<=', $today->copy()->addDays(7)->toDateString());

        return [
            'count' => (clone $query)->count(),
            'total_minor' => (int) (clone $query)->sum('expected_amount_minor'),
            'items' => (clone $query)->with('template')->orderBy('due_date')->limit(5)->get(),
        ];
    }

    /** Subscription metrics (not accounting revenue): only the three strip values are computed. */
    private function saasStrip(Carbon $today): array
    {
        $active = $this->saas->activePeriods($today);
        $arr = (int) $active->sum(fn ($period) => $this->saasEvents->normalizedArrMinorForPeriod($period));

        return [
            'arr_minor' => $arr,
            'mrr_minor' => $this->saasEvents->mrrMinorFromArr($arr),
            'active_subscriptions' => $active->pluck('subscription_id')->unique()->count(),
        ];
    }

    private function comparison(int $current, int $previous): array
    {
        return [
            'current_minor' => $current,
            'previous_minor' => $previous,
            'delta_minor' => $current - $previous,
            // Percentage only when the previous month is a meaningful base.
            'delta_percent' => $previous > 0 ? (int) round((($current - $previous) / $previous) * 100) : null,
        ];
    }

    private function monthPeriod(Carbon $day): ReportingPeriod
    {
        return new ReportingPeriod($day->copy()->startOfMonth(), $day->copy()->endOfMonth()->endOfDay(), 'this_month');
    }
}
