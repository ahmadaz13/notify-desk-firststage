<?php

namespace App\Console\Commands;

use App\Services\SubscriptionBillingService;
use Illuminate\Console\Command;

class GenerateSubscriptionRenewalsCommand extends Command
{
    protected $signature = 'finance:generate-subscription-renewals {--through=} {--dry-run}';

    protected $description = 'Generate due V2 subscription renewal invoices idempotently.';

    public function handle(SubscriptionBillingService $billing): int
    {
        $counts = $billing->generateRenewals($this->option('through') ?: null, (bool) $this->option('dry-run'));
        $this->line(json_encode($counts));

        return self::SUCCESS;
    }
}
