<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CapitalFundingReversal;
use App\Models\CapitalFundingTransaction;
use App\Models\Expense;
use App\Models\ExpenseReversal;
use App\Models\FinancialAccount;
use App\Models\FixedAsset;
use App\Models\FixedAssetAcquisitionReversal;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\Refund;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashMovementService
{
    public function createOpeningBalance(FinancialAccount $account, string|int $amountJod, Carbon $occurredAt, ?int $userId): ?CashMovement
    {
        $amountMinor = Money::fromJod($amountJod)->minorUnits();
        if ($amountMinor < 0) {
            throw ValidationException::withMessages(['opening_balance' => 'الرصيد الافتتاحي لا يمكن أن يكون سالباً.']);
        }
        if ($amountMinor === 0) {
            return null;
        }

        return $this->createMovement([
            'financial_account_id' => $account->id,
            'direction' => CashMovement::DIRECTION_INFLOW,
            'amount_minor' => $amountMinor,
            'currency' => 'JOD',
            'event_type' => CashMovement::EVENT_OPENING_BALANCE,
            'event_key' => 'financial_account:'.$account->id.':opening_balance',
            'source_type' => FinancialAccount::class,
            'source_id' => $account->id,
            'occurred_at' => $occurredAt,
            'description' => 'Opening balance for '.$account->name_ar,
            'metadata' => ['financial_account_id' => $account->id],
            'created_by' => $userId,
        ]);
    }

    public function recordPaymentReceipt(Payment $payment, FinancialAccount $account, ?int $userId): CashMovement
    {
        $payment->refresh();
        $this->assertAccountReceivesOrdinaryMovement($account);

        if ($payment->payment_engine_version !== Payment::ENGINE_V2 || $payment->amount_minor === null) {
            throw ValidationException::withMessages(['payment_id' => 'يمكن إنشاء حركة نقدية لدفعات V2 فقط.']);
        }

        return $this->createMovement([
            'financial_account_id' => $account->id,
            'direction' => CashMovement::DIRECTION_INFLOW,
            'amount_minor' => (int) $payment->amount_minor,
            'currency' => 'JOD',
            'event_type' => CashMovement::EVENT_PAYMENT_RECEIVED,
            'event_key' => $this->paymentReceiptEventKey($payment),
            'source_type' => Payment::class,
            'source_id' => $payment->id,
            'occurred_at' => $payment->received_at ?? $payment->paid_at ?? now(),
            'description' => 'V2 payment received',
            'metadata' => [
                'payment_id' => $payment->id,
                'client_id' => $payment->client_id,
                'payment_method' => $payment->payment_method,
                'reference' => $payment->reference,
            ],
            'created_by' => $userId,
        ]);
    }

    public function recordPaymentReversal(PaymentReversal $reversal, ?int $userId): ?CashMovement
    {
        $reversal->loadMissing('payment');
        $payment = $reversal->payment;
        if ($payment === null) {
            return null;
        }

        $receiptMovement = CashMovement::where('event_key', $this->paymentReceiptEventKey($payment))->first();
        if ($receiptMovement === null) {
            return null;
        }

        return $this->createMovement([
            'financial_account_id' => $receiptMovement->financial_account_id,
            'direction' => CashMovement::DIRECTION_OUTFLOW,
            'amount_minor' => (int) $receiptMovement->amount_minor,
            'currency' => 'JOD',
            'event_type' => CashMovement::EVENT_PAYMENT_REVERSAL,
            'event_key' => 'payment_reversal:'.$reversal->id,
            'source_type' => PaymentReversal::class,
            'source_id' => $reversal->id,
            'occurred_at' => $reversal->reversed_at ?? now(),
            'description' => 'Payment reversal cash correction',
            'metadata' => [
                'payment_id' => $payment->id,
                'payment_reversal_id' => $reversal->id,
                'original_cash_movement_id' => $receiptMovement->id,
                'reason' => $reversal->reason,
            ],
            'created_by' => $userId,
        ]);
    }

    public function recordRefund(Refund $refund, FinancialAccount $account, ?int $userId): CashMovement
    {
        $refund->refresh();
        $this->assertAccountReceivesOrdinaryMovement($account);

        return $this->createMovement([
            'financial_account_id' => $account->id,
            'direction' => CashMovement::DIRECTION_OUTFLOW,
            'amount_minor' => (int) $refund->amount_minor,
            'currency' => 'JOD',
            'event_type' => CashMovement::EVENT_REFUND_ISSUED,
            'event_key' => $this->refundEventKey($refund),
            'source_type' => Refund::class,
            'source_id' => $refund->id,
            'occurred_at' => $refund->refunded_at ?? now(),
            'description' => 'Customer refund issued',
            'metadata' => [
                'refund_id' => $refund->id,
                'refund_number' => $refund->refund_number,
                'client_id' => $refund->client_id,
                'refund_method' => $refund->refund_method,
                'payment_id' => $refund->payment_id,
                'credit_note_id' => $refund->credit_note_id,
            ],
            'created_by' => $userId,
        ]);
    }

    public function recordExpensePaid(Expense $expense, FinancialAccount $account, ?int $userId): CashMovement
    {
        $expense->refresh();
        $this->assertAccountReceivesOrdinaryMovement($account);

        if ($expense->expense_engine_version !== Expense::ENGINE_V2 || $expense->amount_minor === null) {
            throw ValidationException::withMessages(['expense_id' => 'يمكن إنشاء حركة نقدية لمصروفات V2 فقط.']);
        }

        return $this->createMovement([
            'financial_account_id' => $account->id,
            'direction' => CashMovement::DIRECTION_OUTFLOW,
            'amount_minor' => (int) $expense->amount_minor,
            'currency' => 'JOD',
            'event_type' => CashMovement::EVENT_EXPENSE_PAID,
            'event_key' => $this->expensePaidEventKey($expense),
            'source_type' => Expense::class,
            'source_id' => $expense->id,
            'occurred_at' => $expense->paid_at ?? now(),
            'description' => 'V2 operating expense paid',
            'metadata' => [
                'expense_id' => $expense->id,
                'category' => $expense->category_name_snapshot,
                'payee' => $expense->payee_name_snapshot,
                'funding_source' => $expense->funding_source,
                'reference' => $expense->reference,
            ],
            'created_by' => $userId,
        ]);
    }

    public function recordExpenseReversal(ExpenseReversal $reversal, ?int $userId): ?CashMovement
    {
        $reversal->loadMissing('expense');
        $expense = $reversal->expense;
        if ($expense === null) {
            return null;
        }

        $paidMovement = CashMovement::where('event_key', $this->expensePaidEventKey($expense))->first();
        if ($paidMovement === null) {
            return null;
        }

        return $this->createMovement([
            'financial_account_id' => $paidMovement->financial_account_id,
            'direction' => CashMovement::DIRECTION_INFLOW,
            'amount_minor' => (int) $paidMovement->amount_minor,
            'currency' => 'JOD',
            'event_type' => CashMovement::EVENT_EXPENSE_REVERSAL,
            'event_key' => 'expense_reversal:'.$reversal->id,
            'source_type' => ExpenseReversal::class,
            'source_id' => $reversal->id,
            'occurred_at' => $reversal->reversed_at ?? now(),
            'description' => 'Operating expense reversal cash correction',
            'metadata' => [
                'expense_id' => $expense->id,
                'expense_reversal_id' => $reversal->id,
                'original_cash_movement_id' => $paidMovement->id,
                'reason' => $reversal->reason,
            ],
            'created_by' => $userId,
        ]);
    }

    public function recordCapitalFundingReceived(CapitalFundingTransaction $transaction, FinancialAccount $account, ?int $userId): CashMovement
    {
        $transaction->refresh();
        $this->assertAccountReceivesOrdinaryMovement($account);

        return $this->createMovement([
            'financial_account_id' => $account->id,
            'direction' => CashMovement::DIRECTION_INFLOW,
            'amount_minor' => (int) $transaction->amount_minor,
            'currency' => 'JOD',
            'event_type' => CashMovement::EVENT_CAPITAL_FUNDING_RECEIVED,
            'event_key' => $this->capitalFundingEventKey($transaction),
            'source_type' => CapitalFundingTransaction::class,
            'source_id' => $transaction->id,
            'occurred_at' => $transaction->received_at ?? now(),
            'description' => 'Capital funding received',
            'metadata' => [
                'capital_funding_transaction_id' => $transaction->id,
                'funding_number' => $transaction->funding_number,
                'source_name' => $transaction->source_name_snapshot,
                'funding_type' => $transaction->funding_type,
                'reference' => $transaction->reference,
            ],
            'created_by' => $userId,
        ]);
    }

    public function recordCapitalFundingReversal(CapitalFundingReversal $reversal, ?int $userId): ?CashMovement
    {
        $reversal->loadMissing('transaction');
        $transaction = $reversal->transaction;
        if ($transaction === null) {
            return null;
        }

        $fundingMovement = CashMovement::where('event_key', $this->capitalFundingEventKey($transaction))->first();
        if ($fundingMovement === null) {
            return null;
        }

        return $this->createMovement([
            'financial_account_id' => $fundingMovement->financial_account_id,
            'direction' => CashMovement::DIRECTION_OUTFLOW,
            'amount_minor' => (int) $fundingMovement->amount_minor,
            'currency' => 'JOD',
            'event_type' => CashMovement::EVENT_CAPITAL_FUNDING_REVERSAL,
            'event_key' => 'capital_funding_reversal:'.$reversal->id,
            'source_type' => CapitalFundingReversal::class,
            'source_id' => $reversal->id,
            'occurred_at' => $reversal->reversed_at ?? now(),
            'description' => 'Capital funding reversal cash correction',
            'metadata' => [
                'capital_funding_transaction_id' => $transaction->id,
                'capital_funding_reversal_id' => $reversal->id,
                'original_cash_movement_id' => $fundingMovement->id,
                'reason' => $reversal->reason,
            ],
            'created_by' => $userId,
        ]);
    }

    public function recordAssetAcquisition(FixedAsset $asset, FinancialAccount $account, ?int $userId): CashMovement
    {
        $asset->refresh();
        $this->assertAccountReceivesOrdinaryMovement($account);

        return $this->createMovement([
            'financial_account_id' => $account->id,
            'direction' => CashMovement::DIRECTION_OUTFLOW,
            'amount_minor' => (int) $asset->acquisition_cost_minor,
            'currency' => 'JOD',
            'event_type' => CashMovement::EVENT_ASSET_ACQUISITION,
            'event_key' => $this->assetAcquisitionEventKey($asset),
            'source_type' => FixedAsset::class,
            'source_id' => $asset->id,
            'occurred_at' => $asset->acquired_at?->startOfDay() ?? now(),
            'description' => 'Fixed asset acquisition',
            'metadata' => [
                'fixed_asset_id' => $asset->id,
                'asset_number' => $asset->asset_number,
                'category' => $asset->category_name_snapshot,
                'payee' => $asset->payee_name_snapshot,
                'funding_source' => $asset->funding_source,
                'reference' => $asset->reference,
            ],
            'created_by' => $userId,
        ]);
    }

    public function recordAssetAcquisitionReversal(FixedAssetAcquisitionReversal $reversal, ?int $userId): ?CashMovement
    {
        $reversal->loadMissing('fixedAsset');
        $asset = $reversal->fixedAsset;
        if ($asset === null) {
            return null;
        }

        $acquisitionMovement = CashMovement::where('event_key', $this->assetAcquisitionEventKey($asset))->first();
        if ($acquisitionMovement === null) {
            return null;
        }

        return $this->createMovement([
            'financial_account_id' => $acquisitionMovement->financial_account_id,
            'direction' => CashMovement::DIRECTION_INFLOW,
            'amount_minor' => (int) $acquisitionMovement->amount_minor,
            'currency' => 'JOD',
            'event_type' => CashMovement::EVENT_ASSET_ACQUISITION_REVERSAL,
            'event_key' => 'asset_acquisition_reversal:'.$reversal->id,
            'source_type' => FixedAssetAcquisitionReversal::class,
            'source_id' => $reversal->id,
            'occurred_at' => $reversal->reversed_at ?? now(),
            'description' => 'Fixed asset acquisition reversal cash correction',
            'metadata' => [
                'fixed_asset_id' => $asset->id,
                'fixed_asset_acquisition_reversal_id' => $reversal->id,
                'original_cash_movement_id' => $acquisitionMovement->id,
                'reason' => $reversal->reason,
            ],
            'created_by' => $userId,
        ]);
    }

    public function assignHistoricalPayment(Payment $payment, FinancialAccount $account, ?int $userId): CashMovement
    {
        if (CashMovement::where('event_key', $this->paymentReceiptEventKey($payment))->exists()) {
            throw ValidationException::withMessages(['payment_id' => 'تم تعيين الحساب المالي لهذه الدفعة مسبقاً.']);
        }

        $movement = $this->recordPaymentReceipt($payment, $account, $userId);
        $this->log($payment->client_id, $userId, 'cash_event_account_assigned', 'تم تعيين حساب مالي لدفعة تاريخية', [
            'event_type' => 'payment',
            'payment_id' => $payment->id,
            'financial_account_id' => $account->id,
            'cash_movement_id' => $movement->id,
        ]);

        return $movement;
    }

    public function assignHistoricalRefund(Refund $refund, FinancialAccount $account, ?int $userId): CashMovement
    {
        if (CashMovement::where('event_key', $this->refundEventKey($refund))->exists()) {
            throw ValidationException::withMessages(['refund_id' => 'تم تعيين الحساب المالي لهذا الاسترداد مسبقاً.']);
        }

        $movement = $this->recordRefund($refund, $account, $userId);
        $this->log($refund->client_id, $userId, 'cash_event_account_assigned', 'تم تعيين حساب مالي لاسترداد تاريخي', [
            'event_type' => 'refund',
            'refund_id' => $refund->id,
            'financial_account_id' => $account->id,
            'cash_movement_id' => $movement->id,
        ]);

        return $movement;
    }

    public function createMovement(array $attributes): CashMovement
    {
        if ((int) ($attributes['amount_minor'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['amount' => 'قيمة الحركة النقدية يجب أن تكون أكبر من صفر.']);
        }
        if (! in_array($attributes['direction'] ?? '', [CashMovement::DIRECTION_INFLOW, CashMovement::DIRECTION_OUTFLOW], true)) {
            throw ValidationException::withMessages(['direction' => 'اتجاه الحركة النقدية غير صالح.']);
        }

        $existing = CashMovement::where('event_key', $attributes['event_key'])->first();
        if ($existing !== null) {
            return $existing;
        }

        $movement = CashMovement::create($attributes);
        app(AccountingEventPostingService::class)->postCashMovement($movement);

        return $movement;
    }

    public function assertAccountReceivesOrdinaryMovement(FinancialAccount $account): void
    {
        if (! $account->is_active || $account->archived_at !== null) {
            throw ValidationException::withMessages(['financial_account_id' => 'الحساب المالي غير نشط ولا يستقبل حركات جديدة.']);
        }
        if ($account->currency !== 'JOD') {
            throw ValidationException::withMessages(['financial_account_id' => 'حسابات D1 يجب أن تكون بعملة JOD.']);
        }
    }

    public function paymentReceiptEventKey(Payment $payment): string
    {
        return 'payment:'.$payment->id.':received';
    }

    public function refundEventKey(Refund $refund): string
    {
        return 'refund:'.$refund->id.':issued';
    }

    public function expensePaidEventKey(Expense $expense): string
    {
        return 'expense:'.$expense->id.':paid';
    }

    public function capitalFundingEventKey(CapitalFundingTransaction $transaction): string
    {
        return 'capital_funding:'.$transaction->id.':received';
    }

    public function assetAcquisitionEventKey(FixedAsset $asset): string
    {
        return 'fixed_asset:'.$asset->id.':acquired';
    }

    private function log(?int $clientId, ?int $userId, string $type, string $description, array $metadata): void
    {
        DB::table('activity_logs')->insert([
            'client_id' => $clientId,
            'user_id' => $userId,
            'type' => $type,
            'description' => $description,
            'metadata' => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
