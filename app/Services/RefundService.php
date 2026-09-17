<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\FinancialAccount;
use App\Models\Payment;
use App\Models\Refund;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function __construct(
        private readonly ReceivableService $receivables,
        private readonly CashMovementService $cashMovements
    ) {
    }

    public function refundFromPayment(Payment $payment, array $data, ?int $userId): Refund
    {
        return DB::transaction(function () use ($payment, $data, $userId) {
            $payment->refresh();
            $amountMinor = $this->validatedAmountMinor($data['amount'] ?? null);
            $reason = $this->validatedReason($data['reason'] ?? null);

            if ($payment->payment_engine_version !== Payment::ENGINE_V2 || $payment->amount_minor === null) {
                throw ValidationException::withMessages(['payment_id' => 'يمكن تمويل الاسترداد من دفعات V2 فقط.']);
            }
            if ($this->receivables->paymentIsReversed($payment)) {
                throw ValidationException::withMessages(['payment_id' => 'لا يمكن تمويل استرداد من دفعة معكوسة.']);
            }
            if ($amountMinor > $this->receivables->paymentUnallocatedMinor($payment)) {
                throw ValidationException::withMessages(['amount' => 'قيمة الاسترداد أكبر من رصيد الدفعة غير المخصص.']);
            }

            return $this->createRefund([
                'client_id' => $payment->client_id,
                'payment_id' => $payment->id,
                'credit_note_id' => null,
                'amount_minor' => $amountMinor,
                'refund_method' => $data['refund_method'],
                'reference' => $data['reference'] ?? null,
                'reason' => $reason,
                'refunded_at' => Carbon::parse($data['refunded_at'] ?? now()),
                'financial_account_id' => $data['financial_account_id'] ?? null,
                'created_by' => $userId,
            ]);
        });
    }

    public function refundFromCreditNote(CreditNote $creditNote, array $data, ?int $userId): Refund
    {
        return DB::transaction(function () use ($creditNote, $data, $userId) {
            $creditNote->refresh();
            $amountMinor = $this->validatedAmountMinor($data['amount'] ?? null);
            $reason = $this->validatedReason($data['reason'] ?? null);

            if ($creditNote->status !== CreditNote::STATUS_ISSUED) {
                throw ValidationException::withMessages(['credit_note_id' => 'يمكن تمويل الاسترداد من إشعار دائن صادر وغير ملغى فقط.']);
            }
            if ($amountMinor > $this->receivables->creditNoteAvailableMinor($creditNote)) {
                throw ValidationException::withMessages(['amount' => 'قيمة الاسترداد أكبر من رصيد إشعار الدائن المتاح.']);
            }

            return $this->createRefund([
                'client_id' => $creditNote->client_id,
                'payment_id' => null,
                'credit_note_id' => $creditNote->id,
                'amount_minor' => $amountMinor,
                'refund_method' => $data['refund_method'],
                'reference' => $data['reference'] ?? null,
                'reason' => $reason,
                'refunded_at' => Carbon::parse($data['refunded_at'] ?? now()),
                'financial_account_id' => $data['financial_account_id'] ?? null,
                'created_by' => $userId,
            ]);
        });
    }

    private function createRefund(array $attributes): Refund
    {
        if (blank($attributes['refund_method'] ?? null)) {
            throw ValidationException::withMessages(['refund_method' => 'طريقة الاسترداد مطلوبة.']);
        }
        if (empty($attributes['financial_account_id'])) {
            throw ValidationException::withMessages(['financial_account_id' => 'الحساب المالي الذي خرج منه الاسترداد مطلوب.']);
        }

        $financialAccount = FinancialAccount::findOrFail((int) $attributes['financial_account_id']);
        $this->cashMovements->assertAccountReceivesOrdinaryMovement($financialAccount);

        $refund = Refund::create([
            'client_id' => $attributes['client_id'],
            'payment_id' => $attributes['payment_id'],
            'credit_note_id' => $attributes['credit_note_id'],
            'currency' => 'JOD',
            'amount_minor' => $attributes['amount_minor'],
            'refund_method' => $attributes['refund_method'],
            'reference' => $attributes['reference'],
            'reason' => $attributes['reason'],
            'refunded_at' => $attributes['refunded_at'],
            'created_by' => $attributes['created_by'],
        ]);

        $this->assignNumber($refund, $attributes['refunded_at']);
        $refund->refresh();
        $this->cashMovements->recordRefund($refund, $financialAccount, $attributes['created_by']);

        $this->log($refund->client_id, $attributes['created_by'], 'refund_issued', 'تم تسجيل استرداد مالي '.$refund->refund_number, [
            'refund_id' => $refund->id,
            'refund_number' => $refund->refund_number,
            'amount_minor' => $refund->amount_minor,
            'source_type' => $refund->payment_id !== null ? 'payment' : 'credit_note',
            'source_id' => $refund->payment_id ?? $refund->credit_note_id,
            'refund_method' => $refund->refund_method,
            'reason' => $refund->reason,
        ]);

        return $refund->fresh(['payment', 'creditNote']);
    }

    private function validatedAmountMinor(mixed $amount): int
    {
        $amountMinor = Money::fromJod($amount)->minorUnits();
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => 'قيمة الاسترداد يجب أن تكون أكبر من صفر.']);
        }

        return $amountMinor;
    }

    private function validatedReason(mixed $reason): string
    {
        $value = trim((string) $reason);
        if ($value === '') {
            throw ValidationException::withMessages(['reason' => 'سبب الاسترداد مطلوب.']);
        }

        return $value;
    }

    private function assignNumber(Refund $refund, Carbon $refundedAt): void
    {
        $base = sprintf('RF-%s-%06d', $refundedAt->format('Y'), $refund->id);
        $number = $base;
        $suffix = 1;
        while (Refund::where('refund_number', $number)->whereKeyNot($refund->id)->exists()) {
            $number = $base.'-'.$suffix;
            $suffix++;
        }

        $refund->update(['refund_number' => $number]);
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
