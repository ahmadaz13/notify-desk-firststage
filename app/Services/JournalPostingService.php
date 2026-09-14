<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ChartAccount;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalPostingService
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function post(array $entry, array $lines): JournalEntry
    {
        $this->rejectFloats($entry);
        $this->rejectFloats($lines);

        return DB::transaction(function () use ($entry, $lines) {
            $existing = JournalEntry::with('lines')->where('event_key', $entry['event_key'])->first();
            if ($existing !== null) {
                return $existing;
            }

            $entryDate = Carbon::parse($entry['entry_date'])->startOfDay();
            $this->assertOpenPeriod($entryDate);
            $this->validateLines($lines, isset($entry['reversal_of_id']));

            $journal = JournalEntry::create([
                'journal_number' => $this->nextJournalNumber($entryDate),
                'event_type' => $entry['event_type'],
                'event_key' => $entry['event_key'],
                'source_type' => $entry['source_type'] ?? null,
                'source_id' => $entry['source_id'] ?? null,
                'entry_date' => $entryDate->toDateString(),
                'description' => $entry['description'],
                'status' => JournalEntry::STATUS_POSTED,
                'reversal_of_id' => $entry['reversal_of_id'] ?? null,
                'posted_at' => now(),
                'created_by' => $entry['created_by'] ?? null,
                'metadata' => $entry['metadata'] ?? null,
            ]);

            foreach ($lines as $line) {
                $journal->lines()->create([
                    'chart_account_id' => $line['chart_account_id'],
                    'debit_minor' => (int) ($line['debit_minor'] ?? 0),
                    'credit_minor' => (int) ($line['credit_minor'] ?? 0),
                    'description' => $line['description'] ?? null,
                    'financial_account_id' => $line['financial_account_id'] ?? null,
                    'user_id' => $line['user_id'] ?? null,
                    'client_id' => $line['client_id'] ?? null,
                    'metadata' => $line['metadata'] ?? null,
                ]);
            }

            return $journal->fresh(['lines.chartAccount']);
        });
    }

    public function reverse(
        JournalEntry $original,
        string $eventKey,
        string $eventType,
        Carbon|string $entryDate,
        string $description,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?int $createdBy = null,
        array $metadata = []
    ): JournalEntry {
        $this->rejectFloats($metadata);

        return DB::transaction(function () use ($original, $eventKey, $eventType, $entryDate, $description, $sourceType, $sourceId, $createdBy, $metadata) {
            $existing = JournalEntry::with('lines')->where('event_key', $eventKey)->first();
            if ($existing !== null) {
                return $existing;
            }
            if ($original->reversals()->exists()) {
                throw ValidationException::withMessages(['journal_entry_id' => 'تم عكس هذا القيد المحاسبي مسبقاً.']);
            }

            $date = Carbon::parse($entryDate)->startOfDay();
            $period = $this->periodFor($date);
            if ($period->status === AccountingPeriod::STATUS_CLOSED) {
                $metadata['original_requested_entry_date'] = $date->toDateString();
                $date = now()->startOfDay();
                $this->assertOpenPeriod($date);
            }

            $lines = $original->lines()
                ->orderBy('id')
                ->get()
                ->map(fn ($line) => [
                    'chart_account_id' => $line->chart_account_id,
                    'debit_minor' => (int) $line->credit_minor,
                    'credit_minor' => (int) $line->debit_minor,
                    'description' => 'Reversal: '.$line->description,
                    'financial_account_id' => $line->financial_account_id,
                    'user_id' => $line->user_id,
                    'client_id' => $line->client_id,
                    'metadata' => ['reversal_of_journal_line_id' => $line->id],
                ])
                ->all();

            $reversal = $this->post([
                'event_type' => $eventType,
                'event_key' => $eventKey,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'entry_date' => $date,
                'description' => $description,
                'reversal_of_id' => $original->id,
                'created_by' => $createdBy,
                'metadata' => $metadata + ['reversal_of_journal_entry_id' => $original->id],
            ], $lines);

            $original->forceFill(['status' => JournalEntry::STATUS_REVERSED])->save();

            return $reversal->fresh(['lines.chartAccount', 'reversalOf']);
        });
    }

    public function periodFor(Carbon $date): AccountingPeriod
    {
        $key = AccountingPeriod::keyFor($date);

        return AccountingPeriod::firstOrCreate(
            ['period_key' => $key],
            [
                'start_date' => $date->copy()->startOfMonth()->toDateString(),
                'end_date' => $date->copy()->endOfMonth()->toDateString(),
                'status' => AccountingPeriod::STATUS_OPEN,
            ]
        );
    }

    public function closePeriod(AccountingPeriod $period, ?int $userId, ?string $notes = null): AccountingPeriod
    {
        $period->update([
            'status' => AccountingPeriod::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by' => $userId,
            'notes' => $notes,
        ]);

        DB::table('activity_logs')->insert([
            'client_id' => null,
            'user_id' => $userId,
            'type' => 'accounting_period_closed',
            'description' => 'تم إغلاق فترة محاسبية '.$period->period_key,
            'metadata' => json_encode(['period_id' => $period->id, 'period_key' => $period->period_key]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $period->fresh();
    }

    public function reopenPeriod(AccountingPeriod $period, ?int $userId, string $reason): AccountingPeriod
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'سبب إعادة فتح الفترة مطلوب.']);
        }

        $period->update([
            'status' => AccountingPeriod::STATUS_OPEN,
            'closed_at' => null,
            'closed_by' => null,
            'notes' => $reason,
        ]);

        DB::table('activity_logs')->insert([
            'client_id' => null,
            'user_id' => $userId,
            'type' => 'accounting_period_reopened',
            'description' => 'تم إعادة فتح فترة محاسبية '.$period->period_key,
            'metadata' => json_encode(['period_id' => $period->id, 'period_key' => $period->period_key, 'reason' => $reason]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $period->fresh();
    }

    private function assertOpenPeriod(Carbon $entryDate): void
    {
        $period = $this->periodFor($entryDate);
        if ($period->status === AccountingPeriod::STATUS_CLOSED) {
            throw ValidationException::withMessages(['entry_date' => 'لا يمكن الترحيل إلى فترة محاسبية مغلقة.']);
        }
    }

    private function validateLines(array $lines, bool $allowInactive): void
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages(['journal_lines' => 'القيد المحاسبي يحتاج إلى سطرين على الأقل.']);
        }

        $debit = 0;
        $credit = 0;
        foreach ($lines as $line) {
            $debitMinor = (int) ($line['debit_minor'] ?? 0);
            $creditMinor = (int) ($line['credit_minor'] ?? 0);
            $debit += $debitMinor;
            $credit += $creditMinor;

            $account = ChartAccount::find($line['chart_account_id'] ?? null);
            if ($account === null) {
                throw ValidationException::withMessages(['chart_account_id' => 'حساب محاسبي غير موجود.']);
            }
            if (! $allowInactive && (! $account->is_active || $account->archived_at !== null || ! $account->allow_direct_posting)) {
                throw ValidationException::withMessages(['chart_account_id' => 'الحساب المحاسبي لا يسمح بالترحيل المباشر.']);
            }
        }

        if ($debit <= 0 || $debit !== $credit) {
            throw ValidationException::withMessages(['journal_lines' => 'يجب أن يتساوى إجمالي المدين والدائن تماماً.']);
        }
    }

    private function nextJournalNumber(Carbon $entryDate): string
    {
        $year = $entryDate->format('Y');
        $next = JournalEntry::where('journal_number', 'like', 'JE-'.$year.'-%')->count() + 1;

        do {
            $number = 'JE-'.$year.'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            $next++;
        } while (JournalEntry::where('journal_number', $number)->exists());

        return $number;
    }

    private function rejectFloats(mixed $value): void
    {
        if (is_float($value)) {
            throw ValidationException::withMessages(['amount' => 'لا تستخدم الأرقام العشرية العائمة في القيود المحاسبية.']);
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->rejectFloats($item);
            }
        }
    }
}
