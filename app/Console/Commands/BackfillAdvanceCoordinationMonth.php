<?php

namespace App\Console\Commands;

use App\Models\EmployeePayout;
use Illuminate\Console\Command;

class BackfillAdvanceCoordinationMonth extends Command
{
    protected $signature = 'payroll:backfill-advance-coordination-month
        {--dry-run : Preview affected rows without writing}
        {--chunk=500 : Records chunk size}';

    protected $description = 'Backfill coordination_month for historical payroll-scope salary advances based on paid_at month.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(50, (int) $this->option('chunk'));

        $query = EmployeePayout::query()
            ->whereNotNull('paid_at')
            ->orderBy('id');

        $stats = [
            'checked' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        $query->chunkById($chunk, function ($rows) use (&$stats, $dryRun): void {
            foreach ($rows as $payout) {
                $stats['checked']++;

                $metadata = is_array($payout->metadata) ? $payout->metadata : [];
                if (($metadata['type'] ?? null) !== 'advance' || ($metadata['advance_scope'] ?? null) !== 'payroll') {
                    $stats['skipped']++;

                    continue;
                }

                $existingMonth = trim((string) ($metadata['coordination_month'] ?? ''));
                if ($existingMonth !== '' && preg_match('/^\d{4}-\d{2}$/', $existingMonth)) {
                    $stats['skipped']++;

                    continue;
                }

                if (! $payout->paid_at) {
                    $stats['skipped']++;

                    continue;
                }

                $coordinationDate = $payout->paid_at->copy()->startOfMonth();
                $metadata['coordination_month'] = $coordinationDate->format('Y-m');
                $metadata['coordination_month_label'] = $coordinationDate->format('F Y');

                if (! $dryRun) {
                    $payout->forceFill([
                        'metadata' => $metadata,
                    ])->save();
                }

                $stats['updated']++;
            }
        });

        $this->table(
            ['Mode', 'Checked', 'Updated', 'Skipped'],
            [[
                $dryRun ? 'Dry run' : 'Applied',
                $stats['checked'],
                $stats['updated'],
                $stats['skipped'],
            ]]
        );

        if ($dryRun) {
            $this->info('Dry run complete. Re-run without --dry-run to apply.');
        } else {
            $this->info('Backfill complete.');
        }

        return self::SUCCESS;
    }
}

