<?php

namespace App\Console\Commands;

use App\Services\SaasMetricEventService;
use Illuminate\Console\Command;

class BackfillSaasMetricsCommand extends Command
{
    protected $signature = 'finance:backfill-saas-metrics {--dry-run}';

    protected $description = 'Backfill append-only SaaS metric movement events from V2 subscription periods and lifecycle events.';

    public function handle(SaasMetricEventService $events): int
    {
        $this->line(json_encode($events->backfill((bool) $this->option('dry-run'))));

        return self::SUCCESS;
    }
}
