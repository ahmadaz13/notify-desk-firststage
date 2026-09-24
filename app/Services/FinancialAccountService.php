<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\FinancialAccount;
use App\Models\FinancialTransfer;
use App\Models\FinancialTransferReversal;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialAccountService
{
    public function __construct(
        private readonly CashMovementService $cashMovements,
        private readonly FinancialAccountBalanceService $balances
    ) {
    }

    public function createAccount(array $data, ?int $userId): FinancialAccount
    {
        return DB::transaction(function () use ($data, $userId) {
            $account = FinancialAccount::create([
                'code' => $data['code'],
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'] ?? null,
                'type' => $data['type'],
                'currency' => 'JOD',
                'is_active' => true,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $openingBalance = $data['opening_balance'] ?? '0';
            $this->cashMovements->createOpeningBalance(
                $account,
                $openingBalance,
                Carbon::parse($data['opening_date'] ?? now()),
                $userId
            );

            $this->log(null, $userId, 'financial_account_created', 'تم إنشاء حساب مالي '.$account->name_ar, [
                'financial_account_id' => $account->id,
                'code' => $account->code,
                'type' => $account->type,
                'opening_balance_minor' => Money::fromJod($openingBalance)->minorUnits(),
            ]);

            return $account->fresh(['cashMovements']);
        });
    }

    public function archiveAccount(FinancialAccount $account, ?int $userId): FinancialAccount
    {
        // Cash Box and CliQ are the mandatory V1 company accounts [FROZEN D-01] and are never archived.
        if (in_array($account->code, CompanyAccountBootstrapService::V1_CODES, true)) {
            throw ValidationException::withMessages([
                'financial_account_id' => __('notify.finance_hub.accounts.cannot_archive_v1'),
            ]);
        }

        return DB::transaction(function () use ($account, $userId) {
            $account->update([
                'is_active' => false,
                'archived_at' => now(),
            ]);

            $this->log(null, $userId, 'financial_account_archived', 'تم أرشفة حساب مالي '.$account->name_ar, [
                'financial_account_id' => $account->id,
                'code' => $account->code,
            ]);

            return $account->fresh();
        });
    }

    public function createTransfer(array $data, ?int $userId): FinancialTransfer
    {
        return DB::transaction(function () use ($data, $userId) {
            $from = FinancialAccount::findOrFail((int) $data['from_financial_account_id']);
            $to = FinancialAccount::findOrFail((int) $data['to_financial_account_id']);
            $amountMinor = Money::fromJod($data['amount'])->minorUnits();
            $transferredAt = Carbon::parse($data['transferred_at'] ?? now());

            $this->assertValidTransfer($from, $to, $amountMinor);

            $transfer = FinancialTransfer::create([
                'from_financial_account_id' => $from->id,
                'to_financial_account_id' => $to->id,
                'currency' => 'JOD',
                'amount_minor' => $amountMinor,
                'transferred_at' => $transferredAt,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);
            $this->assignTransferNumber($transfer, $transferredAt);
            $transfer->refresh();

            $this->postTransferMovements($transfer, false, $userId);
            app(AccountingEventPostingService::class)->postFinancialTransfer($transfer, $userId);

            $this->log(null, $userId, 'financial_transfer_created', 'تم إنشاء تحويل داخلي '.$transfer->transfer_number, [
                'financial_transfer_id' => $transfer->id,
                'transfer_number' => $transfer->transfer_number,
                'from_financial_account_id' => $from->id,
                'to_financial_account_id' => $to->id,
                'amount_minor' => $amountMinor,
            ]);

            return $transfer->fresh(['fromAccount', 'toAccount']);
        });
    }

    public function reverseTransfer(FinancialTransfer $transfer, string $reason, ?int $userId): FinancialTransferReversal
    {
        return DB::transaction(function () use ($transfer, $reason, $userId) {
            $reason = trim($reason);
            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => 'سبب عكس التحويل مطلوب.']);
            }
            $transfer->loadMissing(['fromAccount', 'toAccount', 'reversal']);
            if ($transfer->reversal !== null) {
                throw ValidationException::withMessages(['financial_transfer_id' => 'تم عكس هذا التحويل مسبقاً.']);
            }
            $this->cashMovements->assertAccountReceivesOrdinaryMovement($transfer->fromAccount);
            $this->cashMovements->assertAccountReceivesOrdinaryMovement($transfer->toAccount);

            $reversal = FinancialTransferReversal::create([
                'financial_transfer_id' => $transfer->id,
                'reason' => $reason,
                'reversed_at' => now(),
                'reversed_by' => $userId,
            ]);

            $this->postTransferMovements($transfer, true, $userId, $reversal->id);
            app(AccountingEventPostingService::class)->postFinancialTransferReversal($reversal, $userId);

            $this->log(null, $userId, 'financial_transfer_reversed', 'تم عكس تحويل داخلي '.$transfer->transfer_number, [
                'financial_transfer_id' => $transfer->id,
                'financial_transfer_reversal_id' => $reversal->id,
                'transfer_number' => $transfer->transfer_number,
                'amount_minor' => $transfer->amount_minor,
                'reason' => $reason,
            ]);

            return $reversal->fresh(['transfer']);
        });
    }

    private function assertValidTransfer(FinancialAccount $from, FinancialAccount $to, int $amountMinor): void
    {
        if ((int) $from->id === (int) $to->id) {
            throw ValidationException::withMessages(['to_financial_account_id' => 'لا يمكن التحويل إلى نفس الحساب.']);
        }
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => 'قيمة التحويل يجب أن تكون أكبر من صفر.']);
        }

        $this->cashMovements->assertAccountReceivesOrdinaryMovement($from);
        $this->cashMovements->assertAccountReceivesOrdinaryMovement($to);

        if ($amountMinor > $this->balances->currentBalanceMinor($from)) {
            throw ValidationException::withMessages(['amount' => 'قيمة التحويل أكبر من رصيد الحساب المصدر.']);
        }
    }

    private function postTransferMovements(FinancialTransfer $transfer, bool $isReversal, ?int $userId, ?int $reversalId = null): void
    {
        $sourceType = $isReversal ? FinancialTransferReversal::class : FinancialTransfer::class;
        $sourceId = $isReversal ? $reversalId : $transfer->id;
        $suffix = $isReversal ? 'financial_transfer_reversal:'.$reversalId : 'financial_transfer:'.$transfer->id;
        $occurredAt = $isReversal ? now() : $transfer->transferred_at;

        $this->cashMovements->createMovement([
            'financial_account_id' => $isReversal ? $transfer->from_financial_account_id : $transfer->from_financial_account_id,
            'direction' => $isReversal ? CashMovement::DIRECTION_INFLOW : CashMovement::DIRECTION_OUTFLOW,
            'amount_minor' => $transfer->amount_minor,
            'currency' => 'JOD',
            'event_type' => $isReversal ? CashMovement::EVENT_TRANSFER_IN : CashMovement::EVENT_TRANSFER_OUT,
            'event_key' => $suffix.':from',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'occurred_at' => $occurredAt,
            'description' => $isReversal ? 'Transfer reversal to source account' : 'Internal transfer out',
            'metadata' => ['financial_transfer_id' => $transfer->id, 'transfer_number' => $transfer->transfer_number],
            'created_by' => $userId,
        ]);

        $this->cashMovements->createMovement([
            'financial_account_id' => $transfer->to_financial_account_id,
            'direction' => $isReversal ? CashMovement::DIRECTION_OUTFLOW : CashMovement::DIRECTION_INFLOW,
            'amount_minor' => $transfer->amount_minor,
            'currency' => 'JOD',
            'event_type' => $isReversal ? CashMovement::EVENT_TRANSFER_OUT : CashMovement::EVENT_TRANSFER_IN,
            'event_key' => $suffix.':to',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'occurred_at' => $occurredAt,
            'description' => $isReversal ? 'Transfer reversal from destination account' : 'Internal transfer in',
            'metadata' => ['financial_transfer_id' => $transfer->id, 'transfer_number' => $transfer->transfer_number],
            'created_by' => $userId,
        ]);
    }

    private function assignTransferNumber(FinancialTransfer $transfer, Carbon $transferredAt): void
    {
        $base = sprintf('TR-%s-%06d', $transferredAt->format('Y'), $transfer->id);
        $number = $base;
        $suffix = 1;
        while (FinancialTransfer::where('transfer_number', $number)->whereKeyNot($transfer->id)->exists()) {
            $number = $base.'-'.$suffix;
            $suffix++;
        }

        $transfer->update(['transfer_number' => $number]);
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
