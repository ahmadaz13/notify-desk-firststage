<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\ChartAccount;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\FixedAsset;
use App\Models\FundingSource;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\RecurringExpenseObligation;
use App\Models\RevenueRecognitionAdjustment;
use App\Models\RevenueRecognitionPeriod;
use App\Models\RevenueRecognitionSchedule;
use App\Support\ReportingPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialStatementService
{
    public function __construct(
        private readonly AccountingSetupService $setup,
        private readonly ReceivableService $receivables,
        private readonly FinancialAccountBalanceService $cashBalances,
        private readonly RevenueRecognitionService $revenueRecognition
    ) {
    }

    public function dashboard(ReportingPeriod $period): array
    {
        $pl = $this->profitAndLoss($period);
        $bs = $this->balanceSheet($period->end);
        $aging = $this->arAging($period->end);
        $credits = $this->customerCredits();
        $tax = $this->salesTaxReport();

        return [
            'cash_available_minor' => $bs['sections']['assets']['cash_and_cash_equivalents']['total_minor'],
            'accounts_receivable_minor' => $bs['sections']['assets']['accounts_receivable']['total_minor'],
            'overdue_receivables_minor' => $aging['summary']['total_overdue_minor'],
            'deferred_revenue_minor' => $bs['sections']['liabilities']['deferred_revenue']['total_minor'],
            'recognized_revenue_this_period_minor' => $pl['total_revenue_minor'],
            'operating_expenses_this_period_minor' => $pl['total_expenses_minor'],
            'management_net_income_this_period_minor' => $pl['net_income_minor'],
            'customer_credits_minor' => $credits['credit_note_credit_minor'],
            'sales_tax_payable_minor' => $tax['net_sales_tax_payable_minor'],
            'upcoming_recurring_expenses_minor' => (int) RecurringExpenseObligation::pending()
                ->whereDate('due_date', '<=', $period->end->copy()->addDays(30)->toDateString())
                ->sum('expected_amount_minor'),
            'revenue_by_month' => $this->recognizedRevenueByMonth($period),
            'expenses_by_month' => $this->expenseByMonth($period),
            'net_income_by_month' => $this->netIncomeByMonth($period),
            'cash_balance_trend' => $this->cashBalanceTrend($period),
            'ar_aging' => $aging['summary']['buckets'],
        ];
    }

    public function profitAndLoss(ReportingPeriod $period): array
    {
        $revenueAccounts = ChartAccount::query()
            ->where('account_type', ChartAccount::TYPE_REVENUE)
            ->where('allow_direct_posting', true)
            ->orderBy('code')
            ->get();
        $expenseAccounts = ChartAccount::query()
            ->where('account_type', ChartAccount::TYPE_EXPENSE)
            ->where('allow_direct_posting', true)
            ->orderBy('code')
            ->get();

        $revenues = $revenueAccounts->map(fn (ChartAccount $account) => $this->statementRow($account, $period))->values();
        $expenses = $expenseAccounts->map(fn (ChartAccount $account) => $this->statementRow($account, $period))->values();
        $totalRevenue = (int) $revenues->sum('amount_minor');
        $totalExpenses = (int) $expenses->sum('amount_minor');

        return [
            'period' => $period,
            'revenue_rows' => $revenues,
            'expense_rows' => $expenses,
            'saas_revenue_minor' => (int) ($revenues->firstWhere('mapping_key', 'saas_subscription_revenue')['amount_minor'] ?? 0),
            'one_time_revenue_minor' => (int) ($revenues->firstWhere('mapping_key', 'one_time_service_revenue')['amount_minor'] ?? 0),
            'total_revenue_minor' => $totalRevenue,
            'total_expenses_minor' => $totalExpenses,
            'net_income_minor' => $totalRevenue - $totalExpenses,
            'comparison' => $period->previousComparison() ? $this->profitAndLoss($period->previousComparison()) : null,
            'warnings' => [
                'Revenue is recognized revenue from GL revenue accounts, not cash receipts.',
                'Depreciation is excluded until implemented.',
            ],
        ];
    }

    public function balanceSheet(Carbon $asOf): array
    {
        $assets = $this->balanceRows(ChartAccount::TYPE_ASSET, $asOf);
        $liabilities = $this->balanceRows(ChartAccount::TYPE_LIABILITY, $asOf);
        $equityRows = $this->balanceRows(ChartAccount::TYPE_EQUITY, $asOf);
        $earnings = $this->cumulativeManagementEarnings($asOf);
        $equityRows->push([
            'account' => null,
            'label' => 'Current Accumulated Management Earnings',
            'amount_minor' => $earnings,
            'drilldown' => 'profit_and_loss_cumulative',
        ]);

        $assetSections = [
            'cash_and_cash_equivalents' => $this->sectionFromMappings($assets, ['cash_parent' => '1100', 'cash_ledgers' => '111']),
            'accounts_receivable' => $this->sectionFromMappings($assets, ['accounts_receivable' => '1200']),
            'fixed_assets_at_recorded_cost' => $this->sectionFromMappings($assets, ['fixed_assets_parent' => '1500', 'fixed_asset_ledgers' => '15']),
            'other_assets' => $this->otherSection($assets, ['1100', '111', '1200', '1500', '15']),
        ];
        $liabilitySections = [
            'billing_clearing' => $this->sectionFromMappings($liabilities, ['billing_clearing' => '2100']),
            'customer_credits' => $this->sectionFromMappings($liabilities, ['customer_credits' => '2500']),
            'sales_tax_payable' => $this->sectionFromMappings($liabilities, ['sales_tax_payable' => '2600']),
            'deferred_revenue' => $this->sectionFromMappings($liabilities, ['deferred_revenue' => '2400']),
            'due_to_related_parties' => $this->sectionFromMappings($liabilities, ['related_party_payable' => '2200']),
            'loans_payable' => $this->sectionFromMappings($liabilities, ['loan_payable' => '2300']),
            'unclassified_funding' => $this->sectionFromMappings($liabilities, ['unclassified_funding' => '2900']),
            'other_liabilities' => $this->otherSection($liabilities, ['2100', '2200', '2300', '2400', '2500', '2600', '2900']),
        ];
        $equitySections = [
            'contributed_capital' => $this->sectionFromMappings($equityRows, ['contributed_capital' => '3100']),
            'opening_balance_equity' => $this->sectionFromMappings($equityRows, ['opening_balance_equity' => '3200']),
            'current_accumulated_management_earnings' => [
                'rows' => $equityRows->where('drilldown', 'profit_and_loss_cumulative')->values(),
                'total_minor' => $earnings,
            ],
        ];

        $assetTotal = $this->sectionTotal($assetSections);
        $liabilityTotal = $this->sectionTotal($liabilitySections);
        $equityTotal = $this->sectionTotal($equitySections);

        return [
            'as_of' => $asOf,
            'sections' => [
                'assets' => $assetSections,
                'liabilities' => $liabilitySections,
                'equity' => $equitySections,
            ],
            'total_assets_minor' => $assetTotal,
            'total_liabilities_minor' => $liabilityTotal,
            'total_equity_minor' => $equityTotal,
            'equation_difference_minor' => $assetTotal - ($liabilityTotal + $equityTotal),
            'warnings' => [
                'Internal management balance sheet, not statutory audited financial statements.',
                'Fixed assets are shown at recorded acquisition cost; depreciation is not yet implemented.',
            ],
        ];
    }

    public function cashFlow(ReportingPeriod $period): array
    {
        $openingCash = $this->companyCashAsOf($period->start->copy()->subSecond());
        $closingCash = $this->companyCashAsOf($period->end);
        $rows = CashMovement::with('financialAccount')
            ->whereBetween('occurred_at', [$period->start, $period->end])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(fn (CashMovement $movement) => $this->cashFlowRow($movement));

        $operating = (int) $rows->where('section', 'operating')->sum('signed_minor');
        $investing = (int) $rows->where('section', 'investing')->sum('signed_minor');
        $financing = (int) $rows->where('section', 'financing')->sum('signed_minor');
        $internal = (int) $rows->where('section', 'internal_transfer')->sum('signed_minor');
        $openingEvents = (int) $rows->where('section', 'opening')->sum('signed_minor');
        $netMovement = $operating + $investing + $financing + $openingEvents;

        return [
            'period' => $period,
            'opening_cash_minor' => $openingCash,
            'operating_cash_flow_minor' => $operating,
            'investing_cash_flow_minor' => $investing,
            'financing_cash_flow_minor' => $financing,
            'opening_balance_events_minor' => $openingEvents,
            'internal_transfer_minor' => $internal,
            'net_company_cash_movement_minor' => $netMovement,
            'closing_cash_minor' => $closingCash,
            'calculated_closing_cash_minor' => $openingCash + $netMovement,
            'closing_difference_minor' => $closingCash - ($openingCash + $netMovement),
            'gl_cash_closing_minor' => $this->glCashAsOf($period->end),
            'gl_cash_difference_minor' => $this->glCashAsOf($period->end) - $closingCash,
            'rows' => $rows,
        ];
    }

    public function arAging(?Carbon $asOf = null): array
    {
        $asOf ??= Carbon::today();
        $items = $this->receivables->outstandingInvoices([], $asOf)->map(function (array $item) use ($asOf) {
            $invoice = $item['invoice'];
            $projection = $item['projection'];
            $days = $invoice->due_date && $invoice->due_date->lt($asOf)
                ? $invoice->due_date->diffInDays($asOf)
                : 0;

            return [
                'client' => $invoice->client,
                'invoice' => $invoice,
                'due_date' => $invoice->due_date,
                'original_total_minor' => (int) $invoice->total_minor,
                'payment_allocated_minor' => (int) $projection['allocated_minor'],
                'credit_applied_minor' => (int) $projection['credit_applied_minor'],
                'outstanding_minor' => (int) $projection['outstanding_minor'],
                'days_overdue' => $days,
                'bucket' => $projection['age_bucket'],
            ];
        });
        $buckets = $this->receivables->agingBuckets(null, $asOf);
        $total = (int) array_sum($buckets);
        $overdue = $total - (int) ($buckets['not_due'] ?? 0);
        $gl = $this->accountBalanceAsOf($this->setup->systemAccount('accounts_receivable'), $asOf);

        return [
            'as_of' => $asOf,
            'items' => $items,
            'summary' => [
                'total_ar_minor' => $total,
                'not_due_minor' => (int) ($buckets['not_due'] ?? 0),
                'total_overdue_minor' => $overdue,
                'buckets' => $buckets,
                'gl_accounts_receivable_minor' => $gl,
                'difference_minor' => $gl - $total,
            ],
        ];
    }

    public function deferredRevenueReport(ReportingPeriod $period): array
    {
        $account = $this->setup->systemAccount('deferred_revenue');
        $opening = $this->accountBalanceAsOf($account, $period->start->copy()->subSecond());
        $closing = $this->accountBalanceAsOf($account, $period->end);
        $credits = $this->accountCredits($account, $period);
        $recognition = $this->accountDebits($account, $period, ['revenue_recognized']);
        $creditReductions = $this->accountDebits($account, $period, ['credit_note_issued_accounting', 'credit_note_void_accounting']);
        $schedules = RevenueRecognitionSchedule::with(['invoice.client', 'invoiceLine', 'periods', 'adjustments'])
            ->orderByDesc('id')
            ->get()
            ->map(function (RevenueRecognitionSchedule $schedule) {
                $recognized = (int) $schedule->periods->sum('recognized_minor');
                $adjustments = (int) $schedule->adjustments
                    ->where('adjustment_type', RevenueRecognitionAdjustment::TYPE_FUTURE_DEFERRED_REDUCTION)
                    ->sum('amount_minor');
                $next = $schedule->periods
                    ->where('status', RevenueRecognitionPeriod::STATUS_PENDING)
                    ->sortBy('period_end')
                    ->first();

                return [
                    'schedule' => $schedule,
                    'client' => $schedule->invoice?->client,
                    'invoice' => $schedule->invoice,
                    'invoice_line' => $schedule->invoiceLine,
                    'original_recognizable_minor' => (int) $schedule->original_recognizable_minor,
                    'recognized_minor' => $recognized,
                    'credit_adjustments_minor' => $adjustments,
                    'remaining_deferred_minor' => max(0, (int) $schedule->original_recognizable_minor - $recognized - $adjustments),
                    'next_recognition_date' => $next?->period_end,
                ];
            });

        return [
            'period' => $period,
            'items' => $schedules,
            'summary' => [
                'opening_deferred_revenue_minor' => $opening,
                'new_deferred_billings_minor' => $credits,
                'recognition_during_period_minor' => $recognition,
                'credit_reductions_minor' => $creditReductions,
                'closing_deferred_revenue_minor' => $closing,
                'operational_closing_deferred_minor' => $this->revenueRecognition->operationalDeferredRevenueMinor(),
                'difference_minor' => $closing - $this->revenueRecognition->operationalDeferredRevenueMinor(),
            ],
        ];
    }

    public function recognizedRevenueReport(ReportingPeriod $period): array
    {
        return [
            'period' => $period,
            'by_month' => $this->recognizedRevenueByMonth($period),
            'by_client' => $this->recognizedRevenueByClient($period),
            'by_plan' => $this->recognizedRevenueByPlan($period),
            'adjustments' => RevenueRecognitionAdjustment::with(['schedule.invoice', 'creditNoteLine.creditNote'])
                ->whereBetween('effective_date', [$period->start->toDateString(), $period->end->toDateString()])
                ->orderBy('effective_date')
                ->get(),
            'saas_revenue_minor' => $this->accountBalanceForPeriod($this->setup->systemAccount('saas_subscription_revenue'), $period),
            'one_time_revenue_minor' => $this->accountBalanceForPeriod($this->setup->systemAccount('one_time_service_revenue'), $period),
        ];
    }

    public function customerCredits(): array
    {
        $credits = $this->receivables->availableCustomerCredits();
        $payment = (int) $credits->where('source_type', 'payment')->sum('available_minor');
        $creditNote = (int) $credits->where('source_type', 'credit_note')->sum('available_minor');

        return [
            'items' => $credits,
            'unallocated_payment_credit_minor' => $payment,
            'credit_note_credit_minor' => $creditNote,
            'billing_clearing_gl_minor' => $this->accountBalanceAsOf($this->setup->systemAccount('billing_clearing'), null),
            'customer_credits_gl_minor' => $this->accountBalanceAsOf($this->setup->systemAccount('customer_credits'), null),
        ];
    }

    public function salesTaxReport(): array
    {
        $invoiceTax = (int) Invoice::where('status', Invoice::STATUS_ISSUED)->sum('tax_minor');
        $creditTax = (int) CreditNote::where('status', CreditNote::STATUS_ISSUED)->sum('tax_minor');
        $net = $invoiceTax - $creditTax;

        return [
            'invoice_tax_minor' => $invoiceTax,
            'credit_note_tax_reductions_minor' => $creditTax,
            'net_sales_tax_payable_minor' => $net,
            'gl_sales_tax_payable_minor' => $this->accountBalanceAsOf($this->setup->systemAccount('sales_tax_payable'), null),
            'difference_minor' => $this->accountBalanceAsOf($this->setup->systemAccount('sales_tax_payable'), null) - $net,
            'warning' => 'Internal ledger report only; not a statutory Jordan tax filing report.',
        ];
    }

    public function expenseReport(ReportingPeriod $period): array
    {
        return [
            'period' => $period,
            'by_account' => $this->profitAndLoss($period)['expense_rows'],
            'by_month' => $this->expenseByMonth($period),
            'by_category' => Expense::activeV2()
                ->whereBetween('paid_at', [$period->start, $period->end])
                ->select('category_name_snapshot', DB::raw('SUM(amount_minor) as amount_minor'))
                ->groupBy('category_name_snapshot')
                ->orderByDesc('amount_minor')
                ->get(),
            'company_funded_minor' => (int) Expense::activeV2()->where('funding_source', Expense::FUNDING_COMPANY_ACCOUNT)->whereBetween('paid_at', [$period->start, $period->end])->sum('amount_minor'),
            'personally_funded_minor' => (int) Expense::activeV2()->where('funding_source', Expense::FUNDING_PERSONAL)->whereBetween('paid_at', [$period->start, $period->end])->sum('amount_minor'),
            'upcoming_recurring_obligations' => RecurringExpenseObligation::pending()->whereDate('due_date', '<=', $period->end->copy()->addDays(30)->toDateString())->orderBy('due_date')->get(),
        ];
    }

    public function capitalAssetReport(ReportingPeriod $period): array
    {
        return [
            'period' => $period,
            'funding_by_source' => FundingSource::with('transactions')->get()->map(fn (FundingSource $source) => [
                'source' => $source,
                'amount_minor' => (int) $source->transactions()->active()->whereBetween('received_at', [$period->start, $period->end])->sum('amount_minor'),
            ]),
            'funding_by_month' => $this->cashFlowMonthly($period, [CashMovement::EVENT_CAPITAL_FUNDING_RECEIVED, CashMovement::EVENT_CAPITAL_FUNDING_REVERSAL]),
            'assets_by_category' => FixedAsset::activeAcquisitions()
                ->select('category_name_snapshot', DB::raw('SUM(acquisition_cost_minor) as acquisition_cost_minor'), DB::raw('COUNT(*) as aggregate'))
                ->groupBy('category_name_snapshot')
                ->get(),
            'company_funded_assets_minor' => (int) FixedAsset::activeAcquisitions()->where('funding_source', FixedAsset::FUNDING_COMPANY_ACCOUNT)->sum('acquisition_cost_minor'),
            'personally_funded_assets_minor' => (int) FixedAsset::activeAcquisitions()->where('funding_source', FixedAsset::FUNDING_PERSONAL)->sum('acquisition_cost_minor'),
            'active_asset_count' => FixedAsset::activeAcquisitions()->where('status', FixedAsset::STATUS_ACTIVE)->count(),
            'warnings' => [
                'Capital funding is not revenue.',
                'Asset acquisition cost is not operating expense.',
                'Asset amounts are acquisition cost, not depreciated book value.',
            ],
        ];
    }

    public function ledgerDrilldown(ChartAccount $account, ReportingPeriod $period): Collection
    {
        return JournalLine::with(['entry', 'client', 'financialAccount'])
            ->where('chart_account_id', $account->id)
            ->whereHas('entry', fn ($query) => $query->whereBetween('entry_date', [$period->start->toDateString(), $period->end->toDateString()]))
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_lines.id')
            ->select('journal_lines.*')
            ->get();
    }

    private function statementRow(ChartAccount $account, ReportingPeriod $period): array
    {
        $amount = $this->accountBalanceForPeriod($account, $period);

        return [
            'account' => $account,
            'mapping_key' => $this->mappingKeyForAccount($account),
            'amount_minor' => $amount,
            'journal_count' => $this->journalLineQuery($account, $period)->distinct('journal_entries.id')->count('journal_entries.id'),
        ];
    }

    private function accountBalanceForPeriod(ChartAccount $account, ReportingPeriod $period): int
    {
        $query = $this->journalLineQuery($account, $period);
        $debit = (int) (clone $query)->sum('journal_lines.debit_minor');
        $credit = (int) (clone $query)->sum('journal_lines.credit_minor');

        return $account->normal_balance === ChartAccount::NORMAL_DEBIT ? $debit - $credit : $credit - $debit;
    }

    private function accountBalanceAsOf(ChartAccount $account, ?Carbon $asOf): int
    {
        $query = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.chart_account_id', $account->id);
        if ($asOf !== null) {
            $query->whereDate('journal_entries.entry_date', '<=', $asOf->toDateString());
        }
        $debit = (int) (clone $query)->sum('journal_lines.debit_minor');
        $credit = (int) (clone $query)->sum('journal_lines.credit_minor');

        return $account->normal_balance === ChartAccount::NORMAL_DEBIT ? $debit - $credit : $credit - $debit;
    }

    private function balanceRows(string $type, Carbon $asOf): Collection
    {
        return ChartAccount::query()
            ->where('account_type', $type)
            ->where('allow_direct_posting', true)
            ->orderBy('code')
            ->get()
            ->map(fn (ChartAccount $account) => [
                'account' => $account,
                'label' => $account->name_ar,
                'amount_minor' => $this->accountBalanceAsOf($account, $asOf),
                'drilldown' => 'account_ledger',
            ]);
    }

    private function sectionFromMappings(Collection $rows, array $codesByKey): array
    {
        $codes = array_values($codesByKey);
        $sectionRows = $rows
            ->filter(fn (array $row) => $row['account'] && $this->codeMatchesAnyPrefix($row['account']->code, $codes))
            ->values();

        return ['rows' => $sectionRows, 'total_minor' => (int) $sectionRows->sum('amount_minor')];
    }

    private function otherSection(Collection $rows, array $excludedCodes): array
    {
        $sectionRows = $rows
            ->filter(fn (array $row) => $row['account'] && ! $this->codeMatchesAnyPrefix($row['account']->code, $excludedCodes) && $row['amount_minor'] !== 0)
            ->values();

        return ['rows' => $sectionRows, 'total_minor' => (int) $sectionRows->sum('amount_minor')];
    }

    private function sectionTotal(array $sections): int
    {
        return (int) collect($sections)->sum('total_minor');
    }

    private function cumulativeManagementEarnings(Carbon $asOf): int
    {
        $revenue = ChartAccount::where('account_type', ChartAccount::TYPE_REVENUE)->get()
            ->sum(fn (ChartAccount $account) => $this->accountBalanceAsOf($account, $asOf));
        $expenses = ChartAccount::where('account_type', ChartAccount::TYPE_EXPENSE)->get()
            ->sum(fn (ChartAccount $account) => $this->accountBalanceAsOf($account, $asOf));

        return (int) $revenue - (int) $expenses;
    }

    private function cashFlowRow(CashMovement $movement): array
    {
        $sign = $movement->direction === CashMovement::DIRECTION_INFLOW ? 1 : -1;
        $section = match ($movement->event_type) {
            CashMovement::EVENT_PAYMENT_RECEIVED,
            CashMovement::EVENT_PAYMENT_REVERSAL,
            CashMovement::EVENT_REFUND_ISSUED,
            CashMovement::EVENT_EXPENSE_PAID,
            CashMovement::EVENT_EXPENSE_REVERSAL => 'operating',
            CashMovement::EVENT_ASSET_ACQUISITION,
            CashMovement::EVENT_ASSET_ACQUISITION_REVERSAL => 'investing',
            CashMovement::EVENT_CAPITAL_FUNDING_RECEIVED,
            CashMovement::EVENT_CAPITAL_FUNDING_REVERSAL => 'financing',
            CashMovement::EVENT_TRANSFER_IN,
            CashMovement::EVENT_TRANSFER_OUT => 'internal_transfer',
            CashMovement::EVENT_OPENING_BALANCE => 'opening',
            default => 'operating',
        };

        return [
            'movement' => $movement,
            'section' => $section,
            'signed_minor' => $section === 'internal_transfer' ? 0 : $sign * (int) $movement->amount_minor,
            'raw_signed_minor' => $sign * (int) $movement->amount_minor,
        ];
    }

    private function companyCashAsOf(Carbon $asOf): int
    {
        return (int) FinancialAccount::query()
            ->where('type', '!=', FinancialAccount::TYPE_CLEARING)
            ->get()
            ->sum(fn (FinancialAccount $account) => $this->cashBalances->balanceAsOfMinor($account, $asOf));
    }

    private function glCashAsOf(Carbon $asOf): int
    {
        return (int) FinancialAccount::query()
            ->where('type', '!=', FinancialAccount::TYPE_CLEARING)
            ->get()
            ->sum(function (FinancialAccount $account) use ($asOf) {
                $chart = $this->setup->ensureFinancialAccountMapping($account);

                return $this->accountBalanceAsOf($chart, $asOf);
            });
    }

    private function accountCredits(ChartAccount $account, ReportingPeriod $period): int
    {
        return (int) $this->journalLineQuery($account, $period)->sum('journal_lines.credit_minor');
    }

    private function accountDebits(ChartAccount $account, ReportingPeriod $period, array $eventTypes): int
    {
        return (int) $this->journalLineQuery($account, $period)
            ->whereIn('journal_entries.event_type', $eventTypes)
            ->sum('journal_lines.debit_minor');
    }

    private function journalLineQuery(ChartAccount $account, ReportingPeriod $period)
    {
        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.chart_account_id', $account->id)
            ->whereDate('journal_entries.entry_date', '>=', $period->start->toDateString())
            ->whereDate('journal_entries.entry_date', '<=', $period->end->toDateString());
    }

    private function recognizedRevenueByMonth(ReportingPeriod $period): Collection
    {
        return $this->monthlyRevenueExpense($period, ChartAccount::TYPE_REVENUE);
    }

    private function expenseByMonth(ReportingPeriod $period): Collection
    {
        return $this->monthlyRevenueExpense($period, ChartAccount::TYPE_EXPENSE);
    }

    private function netIncomeByMonth(ReportingPeriod $period): Collection
    {
        $revenue = $this->recognizedRevenueByMonth($period)->keyBy('month');
        $expense = $this->expenseByMonth($period)->keyBy('month');

        return $revenue->keys()->merge($expense->keys())->unique()->sort()->values()->map(fn (string $month) => [
            'month' => $month,
            'amount_minor' => (int) ($revenue[$month]['amount_minor'] ?? 0) - (int) ($expense[$month]['amount_minor'] ?? 0),
        ]);
    }

    private function monthlyRevenueExpense(ReportingPeriod $period, string $accountType): Collection
    {
        $normalCredit = $accountType === ChartAccount::TYPE_REVENUE;

        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('chart_accounts', 'chart_accounts.id', '=', 'journal_lines.chart_account_id')
            ->where('chart_accounts.account_type', $accountType)
            ->whereDate('journal_entries.entry_date', '>=', $period->start->toDateString())
            ->whereDate('journal_entries.entry_date', '<=', $period->end->toDateString())
            ->selectRaw("strftime('%Y-%m', journal_entries.entry_date) as month")
            ->selectRaw('SUM(journal_lines.debit_minor) as debit_minor')
            ->selectRaw('SUM(journal_lines.credit_minor) as credit_minor')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month,
                'amount_minor' => $normalCredit
                    ? (int) $row->credit_minor - (int) $row->debit_minor
                    : (int) $row->debit_minor - (int) $row->credit_minor,
            ]);
    }

    private function recognizedRevenueByClient(ReportingPeriod $period): Collection
    {
        return JournalLine::query()
            ->with('client')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('chart_accounts', 'chart_accounts.id', '=', 'journal_lines.chart_account_id')
            ->where('chart_accounts.account_type', ChartAccount::TYPE_REVENUE)
            ->whereDate('journal_entries.entry_date', '>=', $period->start->toDateString())
            ->whereDate('journal_entries.entry_date', '<=', $period->end->toDateString())
            ->select('journal_lines.client_id')
            ->selectRaw('SUM(journal_lines.credit_minor - journal_lines.debit_minor) as amount_minor')
            ->groupBy('journal_lines.client_id')
            ->orderByDesc('amount_minor')
            ->get();
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

    private function cashBalanceTrend(ReportingPeriod $period): Collection
    {
        $cursor = $period->start->copy()->startOfMonth();
        $rows = collect();
        while ($cursor->lte($period->end)) {
            $asOf = $cursor->copy()->endOfMonth();
            if ($asOf->gt($period->end)) {
                $asOf = $period->end->copy();
            }

            $rows->push([
                'month' => $cursor->format('Y-m'),
                'amount_minor' => $this->companyCashAsOf($asOf),
            ]);
            $cursor->addMonthNoOverflow();
        }

        return $rows;
    }

    private function cashFlowMonthly(ReportingPeriod $period, array $eventTypes): Collection
    {
        return CashMovement::query()
            ->whereIn('event_type', $eventTypes)
            ->whereBetween('occurred_at', [$period->start, $period->end])
            ->selectRaw("strftime('%Y-%m', occurred_at) as month")
            ->selectRaw("SUM(CASE WHEN direction = 'inflow' THEN amount_minor ELSE -amount_minor END) as amount_minor")
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }

    private function mappingKeyForAccount(ChartAccount $account): ?string
    {
        return DB::table('accounting_system_mappings')
            ->where('chart_account_id', $account->id)
            ->value('mapping_key');
    }

    private function codeMatchesAnyPrefix(string $code, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($code === $prefix || str_starts_with($code, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
