<?php

namespace App\Console\Commands;

use App\Services\RecurringExpenseService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateRecurringExpensesCommand extends Command
{
    protected $signature = 'finance:generate-recurring-expenses {--date= : Business date through which obligations should be generated}';

    protected $description = 'Generate due recurring operating expense obligations without paying them';

    public function handle(RecurringExpenseService $recurringExpenses): int
    {
        $businessDate = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : today();

        $generated = $recurringExpenses->generateDueObligations($businessDate);
        $this->info("Generated {$generated} recurring expense obligation(s).");

        return Command::SUCCESS;
    }
}
