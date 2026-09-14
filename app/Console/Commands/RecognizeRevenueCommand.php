<?php

namespace App\Console\Commands;

use App\Services\RevenueRecognitionService;
use Illuminate\Console\Command;

class RecognizeRevenueCommand extends Command
{
    protected $signature = 'finance:recognize-revenue {--through=} {--dry-run}';

    protected $description = 'Post due revenue-recognition journals for open accounting periods.';

    public function handle(RevenueRecognitionService $recognition): int
    {
        $counts = $recognition->recognizeDue($this->option('through') ?: null, (bool) $this->option('dry-run'));
        $this->line(json_encode($counts));

        return self::SUCCESS;
    }
}
