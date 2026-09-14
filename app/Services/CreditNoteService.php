<?php

namespace App\Services;

use App\Models\Client;
use App\Models\CreditNote;
use App\Models\CreditNoteApplication;
use App\Models\CreditNoteApplicationReversal;
use App\Models\Invoice;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditNoteService
{
    public function __construct(private readonly ReceivableService $receivables)
    {
    }

    public function createIssued(Client $client, array $data, ?int $userId): CreditNote
    {
        return DB::transaction(function () use ($client, $data, $userId) {
            $reason = trim((string) ($data['reason'] ?? ''));
            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => 'سبب إشعار الدائن مطلوب.']);
            }

            $issueDate = Carbon::parse($data['issue_date'] ?? now());
            $originalInvoice = null;
            if (! empty($data['original_invoice_id'])) {
                $originalInvoice = Invoice::findOrFail((int) $data['original_invoice_id']);
                if ((int) $originalInvoice->client_id !== (int) $client->id) {
                    throw ValidationException::withMessages(['original_invoice_id' => 'لا يمكن ربط إشعار دائن بفاتورة عميل آخر.']);
                }
                if ($originalInvoice->status !== Invoice::STATUS_ISSUED) {
                    throw ValidationException::withMessages(['original_invoice_id' => 'يمكن ربط إشعار الدائن بالفواتير الصادرة فقط.']);
                }
            }

            $lineRows = $this->normalizeLines($data['lines'] ?? [], $originalInvoice);
            if ($lineRows === []) {
                throw ValidationException::withMessages(['lines' => 'يجب إضافة سطر واحد على الأقل لإشعار الدائن.']);
            }

            $subtotalMinor = array_sum(array_column($lineRows, 'subtotal_minor'));
            $taxMinor = array_sum(array_column($lineRows, 'tax_minor'));
            $totalMinor = array_sum(array_column($lineRows, 'total_minor'));
            if ($totalMinor <= 0) {
                throw ValidationException::withMessages(['lines' => 'إجمالي إشعار الدائن يجب أن يكون أكبر من صفر.']);
            }

            if ($originalInvoice !== null) {
                $existingCreditMinor = (int) CreditNote::query()
                    ->where('original_invoice_id', $originalInvoice->id)
                    ->where('status', '!=', CreditNote::STATUS_VOIDED)
                    ->sum('total_minor');

                if ($existingCreditMinor + $totalMinor > (int) $originalInvoice->total_minor) {
                    throw ValidationException::withMessages(['total' => 'إجمالي إشعارات الدائن لهذه الفاتورة يتجاوز إجمالي الفاتورة الأصلي.']);
                }
            }

            $creditNote = CreditNote::create([
                'client_id' => $client->id,
                'original_invoice_id' => $originalInvoice?->id,
                'currency' => 'JOD',
                'status' => CreditNote::STATUS_DRAFT,
                'issue_date' => $issueDate->toDateString(),
                'subtotal_minor' => $subtotalMinor,
                'tax_minor' => $taxMinor,
                'total_minor' => $totalMinor,
                'reason' => $reason,
                'created_by' => $userId,
            ]);

            foreach ($lineRows as $line) {
                $creditNote->lines()->create($line);
            }

            $this->assignNumberAndIssue($creditNote, $issueDate);
            $creditNote->refresh();
            app(BillingAccountingService::class)->postCreditNoteIssued($creditNote);

            $this->log($client->id, $userId, 'credit_note_created', 'تم إنشاء إشعار دائن '.$creditNote->credit_note_number, [
                'credit_note_id' => $creditNote->id,
                'credit_note_number' => $creditNote->credit_note_number,
                'original_invoice_id' => $creditNote->original_invoice_id,
                'total_minor' => $creditNote->total_minor,
                'reason' => $reason,
            ]);
            $this->log($client->id, $userId, 'credit_note_issued', 'تم إصدار إشعار دائن '.$creditNote->credit_note_number, [
                'credit_note_id' => $creditNote->id,
                'credit_note_number' => $creditNote->credit_note_number,
                'original_invoice_id' => $creditNote->original_invoice_id,
                'total_minor' => $creditNote->total_minor,
                'reason' => $reason,
            ]);

            return $creditNote->fresh(['lines', 'applications']);
        });
    }

    public function applyCredit(CreditNote $creditNote, Invoice $invoice, string $amountJod, ?int $userId): CreditNoteApplication
    {
        return DB::transaction(function () use ($creditNote, $invoice, $amountJod, $userId) {
            $creditNote->refresh();
            $invoice->refresh();
            $amountMinor = Money::fromJod($amountJod)->minorUnits();

            if ($amountMinor <= 0) {
                throw ValidationException::withMessages(['amount' => 'قيمة تطبيق الرصيد يجب أن تكون أكبر من صفر.']);
            }
            if ($creditNote->status !== CreditNote::STATUS_ISSUED) {
                throw ValidationException::withMessages(['credit_note_id' => 'يمكن تطبيق إشعارات الدائن الصادرة وغير الملغاة فقط.']);
            }
            if ($invoice->status !== Invoice::STATUS_ISSUED) {
                throw ValidationException::withMessages(['invoice_id' => 'يمكن تطبيق الرصيد على الفواتير الصادرة فقط.']);
            }
            if ((int) $creditNote->client_id !== (int) $invoice->client_id) {
                throw ValidationException::withMessages(['invoice_id' => 'لا يمكن تطبيق رصيد عميل على فاتورة عميل آخر.']);
            }

            $outstandingMinor = $this->receivables->invoiceOutstandingMinor($invoice);
            if ($amountMinor > $outstandingMinor) {
                throw ValidationException::withMessages(['amount' => 'قيمة الرصيد أكبر من رصيد الفاتورة المستحق.']);
            }

            $availableMinor = $this->receivables->creditNoteAvailableMinor($creditNote);
            if ($amountMinor > $availableMinor) {
                throw ValidationException::withMessages(['amount' => 'قيمة الرصيد أكبر من رصيد إشعار الدائن المتاح.']);
            }

            $application = CreditNoteApplication::create([
                'credit_note_id' => $creditNote->id,
                'invoice_id' => $invoice->id,
                'amount_minor' => $amountMinor,
                'applied_at' => now(),
                'created_by' => $userId,
            ]);
            app(BillingAccountingService::class)->postCreditApplication($application);

            $this->log($invoice->client_id, $userId, 'credit_note_applied', 'تم تطبيق رصيد إشعار دائن على الفاتورة '.$invoice->invoice_number, [
                'credit_note_id' => $creditNote->id,
                'credit_note_number' => $creditNote->credit_note_number,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount_minor' => $amountMinor,
            ]);

            return $application->fresh(['creditNote', 'invoice']);
        });
    }

    public function reverseApplication(CreditNoteApplication $application, string $reason, ?int $userId): CreditNoteApplicationReversal
    {
        return DB::transaction(function () use ($application, $reason, $userId) {
            $reason = trim($reason);
            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => 'سبب عكس تطبيق الرصيد مطلوب.']);
            }

            $application->loadMissing(['creditNote', 'invoice']);
            if ($this->receivables->creditApplicationIsReversed($application)) {
                throw ValidationException::withMessages(['credit_note_application_id' => 'تم عكس تطبيق الرصيد مسبقاً.']);
            }

            $reversal = CreditNoteApplicationReversal::create([
                'credit_note_application_id' => $application->id,
                'reason' => $reason,
                'reversed_at' => now(),
                'reversed_by' => $userId,
            ]);
            app(BillingAccountingService::class)->postCreditApplicationReversal($reversal);

            $this->log($application->invoice->client_id, $userId, 'credit_note_application_reversed', 'تم عكس تطبيق رصيد إشعار دائن على الفاتورة '.$application->invoice->invoice_number, [
                'credit_note_application_id' => $application->id,
                'credit_note_id' => $application->credit_note_id,
                'credit_note_number' => $application->creditNote->credit_note_number,
                'invoice_id' => $application->invoice_id,
                'invoice_number' => $application->invoice->invoice_number,
                'amount_minor' => $application->amount_minor,
                'reason' => $reason,
            ]);

            return $reversal->fresh(['application']);
        });
    }

    public function void(CreditNote $creditNote, string $reason, ?int $userId): CreditNote
    {
        return DB::transaction(function () use ($creditNote, $reason, $userId) {
            $reason = trim($reason);
            if ($reason === '') {
                throw ValidationException::withMessages(['void_reason' => 'سبب إلغاء إشعار الدائن مطلوب.']);
            }

            $creditNote->refresh();
            if ($creditNote->status === CreditNote::STATUS_VOIDED) {
                return $creditNote;
            }
            if ($creditNote->status !== CreditNote::STATUS_ISSUED) {
                throw ValidationException::withMessages(['credit_note_id' => 'يمكن إلغاء إشعار دائن صادر فقط.']);
            }
            if ($this->receivables->creditNoteAppliedMinor($creditNote) > 0) {
                throw ValidationException::withMessages(['credit_note_id' => 'يجب عكس كل تطبيقات الرصيد النشطة قبل إلغاء إشعار الدائن.']);
            }
            if ($this->receivables->creditNoteRefundedMinor($creditNote) > 0) {
                throw ValidationException::withMessages(['credit_note_id' => 'لا يمكن إلغاء إشعار دائن تم تمويل استرداد منه.']);
            }

            $creditNote->update([
                'status' => CreditNote::STATUS_VOIDED,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);
            app(BillingAccountingService::class)->postCreditNoteVoid($creditNote->fresh(), $userId);

            $this->log($creditNote->client_id, $userId, 'credit_note_voided', 'تم إلغاء إشعار الدائن '.$creditNote->credit_note_number, [
                'credit_note_id' => $creditNote->id,
                'credit_note_number' => $creditNote->credit_note_number,
                'original_invoice_id' => $creditNote->original_invoice_id,
                'total_minor' => $creditNote->total_minor,
                'reason' => $reason,
            ]);

            return $creditNote->fresh(['lines', 'applications', 'refunds']);
        });
    }

    private function normalizeLines(array $lines, ?Invoice $originalInvoice = null): array
    {
        $normalized = [];
        $singleInvoiceLineId = null;
        if ($originalInvoice !== null) {
            $invoiceLines = $originalInvoice->lines()->get();
            if ($invoiceLines->count() === 1) {
                $singleInvoiceLineId = $invoiceLines->first()->id;
            }
        }

        foreach (array_values($lines) as $index => $line) {
            $description = trim((string) ($line['description'] ?? $line['description_snapshot'] ?? ''));
            if ($description === '') {
                throw ValidationException::withMessages(["lines.$index.description" => 'وصف سطر إشعار الدائن مطلوب.']);
            }

            $subtotalMinor = Money::fromJod($line['subtotal_jod'] ?? $line['subtotal'] ?? '0')->minorUnits();
            $taxMinor = Money::fromJod($line['tax_jod'] ?? $line['tax'] ?? '0')->minorUnits();
            if ($subtotalMinor < 0 || $taxMinor < 0 || $subtotalMinor + $taxMinor <= 0) {
                throw ValidationException::withMessages(["lines.$index.subtotal_jod" => 'قيمة السطر يجب أن تكون أكبر من صفر.']);
            }

            $invoiceLineId = filled($line['invoice_line_id'] ?? null) ? (int) $line['invoice_line_id'] : $singleInvoiceLineId;
            if ($originalInvoice !== null && $invoiceLineId === null && $subtotalMinor > 0 && $originalInvoice->lines()->count() > 1) {
                throw ValidationException::withMessages(["lines.$index.invoice_line_id" => 'يجب ربط سطر إشعار الدائن بسطر الفاتورة عند وجود أكثر من سطر قابل للاعتراف.']);
            }
            if ($invoiceLineId !== null && $originalInvoice !== null && ! $originalInvoice->lines()->whereKey($invoiceLineId)->exists()) {
                throw ValidationException::withMessages(["lines.$index.invoice_line_id" => 'سطر الفاتورة المحدد لا يتبع الفاتورة الأصلية.']);
            }
            $normalized[] = [
                'invoice_line_id' => $invoiceLineId,
                'description_snapshot' => $description,
                'quantity' => max((int) ($line['quantity'] ?? 1), 1),
                'subtotal_minor' => $subtotalMinor,
                'tax_minor' => $taxMinor,
                'total_minor' => $subtotalMinor + $taxMinor,
                'metadata' => $line['metadata'] ?? null,
                'sort_order' => $index,
            ];
        }

        return $normalized;
    }

    private function assignNumberAndIssue(CreditNote $creditNote, Carbon $issueDate): void
    {
        $base = sprintf('CN-%s-%06d', $issueDate->format('Y'), $creditNote->id);
        $number = $base;
        $suffix = 1;
        while (CreditNote::where('credit_note_number', $number)->whereKeyNot($creditNote->id)->exists()) {
            $number = $base.'-'.$suffix;
            $suffix++;
        }

        $creditNote->update([
            'credit_note_number' => $number,
            'status' => CreditNote::STATUS_ISSUED,
            'issued_at' => now(),
        ]);
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
