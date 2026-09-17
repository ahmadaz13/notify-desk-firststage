<?php

namespace App\Console\Commands;

use App\Services\SubscriptionBillingService;
use Illuminate\Console\Command;

class BackfillSubscriptionPeriodsCommand extends Command
{
    protected $signature = 'finance:backfill-subscription-periods {--dry-run}';

    protected $description = 'Backfill deterministic initial V2 subscription billing periods without creating invoices.';

    public function handle(SubscriptionBillingService $billing): int
    {
        $counts = $billing->backfillInitialPeriods((bool) $this->option('dry-run'));
        $this->line(json_encode($counts));

        return self::SUCCESS;
    }
}
