<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\AssetCategory;
use App\Models\CapitalFundingTransaction;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\FixedAsset;
use App\Models\FundingSource;
use App\Models\RecurringExpenseObligation;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CapitalManagementService;
use App\Services\FinancialReportingReconciliationService;
use App\Services\FinancialStatementService;
use App\Services\OperatingExpenseService;
use App\Services\ReceivableService;
use App\Services\SaasMetricsService;
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
        FinancialReportingReconciliationService $reconciliation,
        SaasMetricsService $saas,
        ReceivableService $receivables,
        OperatingExpenseService $expenseService,
        CapitalManagementService $capitalService
    ) {
        Gate::authorize(FinancialPermissions::VIEW_FINANCIAL_STATEMENTS);

        $section = $request->query('section', 'overview');
        if (! in_array($section, ['overview', 'collections', 'expenses', 'capital_assets', 'reports', 'advanced'], true)) {
            $section = 'overview';
        }
        if ($section === 'advanced') {
            Gate::authorize(FinancialPermissions::VIEW_ACCOUNTING);
        }

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

        $saasReport = $saas->dashboard($period);

        // Collections Section Data
        $filters = $request->only(['client_id', 'due_state', 'settlement_state', 'date_from', 'date_to']);
        $filters['due_state'] = ($filters['due_state'] ?? 'all') === 'all' ? null : $filters['due_state'];
        $outstandingInvoices = $receivables->outstandingInvoices($filters);
        $overdueInvoices = $receivables->outstandingInvoices(array_merge($filters, ['due_state' => 'overdue']));
        $partiallyPaidInvoices = $receivables->outstandingInvoices(array_merge($filters, ['settlement_state' => 'partially_paid']));
        $unallocatedCredits = $receivables->unallocatedCredits($filters);
        $availableCustomerCredits = $receivables->availableCustomerCredits($filters);
        $clients = Client::orderBy('business_name')->get(['id', 'business_name']);

        // Expenses Section Data
        $expenseTotals = $expenseService->activeTotals();
        $recentExpenses = Expense::with(['categoryModel', 'vendor', 'financialAccount', 'personalPayer', 'reversal', 'recurringObligation'])
            ->v2()
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
        $activeCategories = ExpenseCategory::active()->orderBy('name_ar')->get();
        $activeVendors = Vendor::active()->orderBy('name')->get();
        $activeFinancialAccounts = FinancialAccount::where('is_active', true)->whereNull('archived_at')->orderBy('name_ar')->get();
        $internalUsers = User::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('role')->orWhereIn('role', User::activeInternalRoles()))
            ->orderBy('name')
            ->get();
        $upcomingObligations = RecurringExpenseObligation::with(['template', 'vendor'])
            ->pending()
            ->whereDate('due_date', '>=', today())
            ->orderBy('due_date')
            ->limit(10)
            ->get();
        $overdueObligations = RecurringExpenseObligation::with(['template', 'vendor'])
            ->pending()
            ->whereDate('due_date', '<', today())
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        // Capital & Assets Section Data
        $capitalTotals = $capitalService->activeTotals();
        $fundingSources = FundingSource::orderByDesc('is_active')->orderBy('name')->get();
        $activeFundingSources = FundingSource::active()->get();
        $fundingTransactions = CapitalFundingTransaction::with(['fundingSource', 'financialAccount', 'reversal'])
            ->orderByDesc('received_at')
            ->limit(20)
            ->get();
        $assetCategories = AssetCategory::orderByDesc('is_active')->orderBy('sort_order')->orderBy('name_ar')->get();
        $activeAssetCategories = AssetCategory::active()->get();
        $fixedAssets = FixedAsset::with(['category', 'vendor', 'financialAccount', 'personalPayer', 'acquisitionReversal'])
            ->orderByDesc('acquired_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        // Advanced Section Data
        $accountingPeriods = AccountingPeriod::orderByDesc('period_key')->limit(12)->get();

        return view('finance.index', compact(
            'section',
            'period',
            'reports',
            'saasReport',
            'filters',
            'outstandingInvoices',
            'overdueInvoices',
            'partiallyPaidInvoices',
            'unallocatedCredits',
            'availableCustomerCredits',
            'clients',
            'expenseTotals',
            'recentExpenses',
            'activeCategories',
            'activeVendors',
            'activeFinancialAccounts',
            'internalUsers',
            'upcomingObligations',
            'overdueObligations',
            'capitalTotals',
            'fundingSources',
            'activeFundingSources',
            'fundingTransactions',
            'assetCategories',
            'activeAssetCategories',
            'fixedAssets',
            'accountingPeriods'
        ));
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
