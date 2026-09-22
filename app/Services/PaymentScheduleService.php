<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentSchedule;
use App\Models\Subscription;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PaymentScheduleService
{
    public function __construct(
        private readonly ReceivableService $receivables
    ) {}

    /**
     * Build exact collection dates for one annual obligation without changing its term.
     */
    public function previewAnnualInstallments(
        int $totalMinor,
        int $installmentsCount,
        string|Carbon $startDate,
        int $dueDay
    ): array {
        if ($totalMinor <= 0) {
            throw new InvalidArgumentException('Annual installment total must be positive.');
        }
        if ($installmentsCount < 2 || $installmentsCount > 12) {
            throw new InvalidArgumentException('Annual installment count must be between 2 and 12.');
        }
        if (! in_array($dueDay, [1, 5, 15, 30], true)) {
            throw new InvalidArgumentException('Installment due day is invalid.');
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $baseMinor = intdiv($totalMinor, $installmentsCount);
        $allocatedMinor = 0;
        $rows = [];

        for ($sequence = 1; $sequence <= $installmentsCount; $sequence++) {
            $amountMinor = $sequence === $installmentsCount
                ? $totalMinor - $allocatedMinor
                : $baseMinor;
            $dueDate = $sequence === 1
                ? $start->copy()
                : $this->installmentDueDate($start, $sequence, $dueDay);

            $rows[] = [
                'sequence' => $sequence,
                'due_date' => $dueDate->toDateString(),
                'amount_due_minor' => $amountMinor,
                'amount_due' => Money::fromMinorUnits($amountMinor)->format(),
            ];
            $allocatedMinor += $amountMinor;
        }

        return $rows;
    }

    /**
     * Persist an idempotent V2 schedule that remains subordinate to one annual invoice.
     */
    public function ensureAnnualInstallmentSchedule(
        Subscription $subscription,
        Invoice $invoice,
        int $installmentsCount,
        int $dueDay
    ): Collection {
        if (
            $subscription->billing_engine_version !== 'v2'
            || $subscription->billing_interval_v2 !== 'annual'
            || (int) $invoice->subscription_id !== (int) $subscription->id
        ) {
            throw ValidationException::withMessages([
                'payment_terms' => 'يمكن إنشاء جدول أقساط V2 لفاتورة اشتراك سنوي فقط.',
            ]);
        }

        return DB::transaction(function () use ($subscription, $invoice, $installmentsCount, $dueDay) {
            $subscription = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $existing = PaymentSchedule::query()
                ->where('subscription_id', $subscription->id)
                ->where('schedule_engine_version', PaymentSchedule::ENGINE_V2)
                ->where('invoice_id', $invoice->id)
                ->orderBy('sequence')
                ->get();

            if ($existing->isNotEmpty()) {
                $matches = $existing->count() === $installmentsCount
                    && $existing->every(fn (PaymentSchedule $schedule) => (int) $schedule->invoice_id === (int) $invoice->id)
                    && (int) $existing->sum('amount_due_minor') === (int) $invoice->total_minor;

                if (! $matches) {
                    throw ValidationException::withMessages([
                        'installments_count' => 'يوجد جدول أقساط محفوظ لهذا الاشتراك ولا يمكن إعادة كتابة تاريخه.',
                    ]);
                }

                return $existing;
            }

            if (PaymentSchedule::where('subscription_id', $subscription->id)
                ->whereNull('schedule_engine_version')
                ->exists()) {
                throw ValidationException::withMessages([
                    'payment_terms' => 'يوجد جدول دفعات تاريخي لهذا الاشتراك ويحتاج مراجعة يدوية.',
                ]);
            }

            $rows = $this->previewAnnualInstallments(
                (int) $invoice->total_minor,
                $installmentsCount,
                $subscription->current_period_start ?: $subscription->start_date,
                $dueDay
            );

            foreach ($rows as $row) {
                PaymentSchedule::create([
                    'subscription_id' => $subscription->id,
                    'schedule_engine_version' => PaymentSchedule::ENGINE_V2,
                    'invoice_id' => $invoice->id,
                    'sequence' => $row['sequence'],
                    'amount_due' => $row['amount_due'],
                    'amount_due_minor' => $row['amount_due_minor'],
                    'subtotal' => null,
                    'discount_amount' => '0.000',
                    'setup_fee_amount' => '0.000',
                    'tax_amount' => '0.000',
                    'total_amount' => $row['amount_due'],
                    'paid_amount' => '0.000',
                    'due_date' => $row['due_date'],
                    'status' => $row['sequence'] === 1 ? 'due' : 'upcoming',
                ]);
            }

            return PaymentSchedule::query()
                ->where('subscription_id', $subscription->id)
                ->where('schedule_engine_version', PaymentSchedule::ENGINE_V2)
                ->where('invoice_id', $invoice->id)
                ->orderBy('sequence')
                ->get();
        });
    }

    /**
     * Project installment state from authoritative invoice allocations without mutating history.
     */
    public function annualInstallmentProjection(Subscription $subscription): ?array
    {
        $latestInvoiceId = $subscription->paymentSchedules()
            ->where('schedule_engine_version', PaymentSchedule::ENGINE_V2)
            ->max('invoice_id');
        $schedules = $subscription->paymentSchedules()
            ->with('invoice')
            ->where('schedule_engine_version', PaymentSchedule::ENGINE_V2)
            ->where('invoice_id', $latestInvoiceId)
            ->orderBy('sequence')
            ->get();

        if ($schedules->isEmpty()) {
            return null;
        }

        $invoice = $schedules->first()->invoice;
        if (! $invoice) {
            return null;
        }

        $paidMinor = min($this->receivables->invoiceAllocatedMinor($invoice), (int) $schedules->sum('amount_due_minor'));
        $unappliedPaidMinor = $paidMinor;
        $rows = $schedules->map(function (PaymentSchedule $schedule) use (&$unappliedPaidMinor) {
            $amountMinor = (int) $schedule->amount_due_minor;
            $rowPaidMinor = min($unappliedPaidMinor, $amountMinor);
            $unappliedPaidMinor -= $rowPaidMinor;
            $remainingMinor = $amountMinor - $rowPaidMinor;
            $status = match (true) {
                $remainingMinor === 0 => 'paid',
                $rowPaidMinor > 0 => 'partially_paid',
                $schedule->due_date->isPast() => 'overdue',
                $schedule->due_date->isToday() => 'due',
                default => 'upcoming',
            };

            return [
                'id' => $schedule->id,
                'sequence' => $schedule->sequence,
                'due_date' => $schedule->due_date,
                'amount_minor' => $amountMinor,
                'paid_minor' => $rowPaidMinor,
                'remaining_minor' => $remainingMinor,
                'status' => $status,
            ];
        });

        return [
            'invoice_id' => $invoice->id,
            'total_minor' => (int) $schedules->sum('amount_due_minor'),
            'paid_minor' => $paidMinor,
            'remaining_minor' => $this->receivables->invoiceOutstandingMinor($invoice),
            'next_due_date' => $rows->first(fn (array $row) => $row['status'] !== 'paid')['due_date'] ?? null,
            'rows' => $rows,
        ];
    }

    private function installmentDueDate(Carbon $start, int $sequence, int $dueDay): Carbon
    {
        $month = $start->copy()->addMonthsNoOverflow($sequence - 1);

        return $month->day(min($dueDay, $month->daysInMonth));
    }
}
