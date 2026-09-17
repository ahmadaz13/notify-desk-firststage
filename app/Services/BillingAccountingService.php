<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\CreditNoteApplication;
use App\Models\CreditNoteApplicationReversal;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PaymentAllocation;
use App\Models\PaymentAllocationReversal;
use App\Models\Refund;
use Carbon\Carbon;

class BillingAccountingService
{
    public function __construct(
        private readonly AccountingSetupService $setup,
        private readonly JournalPostingService $journals,
        private readonly RevenueRecognitionService $revenueRecognition
    ) {
    }

    public function postInvoiceIssued(Invoice $invoice): ?JournalEntry
    {
        $invoice->refresh();
        if ($invoice->status !== Invoice::STATUS_ISSUED) {
            return null;
        }

        $total = (int) $invoice->total_minor;
        $tax = (int) $invoice->tax_minor;
        $deferred = $total - $tax;
        if ($total <= 0 || $deferred < 0) {
            return null;
        }

        $lines = [
            $this->debit($this->setup->systemAccount('accounts_receivable')->id, $total, 'Accounts receivable', null, $invoice->client_id),
            $this->credit($this->setup->systemAccount('deferred_revenue')->id, $deferred, 'Deferred billed value', null, $invoice->client_id),
        ];
        if ($tax > 0) {
            $lines[] = $this->credit($this->setup->systemAccount('sales_tax_payable')->id, $tax, 'Sales tax payable', null, $invoice->client_id);
        }

        return $this->journals->post([
            'event_type' => 'invoice_issued_accounting',
            'event_key' => $this->invoiceIssuedEventKey($invoice),
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'entry_date' => $invoice->issue_date,
            'description' => 'Issued invoice '.$invoice->invoice_number,
            'created_by' => $invoice->created_by,
            'metadata' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'billing_period_start' => $invoice->billing_period_start?->toDateString(),
                'billing_period_end' => $invoice->billing_period_end?->toDateString(),
                'revenue_recognition_deferred_until_e2b' => true,
            ],
        ], $lines);
    }

    public function postInvoiceVoid(Invoice $invoice, ?int $userId = null): ?JournalEntry
    {
        $original = JournalEntry::where('event_key', $this->invoiceIssuedEventKey($invoice))->first();
        if ($original === null) {
            return null;
        }

        return $this->journals->reverse(
            $original,
            'billing:invoice:'.$invoice->id.':void',
            'invoice_void_accounting',
            $invoice->voided_at ?? now(),
            'Void invoice '.$invoice->invoice_number,
            Invoice::class,
            $invoice->id,
            $userId,
            ['invoice_id' => $invoice->id, 'void_reason' => $invoice->void_reason]
        );
    }

    public function postPaymentAllocation(PaymentAllocation $allocation): JournalEntry
    {
        $allocation->loadMissing(['invoice', 'payment']);
        $amount = (int) $allocation->amount_minor;

        return $this->journals->post([
            'event_type' => 'payment_allocation_accounting',
            'event_key' => 'billing:payment-allocation:'.$allocation->id,
            'source_type' => PaymentAllocation::class,
            'source_id' => $allocation->id,
            'entry_date' => $allocation->allocated_at,
            'description' => 'Payment allocation to invoice '.$allocation->invoice?->invoice_number,
            'created_by' => $allocation->created_by,
            'metadata' => [
                'payment_allocation_id' => $allocation->id,
                'payment_id' => $allocation->payment_id,
                'invoice_id' => $allocation->invoice_id,
                'invoice_number' => $allocation->invoice?->invoice_number,
            ],
        ], [
            $this->debit($this->setup->systemAccount('billing_clearing')->id, $amount, 'Clear customer payment liability', null, $allocation->client_id),
            $this->credit($this->setup->systemAccount('accounts_receivable')->id, $amount, 'Settle accounts receivable', null, $allocation->client_id),
        ]);
    }

    public function postPaymentAllocationReversal(PaymentAllocationReversal $reversal): ?JournalEntry
    {
        $reversal->loadMissing('allocation');
        if ($reversal->allocation === null) {
            return null;
        }

        $original = JournalEntry::where('event_key', 'billing:payment-allocation:'.$reversal->payment_allocation_id)->first();
        if ($original === null) {
            return null;
        }

        return $this->journals->reverse(
            $original,
            'billing:payment-allocation-reversal:'.$reversal->id,
            'payment_allocation_reversal_accounting',
            $reversal->reversed_at,
            'Payment allocation reversal',
            PaymentAllocationReversal::class,
            $reversal->id,
            $reversal->reversed_by,
            ['reason' => $reversal->reason, 'payment_allocation_id' => $reversal->payment_allocation_id]
        );
    }

    public function postCreditNoteIssued(CreditNote $creditNote): ?JournalEntry
    {
        $creditNote->refresh();
        if ($creditNote->status !== CreditNote::STATUS_ISSUED) {
            return null;
        }

        $total = (int) $creditNote->total_minor;
        $tax = (int) $creditNote->tax_minor;
        $deferred = $total - $tax;
        if ($total <= 0 || $deferred < 0) {
            return null;
        }

        $split = $this->revenueRecognition->creditNoteRecognitionSplit($creditNote);

        $lines = [];
        if ($split['deferred_minor'] > 0) {
            $lines[] = $this->debit($this->setup->systemAccount('deferred_revenue')->id, (int) $split['deferred_minor'], 'Reduce deferred billed value', null, $creditNote->client_id);
        }
        foreach ($split['recognized_by_account'] as $accountId => $recognizedMinor) {
            if ($recognizedMinor > 0) {
                $lines[] = $this->debit((int) $accountId, (int) $recognizedMinor, 'Reduce previously recognized revenue', null, $creditNote->client_id);
            }
        }
        if ($tax > 0) {
            $lines[] = $this->debit($this->setup->systemAccount('sales_tax_payable')->id, $tax, 'Reverse sales tax payable', null, $creditNote->client_id);
        }
        $lines[] = $this->credit($this->setup->systemAccount('customer_credits')->id, $total, 'Customer credit liability', null, $creditNote->client_id);

        $journal = $this->journals->post([
            'event_type' => 'credit_note_issued_accounting',
            'event_key' => $this->creditNoteIssuedEventKey($creditNote),
            'source_type' => CreditNote::class,
            'source_id' => $creditNote->id,
            'entry_date' => $creditNote->issue_date,
            'description' => 'Issued credit note '.$creditNote->credit_note_number,
            'created_by' => $creditNote->created_by,
            'metadata' => [
                'credit_note_id' => $creditNote->id,
                'credit_note_number' => $creditNote->credit_note_number,
                'original_invoice_id' => $creditNote->original_invoice_id,
                'revenue_recognition_aware' => true,
                'deferred_reduction_minor' => (int) $split['deferred_minor'],
                'recognized_revenue_reduction_minor' => array_sum($split['recognized_by_account']),
            ],
        ], $lines);

        $this->revenueRecognition->persistCreditNoteAdjustments($creditNote, $journal);

        return $journal;
    }

    public function postCreditNoteVoid(CreditNote $creditNote, ?int $userId = null): ?JournalEntry
    {
        $original = JournalEntry::where('event_key', $this->creditNoteIssuedEventKey($creditNote))->first();
        if ($original === null) {
            return null;
        }

        return $this->journals->reverse(
            $original,
            'billing:credit-note:'.$creditNote->id.':void',
            'credit_note_void_accounting',
            $creditNote->voided_at ?? now(),
            'Void credit note '.$creditNote->credit_note_number,
            CreditNote::class,
            $creditNote->id,
            $userId,
            ['credit_note_id' => $creditNote->id, 'void_reason' => $creditNote->void_reason]
        );
    }

    public function postCreditApplication(CreditNoteApplication $application): JournalEntry
    {
        $application->loadMissing(['creditNote', 'invoice']);
        $amount = (int) $application->amount_minor;

        return $this->journals->post([
            'event_type' => 'credit_note_application_accounting',
            'event_key' => 'billing:credit-application:'.$application->id,
            'source_type' => CreditNoteApplication::class,
            'source_id' => $application->id,
            'entry_date' => $application->applied_at,
            'description' => 'Credit note application to invoice '.$application->invoice?->invoice_number,
            'created_by' => $application->created_by,
            'metadata' => [
                'credit_note_application_id' => $application->id,
                'credit_note_id' => $application->credit_note_id,
                'invoice_id' => $application->invoice_id,
                'invoice_number' => $application->invoice?->invoice_number,
            ],
        ], [
            $this->debit($this->setup->systemAccount('customer_credits')->id, $amount, 'Use customer credit', null, $application->invoice?->client_id),
            $this->credit($this->setup->systemAccount('accounts_receivable')->id, $amount, 'Settle accounts receivable', null, $application->invoice?->client_id),
        ]);
    }

    public function postCreditApplicationReversal(CreditNoteApplicationReversal $reversal): ?JournalEntry
    {
        $reversal->loadMissing('application');
        if ($reversal->application === null) {
            return null;
        }

        $original = JournalEntry::where('event_key', 'billing:credit-application:'.$reversal->credit_note_application_id)->first();
        if ($original === null) {
            return null;
        }

        return $this->journals->reverse(
            $original,
            'billing:credit-application-reversal:'.$reversal->id,
            'credit_application_reversal_accounting',
            $reversal->reversed_at,
            'Credit application reversal',
            CreditNoteApplicationReversal::class,
            $reversal->id,
            $reversal->reversed_by,
            ['reason' => $reversal->reason, 'credit_note_application_id' => $reversal->credit_note_application_id]
        );
    }

    public function postHistoricalCreditNoteRefundReclassification(Refund $refund): ?JournalEntry
    {
        $refund->refresh();
        if ($refund->credit_note_id === null) {
            return null;
        }

        $e1Refund = JournalEntry::where('event_key', 'cash:refund:'.$refund->id.':issued')->first();
        if ($e1Refund === null) {
            return null;
        }

        $billingClearingId = $this->setup->systemAccount('billing_clearing')->id;
        $hasHistoricalBillingClearingDebit = $e1Refund->lines()
            ->where('chart_account_id', $billingClearingId)
            ->where('debit_minor', '>', 0)
            ->exists();
        if (! $hasHistoricalBillingClearingDebit) {
            return null;
        }

        return $this->journals->post([
            'event_type' => 'credit_note_refund_reclassification',
            'event_key' => 'billing:refund:'.$refund->id.':credit-source-reclassification',
            'source_type' => Refund::class,
            'source_id' => $refund->id,
            'entry_date' => $refund->refunded_at,
            'description' => 'Reclassify credit-note-funded refund '.$refund->refund_number,
            'created_by' => $refund->created_by,
            'metadata' => [
                'refund_id' => $refund->id,
                'refund_number' => $refund->refund_number,
                'credit_note_id' => $refund->credit_note_id,
                'preserves_e1_refund_journal' => true,
            ],
        ], [
            $this->debit($this->setup->systemAccount('customer_credits')->id, (int) $refund->amount_minor, 'Credit-note-funded refund source', null, $refund->client_id),
            $this->credit($this->setup->systemAccount('billing_clearing')->id, (int) $refund->amount_minor, 'Reclassify from E1 Billing Clearing', null, $refund->client_id),
        ]);
    }

    public function invoiceIssuedEventKey(Invoice $invoice): string
    {
        return 'billing:invoice:'.$invoice->id.':issued';
    }

    public function creditNoteIssuedEventKey(CreditNote $creditNote): string
    {
        return 'billing:credit-note:'.$creditNote->id.':issued';
    }

    private function debit(int $accountId, int $amount, string $description, ?int $financialAccountId = null, ?int $clientId = null): array
    {
        return [
            'chart_account_id' => $accountId,
            'debit_minor' => $amount,
            'credit_minor' => 0,
            'description' => $description,
            'financial_account_id' => $financialAccountId,
            'client_id' => $clientId,
        ];
    }

    private function credit(int $accountId, int $amount, string $description, ?int $financialAccountId = null, ?int $clientId = null): array
    {
        return [
            'chart_account_id' => $accountId,
            'debit_minor' => 0,
            'credit_minor' => $amount,
            'description' => $description,
            'financial_account_id' => $financialAccountId,
            'client_id' => $clientId,
        ];
    }
}
