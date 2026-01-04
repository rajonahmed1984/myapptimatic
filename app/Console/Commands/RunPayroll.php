<?php

namespace App\Console\Commands;

use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunPayroll extends Command
{
    protected $signature = 'payroll:run {--period=}';

    protected $description = 'Generate payroll for a period (default current month) and mark drafts.';

    public function handle(PayrollService $service): int
    {
        $periodKey = $this->option('period') ?: Carbon::now()->format('Y-m');

        $period = $service->generatePeriod($periodKey);

        $this->info("Payroll period {$periodKey} ready with {$period->payrollItems()->count()} items.");

        return self::SUCCESS;
    }
}
