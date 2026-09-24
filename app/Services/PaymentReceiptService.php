<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Payment;
use App\Models\PaymentReceiptConfirmation;
use App\Models\User;
use App\Support\Money;
use App\Support\PaymentMethods;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Staff → Owner payment receipt confirmation workflow [FROZEN D-15, §9.2–9.3].
 *
 * Submission, rejection and cancellation are pre-financial and create no financial record.
 * Approval delegates to the authoritative PaymentAllocationService::recordV2Payment pipeline.
 */
class PaymentReceiptService
{
    public const NOTIFICATION_SOURCE = 'payment_receipt';

    public function __construct(
        private readonly PaymentAllocationService $payments,
        private readonly PaymentFinancialAccountResolver $accountResolver,
        private readonly NotificationService $notifications
    ) {
    }

    /**
     * @param  array{amount: string, payment_method: string, received_at?: ?string, reference?: ?string, note?: ?string}  $data
     */
    public function submit(Client $client, array $data, User $submitter, ?string $idempotencyKey = null): PaymentReceiptConfirmation
    {
        $idempotencyKey = filled($idempotencyKey) ? substr(trim($idempotencyKey), 0, 64) : 'rcpt_'.Str::uuid();

        $existing = $this->findReplay($idempotencyKey, $submitter);
        if ($existing !== null) {
            return $existing;
        }

        $amountMinor = $this->validatedAmountMinor($data['amount'] ?? null);
        $method = $this->validatedMethod($data['payment_method'] ?? null);
        $receivedAt = $this->validatedStaffReceivedAt($data['received_at'] ?? null);

        try {
            return DB::transaction(function () use ($client, $data, $submitter, $idempotencyKey, $amountMinor, $method, $receivedAt) {
                $receipt = PaymentReceiptConfirmation::create([
                    'client_id' => $client->id,
                    'amount_minor' => $amountMinor,
                    'currency' => 'JOD',
                    'payment_method' => $method,
                    'received_at' => $receivedAt,
                    'reference' => $data['reference'] ?? null,
                    'note' => $data['note'] ?? null,
                    'status' => PaymentReceiptConfirmation::STATUS_PENDING,
                    'submitted_by' => $submitter->id,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $this->notifyOwnersOfSubmission($receipt, $client, $submitter);

                return $receipt;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent submission with the same key won the race; resolve to its receipt.
            return $this->findReplay($idempotencyKey, $submitter) ?? throw $exception;
        }
    }

    /**
     * Authoritative approval. A second approval of the same receipt is a no-op returning the same Payment.
     */
    public function approve(PaymentReceiptConfirmation $receipt, User $approver): Payment
    {
        return DB::transaction(function () use ($receipt, $approver) {
            $locked = PaymentReceiptConfirmation::query()->lockForUpdate()->findOrFail($receipt->id);

            if ($locked->status === PaymentReceiptConfirmation::STATUS_APPROVED && $locked->payment_id !== null) {
                return Payment::findOrFail($locked->payment_id);
            }

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'receipt' => __('notify.payment_receipts.errors.not_pending'),
                ]);
            }

            $client = Client::findOrFail($locked->client_id);
            $account = $this->accountResolver->resolve($locked->payment_method);

            $payment = $this->payments->recordV2Payment(
                $client,
                [
                    'amount' => Money::fromMinorUnits((int) $locked->amount_minor)->format(),
                    'financial_account_id' => $account->id,
                    'payment_method' => $locked->payment_method,
                    'received_at' => $locked->received_at->format('Y-m-d H:i:s'),
                    'reference' => $locked->reference,
                    'notes' => $locked->note,
                ],
                [],
                true,
                $approver->id
            );

            $locked->forceFill([
                'status' => PaymentReceiptConfirmation::STATUS_APPROVED,
                'payment_id' => $payment->id,
                'reviewed_by' => $approver->id,
                'reviewed_at' => now(),
            ])->save();

            $amount = $locked->amountFormatted();
            $this->logActivity($locked, $approver, 'payment_receipt_approved', __('notify.payment_receipts.activity.approved', [
                'amount' => $amount,
                'method' => $locked->methodLabel(),
            ]), ['payment_id' => $payment->id]);

            $this->notifications->createNotification(
                (int) $locked->submitted_by,
                'payment_receipt_approved',
                __('notify.payment_receipts.notifications.approved_title', ['client' => $client->business_name]),
                __('notify.payment_receipts.notifications.approved_message', ['amount' => $amount, 'client' => $client->business_name]),
                route('clients.show', $client->id, false),
                self::NOTIFICATION_SOURCE,
                $locked->id,
                'approved'
            );

            return $payment;
        });
    }

    public function reject(PaymentReceiptConfirmation $receipt, User $reviewer, string $reason): PaymentReceiptConfirmation
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => __('notify.payment_receipts.errors.reason_required'),
            ]);
        }

        return DB::transaction(function () use ($receipt, $reviewer, $reason) {
            $locked = PaymentReceiptConfirmation::query()->lockForUpdate()->findOrFail($receipt->id);

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'receipt' => __('notify.payment_receipts.errors.not_pending'),
                ]);
            }

            $locked->forceFill([
                'status' => PaymentReceiptConfirmation::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            $client = Client::findOrFail($locked->client_id);
            $amount = $locked->amountFormatted();
            $this->logActivity($locked, $reviewer, 'payment_receipt_rejected', __('notify.payment_receipts.activity.rejected', [
                'amount' => $amount,
                'reason' => $reason,
            ]), ['rejection_reason' => $reason]);

            $this->notifications->createNotification(
                (int) $locked->submitted_by,
                'payment_receipt_rejected',
                __('notify.payment_receipts.notifications.rejected_title', ['client' => $client->business_name]),
                __('notify.payment_receipts.notifications.rejected_message', [
                    'amount' => $amount,
                    'client' => $client->business_name,
                    'reason' => $reason,
                ]),
                route('clients.show', $client->id, false),
                self::NOTIFICATION_SOURCE,
                $locked->id,
                'rejected'
            );

            return $locked;
        });
    }

    /**
     * The submitter withdraws their own pending receipt. Zero financial effect.
     */
    public function cancel(PaymentReceiptConfirmation $receipt, User $user): PaymentReceiptConfirmation
    {
        return DB::transaction(function () use ($receipt, $user) {
            $locked = PaymentReceiptConfirmation::query()->lockForUpdate()->findOrFail($receipt->id);

            if ((int) $locked->submitted_by !== (int) $user->id) {
                throw new AuthorizationException(__('notify.payment_receipts.errors.not_owner'));
            }

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'receipt' => __('notify.payment_receipts.errors.not_pending'),
                ]);
            }

            $locked->forceFill(['status' => PaymentReceiptConfirmation::STATUS_CANCELLED])->save();

            return $locked;
        });
    }

    private function findReplay(string $idempotencyKey, User $submitter): ?PaymentReceiptConfirmation
    {
        $existing = PaymentReceiptConfirmation::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null && (int) $existing->submitted_by !== (int) $submitter->id) {
            throw ValidationException::withMessages([
                'receipt' => __('notify.payment_receipts.errors.idempotency_conflict'),
            ]);
        }

        return $existing;
    }

    private function validatedAmountMinor(mixed $amount): int
    {
        try {
            $minor = Money::fromJod($amount)->minorUnits();
        } catch (\Throwable) {
            $minor = 0;
        }

        if ($minor <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('notify.payment_receipts.errors.amount_positive'),
            ]);
        }

        return $minor;
    }

    private function validatedMethod(mixed $method): string
    {
        if (! is_string($method) || ! in_array($method, PaymentMethods::v1(), true)) {
            throw ValidationException::withMessages([
                'payment_method' => __('notify.payment_receipts.errors.unsupported_method'),
            ]);
        }

        return $method;
    }

    private function validatedStaffReceivedAt(mixed $receivedAt): Carbon
    {
        $now = now();
        $at = filled($receivedAt) ? Carbon::parse($receivedAt) : $now->copy();

        if ($at->gt($now)) {
            throw ValidationException::withMessages([
                'received_at' => __('notify.payment_receipts.errors.future_date'),
            ]);
        }

        if ($at->lt($now->copy()->subDays(PaymentReceiptConfirmation::STAFF_MAX_BACKDATE_DAYS))) {
            throw ValidationException::withMessages([
                'received_at' => __('notify.payment_receipts.errors.backdate_limit', [
                    'days' => PaymentReceiptConfirmation::STAFF_MAX_BACKDATE_DAYS,
                ]),
            ]);
        }

        return $at;
    }

    private function notifyOwnersOfSubmission(PaymentReceiptConfirmation $receipt, Client $client, User $submitter): void
    {
        $ownerIds = User::query()
            ->where('is_active', true)
            ->whereIn('role', User::ownerLevelRoles())
            ->where('id', '!=', $submitter->id)
            ->orderBy('id')
            ->pluck('id');

        foreach ($ownerIds as $ownerId) {
            $this->notifications->createNotification(
                (int) $ownerId,
                'payment_receipt_submitted',
                __('notify.payment_receipts.notifications.submitted_title', ['client' => $client->business_name]),
                __('notify.payment_receipts.notifications.submitted_message', [
                    'submitter' => $submitter->name,
                    'amount' => $receipt->amountFormatted(),
                    'method' => $receipt->methodLabel(),
                    'client' => $client->business_name,
                ]),
                route('finance.collections', ['tab' => 'pending'], false),
                self::NOTIFICATION_SOURCE,
                $receipt->id,
                'submitted'
            );
        }
    }

    private function logActivity(PaymentReceiptConfirmation $receipt, User $actor, string $type, string $description, array $metadata = []): void
    {
        DB::table('activity_logs')->insert([
            'client_id' => $receipt->client_id,
            'user_id' => $actor->id,
            'type' => $type,
            'description' => $description,
            'metadata' => json_encode($metadata + [
                'payment_receipt_confirmation_id' => $receipt->id,
                'amount_minor' => (int) $receipt->amount_minor,
                'payment_method' => $receipt->payment_method,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
