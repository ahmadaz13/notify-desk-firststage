<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CreditNote;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class AccountingReconciliationService
{
    public function __construct(
        private readonly AccountingReportService $reports,
        private readonly AccountingSetupService $setup,
        private readonly FinancialAccountBalanceService $cashBalances,
        private readonly ReceivableService $receivables,
        private readonly RevenueRecognitionService $revenueRecognition
    ) {
    }

    public function run(): array
    {
        $trial = $this->reports->trialBalance();
        $cash = $this->cashReconciliation();
        $billing = $this->billingReconciliation();
        $revenue = $this->recognizedRevenueReconciliation();
        $duplicateEventKeys = JournalEntry::query()
            ->select('event_key', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('event_key')
            ->having('aggregate', '>', 1)
            ->pluck('event_key')
            ->all();

        $failures = [];
        if ($trial['difference_minor'] !== 0) {
            $failures[] = 'trial_balance_out_of_balance';
        }
        foreach ($cash as $row) {
            if ($row['difference_minor'] !== 0) {
                $failures[] = 'cash_account_'.$row['financial_account']->id.'_difference';
            }
        }
        foreach ($billing as $key => $row) {
            if ($row['difference_minor'] !== 0) {
                $failures[] = $key.'_difference';
            }
        }
        foreach ($revenue as $key => $row) {
            if ($row['difference_minor'] !== 0) {
                $failures[] = $key.'_difference';
            }
        }
        if ($duplicateEventKeys !== []) {
            $failures[] = 'duplicate_journal_event_keys';
        }

        return [
            'ok' => $failures === [],
            'failures' => $failures,
            'trial_balance' => [
                'total_debit_minor' => $trial['total_debit_minor'],
                'total_credit_minor' => $trial['total_credit_minor'],
                'difference_minor' => $trial['difference_minor'],
            ],
            'cash_accounts' => $cash,
            'billing' => $billing,
            'recognized_revenue' => $revenue,
            'duplicate_event_keys' => $duplicateEventKeys,
        ];
    }

    private function cashReconciliation(): array
    {
        return FinancialAccount::query()
            ->orderBy('id')
            ->get()
            ->map(function (FinancialAccount $account) {
                $chartAccount = $this->setup->ensureFinancialAccountMapping($account);
                $debit = (int) DB::table('journal_lines')
                    ->where('chart_account_id', $chartAccount->id)
                    ->sum('debit_minor');
                $credit = (int) DB::table('journal_lines')
                    ->where('chart_account_id', $chartAccount->id)
                    ->sum('credit_minor');
                $gl = $debit - $credit;
                $cash = $this->cashBalances->currentBalanceMinor($account);

                return [
                    'financial_account' => $account,
                    'chart_account' => $chartAccount,
                    'cash_subledger_minor' => $cash,
                    'general_ledger_minor' => $gl,
                    'difference_minor' => $gl - $cash,
                ];
            })
            ->all();
    }

    public function billingReconciliation(): array
    {
        $this->setup->ensureSeeded();

        $ar = $this->activeIssuedInvoices()
            ->get()
            ->sum(fn (Invoice $invoice) => $this->receivables->invoiceOutstandingMinor($invoice));

        $billingClearing = Payment::query()
            ->where('payment_engine_version', Payment::ENGINE_V2)
            ->whereNotNull('amount_minor')
            ->get()
            ->sum(fn (Payment $payment) => $this->receivables->paymentUnallocatedMinor($payment));

        $customerCredits = CreditNote::query()
            ->where('status', CreditNote::STATUS_ISSUED)
            ->get()
            ->sum(fn (CreditNote $creditNote) => $this->receivables->creditNoteAvailableMinor($creditNote));

        $invoiceTax = (int) $this->activeIssuedInvoices()->sum('tax_minor');
        $creditTax = (int) CreditNote::query()
            ->where('status', CreditNote::STATUS_ISSUED)
            ->sum('tax_minor');
        $salesTax = $invoiceTax - $creditTax;

        $deferred = $this->revenueRecognition->operationalDeferredRevenueMinor();

        return [
            'accounts_receivable' => $this->billingRow('accounts_receivable', (int) $ar),
            'billing_clearing' => $this->billingRow('billing_clearing', (int) $billingClearing),
            'customer_credits' => $this->billingRow('customer_credits', (int) $customerCredits),
            'sales_tax_payable' => $this->billingRow('sales_tax_payable', (int) $salesTax),
            'deferred_revenue' => $this->billingRow('deferred_revenue', (int) $deferred),
        ];
    }

    public function recognizedRevenueReconciliation(): array
    {
        $rows = [];
        foreach ($this->revenueRecognition->recognizedProjectionByAccount() as $key => $projection) {
            $account = $projection['chart_account'];
            $glMinor = $this->accountBalanceMinor($account->id);
            $rows[$key] = [
                'chart_account' => $account,
                'operational_minor' => $projection['operational_minor'],
                'general_ledger_minor' => $glMinor,
                'difference_minor' => $glMinor - $projection['operational_minor'],
            ];
        }

        return $rows;
    }

    private function billingRow(string $mappingKey, int $operationalMinor): array
    {
        $account = $this->setup->systemAccount($mappingKey);
        $glMinor = $this->accountBalanceMinor($account->id);

        return [
            'chart_account' => $account,
            'operational_minor' => $operationalMinor,
            'general_ledger_minor' => $glMinor,
            'difference_minor' => $glMinor - $operationalMinor,
        ];
    }

    private function accountBalanceMinor(int $chartAccountId): int
    {
        $account = \App\Models\ChartAccount::findOrFail($chartAccountId);
        $debit = (int) DB::table('journal_lines')
            ->where('chart_account_id', $chartAccountId)
            ->sum('debit_minor');
        $credit = (int) DB::table('journal_lines')
            ->where('chart_account_id', $chartAccountId)
            ->sum('credit_minor');

        return $account->normal_balance === \App\Models\ChartAccount::NORMAL_DEBIT
            ? $debit - $credit
            : $credit - $debit;
    }

    private function activeIssuedInvoices()
    {
        return Invoice::query()->where('status', Invoice::STATUS_ISSUED);
    }
}
