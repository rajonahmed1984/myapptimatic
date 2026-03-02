<?php

namespace App\Console\Commands;

use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPayrollCoordinatedAdvances extends Command
{
    protected $signature = 'payroll:backfill-coordinated-advances
        {--period= : Backfill a specific period key (YYYY-MM)}
        {--dry-run : Preview changes without writing to database}
        {--chunk=200 : Payroll periods chunk size}';

    protected $description = 'Backfill missing coordinated salary advances into already generated payroll items (idempotent).';

    public function __construct(
        private PayrollService $payrollService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $periodKey = trim((string) ($this->option('period') ?? ''));
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(50, (int) $this->option('chunk'));

        if ($periodKey !== '' && ! preg_match('/^\d{4}-\d{2}$/', $periodKey)) {
            $this->error('Invalid --period format. Use YYYY-MM, for example 2026-05.');

            return self::FAILURE;
        }

        $query = PayrollPeriod::query()
            ->whereHas('payrollItems')
            ->when($periodKey !== '', fn ($q) => $q->where('period_key', $periodKey))
            ->orderBy('id');

        $totalPeriods = (clone $query)->count();
        if ($totalPeriods === 0) {
            $this->info('No generated payroll periods found for backfill.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[Dry run] ' : '')."Processing {$totalPeriods} payroll period(s)...");

        $stats = [
            'periods' => 0,
            'items_checked' => 0,
            'items_updated' => 0,
            'total_added_advance' => 0.0,
        ];

        $query->chunkById($chunk, function ($periods) use (&$stats, $dryRun): void {
            foreach ($periods as $period) {
                /** @var PayrollPeriod $period */
                $stats['periods']++;

                if (! $period->start_date || ! $period->end_date) {
                    continue;
                }

                $expectedByEmployee = $this->payrollService->coordinatedPayrollAdvancesByEmployee(
                    (string) $period->period_key,
                    $period->start_date->copy()->startOfDay(),
                    $period->end_date->copy()->endOfDay()
                );

                if (empty($expectedByEmployee)) {
                    continue;
                }

                $items = PayrollItem::query()
                    ->where('payroll_period_id', $period->id)
                    ->whereIn('employee_id', array_keys($expectedByEmployee))
                    ->get(['id', 'employee_id', 'advances', 'net_pay']);

                foreach ($items as $item) {
                    $stats['items_checked']++;

                    $expectedAdvance = round((float) ($expectedByEmployee[$item->employee_id] ?? 0), 2, PHP_ROUND_HALF_UP);
                    $currentAdvance = round((float) ($item->advances ?? 0), 2, PHP_ROUND_HALF_UP);

                    if ($expectedAdvance <= $currentAdvance) {
                        continue;
                    }

                    $delta = round($expectedAdvance - $currentAdvance, 2, PHP_ROUND_HALF_UP);
                    $newNet = round((float) ($item->net_pay ?? 0) - $delta, 2, PHP_ROUND_HALF_UP);

                    if (! $dryRun) {
                        DB::table('payroll_items')
                            ->where('id', $item->id)
                            ->update([
                                'advances' => $expectedAdvance,
                                'net_pay' => $newNet,
                                'updated_at' => now(),
                            ]);
                    }

                    $stats['items_updated']++;
                    $stats['total_added_advance'] += $delta;
                }
            }
        });

        $this->table(
            ['Mode', 'Periods', 'Items Checked', 'Items Updated', 'Advance Added'],
            [[
                $dryRun ? 'Dry run' : 'Applied',
                $stats['periods'],
                $stats['items_checked'],
                $stats['items_updated'],
                number_format($stats['total_added_advance'], 2),
            ]]
        );

        if ($dryRun) {
            $this->info('Dry run complete. Re-run without --dry-run to apply changes.');
        } else {
            $this->info('Backfill complete.');
        }

        return self::SUCCESS;
    }
}

