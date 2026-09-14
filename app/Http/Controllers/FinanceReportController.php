<?php

namespace App\Http\Controllers;

use App\Services\FinancialReportingReconciliationService;
use App\Services\FinancialStatementService;
use App\Support\FinancialPermissions;
use App\Support\Money;
use App\Support\ReportingPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceReportController extends Controller
{
    public function index(
        Request $request,
        FinancialStatementService $statements,
        FinancialReportingReconciliationService $reconciliation
    ) {
        Gate::authorize(FinancialPermissions::VIEW_FINANCIAL_STATEMENTS);

        $period = ReportingPeriod::fromRequest($request);
        $reports = [
            'dashboard' => $statements->dashboard($period),
            'profit_and_loss' => $statements->profitAndLoss($period),
            'balance_sheet' => $statements->balanceSheet($period->end),
            'cash_flow' => $statements->cashFlow($period),
            'ar_aging' => $statements->arAging($period->end),
            'deferred_revenue' => $statements->deferredRevenueReport($period),
            'recognized_revenue' => $statements->recognizedRevenueReport($period),
            'customer_credits' => $statements->customerCredits(),
            'sales_tax' => $statements->salesTaxReport(),
            'expenses' => $statements->expenseReport($period),
            'capital_assets' => $statements->capitalAssetReport($period),
            'reconciliation' => $reconciliation->run($period),
        ];

        return view('finance.index', compact('period', 'reports'));
    }

    public function export(Request $request, string $report, FinancialStatementService $statements): StreamedResponse
    {
        Gate::authorize(FinancialPermissions::EXPORT_FINANCIAL_REPORTS);

        $period = ReportingPeriod::fromRequest($request);
        [$filename, $rows] = $this->exportRows($report, $period, $statements);

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportRows(string $report, ReportingPeriod $period, FinancialStatementService $statements): array
    {
        return match ($report) {
            'profit-and-loss' => ['profit-and-loss.csv', $this->profitAndLossRows($statements->profitAndLoss($period))],
            'balance-sheet' => ['management-balance-sheet.csv', $this->balanceSheetRows($statements->balanceSheet($period->end))],
            'cash-flow' => ['cash-flow.csv', $this->cashFlowRows($statements->cashFlow($period))],
            'ar-aging' => ['ar-aging.csv', $this->arAgingRows($statements->arAging($period->end))],
            'deferred-revenue' => ['deferred-revenue.csv', $this->deferredRows($statements->deferredRevenueReport($period))],
            'recognized-revenue' => ['recognized-revenue.csv', $this->recognizedRows($statements->recognizedRevenueReport($period))],
            'expense-breakdown' => ['expense-breakdown.csv', $this->expenseRows($statements->expenseReport($period))],
            default => abort(404),
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

    private function arAgingRows(array $report): array
    {
        $rows = [['Client', 'Invoice', 'Due Date', 'Original Total JOD', 'Payment Allocated JOD', 'Credit Applied JOD', 'Outstanding JOD', 'Days Overdue', 'Bucket']];
        foreach ($report['items'] as $item) {
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
