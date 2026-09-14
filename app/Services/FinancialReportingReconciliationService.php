<?php

namespace App\Services;

use App\Support\ReportingPeriod;
use Carbon\Carbon;

class FinancialReportingReconciliationService
{
    public function __construct(
        private readonly AccountingReconciliationService $accounting,
        private readonly FinancialStatementService $statements
    ) {
    }

    public function run(?ReportingPeriod $period = null): array
    {
        $period ??= new ReportingPeriod(Carbon::today()->startOfMonth(), Carbon::today()->endOfMonth()->endOfDay(), 'this_month');
        $accounting = $this->accounting->run();
        $balance = $this->statements->balanceSheet($period->end);
        $cash = $this->statements->cashFlow($period);
        $aging = $this->statements->arAging($period->end);
        $deferred = $this->statements->deferredRevenueReport($period);
        $credits = $this->statements->customerCredits();
        $tax = $this->statements->salesTaxReport();
        $revenue = $this->statements->recognizedRevenueReport($period);

        $checks = [
            'trial_balance_difference_minor' => $accounting['trial_balance']['difference_minor'],
            'balance_sheet_equation_difference_minor' => $balance['equation_difference_minor'],
            'closing_cash_statement_difference_minor' => $cash['closing_difference_minor'],
            'gl_cash_to_d1_cash_difference_minor' => $cash['gl_cash_difference_minor'],
            'ar_aging_to_gl_difference_minor' => $aging['summary']['difference_minor'],
            'deferred_revenue_to_gl_difference_minor' => $deferred['summary']['difference_minor'],
            'billing_clearing_difference_minor' => $credits['billing_clearing_gl_minor'] - $credits['unallocated_payment_credit_minor'],
            'customer_credits_difference_minor' => $credits['customer_credits_gl_minor'] - $credits['credit_note_credit_minor'],
            'sales_tax_difference_minor' => $tax['difference_minor'],
            'saas_revenue_report_minor' => $revenue['saas_revenue_minor'] - ($accounting['recognized_revenue']['saas_subscription_revenue']['general_ledger_minor'] ?? 0),
            'one_time_revenue_report_minor' => $revenue['one_time_revenue_minor'] - ($accounting['recognized_revenue']['one_time_service_revenue']['general_ledger_minor'] ?? 0),
        ];

        $failures = collect($checks)
            ->filter(fn (int $difference) => $difference !== 0)
            ->keys()
            ->values()
            ->all();

        return [
            'ok' => $failures === [] && $accounting['ok'],
            'checks' => $checks,
            'failures' => array_merge($accounting['ok'] ? [] : $accounting['failures'], $failures),
        ];
    }
}
