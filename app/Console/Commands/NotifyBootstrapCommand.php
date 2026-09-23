<?php

namespace App\Console\Commands;

use App\Services\CompanyAccountBootstrapService;
use Illuminate\Console\Command;

class NotifyBootstrapCommand extends Command
{
    protected $signature = 'notify:bootstrap';

    protected $description = 'Idempotently ensure V1 system data: chart of accounts and the CASH-BOX / CLIQ company accounts.';

    public function handle(CompanyAccountBootstrapService $companyAccounts): int
    {
        foreach ($companyAccounts->ensureDefaults() as $code => $account) {
            $this->line(sprintf('%s: #%d %s (%s, %s)', $code, $account->id, $account->name_en, $account->type, $account->currency));
        }

        $this->info('Notify Desk bootstrap complete.');

        return self::SUCCESS;
    }
}
