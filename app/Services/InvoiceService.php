<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function createIssuedForSubscription(Client $client, Subscription $subscription, array $pricing, Carbon $issueDate, Carbon $dueDate, ?string $description, ?int $userId): Invoice
    {
        return DB::transaction(function () use ($client, $subscription, $pricing, $issueDate, $dueDate, $description, $userId) {
            $invoice = Invoice::create([
                'client_id' => $client->id,
                'subscription_id' => $subscription->id,
                'currency' => 'JOD',
                'status' => Invoice::STATUS_DRAFT,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'subtotal_minor' => $pricing['subtotal_minor'],
                'discount_minor' => $pricing['discount_minor'],
                'tax_minor' => $pricing['tax_minor'],
                'total_minor' => $pricing['total_minor'],
                'billing_period_start' => $subscription->current_period_start,
                'billing_period_end' => $subscription->current_period_end,
                'description' => $description,
                'created_by' => $userId,
            ]);

            foreach ($pricing['lines'] as $line) {
                $invoice->lines()->create($line);
            }

            $this->assertTotalsMatchLines($invoice->fresh('lines'));
            $this->assignNumberAndIssue($invoice, $issueDate);
            app(BillingAccountingService::class)->postInvoiceIssued($invoice);
            app(RevenueRecognitionService::class)->createSchedulesForInvoice($invoice->fresh('lines'), $userId);
            $this->log($client->id, $userId, 'invoice_created', 'تم إنشاء فاتورة جديدة '.$invoice->invoice_number, $invoice);
            $this->log($client->id, $userId, 'invoice_issued', 'تم إصدار الفاتورة '.$invoice->invoice_number, $invoice);

            return $invoice->fresh('lines');
        });
    }

    public function createIssuedOneTime(Client $client, array $lines, Carbon $issueDate, Carbon $dueDate, ?string $description, ?int $userId): Invoice
    {
        return DB::transaction(function () use ($client, $lines, $issueDate, $dueDate, $description, $userId) {
            $totals = $this->totalsFromLines($lines);

            $invoice = Invoice::create([
                'client_id' => $client->id,
                'subscription_id' => null,
                'currency' => 'JOD',
                'status' => Invoice::STATUS_DRAFT,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'subtotal_minor' => $totals['subtotal_minor'],
                'discount_minor' => $totals['discount_minor'],
                'tax_minor' => $totals['tax_minor'],
                'total_minor' => $totals['total_minor'],
                'description' => $description,
                'created_by' => $userId,
            ]);

            foreach ($lines as $line) {
                $invoice->lines()->create($line);
            }

            $this->assertTotalsMatchLines($invoice->fresh('lines'));
            $this->assignNumberAndIssue($invoice, $issueDate);
            app(BillingAccountingService::class)->postInvoiceIssued($invoice);
            app(RevenueRecognitionService::class)->createSchedulesForInvoice($invoice->fresh('lines'), $userId);
            $this->log($client->id, $userId, 'invoice_created', 'تم إنشاء فاتورة عمل إضافي '.$invoice->invoice_number, $invoice);
            $this->log($client->id, $userId, 'invoice_issued', 'تم إصدار الفاتورة '.$invoice->invoice_number, $invoice);

            return $invoice->fresh('lines');
        });
    }

    public function void(Invoice $invoice, string $reason, ?int $userId): Invoice
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['void_reason' => 'سبب الإلغاء مطلوب.']);
        }

        if ($invoice->status === Invoice::STATUS_VOIDED) {
            return $invoice;
        }

        $receivables = app(ReceivableService::class);
        if ($receivables->activeAllocationQuery()->where('invoice_id', $invoice->id)->exists()) {
            throw ValidationException::withMessages([
                'void_reason' => 'لا يمكن إلغاء فاتورة عليها تخصيص دفعات في C1. يجب انتظار مسار التصحيح وعكس التخصيص لاحقاً.',
            ]);
        }
        if ($receivables->activeCreditApplicationQuery()->where('invoice_id', $invoice->id)->exists()) {
            throw ValidationException::withMessages([
                'void_reason' => 'لا يمكن إلغاء فاتورة عليها تطبيق رصيد إشعار دائن نشط. يجب عكس تطبيق الرصيد أولاً.',
            ]);
        }

        $invoice->update([
            'status' => Invoice::STATUS_VOIDED,
            'voided_at' => now(),
            'void_reason' => $reason,
        ]);
        app(BillingAccountingService::class)->postInvoiceVoid($invoice->fresh(), $userId);

        $this->log($invoice->client_id, $userId, 'invoice_voided', 'تم إلغاء الفاتورة '.$invoice->invoice_number, $invoice);

        return $invoice->fresh('lines');
    }

    private function assignNumberAndIssue(Invoice $invoice, Carbon $issueDate): void
    {
        $invoice->update([
            'invoice_number' => sprintf('INV-%s-%06d', $issueDate->format('Y'), $invoice->id),
            'status' => Invoice::STATUS_ISSUED,
            'issued_at' => now(),
        ]);
    }

    private function assertTotalsMatchLines(Invoice $invoice): void
    {
        $totals = $this->totalsFromLines($invoice->lines->map->toArray()->all());

        if (
            $invoice->subtotal_minor !== $totals['subtotal_minor']
            || $invoice->discount_minor !== $totals['discount_minor']
            || $invoice->tax_minor !== $totals['tax_minor']
            || $invoice->total_minor !== $totals['total_minor']
        ) {
            throw ValidationException::withMessages(['invoice' => 'Invoice totals do not match invoice lines.']);
        }
    }

    private function totalsFromLines(array $lines): array
    {
        return [
            'subtotal_minor' => array_sum(array_map(fn ($line) => (int) $line['subtotal_minor'], $lines)),
            'discount_minor' => array_sum(array_map(fn ($line) => (int) $line['discount_minor'], $lines)),
            'tax_minor' => array_sum(array_map(fn ($line) => (int) $line['tax_minor'], $lines)),
            'total_minor' => array_sum(array_map(fn ($line) => (int) $line['total_minor'], $lines)),
        ];
    }

    private function log(?int $clientId, ?int $userId, string $type, string $description, Invoice $invoice): void
    {
        DB::table('activity_logs')->insert([
            'client_id' => $clientId,
            'user_id' => $userId,
            'type' => $type,
            'description' => $description,
            'metadata' => json_encode([
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total_minor' => $invoice->total_minor,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
