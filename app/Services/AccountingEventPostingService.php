<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CapitalFundingReversal;
use App\Models\CapitalFundingTransaction;
use App\Models\Expense;
use App\Models\ExpenseReversal;
use App\Models\FinancialTransfer;
use App\Models\FinancialTransferReversal;
use App\Models\FixedAsset;
use App\Models\FixedAssetAcquisitionReversal;
use App\Models\JournalEntry;
use App\Models\Refund;
use Carbon\Carbon;

class AccountingEventPostingService
{
    public function __construct(
        private readonly AccountingSetupService $setup,
        private readonly JournalPostingService $journals
    ) {
    }

    public function postCashMovement(CashMovement $movement): ?JournalEntry
    {
        $movement->refresh();

        if (in_array($movement->event_type, [CashMovement::EVENT_TRANSFER_IN, CashMovement::EVENT_TRANSFER_OUT], true)) {
            return null;
        }

        return match ($movement->event_type) {
            CashMovement::EVENT_OPENING_BALANCE => $this->postSimpleCashMovement(
                $movement,
                'opening_balance',
                'cash:'.$movement->event_key,
                $this->cashAccountId($movement),
                $this->setup->systemAccount('opening_balance_equity')->id,
                'Opening balance posted to general ledger'
            ),
            CashMovement::EVENT_PAYMENT_RECEIVED => $this->postSimpleCashMovement(
                $movement,
                'payment_received',
                'cash:'.$movement->event_key,
                $this->cashAccountId($movement),
                $this->setup->systemAccount('billing_clearing')->id,
                'Payment receipt posted to temporary Billing Clearing'
            ),
            CashMovement::EVENT_REFUND_ISSUED => $this->postRefundIssued($movement),
            CashMovement::EVENT_EXPENSE_PAID => $this->postCompanyExpense($movement),
            CashMovement::EVENT_CAPITAL_FUNDING_RECEIVED => $this->postCapitalFunding($movement),
            CashMovement::EVENT_ASSET_ACQUISITION => $this->postCompanyFixedAsset($movement),
            CashMovement::EVENT_PAYMENT_REVERSAL,
            CashMovement::EVENT_EXPENSE_REVERSAL,
            CashMovement::EVENT_CAPITAL_FUNDING_REVERSAL,
            CashMovement::EVENT_ASSET_ACQUISITION_REVERSAL => $this->postCashReversal($movement),
            default => null,
        };
    }

    public function postFinancialTransfer(FinancialTransfer $transfer, ?int $createdBy = null): JournalEntry
    {
        $transfer->loadMissing(['fromAccount', 'toAccount']);
        $amount = (int) $transfer->amount_minor;

        return $this->journals->post([
            'event_type' => 'financial_transfer',
            'event_key' => 'financial_transfer:'.$transfer->id.':accounting',
            'source_type' => FinancialTransfer::class,
            'source_id' => $transfer->id,
            'entry_date' => $transfer->transferred_at,
            'description' => 'Internal transfer '.$transfer->transfer_number,
            'created_by' => $createdBy ?? $transfer->created_by,
            'metadata' => [
                'temporary_billing_clearing_policy' => false,
                'transfer_number' => $transfer->transfer_number,
            ],
        ], [
            $this->debit($this->setup->ensureFinancialAccountMapping($transfer->toAccount)->id, $amount, 'Destination cash ledger', $transfer->to_financial_account_id),
            $this->credit($this->setup->ensureFinancialAccountMapping($transfer->fromAccount)->id, $amount, 'Source cash ledger', $transfer->from_financial_account_id),
        ]);
    }

    public function postFinancialTransferReversal(FinancialTransferReversal $reversal, ?int $createdBy = null): ?JournalEntry
    {
        $reversal->loadMissing('transfer');
        $original = JournalEntry::where('event_key', 'financial_transfer:'.$reversal->financial_transfer_id.':accounting')->first();
        if ($original === null || $reversal->transfer === null) {
            return null;
        }

        return $this->journals->reverse(
            $original,
            'financial_transfer_reversal:'.$reversal->id.':accounting',
            'financial_transfer_reversal',
            $reversal->reversed_at,
            'Internal transfer reversal '.$reversal->transfer->transfer_number,
            FinancialTransferReversal::class,
            $reversal->id,
            $createdBy ?? $reversal->reversed_by,
            ['reason' => $reversal->reason]
        );
    }

    public function postPersonalExpense(Expense $expense): JournalEntry
    {
        $expense->loadMissing('categoryModel');
        $amount = (int) $expense->amount_minor;

        return $this->journals->post([
            'event_type' => 'personally_funded_operating_expense',
            'event_key' => 'expense:'.$expense->id.':accounting',
            'source_type' => Expense::class,
            'source_id' => $expense->id,
            'entry_date' => $expense->paid_at,
            'description' => 'Personally funded operating expense',
            'created_by' => $expense->created_by,
            'metadata' => [
                'expense_id' => $expense->id,
                'funding_source' => $expense->funding_source,
                'paid_by_user_id' => $expense->paid_by_user_id,
            ],
        ], [
            $this->debit($this->setup->ensureExpenseCategoryMapping($expense->categoryModel)->id, $amount, 'Operating expense'),
            $this->credit($this->setup->systemAccount('related_party_payable')->id, $amount, 'Due to related parties', null, $expense->paid_by_user_id),
        ]);
    }

    public function postPersonalExpenseReversal(ExpenseReversal $reversal): ?JournalEntry
    {
        $reversal->loadMissing('expense');
        if ($reversal->expense === null) {
            return null;
        }

        $original = JournalEntry::where('event_key', 'expense:'.$reversal->expense_id.':accounting')->first();
        if ($original === null) {
            return null;
        }

        return $this->journals->reverse(
            $original,
            'expense_reversal:'.$reversal->id.':accounting',
            'expense_reversal',
            $reversal->reversed_at,
            'Operating expense reversal',
            ExpenseReversal::class,
            $reversal->id,
            $reversal->reversed_by,
            ['reason' => $reversal->reason, 'expense_id' => $reversal->expense_id]
        );
    }

    public function postPersonalFixedAsset(FixedAsset $asset): JournalEntry
    {
        $asset->loadMissing('category');
        $amount = (int) $asset->acquisition_cost_minor;

        return $this->journals->post([
            'event_type' => 'personally_funded_fixed_asset',
            'event_key' => 'fixed_asset:'.$asset->id.':accounting',
            'source_type' => FixedAsset::class,
            'source_id' => $asset->id,
            'entry_date' => $asset->acquired_at,
            'description' => 'Personally funded fixed asset acquisition',
            'created_by' => $asset->created_by,
            'metadata' => [
                'fixed_asset_id' => $asset->id,
                'funding_source' => $asset->funding_source,
                'paid_by_user_id' => $asset->paid_by_user_id,
            ],
        ], [
            $this->debit($this->setup->ensureAssetCategoryMapping($asset->category)->id, $amount, 'Fixed asset category'),
            $this->credit($this->setup->systemAccount('related_party_payable')->id, $amount, 'Due to related parties', null, $asset->paid_by_user_id),
        ]);
    }

    public function postPersonalFixedAssetReversal(FixedAssetAcquisitionReversal $reversal): ?JournalEntry
    {
        $reversal->loadMissing('fixedAsset');
        if ($reversal->fixedAsset === null) {
            return null;
        }

        $original = JournalEntry::where('event_key', 'fixed_asset:'.$reversal->fixed_asset_id.':accounting')->first();
        if ($original === null) {
            return null;
        }

        return $this->journals->reverse(
            $original,
            'fixed_asset_acquisition_reversal:'.$reversal->id.':accounting',
            'fixed_asset_acquisition_reversal',
            $reversal->reversed_at,
            'Fixed asset acquisition reversal',
            FixedAssetAcquisitionReversal::class,
            $reversal->id,
            $reversal->reversed_by,
            ['reason' => $reversal->reason, 'fixed_asset_id' => $reversal->fixed_asset_id]
        );
    }

    private function postCompanyExpense(CashMovement $movement): ?JournalEntry
    {
        $expense = Expense::with('categoryModel')->find($movement->source_id);
        if ($expense?->categoryModel === null) {
            return null;
        }

        return $this->postSimpleCashMovement(
            $movement,
            'company_funded_operating_expense',
            'cash:'.$movement->event_key,
            $this->setup->ensureExpenseCategoryMapping($expense->categoryModel)->id,
            $this->cashAccountId($movement),
            'Company-funded operating expense'
        );
    }

    private function postRefundIssued(CashMovement $movement): JournalEntry
    {
        $refund = Refund::find($movement->source_id);
        $debitAccount = $refund?->credit_note_id !== null
            ? $this->setup->systemAccount('customer_credits')
            : $this->setup->systemAccount('billing_clearing');

        return $this->postSimpleCashMovement(
            $movement,
            'refund_issued',
            'cash:'.$movement->event_key,
            $debitAccount->id,
            $this->cashAccountId($movement),
            $refund?->credit_note_id !== null
                ? 'Credit-note-funded refund issued'
                : 'Payment-funded refund issued from Billing Clearing'
        );
    }

    private function postCapitalFunding(CashMovement $movement): ?JournalEntry
    {
        $transaction = CapitalFundingTransaction::find($movement->source_id);
        if ($transaction === null) {
            return null;
        }

        $creditKey = match ($transaction->funding_type) {
            CapitalFundingTransaction::TYPE_LOAN_FUNDING => 'loan_payable',
            CapitalFundingTransaction::TYPE_OTHER_FUNDING => 'unclassified_funding',
            default => 'contributed_capital',
        };

        return $this->postSimpleCashMovement(
            $movement,
            'capital_funding_received',
            'cash:'.$movement->event_key,
            $this->cashAccountId($movement),
            $this->setup->systemAccount($creditKey)->id,
            'Capital funding received'
        );
    }

    private function postCompanyFixedAsset(CashMovement $movement): ?JournalEntry
    {
        $asset = FixedAsset::with('category')->find($movement->source_id);
        if ($asset?->category === null) {
            return null;
        }

        return $this->postSimpleCashMovement(
            $movement,
            'company_funded_fixed_asset',
            'cash:'.$movement->event_key,
            $this->setup->ensureAssetCategoryMapping($asset->category)->id,
            $this->cashAccountId($movement),
            'Company-funded fixed asset acquisition'
        );
    }

    private function postCashReversal(CashMovement $movement): ?JournalEntry
    {
        $metadata = $movement->metadata ?? [];
        $originalMovement = isset($metadata['original_cash_movement_id'])
            ? CashMovement::find((int) $metadata['original_cash_movement_id'])
            : null;

        if ($originalMovement === null) {
            return null;
        }

        $original = JournalEntry::where('event_key', 'cash:'.$originalMovement->event_key)->first();
        if ($original === null) {
            return null;
        }

        $sourceClass = match ($movement->event_type) {
            CashMovement::EVENT_PAYMENT_REVERSAL => \App\Models\PaymentReversal::class,
            CashMovement::EVENT_EXPENSE_REVERSAL => ExpenseReversal::class,
            CashMovement::EVENT_CAPITAL_FUNDING_REVERSAL => CapitalFundingReversal::class,
            CashMovement::EVENT_ASSET_ACQUISITION_REVERSAL => FixedAssetAcquisitionReversal::class,
            default => $movement->source_type,
        };

        return $this->journals->reverse(
            $original,
            'cash:'.$movement->event_key,
            $movement->event_type,
            $movement->occurred_at,
            $movement->description ?: 'Accounting reversal',
            $sourceClass,
            $movement->source_id,
            $movement->created_by,
            $metadata
        );
    }

    private function postSimpleCashMovement(
        CashMovement $movement,
        string $eventType,
        string $eventKey,
        int $debitAccountId,
        int $creditAccountId,
        string $description
    ): JournalEntry {
        $amount = (int) $movement->amount_minor;

        return $this->journals->post([
            'event_type' => $eventType,
            'event_key' => $eventKey,
            'source_type' => $movement->source_type,
            'source_id' => $movement->source_id,
            'entry_date' => Carbon::parse($movement->occurred_at),
            'description' => $description,
            'created_by' => $movement->created_by,
            'metadata' => ($movement->metadata ?? []) + [
                'cash_movement_id' => $movement->id,
                'billing_clearing_is_temporary_until_finance_e2' => in_array($eventType, ['payment_received', 'refund_issued'], true),
            ],
        ], [
            $this->debit($debitAccountId, $amount, $description, $movement->financial_account_id, null, $movement->metadata['client_id'] ?? null),
            $this->credit($creditAccountId, $amount, $description, $movement->financial_account_id, null, $movement->metadata['client_id'] ?? null),
        ]);
    }

    private function cashAccountId(CashMovement $movement): int
    {
        $movement->loadMissing('financialAccount');

        return $this->setup->ensureFinancialAccountMapping($movement->financialAccount)->id;
    }

    private function debit(int $accountId, int $amount, string $description, ?int $financialAccountId = null, ?int $userId = null, ?int $clientId = null): array
    {
        return [
            'chart_account_id' => $accountId,
            'debit_minor' => $amount,
            'credit_minor' => 0,
            'description' => $description,
            'financial_account_id' => $financialAccountId,
            'user_id' => $userId,
            'client_id' => $clientId,
        ];
    }

    private function credit(int $accountId, int $amount, string $description, ?int $financialAccountId = null, ?int $userId = null, ?int $clientId = null): array
    {
        return [
            'chart_account_id' => $accountId,
            'debit_minor' => 0,
            'credit_minor' => $amount,
            'description' => $description,
            'financial_account_id' => $financialAccountId,
            'user_id' => $userId,
            'client_id' => $clientId,
        ];
    }
}
