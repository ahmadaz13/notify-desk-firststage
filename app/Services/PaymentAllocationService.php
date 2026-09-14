<?php

namespace App\Services;

use App\Models\Client;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentAllocationService
{
    public function __construct(
        private readonly ReceivableService $receivables,
        private readonly CashMovementService $cashMovements
    ) {
    }

    public function recordV2Payment(Client $client, array $data, array $allocations, bool $autoAllocateOldest, ?int $userId): Payment
    {
        return DB::transaction(function () use ($client, $data, $allocations, $autoAllocateOldest, $userId) {
            $money = Money::fromJod($data['amount']);
            if ($money->minorUnits() <= 0) {
                throw ValidationException::withMessages(['amount' => 'قيمة الدفعة يجب أن تكون أكبر من صفر.']);
            }
            if (empty($data['financial_account_id'])) {
                throw ValidationException::withMessages(['financial_account_id' => 'الحساب المالي المستلم مطلوب لدفعات V2.']);
            }

            $financialAccount = FinancialAccount::findOrFail((int) $data['financial_account_id']);
            $this->cashMovements->assertAccountReceivesOrdinaryMovement($financialAccount);

            $receivedAt = Carbon::parse($data['received_at']);
            $payment = Payment::create([
                'client_id' => $client->id,
                'subscription_id' => null,
                'payment_schedule_id' => null,
                'amount' => $money->format(),
                'amount_minor' => $money->minorUnits(),
                'currency' => 'JOD',
                'payment_engine_version' => Payment::ENGINE_V2,
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'paid_at' => $receivedAt,
                'received_at' => $receivedAt,
                'recorded_by' => $userId,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->cashMovements->recordPaymentReceipt($payment, $financialAccount, $userId);

            $this->log($client->id, $userId, 'payment_received_v2', 'تم تسجيل دفعة V2 بقيمة '.$money->format().' د.أ', [
                'payment_id' => $payment->id,
                'amount_minor' => $money->minorUnits(),
                'payment_method' => $payment->payment_method,
                'reference' => $payment->reference,
            ]);

            foreach ($allocations as $allocation) {
                $invoice = Invoice::findOrFail((int) $allocation['invoice_id']);
                $this->allocateWithoutTransaction($payment, $invoice, Money::fromJod($allocation['amount'])->minorUnits(), $userId);
            }

            if ($autoAllocateOldest) {
                $this->autoAllocateWithoutTransaction($payment, $userId);
            }

            return $payment->fresh(['allocations']);
        });
    }

    public function allocate(Payment $payment, Invoice $invoice, string $amountJod, ?int $userId): PaymentAllocation
    {
        return DB::transaction(function () use ($payment, $invoice, $amountJod, $userId) {
            return $this->allocateWithoutTransaction($payment, $invoice, Money::fromJod($amountJod)->minorUnits(), $userId);
        });
    }

    public function autoAllocateOldest(Payment $payment, ?int $userId): Payment
    {
        return DB::transaction(function () use ($payment, $userId) {
            $this->autoAllocateWithoutTransaction($payment, $userId);

            return $payment->fresh(['allocations']);
        });
    }

    private function allocateWithoutTransaction(Payment $payment, Invoice $invoice, int $amountMinor, ?int $userId): PaymentAllocation
    {
        $payment->refresh();
        $invoice->refresh();

        if ($payment->payment_engine_version !== Payment::ENGINE_V2 || $payment->amount_minor === null) {
            throw ValidationException::withMessages(['payment_id' => 'يمكن تخصيص دفعات V2 فقط.']);
        }

        if ($this->receivables->paymentIsReversed($payment)) {
            throw ValidationException::withMessages(['payment_id' => 'لا يمكن تخصيص دفعة تم عكسها.']);
        }

        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => 'قيمة التخصيص يجب أن تكون أكبر من صفر.']);
        }

        if ((int) $payment->client_id !== (int) $invoice->client_id) {
            throw ValidationException::withMessages(['invoice_id' => 'لا يمكن تخصيص دفعة عميل على فاتورة عميل آخر.']);
        }

        if ($invoice->status !== Invoice::STATUS_ISSUED) {
            throw ValidationException::withMessages(['invoice_id' => 'يمكن تخصيص الدفعات على الفواتير الصادرة فقط.']);
        }

        $outstandingMinor = $this->receivables->invoiceOutstandingMinor($invoice);
        if ($amountMinor > $outstandingMinor) {
            throw ValidationException::withMessages(['amount' => 'قيمة التخصيص أكبر من الرصيد المستحق على الفاتورة.']);
        }

        $unallocatedMinor = $this->receivables->paymentUnallocatedMinor($payment);
        if ($amountMinor > $unallocatedMinor) {
            throw ValidationException::withMessages(['amount' => 'قيمة التخصيص أكبر من الرصيد غير المخصص للدفعة.']);
        }

        $allocation = PaymentAllocation::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'amount_minor' => $amountMinor,
            'allocated_at' => now(),
            'created_by' => $userId,
        ]);

        $this->log($invoice->client_id, $userId, 'payment_allocated', 'تم تخصيص دفعة على الفاتورة '.$invoice->invoice_number, [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'amount_minor' => $amountMinor,
        ]);

        return $allocation;
    }

    private function autoAllocateWithoutTransaction(Payment $payment, ?int $userId): void
    {
        $payment->refresh();

        $invoices = Invoice::where('client_id', $payment->client_id)
            ->where('status', Invoice::STATUS_ISSUED)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        foreach ($invoices as $invoice) {
            $unallocatedMinor = $this->receivables->paymentUnallocatedMinor($payment);
            if ($unallocatedMinor <= 0) {
                return;
            }

            $outstandingMinor = $this->receivables->invoiceOutstandingMinor($invoice);
            if ($outstandingMinor <= 0) {
                continue;
            }

            $this->allocateWithoutTransaction($payment, $invoice, min($unallocatedMinor, $outstandingMinor), $userId);
        }
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
