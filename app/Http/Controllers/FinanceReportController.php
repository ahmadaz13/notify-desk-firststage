<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\FinancialStatementService;
use App\Services\SaasMetricsService;
use App\Support\Money;
use App\Support\Permissions;
use App\Support\ReportingPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Financial Reports (§14, D-19): one page, one selected report computed per request, CSV (UTF-8 BOM)
 * export only from here.
 */
class FinanceReportController extends Controller
{
    public const REPORTS = [
        'profit-and-loss',
        'financial-position',
        'cash-flow',
        'revenue',
        'expenses',
        'receivables',
        'subscription-metrics',
    ];

    public const SAAS_DATASETS = ['summary', 'monthly-movement', 'active-subscriptions', 'churned-subscriptions', 'mrr-by-plan'];

    /** Pre-P6 `/finance/export/{report}` keys → canonical report (+ dataset). */
    public const LEGACY_EXPORTS = [
        'profit-and-loss' => ['profit-and-loss', null],
        'balance-sheet' => ['financial-position', null],
        'cash-flow' => ['cash-flow', null],
        'ar-aging' => ['receivables', null],
        'deferred-revenue' => ['revenue', 'deferred'],
        'recognized-revenue' => ['revenue', null],
        'expense-breakdown' => ['expenses', null],
    ];

    public function show(Request $request, FinancialStatementService $statements, SaasMetricsService $saas, ?string $report = null): View
    {
        Gate::authorize(Permissions::VIEW_FINANCIAL_STATEMENTS);

        $report ??= 'profit-and-loss';
        abort_unless(in_array($report, self::REPORTS, true), 404);
        if ($report === 'subscription-metrics') {
            Gate::authorize(Permissions::VIEW_SAAS_METRICS);
        }

        $period = ReportingPeriod::fromRequest($request);
        $revenueView = $request->query('view') === 'deferred' ? 'deferred' : 'recognized';

        // Only the selected report is computed.
        $data = match ($report) {
            'profit-and-loss' => $statements->profitAndLoss($period),
            'financial-position' => [
                'current' => $statements->balanceSheet($period->end),
                'comparison' => $period->previousComparison() ? $statements->balanceSheet($period->previousComparison()->end) : null,
            ],
            'cash-flow' => $statements->cashFlow($period),
            'revenue' => $revenueView === 'deferred'
                ? $statements->deferredRevenueReport($period)
                : $statements->recognizedRevenueReport($period),
            'expenses' => $statements->expenseReport($period),
            'receivables' => [
                'aging' => $statements->arAging($period->end),
                'collected_minor' => $saas->cashCollectedMinor($period),
                'payments' => $this->paymentsReceived($period)->limit(50)->get(),
            ],
            'subscription-metrics' => $saas->dashboard($period),
        };

        return view('finance.reports', [
            'report' => $report,
            'reports' => $this->availableReports($request),
            'period' => $period,
            'data' => $data,
            'revenueView' => $revenueView,
            'canExport' => Gate::allows(Permissions::EXPORT_FINANCIAL_REPORTS)
                && ($report !== 'subscription-metrics' || Gate::allows(Permissions::EXPORT_SAAS_METRICS)),
        ]);
    }

    public function export(Request $request, string $report, FinancialStatementService $statements, SaasMetricsService $saas): StreamedResponse
    {
        Gate::authorize(Permissions::EXPORT_FINANCIAL_REPORTS);
        abort_unless(in_array($report, self::REPORTS, true), 404);
        if ($report === 'subscription-metrics') {
            Gate::authorize(Permissions::EXPORT_SAAS_METRICS);
        }

        $period = ReportingPeriod::fromRequest($request);
        $dataset = (string) $request->query('dataset', '');
        [$suffix, $rows] = $this->exportRows($report, $dataset, $period, $statements, $saas);

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens Arabic text correctly (D-19).
            fwrite($handle, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'notify-'.$report.$suffix.'-'.$period->start->toDateString().'_'.$period->end->toDateString().'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Legacy `/finance/export/{report}` → canonical report export (same permission). */
    public function legacyExport(Request $request, string $report): RedirectResponse
    {
        Gate::authorize(Permissions::EXPORT_FINANCIAL_REPORTS);
        [$target, $dataset] = self::LEGACY_EXPORTS[$report] ?? abort(404);

        return redirect()->route('finance.reports.export', ['report' => $target] + array_filter(['dataset' => $dataset]) + $request->query(), 301);
    }

    private function availableReports(Request $request): array
    {
        return collect(self::REPORTS)
            ->reject(fn (string $key) => $key === 'subscription-metrics' && ! Gate::allows(Permissions::VIEW_SAAS_METRICS))
            ->values()
            ->all();
    }

    private function paymentsReceived(ReportingPeriod $period)
    {
        return Payment::with('client:id,business_name')
            ->where('payment_engine_version', Payment::ENGINE_V2)
            ->whereBetween('received_at', [$period->start, $period->end])
            ->orderByDesc('received_at')
            ->orderByDesc('id');
    }

    /**
     * @return array{0: string, 1: array<int, array<int, mixed>>}
     */
    private function exportRows(string $report, string $dataset, ReportingPeriod $period, FinancialStatementService $statements, SaasMetricsService $saas): array
    {
        return match ($report) {
            'profit-and-loss' => ['', $this->profitAndLossRows($statements->profitAndLoss($period))],
            'financial-position' => ['', $this->balanceSheetRows($statements->balanceSheet($period->end))],
            'cash-flow' => ['', $this->cashFlowRows($statements->cashFlow($period))],
            'revenue' => $dataset === 'deferred'
                ? ['-deferred', $this->deferredRows($statements->deferredRevenueReport($period))]
                : ['', $this->recognizedRows($statements->recognizedRevenueReport($period))],
            'expenses' => ['', $this->expenseRows($statements->expenseReport($period))],
            'receivables' => ['', $this->receivablesRows($statements->arAging($period->end), $this->paymentsReceived($period)->get())],
            'subscription-metrics' => (function () use ($dataset, $period, $saas) {
                $dataset = in_array($dataset, self::SAAS_DATASETS, true) ? $dataset : 'summary';

                return ['-'.$dataset, array_merge(
                    [['Subscription metrics - not accounting revenue']],
                    $saas->exportRows($dataset, $period)
                )];
            })(),
        };
    }

    private function profitAndLossRows(array $report): array
    {
        $rows = [['Section', 'Account Code', 'Account Name', 'Amount JOD']];
        foreach ($report['revenue_rows'] as $row) {
            $rows[] = ['Revenue', $row['account']->code, $row['account']->name_en ?: $row['account']->name_ar, $this->jod($row['amount_minor'])];
        }
        $rows[] = ['Total Recognized Revenue', '', '', $this->jod($report['total_revenue_minor'])];
        foreach ($report['expense_rows'] as $row) {
            $rows[] = ['Operating Expense', $row['account']->code, $row['account']->name_en ?: $row['account']->name_ar, $this->jod($row['amount_minor'])];
        }
        $rows[] = ['Total Operating Expenses', '', '', $this->jod($report['total_expenses_minor'])];
        $rows[] = ['Management Net Income', '', '', $this->jod($report['net_income_minor'])];

        return $rows;
    }

    private function balanceSheetRows(array $report): array
    {
        $rows = [['Section', 'Group', 'Account Code', 'Account Name', 'Amount JOD']];
        foreach ($report['sections'] as $section => $groups) {
            foreach ($groups as $group => $data) {
                foreach ($data['rows'] as $row) {
                    $rows[] = [$section, $group, $row['account']?->code, $row['label'], $this->jod($row['amount_minor'])];
                }
                $rows[] = [$section, $group.' total', '', '', $this->jod($data['total_minor'])];
            }
        }
        $rows[] = ['Equation Difference', '', '', '', $this->jod($report['equation_difference_minor'])];

        return $rows;
    }

    private function cashFlowRows(array $report): array
    {
        $rows = [['Section', 'Event', 'Account', 'Date', 'Amount JOD']];
        foreach ($report['rows'] as $row) {
            $movement = $row['movement'];
            $rows[] = [$row['section'], $movement->event_type, $movement->financialAccount?->name_ar, $movement->occurred_at?->toDateTimeString(), $this->jod($row['signed_minor'])];
        }
        $rows[] = ['Opening Cash', '', '', '', $this->jod($report['opening_cash_minor'])];
        $rows[] = ['Net Company Cash Movement', '', '', '', $this->jod($report['net_company_cash_movement_minor'])];
        $rows[] = ['Closing Cash', '', '', '', $this->jod($report['closing_cash_minor'])];

        return $rows;
    }

    private function receivablesRows(array $aging, $payments): array
    {
        $rows = [['Client', 'Invoice', 'Due Date', 'Original Total JOD', 'Payment Allocated JOD', 'Credit Applied JOD', 'Outstanding JOD', 'Days Overdue', 'Bucket']];
        foreach ($aging['items'] as $item) {
            $rows[] = [
                $item['client']?->business_name,
                $item['invoice']->invoice_number,
                $item['due_date']?->toDateString(),
                $this->jod($item['original_total_minor']),
                $this->jod($item['payment_allocated_minor']),
                $this->jod($item['credit_applied_minor']),
                $this->jod($item['outstanding_minor']),
                $item['days_overdue'],
                $item['bucket'],
            ];
        }
        $rows[] = [];
        $rows[] = ['Payments received', 'Reference', 'Received At', 'Method', 'Amount JOD'];
        foreach ($payments as $payment) {
            $rows[] = [
                $payment->client?->business_name,
                $payment->reference,
                $payment->received_at?->toDateTimeString(),
                $payment->payment_method,
                $this->jod((int) $payment->amount_minor),
            ];
        }

        return $rows;
    }

    private function deferredRows(array $report): array
    {
        $rows = [['Client', 'Invoice', 'Invoice Line', 'Original JOD', 'Recognized JOD', 'Credit Adjustments JOD', 'Remaining Deferred JOD', 'Next Recognition Date']];
        foreach ($report['items'] as $item) {
            $rows[] = [
                $item['client']?->business_name,
                $item['invoice']?->invoice_number,
                $item['invoice_line']?->description_snapshot,
                $this->jod($item['original_recognizable_minor']),
                $this->jod($item['recognized_minor']),
                $this->jod($item['credit_adjustments_minor']),
                $this->jod($item['remaining_deferred_minor']),
                $item['next_recognition_date']?->toDateString(),
            ];
        }

        return $rows;
    }

    private function recognizedRows(array $report): array
    {
        $rows = [['Month', 'Recognized Revenue JOD']];
        foreach ($report['by_month'] as $row) {
            $rows[] = [$row['month'], $this->jod($row['amount_minor'])];
        }

        return $rows;
    }

    private function expenseRows(array $report): array
    {
        $rows = [['Category', 'Amount JOD']];
        foreach ($report['by_category'] as $row) {
            $rows[] = [$row->category_name_snapshot, $this->jod((int) $row->amount_minor)];
        }
        $rows[] = ['Company-funded', $this->jod($report['company_funded_minor'])];
        $rows[] = ['Personally-funded', $this->jod($report['personally_funded_minor'])];

        return $rows;
    }

    private function jod(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';

        return $sign.Money::fromMinorUnits(abs($minor))->format();
    }
}
