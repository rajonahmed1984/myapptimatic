<?php

namespace App\Console\Commands;

use App\Services\RecurringExpenseGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateRecurringExpenses extends Command
{
    protected $signature = 'expenses:generate-recurring {--date=} {--template=}';
    protected $description = 'Generate occurrences for active recurring expenses.';

    public function handle(RecurringExpenseGenerator $generator): int
    {
        $dateInput = $this->option('date');
        $templateId = $this->option('template') ? (int) $this->option('template') : null;
        $asOf = $dateInput ? Carbon::parse($dateInput)->startOfDay() : now()->startOfDay();

        $result = $generator->generate($asOf, $templateId, RecurringExpenseGenerator::DEFAULT_LOOKAHEAD_DAYS);

        $this->info("Recurring expenses processed: {$result['processed']}, created: {$result['created']}.");

        return self::SUCCESS;
    }
}
