<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentAllocationReversal;
use App\Models\PaymentReversal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CollectionCorrectionService
{
    public function __construct(
        private readonly ReceivableService $receivables,
        private readonly CashMovementService $cashMovements
    ) {
    }

    public function reverseAllocation(PaymentAllocation $allocation, string $reason, ?int $userId): PaymentAllocationReversal
    {
        return DB::transaction(function () use ($allocation, $reason, $userId) {
            $reason = trim($reason);
            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => 'سبب عكس التخصيص مطلوب.']);
            }

            $allocation->loadMissing(['invoice', 'payment']);

            if ($this->receivables->allocationIsReversed($allocation)) {
                throw ValidationException::withMessages(['payment_allocation_id' => 'تم عكس هذا التخصيص مسبقاً.']);
            }

            $reversal = PaymentAllocationReversal::create([
                'payment_allocation_id' => $allocation->id,
                'reason' => $reason,
                'reversed_at' => now(),
                'reversed_by' => $userId,
            ]);
            app(BillingAccountingService::class)->postPaymentAllocationReversal($reversal);

            $this->log($allocation->client_id, $userId, 'allocation_reversed', 'تم عكس تخصيص دفعة على الفاتورة '.$allocation->invoice->invoice_number, [
                'payment_allocation_id' => $allocation->id,
                'payment_id' => $allocation->payment_id,
                'invoice_id' => $allocation->invoice_id,
                'invoice_number' => $allocation->invoice->invoice_number,
                'amount_minor' => $allocation->amount_minor,
                'reason' => $reason,
            ]);

            return $reversal->fresh(['allocation']);
        });
    }

    public function reversePayment(Payment $payment, string $reason, ?int $userId): PaymentReversal
    {
        return DB::transaction(function () use ($payment, $reason, $userId) {
            $reason = trim($reason);
            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => 'سبب عكس الدفعة مطلوب.']);
            }

            $payment->refresh();

            if ($payment->payment_engine_version !== Payment::ENGINE_V2 || $payment->amount_minor === null) {
                throw ValidationException::withMessages(['payment_id' => 'يمكن عكس دفعات V2 فقط من هذا المسار.']);
            }

            if ($this->receivables->paymentIsReversed($payment)) {
                throw ValidationException::withMessages(['payment_id' => 'تم عكس هذه الدفعة مسبقاً.']);
            }

            if ($this->receivables->paymentAllocatedMinor($payment) > 0) {
                throw ValidationException::withMessages(['payment_id' => 'يجب عكس كل التخصيصات النشطة أولاً قبل عكس الدفعة.']);
            }

            $reversal = PaymentReversal::create([
                'payment_id' => $payment->id,
                'reason' => $reason,
                'reversed_at' => now(),
                'reversed_by' => $userId,
            ]);

            $this->cashMovements->recordPaymentReversal($reversal, $userId);

            $this->log($payment->client_id, $userId, 'payment_reversed', 'تم عكس دفعة V2 مسجلة بالخطأ', [
                'payment_id' => $payment->id,
                'amount_minor' => $payment->amount_minor,
                'payment_method' => $payment->payment_method,
                'reason' => $reason,
            ]);

            return $reversal->fresh(['payment']);
        });
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
