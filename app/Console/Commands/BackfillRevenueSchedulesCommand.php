<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\RevenueRecognitionSchedule;
use App\Services\RevenueRecognitionService;
use Illuminate\Console\Command;

class BackfillRevenueSchedulesCommand extends Command
{
    protected $signature = 'finance:backfill-revenue-schedules {--dry-run}';

    protected $description = 'Create missing revenue-recognition schedules for issued V2 invoice lines.';

    public function handle(RevenueRecognitionService $recognition): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $counts = [
            'created' => 0,
            'existing' => 0,
            'needs_review' => 0,
            'ambiguous' => 0,
            'skipped' => 0,
            'dry_run' => $dryRun,
        ];

        Invoice::with(['lines', 'subscription'])
            ->where('status', Invoice::STATUS_ISSUED)
            ->orderBy('id')
            ->each(function (Invoice $invoice) use ($recognition, $dryRun, &$counts) {
                foreach ($invoice->lines as $line) {
                    if (RevenueRecognitionSchedule::where('invoice_line_id', $line->id)->exists()) {
                        $counts['existing']++;
                        continue;
                    }

                    if ($recognition->recognizableMinor($line) <= 0) {
                        $counts['skipped']++;
                        continue;
                    }

                    if ($dryRun) {
                        $counts['created']++;
                        continue;
                    }

                    try {
                        $schedule = $recognition->createScheduleForInvoiceLine($line, $invoice->created_by);
                        if ($schedule === null) {
                            $counts['skipped']++;
                        } elseif ($schedule->status === RevenueRecognitionSchedule::STATUS_NEEDS_REVIEW) {
                            $counts['needs_review']++;
                        } else {
                            $counts['created']++;
                        }
                    } catch (\Throwable) {
                        $counts['ambiguous']++;
                    }
                }
            });

        if (! $dryRun) {
            $historical = $recognition->applyHistoricalCreditAdjustments();
            $counts['historical_credit_adjustments_created'] = $historical['created'];
            $counts['historical_credit_adjustments_existing'] = $historical['existing'];
            $counts['historical_credit_adjustments_ambiguous'] = $historical['ambiguous'];
        }

        $this->line(json_encode($counts));

        return self::SUCCESS;
    }
}
