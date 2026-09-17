<?php

namespace App\Console\Commands;

use App\Models\CashMovement;
use App\Models\CreditNote;
use App\Models\CreditNoteApplication;
use App\Models\Invoice;
use App\Models\Expense;
use App\Models\FinancialTransfer;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use App\Models\PaymentAllocation;
use App\Models\Refund;
use App\Services\AccountingEventPostingService;
use App\Services\AccountingSetupService;
use App\Services\BillingAccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BackfillAccountingLedgerCommand extends Command
{
    protected $signature = 'finance:backfill-accounting-ledger {--dry-run : Report eligible events without writing journals}';

    protected $description = 'Idempotently backfill Finance E1 accounting journals for eligible V2 operational events.';

    public function handle(AccountingSetupService $setup, AccountingEventPostingService $posting, BillingAccountingService $billing): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $setup->ensureSeeded();
        $counts = [
            'posted' => 0,
            'already_posted' => 0,
            'skipped' => 0,
            'ambiguous' => 0,
        ];

        $this->backfillCashMovements($posting, $dryRun, $counts);
        $this->backfillTransfers($posting, $dryRun, $counts);
        $this->backfillPersonalExpenses($posting, $dryRun, $counts);
        $this->backfillPersonalFixedAssets($posting, $dryRun, $counts);
        $this->backfillInvoices($billing, $dryRun, $counts);
        $this->backfillCreditNotes($billing, $dryRun, $counts);
        $this->backfillPaymentAllocations($billing, $dryRun, $counts);
        $this->backfillCreditApplications($billing, $dryRun, $counts);
        $this->backfillCreditNoteRefundReclassifications($billing, $dryRun, $counts);

        DB::table('activity_logs')->insert([
            'client_id' => null,
            'user_id' => null,
            'type' => 'accounting_backfill_run',
            'description' => $dryRun ? 'Accounting backfill dry run' : 'Accounting backfill run',
            'metadata' => json_encode($counts + ['dry_run' => $dryRun]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info(json_encode($counts + ['dry_run' => $dryRun], JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    private function backfillCashMovements(AccountingEventPostingService $posting, bool $dryRun, array &$counts): void
    {
        CashMovement::query()
            ->whereNotIn('event_type', [CashMovement::EVENT_TRANSFER_IN, CashMovement::EVENT_TRANSFER_OUT])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->each(function (CashMovement $movement) use ($posting, $dryRun, &$counts) {
                $eventKey = 'cash:'.$movement->event_key;
                if (JournalEntry::where('event_key', $eventKey)->exists()) {
                    $counts['already_posted']++;
                    return;
                }
                if ($dryRun) {
                    $counts['posted']++;
                    return;
                }

                try {
                    $journal = $posting->postCashMovement($movement);
                    $journal === null ? $counts['skipped']++ : $counts['posted']++;
                } catch (Throwable $e) {
                    $counts['ambiguous']++;
                    $this->warn('Skipped cash movement '.$movement->id.': '.$e->getMessage());
                }
            });
    }

    private function backfillTransfers(AccountingEventPostingService $posting, bool $dryRun, array &$counts): void
    {
        FinancialTransfer::query()
            ->with('reversal')
            ->orderBy('transferred_at')
            ->orderBy('id')
            ->each(function (FinancialTransfer $transfer) use ($posting, $dryRun, &$counts) {
                $eventKey = 'financial_transfer:'.$transfer->id.':accounting';
                if (JournalEntry::where('event_key', $eventKey)->exists()) {
                    $counts['already_posted']++;
                } elseif ($dryRun) {
                    $counts['posted']++;
                } else {
                    try {
                        $posting->postFinancialTransfer($transfer, $transfer->created_by);
                        $counts['posted']++;
                    } catch (Throwable $e) {
                        $counts['ambiguous']++;
                        $this->warn('Skipped transfer '.$transfer->id.': '.$e->getMessage());
                    }
                }

                if ($transfer->reversal !== null) {
                    $reversalKey = 'financial_transfer_reversal:'.$transfer->reversal->id.':accounting';
                    if (JournalEntry::where('event_key', $reversalKey)->exists()) {
                        $counts['already_posted']++;
                    } elseif ($dryRun) {
                        $counts['posted']++;
                    } else {
                        try {
                            $posting->postFinancialTransferReversal($transfer->reversal, $transfer->reversal->reversed_by);
                            $counts['posted']++;
                        } catch (Throwable $e) {
                            $counts['ambiguous']++;
                            $this->warn('Skipped transfer reversal '.$transfer->reversal->id.': '.$e->getMessage());
                        }
                    }
                }
            });
    }

    private function backfillPersonalExpenses(AccountingEventPostingService $posting, bool $dryRun, array &$counts): void
    {
        Expense::query()
            ->with('reversal')
            ->where('expense_engine_version', Expense::ENGINE_V2)
            ->where('funding_source', Expense::FUNDING_PERSONAL)
            ->orderBy('paid_at')
            ->orderBy('id')
            ->each(function (Expense $expense) use ($posting, $dryRun, &$counts) {
                $eventKey = 'expense:'.$expense->id.':accounting';
                if (JournalEntry::where('event_key', $eventKey)->exists()) {
                    $counts['already_posted']++;
                } elseif ($dryRun) {
                    $counts['posted']++;
                } else {
                    try {
                        $posting->postPersonalExpense($expense);
                        $counts['posted']++;
                    } catch (Throwable $e) {
                        $counts['ambiguous']++;
                        $this->warn('Skipped personal expense '.$expense->id.': '.$e->getMessage());
                    }
                }

                if ($expense->reversal !== null) {
                    $reversalKey = 'expense_reversal:'.$expense->reversal->id.':accounting';
                    if (JournalEntry::where('event_key', $reversalKey)->exists()) {
                        $counts['already_posted']++;
                    } elseif ($dryRun) {
                        $counts['posted']++;
                    } else {
                        try {
                            $posting->postPersonalExpenseReversal($expense->reversal);
                            $counts['posted']++;
                        } catch (Throwable $e) {
                            $counts['ambiguous']++;
                            $this->warn('Skipped personal expense reversal '.$expense->reversal->id.': '.$e->getMessage());
                        }
                    }
                }
            });
    }

    private function backfillPersonalFixedAssets(AccountingEventPostingService $posting, bool $dryRun, array &$counts): void
    {
        FixedAsset::query()
            ->with('acquisitionReversal')
            ->where('funding_source', FixedAsset::FUNDING_PERSONAL)
            ->orderBy('acquired_at')
            ->orderBy('id')
            ->each(function (FixedAsset $asset) use ($posting, $dryRun, &$counts) {
                $eventKey = 'fixed_asset:'.$asset->id.':accounting';
                if (JournalEntry::where('event_key', $eventKey)->exists()) {
                    $counts['already_posted']++;
                } elseif ($dryRun) {
                    $counts['posted']++;
                } else {
                    try {
                        $posting->postPersonalFixedAsset($asset);
                        $counts['posted']++;
                    } catch (Throwable $e) {
                        $counts['ambiguous']++;
                        $this->warn('Skipped personal fixed asset '.$asset->id.': '.$e->getMessage());
                    }
                }

                if ($asset->acquisitionReversal !== null) {
                    $reversalKey = 'fixed_asset_acquisition_reversal:'.$asset->acquisitionReversal->id.':accounting';
                    if (JournalEntry::where('event_key', $reversalKey)->exists()) {
                        $counts['already_posted']++;
                    } elseif ($dryRun) {
                        $counts['posted']++;
                    } else {
                        try {
                            $posting->postPersonalFixedAssetReversal($asset->acquisitionReversal);
                            $counts['posted']++;
                        } catch (Throwable $e) {
                            $counts['ambiguous']++;
                            $this->warn('Skipped personal fixed asset reversal '.$asset->acquisitionReversal->id.': '.$e->getMessage());
                        }
                    }
                }
            });
    }

    private function backfillInvoices(BillingAccountingService $billing, bool $dryRun, array &$counts): void
    {
        Invoice::query()
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_VOIDED])
            ->whereNotNull('issued_at')
            ->orderBy('issue_date')
            ->orderBy('id')
            ->each(function (Invoice $invoice) use ($billing, $dryRun, &$counts) {
                $eventKey = $billing->invoiceIssuedEventKey($invoice);
                if (JournalEntry::where('event_key', $eventKey)->exists()) {
                    $counts['already_posted']++;
                } elseif ($dryRun) {
                    $counts['posted']++;
                } else {
                    try {
                        $billing->postInvoiceIssued($invoice);
                        $counts['posted']++;
                    } catch (Throwable $e) {
                        $counts['ambiguous']++;
                        $this->warn('Skipped invoice '.$invoice->id.': '.$e->getMessage());
                    }
                }

                if ($invoice->status === Invoice::STATUS_VOIDED) {
                    $voidKey = 'billing:invoice:'.$invoice->id.':void';
                    if (JournalEntry::where('event_key', $voidKey)->exists()) {
                        $counts['already_posted']++;
                    } elseif ($dryRun) {
                        $counts['posted']++;
                    } else {
                        try {
                            $journal = $billing->postInvoiceVoid($invoice, $invoice->created_by);
                            $journal === null ? $counts['skipped']++ : $counts['posted']++;
                        } catch (Throwable $e) {
                            $counts['ambiguous']++;
                            $this->warn('Skipped invoice void '.$invoice->id.': '.$e->getMessage());
                        }
                    }
                }
            });
    }

    private function backfillCreditNotes(BillingAccountingService $billing, bool $dryRun, array &$counts): void
    {
        CreditNote::query()
            ->whereIn('status', [CreditNote::STATUS_ISSUED, CreditNote::STATUS_VOIDED])
            ->orderBy('issue_date')
            ->orderBy('id')
            ->each(function (CreditNote $creditNote) use ($billing, $dryRun, &$counts) {
                $eventKey = $billing->creditNoteIssuedEventKey($creditNote);
                if (JournalEntry::where('event_key', $eventKey)->exists()) {
                    $counts['already_posted']++;
                } elseif ($dryRun) {
                    $counts['posted']++;
                } else {
                    try {
                        $billing->postCreditNoteIssued($creditNote);
                        $counts['posted']++;
                    } catch (Throwable $e) {
                        $counts['ambiguous']++;
                        $this->warn('Skipped credit note '.$creditNote->id.': '.$e->getMessage());
                    }
                }

                if ($creditNote->status === CreditNote::STATUS_VOIDED) {
                    $voidKey = 'billing:credit-note:'.$creditNote->id.':void';
                    if (JournalEntry::where('event_key', $voidKey)->exists()) {
                        $counts['already_posted']++;
                    } elseif ($dryRun) {
                        $counts['posted']++;
                    } else {
                        try {
                            $journal = $billing->postCreditNoteVoid($creditNote, $creditNote->created_by);
                            $journal === null ? $counts['skipped']++ : $counts['posted']++;
                        } catch (Throwable $e) {
                            $counts['ambiguous']++;
                            $this->warn('Skipped credit note void '.$creditNote->id.': '.$e->getMessage());
                        }
                    }
                }
            });
    }

    private function backfillPaymentAllocations(BillingAccountingService $billing, bool $dryRun, array &$counts): void
    {
        PaymentAllocation::query()
            ->with('reversal')
            ->orderBy('allocated_at')
            ->orderBy('id')
            ->each(function (PaymentAllocation $allocation) use ($billing, $dryRun, &$counts) {
                $eventKey = 'billing:payment-allocation:'.$allocation->id;
                if (JournalEntry::where('event_key', $eventKey)->exists()) {
                    $counts['already_posted']++;
                } elseif ($dryRun) {
                    $counts['posted']++;
                } else {
                    try {
                        $billing->postPaymentAllocation($allocation);
                        $counts['posted']++;
                    } catch (Throwable $e) {
                        $counts['ambiguous']++;
                        $this->warn('Skipped payment allocation '.$allocation->id.': '.$e->getMessage());
                    }
                }

                if ($allocation->reversal !== null) {
                    $reversalKey = 'billing:payment-allocation-reversal:'.$allocation->reversal->id;
                    if (JournalEntry::where('event_key', $reversalKey)->exists()) {
                        $counts['already_posted']++;
                    } elseif ($dryRun) {
                        $counts['posted']++;
                    } else {
                        try {
                            $journal = $billing->postPaymentAllocationReversal($allocation->reversal);
                            $journal === null ? $counts['skipped']++ : $counts['posted']++;
                        } catch (Throwable $e) {
                            $counts['ambiguous']++;
                            $this->warn('Skipped payment allocation reversal '.$allocation->reversal->id.': '.$e->getMessage());
                        }
                    }
                }
            });
    }

    private function backfillCreditApplications(BillingAccountingService $billing, bool $dryRun, array &$counts): void
    {
        CreditNoteApplication::query()
            ->with('reversal')
            ->orderBy('applied_at')
            ->orderBy('id')
            ->each(function (CreditNoteApplication $application) use ($billing, $dryRun, &$counts) {
                $eventKey = 'billing:credit-application:'.$application->id;
                if (JournalEntry::where('event_key', $eventKey)->exists()) {
                    $counts['already_posted']++;
                } elseif ($dryRun) {
                    $counts['posted']++;
                } else {
                    try {
                        $billing->postCreditApplication($application);
                        $counts['posted']++;
                    } catch (Throwable $e) {
                        $counts['ambiguous']++;
                        $this->warn('Skipped credit application '.$application->id.': '.$e->getMessage());
                    }
                }

                if ($application->reversal !== null) {
                    $reversalKey = 'billing:credit-application-reversal:'.$application->reversal->id;
                    if (JournalEntry::where('event_key', $reversalKey)->exists()) {
                        $counts['already_posted']++;
                    } elseif ($dryRun) {
                        $counts['posted']++;
                    } else {
                        try {
                            $journal = $billing->postCreditApplicationReversal($application->reversal);
                            $journal === null ? $counts['skipped']++ : $counts['posted']++;
                        } catch (Throwable $e) {
                            $counts['ambiguous']++;
                            $this->warn('Skipped credit application reversal '.$application->reversal->id.': '.$e->getMessage());
                        }
                    }
                }
            });
    }

    private function backfillCreditNoteRefundReclassifications(BillingAccountingService $billing, bool $dryRun, array &$counts): void
    {
        Refund::query()
            ->whereNotNull('credit_note_id')
            ->orderBy('refunded_at')
            ->orderBy('id')
            ->each(function (Refund $refund) use ($billing, $dryRun, &$counts) {
                $eventKey = 'billing:refund:'.$refund->id.':credit-source-reclassification';
                if (JournalEntry::where('event_key', $eventKey)->exists()) {
                    $counts['already_posted']++;
                    return;
                }
                if ($dryRun) {
                    $counts['posted']++;
                    return;
                }

                try {
                    $journal = $billing->postHistoricalCreditNoteRefundReclassification($refund);
                    $journal === null ? $counts['skipped']++ : $counts['posted']++;
                } catch (Throwable $e) {
                    $counts['ambiguous']++;
                    $this->warn('Skipped refund reclassification '.$refund->id.': '.$e->getMessage());
                }
            });
    }
}
