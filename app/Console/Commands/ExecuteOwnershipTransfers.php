<?php

namespace App\Console\Commands;

use App\Services\ProjectTransferService;
use Illuminate\Console\Command;

class ExecuteOwnershipTransfers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ownership-transfers:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute due scheduled ownership transfers and expire stale pending invites.';

    public function handle(ProjectTransferService $service): int
    {
        try {
            $executed = $service->executeDueScheduled();
            $expired = $service->expireStale();

            $this->info("Executed: {$executed}, Expired: {$expired}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error running ownership transfers: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
