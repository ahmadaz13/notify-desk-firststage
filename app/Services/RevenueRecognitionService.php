<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\CreditNote;
use App\Models\CreditNoteLine;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\JournalEntry;
use App\Models\RevenueRecognitionAdjustment;
use App\Models\RevenueRecognitionPeriod;
use App\Models\RevenueRecognitionSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevenueRecognitionService
{
    public function __construct(
        private readonly AccountingSetupService $setup,
        private readonly JournalPostingService $journals
    ) {
    }

    public function createSchedulesForInvoice(Invoice $invoice, ?int $userId = null): array
    {
        $invoice->loadMissing(['lines', 'subscription']);
        if ($invoice->status !== Invoice::STATUS_ISSUED) {
            return [];
        }

        return $invoice->lines
            ->map(fn (InvoiceLine $line) => $this->createScheduleForInvoiceLine($line, $userId))
            ->filter()
            ->all();
    }

    public function createScheduleForInvoiceLine(InvoiceLine $line, ?int $userId = null): ?RevenueRecognitionSchedule
    {
        $line->loadMissing(['invoice.subscription']);
        $invoice = $line->invoice;
        if ($invoice === null || $invoice->status !== Invoice::STATUS_ISSUED) {
            return null;
        }

        $amount = $this->recognizableMinor($line);
        if ($amount <= 0) {
            return null;
        }

        $existing = RevenueRecognitionSchedule::where('invoice_line_id', $line->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $configuration = $this->configurationForLine($line);

        return DB::transaction(function () use ($line, $invoice, $userId, $amount, $configuration) {
            $schedule = RevenueRecognitionSchedule::create([
                'invoice_line_id' => $line->id,
                'invoice_id' => $invoice->id,
                'subscription_id' => $invoice->subscription_id,
                'revenue_account_id' => $configuration['revenue_account_id'],
                'policy' => $configuration['policy'],
                'currency' => $invoice->currency ?: 'JOD',
                'original_recognizable_minor' => $amount,
                'recognition_start_date' => $configuration['start']?->toDateString(),
                'recognition_end_date' => $configuration['end']?->toDateString(),
                'period_count' => $configuration['period_count'],
                'status' => $configuration['status'],
                'requires_manual_confirmation' => $configuration['requires_manual_confirmation'],
                'created_by' => $userId ?? $invoice->created_by,
            ]);

            if ($configuration['status'] === RevenueRecognitionSchedule::STATUS_ACTIVE && $configuration['period_count'] > 0) {
                $this->createPeriods($schedule, $configuration['start'], $configuration['end'], $configuration['period_count']);
            }

            DB::table('activity_logs')->insert([
                'client_id' => $invoice->client_id,
                'user_id' => $userId,
                'type' => 'revenue_schedule_created',
                'description' => 'تم إنشاء جدول تحقق إيراد لسطر فاتورة '.$invoice->invoice_number,
                'metadata' => json_encode([
                    'schedule_id' => $schedule->id,
                    'invoice_id' => $invoice->id,
                    'invoice_line_id' => $line->id,
                    'policy' => $schedule->policy,
                    'amount_minor' => $amount,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $schedule->fresh(['periods', 'revenueAccount']);
        });
    }

    public function confirmPointInTimeService(
        RevenueRecognitionSchedule $schedule,
        Carbon|string $recognitionDate,
        ?string $note,
        ?int $userId
    ): RevenueRecognitionSchedule {
        $schedule->loadMissing(['periods', 'invoice']);
        if (! $schedule->requires_manual_confirmation) {
            throw ValidationException::withMessages(['schedule_id' => 'هذا الجدول لا يحتاج تأكيداً يدوياً.']);
        }
        if (! in_array($schedule->policy, [RevenueRecognitionSchedule::POLICY_POINT_IN_TIME_SERVICE, RevenueRecognitionSchedule::POLICY_MANUAL_REVIEW], true)) {
            throw ValidationException::withMessages(['schedule_id' => 'سياسة هذا الجدول لا تسمح بهذا التأكيد اليدوي.']);
        }
        if ($schedule->periods()->where('status', RevenueRecognitionPeriod::STATUS_RECOGNIZED)->exists()) {
            throw ValidationException::withMessages(['schedule_id' => 'تم الاعتراف بإيراد هذا الجدول مسبقاً.']);
        }

        $date = Carbon::parse($recognitionDate)->startOfDay();
        $remaining = $this->scheduleRemainingDeferredMinor($schedule);
        if ($remaining <= 0) {
            throw ValidationException::withMessages(['schedule_id' => 'لا يوجد مبلغ متبق للاعتراف.']);
        }

        return DB::transaction(function () use ($schedule, $date, $note, $userId, $remaining) {
            $schedule->periods()->where('status', RevenueRecognitionPeriod::STATUS_PENDING)->delete();
            $period = $schedule->periods()->create([
                'period_index' => 1,
                'period_start' => $date->toDateString(),
                'period_end' => $date->toDateString(),
                'scheduled_minor' => $remaining,
                'recognized_minor' => 0,
                'status' => RevenueRecognitionPeriod::STATUS_PENDING,
            ]);

            $schedule->update([
                'policy' => RevenueRecognitionSchedule::POLICY_POINT_IN_TIME_SERVICE,
                'recognition_start_date' => $date->toDateString(),
                'recognition_end_date' => $date->toDateString(),
                'period_count' => 1,
                'status' => RevenueRecognitionSchedule::STATUS_ACTIVE,
            ]);

            DB::table('activity_logs')->insert([
                'client_id' => $schedule->invoice?->client_id,
                'user_id' => $userId,
                'type' => 'one_time_service_recognition_confirmed',
                'description' => 'تم تأكيد اكتمال خدمة للاعتراف بالإيراد',
                'metadata' => json_encode([
                    'schedule_id' => $schedule->id,
                    'period_id' => $period->id,
                    'recognition_date' => $date->toDateString(),
                    'note' => $note,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $schedule->fresh(['periods', 'revenueAccount']);
        });
    }

    public function recognizeDue(Carbon|string|null $through = null, bool $dryRun = false): array
    {
        $throughDate = $through === null ? now()->startOfDay() : Carbon::parse($through)->startOfDay();
        $counts = ['recognized' => 0, 'already_recognized' => 0, 'skipped' => 0, 'ambiguous' => 0, 'dry_run' => $dryRun];

        RevenueRecognitionPeriod::with(['schedule.revenueAccount', 'schedule.invoiceLine', 'schedule.invoice'])
            ->where('status', RevenueRecognitionPeriod::STATUS_PENDING)
            ->whereDate('period_end', '<=', $throughDate->toDateString())
            ->orderBy('period_end')
            ->orderBy('id')
            ->chunkById(100, function ($periods) use ($dryRun, &$counts) {
                foreach ($periods as $period) {
                    try {
                        if ($period->schedule === null || $period->schedule->status !== RevenueRecognitionSchedule::STATUS_ACTIVE) {
                            $counts['skipped']++;
                            continue;
                        }
                        if ($this->netPeriodRecognizableMinor($period) <= 0) {
                            if (! $dryRun) {
                                $period->update([
                                    'recognized_minor' => 0,
                                    'status' => RevenueRecognitionPeriod::STATUS_RECOGNIZED,
                                    'recognized_at' => now(),
                                ]);
                                $this->completeScheduleIfDone($period->schedule);
                            }
                            $counts['already_recognized']++;
                            continue;
                        }
                        if (! $dryRun) {
                            $this->recognizePeriod($period);
                        }
                        $counts['recognized']++;
                    } catch (\Throwable $exception) {
                        report($exception);
                        $counts['ambiguous']++;
                    }
                }
            });

        return $counts;
    }

    public function recognizePeriod(RevenueRecognitionPeriod $period): ?JournalEntry
    {
        $period->loadMissing(['schedule.revenueAccount', 'schedule.invoiceLine', 'schedule.invoice']);
        if ($period->status === RevenueRecognitionPeriod::STATUS_RECOGNIZED) {
            return $period->journalEntry;
        }

        $amount = $this->netPeriodRecognizableMinor($period);
        if ($amount <= 0) {
            $period->update([
                'recognized_minor' => 0,
                'status' => RevenueRecognitionPeriod::STATUS_RECOGNIZED,
                'recognized_at' => now(),
            ]);
            $this->completeScheduleIfDone($period->schedule);

            return null;
        }

        $entryDate = $period->period_end->copy()->startOfDay();
        $metadata = [
            'invoice_id' => $period->schedule->invoice_id,
            'invoice_line_id' => $period->schedule->invoice_line_id,
            'schedule_id' => $period->schedule->id,
            'period_id' => $period->id,
            'period_start' => $period->period_start->toDateString(),
            'period_end' => $period->period_end->toDateString(),
            'recognition_policy' => $period->schedule->policy,
        ];

        if ($this->journals->periodFor($entryDate)->status === AccountingPeriod::STATUS_CLOSED) {
            $metadata['original_service_period_end'] = $entryDate->toDateString();
            $metadata['delayed_recognition_reason'] = 'original_accounting_period_closed';
            $entryDate = now()->startOfDay();
        }

        $journal = $this->journals->post([
            'event_type' => 'revenue_recognized',
            'event_key' => 'revenue-recognition:period:'.$period->id,
            'source_type' => RevenueRecognitionPeriod::class,
            'source_id' => $period->id,
            'entry_date' => $entryDate,
            'description' => 'Revenue recognition for invoice line '.$period->schedule->invoice_line_id,
            'created_by' => $period->schedule->created_by,
            'metadata' => $metadata,
        ], [
            $this->debit($this->setup->systemAccount('deferred_revenue')->id, $amount, 'Recognize deferred revenue', $period->schedule->invoice?->client_id),
            $this->credit($period->schedule->revenue_account_id, $amount, 'Recognized revenue', $period->schedule->invoice?->client_id),
        ]);

        $period->update([
            'recognized_minor' => $amount,
            'status' => RevenueRecognitionPeriod::STATUS_RECOGNIZED,
            'recognized_at' => now(),
            'journal_entry_id' => $journal->id,
        ]);
        $this->completeScheduleIfDone($period->schedule);

        return $journal;
    }

    public function creditNoteRecognitionSplit(CreditNote $creditNote): array
    {
        $creditNote->loadMissing(['lines.invoiceLine', 'originalInvoice.lines']);
        $deferred = 0;
        $recognizedByAccount = [];
        $adjustments = [];

        foreach ($creditNote->lines as $line) {
            $amount = (int) $line->subtotal_minor;
            if ($amount <= 0) {
                continue;
            }

            $schedule = $line->invoice_line_id
                ? RevenueRecognitionSchedule::where('invoice_line_id', $line->invoice_line_id)->first()
                : null;

            if ($schedule === null) {
                $deferred += $amount;
                continue;
            }

            $effectiveDate = Carbon::parse($creditNote->issue_date)->startOfDay();
            $future = min($amount, $this->scheduleRemainingDeferredMinor($schedule, $effectiveDate));
            $recognized = $amount - $future;
            $deferred += $future;

            if ($future > 0) {
                $adjustments = array_merge($adjustments, $this->buildFutureDeferredAdjustments($schedule, $line, $future, $effectiveDate, false));
            }
            if ($recognized > 0) {
                $recognizedByAccount[$schedule->revenue_account_id] = ($recognizedByAccount[$schedule->revenue_account_id] ?? 0) + $recognized;
                $adjustments[] = [
                    'schedule' => $schedule,
                    'credit_note_line' => $line,
                    'type' => RevenueRecognitionAdjustment::TYPE_RECOGNIZED_REVENUE_REDUCTION,
                    'amount_minor' => $recognized,
                    'effective_date' => $effectiveDate,
                    'period_id' => null,
                ];
            }
        }

        return [
            'deferred_minor' => $deferred,
            'recognized_by_account' => $recognizedByAccount,
            'adjustments' => $adjustments,
        ];
    }

    public function persistCreditNoteAdjustments(CreditNote $creditNote, ?JournalEntry $journal = null): void
    {
        $lineIds = $creditNote->lines()->pluck('id');
        if ($lineIds->isEmpty() || RevenueRecognitionAdjustment::whereIn('credit_note_line_id', $lineIds)->exists()) {
            return;
        }

        $split = $this->creditNoteRecognitionSplit($creditNote);
        foreach ($split['adjustments'] as $adjustment) {
            RevenueRecognitionAdjustment::firstOrCreate(
                [
                    'credit_note_line_id' => $adjustment['credit_note_line']->id,
                    'adjustment_type' => $adjustment['type'],
                    'revenue_recognition_period_id' => $adjustment['period_id'],
                ],
                [
                    'revenue_recognition_schedule_id' => $adjustment['schedule']->id,
                    'amount_minor' => $adjustment['amount_minor'],
                    'effective_date' => $adjustment['effective_date']->toDateString(),
                    'journal_entry_id' => $journal?->id,
                    'metadata' => [
                        'credit_note_id' => $creditNote->id,
                        'credit_note_number' => $creditNote->credit_note_number,
                        'source' => 'credit_note_accounting',
                    ],
                ]
            );
        }
    }

    public function applyHistoricalCreditAdjustments(): array
    {
        $counts = ['created' => 0, 'existing' => 0, 'ambiguous' => 0, 'skipped' => 0];

        CreditNote::with(['lines'])
            ->where('status', CreditNote::STATUS_ISSUED)
            ->orderBy('id')
            ->each(function (CreditNote $creditNote) use (&$counts) {
                foreach ($creditNote->lines as $line) {
                    if ($line->invoice_line_id === null || (int) $line->subtotal_minor <= 0) {
                        $counts[$line->invoice_line_id === null ? 'ambiguous' : 'skipped']++;
                        continue;
                    }
                    $schedule = RevenueRecognitionSchedule::where('invoice_line_id', $line->invoice_line_id)->first();
                    if ($schedule === null) {
                        $counts['skipped']++;
                        continue;
                    }
                    $before = RevenueRecognitionAdjustment::where('credit_note_line_id', $line->id)->count();
                    $split = $this->creditNoteRecognitionSplit($creditNote);
                    foreach ($split['adjustments'] as $adjustment) {
                        RevenueRecognitionAdjustment::firstOrCreate(
                            [
                                'credit_note_line_id' => $adjustment['credit_note_line']->id,
                                'adjustment_type' => $adjustment['type'],
                                'revenue_recognition_period_id' => $adjustment['period_id'],
                            ],
                            [
                                'revenue_recognition_schedule_id' => $adjustment['schedule']->id,
                                'amount_minor' => $adjustment['amount_minor'],
                                'effective_date' => $adjustment['effective_date']->toDateString(),
                                'metadata' => [
                                    'credit_note_id' => $creditNote->id,
                                    'historical_pre_e2b' => true,
                                    'preserves_existing_credit_note_journal' => true,
                                ],
                            ]
                        );
                    }
                    $after = RevenueRecognitionAdjustment::where('credit_note_line_id', $line->id)->count();
                    $counts[$after > $before ? 'created' : 'existing']++;
                }
            });

        return $counts;
    }

    public function dashboard(): array
    {
        $due = RevenueRecognitionPeriod::query()
            ->where('status', RevenueRecognitionPeriod::STATUS_PENDING)
            ->whereDate('period_end', '<=', now()->toDateString())
            ->count();

        $schedules = RevenueRecognitionSchedule::with(['invoice', 'invoiceLine', 'revenueAccount', 'periods', 'adjustments'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return [
            'schedule_count' => RevenueRecognitionSchedule::count(),
            'due_period_count' => $due,
            'needs_review_count' => RevenueRecognitionSchedule::where('status', RevenueRecognitionSchedule::STATUS_NEEDS_REVIEW)->count(),
            'recognized_saas_mtd_minor' => $this->recognizedForAccountMonthToDate('saas_subscription_revenue'),
            'recognized_one_time_mtd_minor' => $this->recognizedForAccountMonthToDate('one_time_service_revenue'),
            'deferred_remaining_minor' => $this->operationalDeferredRevenueMinor(),
            'schedule_rows' => $schedules->map(fn (RevenueRecognitionSchedule $schedule) => $this->dashboardScheduleRow($schedule)),
            'review_queue' => RevenueRecognitionSchedule::with(['invoice', 'invoiceLine', 'revenueAccount'])
                ->where('status', RevenueRecognitionSchedule::STATUS_NEEDS_REVIEW)
                ->orderBy('id')
                ->limit(20)
                ->get(),
        ];
    }

    private function dashboardScheduleRow(RevenueRecognitionSchedule $schedule): array
    {
        $schedule->loadMissing(['invoice', 'revenueAccount', 'periods', 'adjustments']);

        return [
            'schedule' => $schedule,
            'invoice_number' => $schedule->invoice?->invoice_number,
            'invoice_line_id' => $schedule->invoice_line_id,
            'policy' => $schedule->policy,
            'original_recognizable_minor' => (int) $schedule->original_recognizable_minor,
            'recognized_minor' => (int) $schedule->periods->sum('recognized_minor'),
            'future_deferred_minor' => $this->scheduleRemainingDeferredMinor($schedule),
            'adjustments_minor' => (int) $schedule->adjustments->sum('amount_minor'),
            'next_recognition_date' => $schedule->periods
                ->where('status', RevenueRecognitionPeriod::STATUS_PENDING)
                ->sortBy('period_end')
                ->first()?->period_end,
            'status' => $schedule->status,
            'revenue_account_code' => $schedule->revenueAccount?->code,
            'revenue_account_name_ar' => $schedule->revenueAccount?->name_ar,
        ];
    }

    public function operationalDeferredRevenueMinor(): int
    {
        return (int) RevenueRecognitionSchedule::with(['periods', 'adjustments'])->get()
            ->sum(fn (RevenueRecognitionSchedule $schedule) => $this->scheduleRemainingDeferredMinor($schedule));
    }

    public function recognizedProjectionByAccount(): array
    {
        $rows = [];
        foreach (['saas_subscription_revenue', 'one_time_service_revenue'] as $mapping) {
            $account = $this->setup->systemAccount($mapping);
            $recognized = (int) RevenueRecognitionSchedule::where('revenue_account_id', $account->id)
                ->with('periods')
                ->get()
                ->sum(fn (RevenueRecognitionSchedule $schedule) => $schedule->periods->sum('recognized_minor'));
            $reductions = (int) RevenueRecognitionAdjustment::whereHas('schedule', fn ($query) => $query->where('revenue_account_id', $account->id))
                ->where('adjustment_type', RevenueRecognitionAdjustment::TYPE_RECOGNIZED_REVENUE_REDUCTION)
                ->sum('amount_minor');
            $rows[$mapping] = [
                'chart_account' => $account,
                'operational_minor' => $recognized - $reductions,
            ];
        }

        return $rows;
    }

    public function netPeriodRecognizableMinor(RevenueRecognitionPeriod $period): int
    {
        $adjustments = (int) RevenueRecognitionAdjustment::where('revenue_recognition_period_id', $period->id)
            ->where('adjustment_type', RevenueRecognitionAdjustment::TYPE_FUTURE_DEFERRED_REDUCTION)
            ->sum('amount_minor');

        return max(0, (int) $period->scheduled_minor - $adjustments - (int) $period->recognized_minor);
    }

    public function scheduleRemainingDeferredMinor(RevenueRecognitionSchedule $schedule, Carbon|string|null $effectiveDate = null): int
    {
        $schedule->loadMissing(['periods', 'adjustments']);
        $recognized = (int) $schedule->periods->sum('recognized_minor');
        $futureReductions = (int) $schedule->adjustments
            ->where('adjustment_type', RevenueRecognitionAdjustment::TYPE_FUTURE_DEFERRED_REDUCTION)
            ->sum('amount_minor');

        if ($effectiveDate === null) {
            return max(0, (int) $schedule->original_recognizable_minor - $futureReductions - $recognized);
        }

        $date = Carbon::parse($effectiveDate)->startOfDay();
        $periodFuture = $schedule->periods
            ->filter(fn (RevenueRecognitionPeriod $period) => $period->status === RevenueRecognitionPeriod::STATUS_PENDING && $period->period_end->greaterThanOrEqualTo($date))
            ->sum(fn (RevenueRecognitionPeriod $period) => $this->netPeriodRecognizableMinor($period));

        if ($schedule->periods->isEmpty()) {
            return max(0, (int) $schedule->original_recognizable_minor - $futureReductions - $recognized);
        }

        return max(0, (int) $periodFuture);
    }

    public function recognizableMinor(InvoiceLine $line): int
    {
        return max(0, (int) $line->total_minor - (int) $line->tax_minor);
    }

    private function configurationForLine(InvoiceLine $line): array
    {
        $invoice = $line->invoice;
        $subscription = $invoice?->subscription;
        $setupDate = $subscription?->current_period_start ?: $invoice?->issue_date;

        return match ($line->line_type) {
            InvoiceLine::TYPE_SUBSCRIPTION => $this->subscriptionConfiguration($line),
            InvoiceLine::TYPE_SETUP_FEE => [
                'policy' => RevenueRecognitionSchedule::POLICY_POINT_IN_TIME_SETUP,
                'revenue_account_id' => $this->setup->systemAccount('one_time_service_revenue')->id,
                'start' => $setupDate ? Carbon::parse($setupDate)->startOfDay() : null,
                'end' => $setupDate ? Carbon::parse($setupDate)->startOfDay() : null,
                'period_count' => $setupDate ? 1 : 0,
                'status' => $setupDate ? RevenueRecognitionSchedule::STATUS_ACTIVE : RevenueRecognitionSchedule::STATUS_NEEDS_REVIEW,
                'requires_manual_confirmation' => false,
            ],
            InvoiceLine::TYPE_ONE_TIME_SERVICE => [
                'policy' => RevenueRecognitionSchedule::POLICY_POINT_IN_TIME_SERVICE,
                'revenue_account_id' => $this->setup->systemAccount('one_time_service_revenue')->id,
                'start' => null,
                'end' => null,
                'period_count' => 0,
                'status' => RevenueRecognitionSchedule::STATUS_NEEDS_REVIEW,
                'requires_manual_confirmation' => true,
            ],
            default => [
                'policy' => RevenueRecognitionSchedule::POLICY_MANUAL_REVIEW,
                'revenue_account_id' => $this->setup->systemAccount('one_time_service_revenue')->id,
                'start' => null,
                'end' => null,
                'period_count' => 0,
                'status' => RevenueRecognitionSchedule::STATUS_NEEDS_REVIEW,
                'requires_manual_confirmation' => true,
            ],
        };
    }

    private function subscriptionConfiguration(InvoiceLine $line): array
    {
        $invoice = $line->invoice;
        $subscription = $invoice?->subscription;
        $start = $invoice?->billing_period_start ? Carbon::parse($invoice->billing_period_start)->startOfDay() : null;
        $end = $invoice?->billing_period_end ? Carbon::parse($invoice->billing_period_end)->startOfDay() : null;
        $isAnnual = ($subscription?->billing_interval_v2 ?? $subscription?->billing_type) === \App\Models\PlanPrice::ANNUAL;
        $periodCount = $isAnnual ? 12 : 1;

        if ($start === null || $end === null || $end->lessThan($start)) {
            return [
                'policy' => $isAnnual ? RevenueRecognitionSchedule::POLICY_SUBSCRIPTION_ANNUAL : RevenueRecognitionSchedule::POLICY_SUBSCRIPTION_MONTHLY,
                'revenue_account_id' => $this->setup->systemAccount('saas_subscription_revenue')->id,
                'start' => $start,
                'end' => $end,
                'period_count' => 0,
                'status' => RevenueRecognitionSchedule::STATUS_NEEDS_REVIEW,
                'requires_manual_confirmation' => false,
            ];
        }

        return [
            'policy' => $isAnnual ? RevenueRecognitionSchedule::POLICY_SUBSCRIPTION_ANNUAL : RevenueRecognitionSchedule::POLICY_SUBSCRIPTION_MONTHLY,
            'revenue_account_id' => $this->setup->systemAccount('saas_subscription_revenue')->id,
            'start' => $start,
            'end' => $end,
            'period_count' => $periodCount,
            'status' => RevenueRecognitionSchedule::STATUS_ACTIVE,
            'requires_manual_confirmation' => false,
        ];
    }

    private function createPeriods(RevenueRecognitionSchedule $schedule, Carbon $start, Carbon $end, int $periodCount): void
    {
        $amounts = $this->distribute((int) $schedule->original_recognizable_minor, $periodCount);
        for ($index = 1; $index <= $periodCount; $index++) {
            $periodStart = $periodCount === 1 ? $start->copy() : $start->copy()->addMonthsNoOverflow($index - 1);
            $periodEnd = $periodCount === 1
                ? $end->copy()
                : ($index === $periodCount ? $end->copy() : $periodStart->copy()->addMonthNoOverflow()->subDay());

            $schedule->periods()->create([
                'period_index' => $index,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'scheduled_minor' => $amounts[$index - 1],
                'recognized_minor' => 0,
                'status' => RevenueRecognitionPeriod::STATUS_PENDING,
            ]);
        }
    }

    private function distribute(int $amount, int $periodCount): array
    {
        if ($periodCount <= 0) {
            return [];
        }

        $base = intdiv($amount, $periodCount);
        $remainder = $amount % $periodCount;
        $amounts = [];
        for ($i = 0; $i < $periodCount; $i++) {
            $amounts[] = $base + ($i < $remainder ? 1 : 0);
        }

        return $amounts;
    }

    private function buildFutureDeferredAdjustments(
        RevenueRecognitionSchedule $schedule,
        CreditNoteLine $line,
        int $amount,
        Carbon $effectiveDate,
        bool $persist
    ): array {
        $adjustments = [];
        $remaining = $amount;
        $periods = $schedule->periods()
            ->where('status', RevenueRecognitionPeriod::STATUS_PENDING)
            ->whereDate('period_end', '>=', $effectiveDate->toDateString())
            ->orderBy('period_end')
            ->orderBy('id')
            ->get();

        foreach ($periods as $period) {
            if ($remaining <= 0) {
                break;
            }
            $available = $this->netPeriodRecognizableMinor($period);
            if ($available <= 0) {
                continue;
            }
            $applied = min($remaining, $available);
            $adjustment = [
                'schedule' => $schedule,
                'credit_note_line' => $line,
                'type' => RevenueRecognitionAdjustment::TYPE_FUTURE_DEFERRED_REDUCTION,
                'amount_minor' => $applied,
                'effective_date' => $effectiveDate,
                'period_id' => $period->id,
            ];
            $adjustments[] = $adjustment;
            if ($persist) {
                RevenueRecognitionAdjustment::create([
                    'revenue_recognition_schedule_id' => $schedule->id,
                    'credit_note_line_id' => $line->id,
                    'adjustment_type' => $adjustment['type'],
                    'amount_minor' => $applied,
                    'effective_date' => $effectiveDate->toDateString(),
                    'revenue_recognition_period_id' => $period->id,
                ]);
            }
            $remaining -= $applied;
        }

        if ($remaining > 0 && $periods->isEmpty()) {
            $adjustments[] = [
                'schedule' => $schedule,
                'credit_note_line' => $line,
                'type' => RevenueRecognitionAdjustment::TYPE_FUTURE_DEFERRED_REDUCTION,
                'amount_minor' => $remaining,
                'effective_date' => $effectiveDate,
                'period_id' => null,
            ];
        }

        return $adjustments;
    }

    private function recognizedForAccountMonthToDate(string $mappingKey): int
    {
        $account = $this->setup->systemAccount($mappingKey);
        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        return (int) DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.event_type', 'revenue_recognized')
            ->whereBetween('journal_entries.entry_date', [$start, $end])
            ->where('journal_lines.chart_account_id', $account->id)
            ->sum('journal_lines.credit_minor');
    }

    private function completeScheduleIfDone(RevenueRecognitionSchedule $schedule): void
    {
        if (! $schedule->periods()->where('status', RevenueRecognitionPeriod::STATUS_PENDING)->exists()) {
            $schedule->update(['status' => RevenueRecognitionSchedule::STATUS_COMPLETED]);
        }
    }

    private function debit(int $accountId, int $amount, string $description, ?int $clientId = null): array
    {
        return [
            'chart_account_id' => $accountId,
            'debit_minor' => $amount,
            'credit_minor' => 0,
            'description' => $description,
            'client_id' => $clientId,
        ];
    }

    private function credit(int $accountId, int $amount, string $description, ?int $clientId = null): array
    {
        return [
            'chart_account_id' => $accountId,
            'debit_minor' => 0,
            'credit_minor' => $amount,
            'description' => $description,
            'client_id' => $clientId,
        ];
    }
}
