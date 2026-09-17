<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpenseObligation;
use App\Models\RecurringExpenseTemplate;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurringExpenseService
{
    public function createTemplate(array $data, User $actor): RecurringExpenseTemplate
    {
        $amountMinor = Money::fromJod($data['amount'])->minorUnits();
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => 'قيمة القالب يجب أن تكون أكبر من صفر.']);
        }
        if (! in_array($data['frequency'], RecurringExpenseTemplate::FREQUENCIES, true)) {
            throw ValidationException::withMessages(['frequency' => 'تكرار القالب غير صالح.']);
        }
        if (($data['default_funding_source'] ?? '') === Expense::FUNDING_PERSONAL && empty($data['default_paid_by_user_id'])) {
            throw ValidationException::withMessages(['default_paid_by_user_id' => 'يجب اختيار الدافع الشخصي الافتراضي.']);
        }

        $category = ExpenseCategory::findOrFail($data['category_id']);
        if (! $category->is_active || $category->archived_at !== null) {
            throw ValidationException::withMessages(['category_id' => 'تصنيف المصروف غير نشط.']);
        }
        $vendor = isset($data['vendor_id']) ? Vendor::findOrFail($data['vendor_id']) : null;
        if ($vendor !== null && (! $vendor->is_active || $vendor->archived_at !== null)) {
            throw ValidationException::withMessages(['vendor_id' => 'المورد غير نشط.']);
        }

        return DB::transaction(function () use ($data, $actor, $amountMinor) {
            $template = RecurringExpenseTemplate::create([
                'name' => $data['name'],
                'category_id' => $data['category_id'],
                'vendor_id' => $data['vendor_id'] ?? null,
                'payee_name' => $data['payee_name'] ?? null,
                'currency' => 'JOD',
                'amount_minor' => $amountMinor,
                'frequency' => $data['frequency'],
                'interval_count' => (int) ($data['interval_count'] ?? 1),
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'next_due_date' => $data['next_due_date'] ?? $data['start_date'],
                'default_financial_account_id' => $data['default_financial_account_id'] ?? null,
                'default_funding_source' => $data['default_funding_source'],
                'default_paid_by_user_id' => $data['default_paid_by_user_id'] ?? null,
                'is_active' => true,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->log($actor->id, 'recurring_expense_created', 'تم إنشاء قالب مصروف متكرر', [
                'recurring_expense_template_id' => $template->id,
                'amount_minor' => $amountMinor,
                'frequency' => $template->frequency,
            ]);

            return $template;
        });
    }

    public function updateTemplate(RecurringExpenseTemplate $template, array $data, User $actor): RecurringExpenseTemplate
    {
        return DB::transaction(function () use ($template, $data, $actor) {
            $updates = [];
            foreach ([
                'name',
                'category_id',
                'vendor_id',
                'payee_name',
                'frequency',
                'interval_count',
                'end_date',
                'next_due_date',
                'default_financial_account_id',
                'default_funding_source',
                'default_paid_by_user_id',
                'is_active',
                'notes',
            ] as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = $data[$field];
                }
            }
            if (array_key_exists('amount', $data)) {
                $amountMinor = Money::fromJod($data['amount'])->minorUnits();
                if ($amountMinor <= 0) {
                    throw ValidationException::withMessages(['amount' => 'قيمة القالب يجب أن تكون أكبر من صفر.']);
                }
                $updates['amount_minor'] = $amountMinor;
            }

            $template->update($updates);
            $this->log($actor->id, 'recurring_expense_updated', 'تم تحديث قالب مصروف متكرر', [
                'recurring_expense_template_id' => $template->id,
            ]);

            return $template->fresh();
        });
    }

    public function generateDueObligations(?Carbon $businessDate = null): int
    {
        $businessDate ??= today();
        $generated = 0;

        RecurringExpenseTemplate::with(['category', 'vendor'])
            ->active()
            ->whereDate('next_due_date', '<=', $businessDate)
            ->orderBy('id')
            ->get()
            ->each(function (RecurringExpenseTemplate $template) use ($businessDate, &$generated) {
                DB::transaction(function () use ($template, $businessDate, &$generated) {
                    $nextDue = $template->next_due_date->copy();
                    while ($nextDue->lte($businessDate) && ($template->end_date === null || $nextDue->lte($template->end_date))) {
                        $obligation = RecurringExpenseObligation::firstOrCreate(
                            [
                                'recurring_expense_template_id' => $template->id,
                                'due_date' => $nextDue->toDateString(),
                            ],
                            [
                                'expected_amount_minor' => $template->amount_minor,
                                'currency' => $template->currency,
                                'category_id' => $template->category_id,
                                'category_name_snapshot' => $template->category?->displayName() ?? 'مصروف',
                                'vendor_id' => $template->vendor_id,
                                'payee_name_snapshot' => $template->vendor?->name ?: $template->payee_name,
                                'default_financial_account_id' => $template->default_financial_account_id,
                                'default_funding_source' => $template->default_funding_source,
                                'status' => RecurringExpenseObligation::STATUS_PENDING,
                                'notes' => $template->notes,
                            ]
                        );

                        if ($obligation->wasRecentlyCreated) {
                            $generated++;
                            $this->log($template->created_by, 'recurring_expense_obligation_generated', 'تم توليد التزام مصروف متكرر', [
                                'recurring_expense_template_id' => $template->id,
                                'recurring_expense_obligation_id' => $obligation->id,
                                'due_date' => $nextDue->toDateString(),
                                'expected_amount_minor' => $template->amount_minor,
                            ]);
                        }

                        $nextDue = $this->advanceDueDate($nextDue, $template);
                    }

                    $template->update(['next_due_date' => $nextDue->toDateString()]);
                });
            });

        return $generated;
    }

    public function skipObligation(RecurringExpenseObligation $obligation, User $actor, ?string $notes = null): RecurringExpenseObligation
    {
        if ($obligation->status !== RecurringExpenseObligation::STATUS_PENDING) {
            throw ValidationException::withMessages(['recurring_expense_obligation_id' => 'يمكن تخطي الالتزامات المعلقة فقط.']);
        }

        $obligation->update([
            'status' => RecurringExpenseObligation::STATUS_SKIPPED,
            'notes' => $notes ?: $obligation->notes,
        ]);

        $this->log($actor->id, 'recurring_expense_obligation_skipped', 'تم تخطي التزام مصروف متكرر', [
            'recurring_expense_obligation_id' => $obligation->id,
        ]);

        return $obligation->fresh();
    }

    public function cancelObligation(RecurringExpenseObligation $obligation, User $actor, ?string $notes = null): RecurringExpenseObligation
    {
        if ($obligation->status !== RecurringExpenseObligation::STATUS_PENDING) {
            throw ValidationException::withMessages(['recurring_expense_obligation_id' => 'يمكن إلغاء الالتزامات المعلقة فقط.']);
        }

        $obligation->update([
            'status' => RecurringExpenseObligation::STATUS_CANCELLED,
            'notes' => $notes ?: $obligation->notes,
        ]);

        return $obligation->fresh();
    }

    private function advanceDueDate(Carbon $dueDate, RecurringExpenseTemplate $template): Carbon
    {
        $interval = max(1, (int) $template->interval_count);

        return match ($template->frequency) {
            RecurringExpenseTemplate::FREQUENCY_WEEKLY => $dueDate->copy()->addWeeks($interval),
            RecurringExpenseTemplate::FREQUENCY_QUARTERLY => $dueDate->copy()->addMonthsNoOverflow(3 * $interval),
            RecurringExpenseTemplate::FREQUENCY_ANNUAL => $dueDate->copy()->addYearsNoOverflow($interval),
            default => $dueDate->copy()->addMonthsNoOverflow($interval),
        };
    }

    private function log(?int $userId, string $type, string $description, array $metadata): void
    {
        DB::table('activity_logs')->insert([
            'client_id' => null,
            'user_id' => $userId,
            'type' => $type,
            'description' => $description,
            'metadata' => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
