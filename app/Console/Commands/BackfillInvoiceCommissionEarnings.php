<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\CommissionService;
use Illuminate\Console\Command;

class BackfillInvoiceCommissionEarnings extends Command
{
    protected $signature = 'commissions:backfill-invoice-earnings
        {--rep-id= : Only process invoices that resolve to this sales rep}
        {--invoice-id=* : Only process specific invoice IDs}
        {--chunk=200 : Chunk size for processing}';

    protected $description = 'Backfill missing commission_earnings rows for historical paid invoices (idempotent).';

    public function __construct(
        private CommissionService $commissionService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $chunk = max(50, (int) $this->option('chunk'));
        $repId = $this->option('rep-id') !== null ? (int) $this->option('rep-id') : null;
        $invoiceIds = collect((array) $this->option('invoice-id'))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $query = Invoice::query()
            ->where(function ($builder) {
                $builder->whereNotNull('paid_at')
                    ->orWhere('status', 'paid');
            })
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->orderBy('id');

        if (! empty($invoiceIds)) {
            $query->whereIn('id', $invoiceIds);
        }

        if ($repId !== null && $repId > 0) {
            $query->where(function ($builder) use ($repId) {
                $builder->whereHas('subscription', function ($inner) use ($repId) {
                    $inner->where('sales_rep_id', $repId);
                })->orWhereHas('orders', function ($inner) use ($repId) {
                    $inner->where('sales_rep_id', $repId);
                })->orWhereHas('customer', function ($inner) use ($repId) {
                    $inner->where('default_sales_rep_id', $repId);
                });
            });
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No eligible invoices found for backfill.');

            return self::SUCCESS;
        }

        $this->info("Processing {$total} paid invoice(s)...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $stats = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $query->chunkById($chunk, function ($invoices) use (&$stats, $bar): void {
            foreach ($invoices as $invoice) {
                try {
                    $earning = $this->commissionService->createOrUpdateEarningOnInvoicePaid($invoice);
                    if (! $earning) {
                        $stats['skipped']++;
                    } elseif ($earning->wasRecentlyCreated) {
                        $stats['created']++;
                    } else {
                        $stats['updated']++;
                    }
                } catch (\Throwable $exception) {
                    $stats['failed']++;
                    $this->newLine();
                    $this->warn("Invoice #{$invoice->id} failed: {$exception->getMessage()}");
                }

                $stats['processed']++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Processed', 'Created', 'Updated', 'Skipped', 'Failed'],
            [[
                $stats['processed'],
                $stats['created'],
                $stats['updated'],
                $stats['skipped'],
                $stats['failed'],
            ]]
        );

        if ($stats['failed'] > 0) {
            $this->warn('Backfill finished with errors. Re-run after checking logs/messages.');

            return self::FAILURE;
        }

        $this->info('Backfill completed successfully.');

        return self::SUCCESS;
    }
}

