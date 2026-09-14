<?php

namespace App\Services;

use App\Models\Client;
use App\Models\CreditNote;
use App\Models\CreditNoteApplication;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReceivableService
{
    public function invoiceAllocatedMinor(Invoice $invoice): int
    {
        return (int) $this->activeAllocationQuery()
            ->where('invoice_id', $invoice->id)
            ->sum('amount_minor');
    }

    public function invoiceOutstandingMinor(Invoice $invoice): int
    {
        if ($invoice->status === Invoice::STATUS_VOIDED) {
            return 0;
        }

        return max((int) $invoice->total_minor - $this->invoiceAllocatedMinor($invoice) - $this->invoiceCreditAppliedMinor($invoice), 0);
    }

    public function invoiceSettlementStatus(Invoice $invoice): string
    {
        $allocatedMinor = $this->invoiceAllocatedMinor($invoice) + $this->invoiceCreditAppliedMinor($invoice);
        $totalMinor = (int) $invoice->total_minor;

        if ($allocatedMinor <= 0 && $totalMinor > 0) {
            return 'unpaid';
        }

        if ($allocatedMinor > 0 && $allocatedMinor < $totalMinor) {
            return 'partially_paid';
        }

        return 'paid';
    }

    public function paymentAllocatedMinor(Payment $payment): int
    {
        if ($this->paymentIsReversed($payment)) {
            return 0;
        }

        return (int) $this->activeAllocationQuery()
            ->where('payment_id', $payment->id)
            ->sum('amount_minor');
    }

    public function invoiceCreditAppliedMinor(Invoice $invoice): int
    {
        return (int) $this->activeCreditApplicationQuery()
            ->where('invoice_id', $invoice->id)
            ->sum('amount_minor');
    }

    public function paymentUnallocatedMinor(Payment $payment): int
    {
        if ($payment->payment_engine_version !== Payment::ENGINE_V2 || $payment->amount_minor === null || $this->paymentIsReversed($payment)) {
            return 0;
        }

        return max((int) $payment->amount_minor - $this->paymentAllocatedMinor($payment) - $this->paymentRefundedMinor($payment), 0);
    }

    public function paymentRefundedMinor(Payment $payment): int
    {
        return (int) DB::table('refunds')
            ->where('payment_id', $payment->id)
            ->sum('amount_minor');
    }

    public function creditNoteAppliedMinor(CreditNote $creditNote): int
    {
        return (int) $this->activeCreditApplicationQuery()
            ->where('credit_note_id', $creditNote->id)
            ->sum('amount_minor');
    }

    public function creditNoteRefundedMinor(CreditNote $creditNote): int
    {
        return (int) DB::table('refunds')
            ->where('credit_note_id', $creditNote->id)
            ->sum('amount_minor');
    }

    public function creditNoteAvailableMinor(CreditNote $creditNote): int
    {
        if ($creditNote->status !== CreditNote::STATUS_ISSUED) {
            return 0;
        }

        return max((int) $creditNote->total_minor - $this->creditNoteAppliedMinor($creditNote) - $this->creditNoteRefundedMinor($creditNote), 0);
    }

    public function clientSummary(Client $client, ?Carbon $businessDate = null): array
    {
        $businessDate ??= Carbon::today();
        $invoiceProjection = $this->invoiceProjections($client->invoices()->get(), $businessDate);
        $paymentProjection = $this->paymentProjections($client->payments()->where('payment_engine_version', Payment::ENGINE_V2)->get());

        $totalOutstandingMinor = $invoiceProjection->sum('outstanding_minor');
        $overdueOutstandingMinor = $invoiceProjection
            ->filter(fn (array $projection) => $projection['is_overdue'])
            ->sum('outstanding_minor');
        $unallocatedPaymentCreditMinor = $paymentProjection->sum('unallocated_minor');
        $availableCreditNoteMinor = $this->creditNoteProjections($client->creditNotes()->get())->sum('available_minor');
        $totalCustomerCreditMinor = $unallocatedPaymentCreditMinor + $availableCreditNoteMinor;

        return [
            'total_outstanding_minor' => $totalOutstandingMinor,
            'overdue_outstanding_minor' => $overdueOutstandingMinor,
            'unallocated_payment_credit_minor' => $unallocatedPaymentCreditMinor,
            'available_credit_note_minor' => $availableCreditNoteMinor,
            'total_customer_credit_minor' => $totalCustomerCreditMinor,
            'unallocated_credit_minor' => $totalCustomerCreditMinor,
            'net_exposure_minor' => $totalOutstandingMinor - $totalCustomerCreditMinor,
            'aging' => $this->agingBuckets($client, $businessDate),
        ];
    }

    public function invoiceProjections(EloquentCollection|Collection $invoices, ?Carbon $businessDate = null): Collection
    {
        $businessDate ??= Carbon::today();
        $invoiceIds = $invoices->pluck('id')->all();
        $allocationSums = $invoiceIds === []
            ? collect()
            : $this->activeAllocationQuery()
                ->select('invoice_id', DB::raw('SUM(amount_minor) as allocated_minor'))
                ->whereIn('invoice_id', $invoiceIds)
                ->groupBy('invoice_id')
                ->pluck('allocated_minor', 'invoice_id');
        $creditSums = $invoiceIds === []
            ? collect()
            : $this->activeCreditApplicationQuery()
                ->select('invoice_id', DB::raw('SUM(amount_minor) as credit_applied_minor'))
                ->whereIn('invoice_id', $invoiceIds)
                ->groupBy('invoice_id')
                ->pluck('credit_applied_minor', 'invoice_id');

        return $invoices->mapWithKeys(function (Invoice $invoice) use ($allocationSums, $creditSums, $businessDate) {
            $allocatedMinor = (int) ($allocationSums[$invoice->id] ?? 0);
            $creditAppliedMinor = (int) ($creditSums[$invoice->id] ?? 0);
            $settledMinor = $allocatedMinor + $creditAppliedMinor;
            $outstandingMinor = $invoice->status === Invoice::STATUS_VOIDED
                ? 0
                : max((int) $invoice->total_minor - $settledMinor, 0);
            $settlementStatus = $this->settlementStatusFromAmounts((int) $invoice->total_minor, $settledMinor);
            $ageBucket = $this->ageBucket($invoice, $outstandingMinor, $businessDate);

            return [$invoice->id => [
                'allocated_minor' => $allocatedMinor,
                'allocated' => Money::fromMinorUnits($allocatedMinor)->format(),
                'credit_applied_minor' => $creditAppliedMinor,
                'credit_applied' => Money::fromMinorUnits($creditAppliedMinor)->format(),
                'settled_minor' => $settledMinor,
                'settled' => Money::fromMinorUnits($settledMinor)->format(),
                'outstanding_minor' => $outstandingMinor,
                'outstanding' => Money::fromMinorUnits($outstandingMinor)->format(),
                'settlement_status' => $settlementStatus,
                'age_bucket' => $ageBucket,
                'is_overdue' => $outstandingMinor > 0
                    && $invoice->status === Invoice::STATUS_ISSUED
                    && $invoice->due_date !== null
                    && $invoice->due_date->lt($businessDate),
            ]];
        });
    }

    public function paymentProjections(EloquentCollection|Collection $payments): Collection
    {
        $paymentIds = $payments->pluck('id')->all();
        $allocationSums = $paymentIds === []
            ? collect()
            : $this->activeAllocationQuery()
                ->select('payment_id', DB::raw('SUM(amount_minor) as allocated_minor'))
                ->whereIn('payment_id', $paymentIds)
                ->groupBy('payment_id')
                ->pluck('allocated_minor', 'payment_id');

        return $payments->mapWithKeys(function (Payment $payment) use ($allocationSums) {
            $isReversed = $this->paymentIsReversed($payment);
            $allocatedMinor = $isReversed ? 0 : (int) ($allocationSums[$payment->id] ?? 0);
            $totalMinor = $payment->payment_engine_version === Payment::ENGINE_V2 && $payment->amount_minor !== null
                ? (int) $payment->amount_minor
                : Money::roundedFromJod((string) $payment->amount)->minorUnits();
            $refundedMinor = $this->paymentRefundedMinor($payment);
            $unallocatedMinor = $payment->payment_engine_version === Payment::ENGINE_V2 && ! $isReversed
                ? max($totalMinor - $allocatedMinor - $refundedMinor, 0)
                : 0;

            return [$payment->id => [
                'total_minor' => $totalMinor,
                'total' => Money::fromMinorUnits($totalMinor)->format(),
                'allocated_minor' => $allocatedMinor,
                'allocated' => Money::fromMinorUnits($allocatedMinor)->format(),
                'refunded_minor' => $refundedMinor,
                'refunded' => Money::fromMinorUnits($refundedMinor)->format(),
                'unallocated_minor' => $unallocatedMinor,
                'unallocated' => Money::fromMinorUnits($unallocatedMinor)->format(),
                'is_reversed' => $isReversed,
            ]];
        });
    }

    public function agingBuckets(?Client $client = null, ?Carbon $businessDate = null): array
    {
        $businessDate ??= Carbon::today();
        $buckets = [
            'not_due' => 0,
            '1_30_days_overdue' => 0,
            '31_60_days_overdue' => 0,
            '61_90_days_overdue' => 0,
            'over_90_days_overdue' => 0,
        ];

        $query = Invoice::query()
            ->where('status', Invoice::STATUS_ISSUED)
            ->orderBy('due_date')
            ->orderBy('id');

        if ($client !== null) {
            $query->where('client_id', $client->id);
        }

        $invoices = $query->get();
        $projections = $this->invoiceProjections($invoices, $businessDate);

        foreach ($invoices as $invoice) {
            $projection = $projections[$invoice->id];
            if ($projection['outstanding_minor'] <= 0) {
                continue;
            }

            $buckets[$projection['age_bucket']] += $projection['outstanding_minor'];
        }

        return $buckets;
    }

    public function outstandingInvoices(array $filters = [], ?Carbon $businessDate = null): Collection
    {
        $businessDate ??= Carbon::today();
        $query = Invoice::with('client')
            ->where('status', Invoice::STATUS_ISSUED)
            ->orderBy('due_date')
            ->orderBy('id');

        if (! empty($filters['client_id'])) {
            $query->where('client_id', (int) $filters['client_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('due_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('due_date', '<=', $filters['date_to']);
        }

        $invoices = $query->get();
        $projections = $this->invoiceProjections($invoices, $businessDate);

        return $invoices
            ->map(function (Invoice $invoice) use ($projections, $filters) {
                $projection = $projections[$invoice->id];
                if ($projection['outstanding_minor'] <= 0) {
                    return null;
                }
                if (($filters['due_state'] ?? null) === 'overdue' && ! $projection['is_overdue']) {
                    return null;
                }
                if (($filters['due_state'] ?? null) === 'not_due' && $projection['is_overdue']) {
                    return null;
                }
                if (! empty($filters['settlement_state']) && $projection['settlement_status'] !== $filters['settlement_state']) {
                    return null;
                }

                return ['invoice' => $invoice, 'projection' => $projection];
            })
            ->filter()
            ->values();
    }

    public function unallocatedCredits(array $filters = []): Collection
    {
        $query = Payment::with('client')
            ->where('payment_engine_version', Payment::ENGINE_V2)
            ->whereNotNull('amount_minor')
            ->orderBy('received_at')
            ->orderBy('id');

        if (! empty($filters['client_id'])) {
            $query->where('client_id', (int) $filters['client_id']);
        }

        $payments = $query->get();
        $projections = $this->paymentProjections($payments);

        return $payments
            ->map(function (Payment $payment) use ($projections) {
                $projection = $projections[$payment->id];
                if ($projection['unallocated_minor'] <= 0) {
                    return null;
                }

                return ['payment' => $payment, 'projection' => $projection];
            })
            ->filter()
            ->values();
    }

    public function creditNoteProjections(EloquentCollection|Collection $creditNotes): Collection
    {
        $creditNoteIds = $creditNotes->pluck('id')->all();
        $applicationSums = $creditNoteIds === []
            ? collect()
            : $this->activeCreditApplicationQuery()
                ->select('credit_note_id', DB::raw('SUM(amount_minor) as applied_minor'))
                ->whereIn('credit_note_id', $creditNoteIds)
                ->groupBy('credit_note_id')
                ->pluck('applied_minor', 'credit_note_id');
        $refundSums = $creditNoteIds === []
            ? collect()
            : DB::table('refunds')
                ->select('credit_note_id', DB::raw('SUM(amount_minor) as refunded_minor'))
                ->whereIn('credit_note_id', $creditNoteIds)
                ->whereNotNull('credit_note_id')
                ->groupBy('credit_note_id')
                ->pluck('refunded_minor', 'credit_note_id');

        return $creditNotes->mapWithKeys(function (CreditNote $creditNote) use ($applicationSums, $refundSums) {
            $appliedMinor = (int) ($applicationSums[$creditNote->id] ?? 0);
            $refundedMinor = (int) ($refundSums[$creditNote->id] ?? 0);
            $availableMinor = $creditNote->status === CreditNote::STATUS_ISSUED
                ? max((int) $creditNote->total_minor - $appliedMinor - $refundedMinor, 0)
                : 0;

            return [$creditNote->id => [
                'total_minor' => (int) $creditNote->total_minor,
                'total' => Money::fromMinorUnits((int) $creditNote->total_minor)->format(),
                'applied_minor' => $appliedMinor,
                'applied' => Money::fromMinorUnits($appliedMinor)->format(),
                'refunded_minor' => $refundedMinor,
                'refunded' => Money::fromMinorUnits($refundedMinor)->format(),
                'available_minor' => $availableMinor,
                'available' => Money::fromMinorUnits($availableMinor)->format(),
            ]];
        });
    }

    public function availableCustomerCredits(array $filters = []): Collection
    {
        $paymentCredits = $this->unallocatedCredits($filters)->map(fn (array $item) => [
            'source_type' => 'payment',
            'source' => $item['payment'],
            'projection' => $item['projection'],
            'available_minor' => $item['projection']['unallocated_minor'],
            'available' => $item['projection']['unallocated'],
        ]);

        $query = CreditNote::with('client')
            ->where('status', CreditNote::STATUS_ISSUED)
            ->orderBy('issue_date')
            ->orderBy('id');

        if (! empty($filters['client_id'])) {
            $query->where('client_id', (int) $filters['client_id']);
        }

        $creditNotes = $query->get();
        $projections = $this->creditNoteProjections($creditNotes);
        $creditNoteCredits = $creditNotes
            ->map(function (CreditNote $creditNote) use ($projections) {
                $projection = $projections[$creditNote->id];
                if ($projection['available_minor'] <= 0) {
                    return null;
                }

                return [
                    'source_type' => 'credit_note',
                    'source' => $creditNote,
                    'projection' => $projection,
                    'available_minor' => $projection['available_minor'],
                    'available' => $projection['available'],
                ];
            })
            ->filter()
            ->values();

        return $paymentCredits->concat($creditNoteCredits)->values();
    }

    private function settlementStatusFromAmounts(int $totalMinor, int $allocatedMinor): string
    {
        if ($allocatedMinor <= 0 && $totalMinor > 0) {
            return 'unpaid';
        }

        if ($allocatedMinor > 0 && $allocatedMinor < $totalMinor) {
            return 'partially_paid';
        }

        return 'paid';
    }

    public function allocationIsReversed(PaymentAllocation $allocation): bool
    {
        return DB::table('payment_allocation_reversals')
            ->where('payment_allocation_id', $allocation->id)
            ->exists();
    }

    public function creditApplicationIsReversed(CreditNoteApplication $application): bool
    {
        return DB::table('credit_note_application_reversals')
            ->where('credit_note_application_id', $application->id)
            ->exists();
    }

    public function paymentIsReversed(Payment $payment): bool
    {
        return DB::table('payment_reversals')
            ->where('payment_id', $payment->id)
            ->exists();
    }

    public function activeAllocationQuery()
    {
        return DB::table('payment_allocations')
            ->leftJoin('payment_allocation_reversals', 'payment_allocation_reversals.payment_allocation_id', '=', 'payment_allocations.id')
            ->whereNull('payment_allocation_reversals.id')
            ->select('payment_allocations.*');
    }

    public function activeCreditApplicationQuery()
    {
        return DB::table('credit_note_applications')
            ->leftJoin('credit_note_application_reversals', 'credit_note_application_reversals.credit_note_application_id', '=', 'credit_note_applications.id')
            ->whereNull('credit_note_application_reversals.id')
            ->select('credit_note_applications.*');
    }

    private function ageBucket(Invoice $invoice, int $outstandingMinor, Carbon $businessDate): string
    {
        if ($outstandingMinor <= 0 || $invoice->due_date === null || $invoice->due_date->gte($businessDate)) {
            return 'not_due';
        }

        $daysOverdue = $invoice->due_date->diffInDays($businessDate);

        return match (true) {
            $daysOverdue <= 30 => '1_30_days_overdue',
            $daysOverdue <= 60 => '31_60_days_overdue',
            $daysOverdue <= 90 => '61_90_days_overdue',
            default => 'over_90_days_overdue',
        };
    }
}
