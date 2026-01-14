<?php

namespace App\Console\Commands;

use App\Models\EmployeeSession;
use Illuminate\Console\Command;

class CloseStaleEmployeeSessions extends Command
{
    protected $signature = 'employee-sessions:close-stale';

    protected $description = 'Mark stale employee sessions as closed to keep online status accurate';

    public function handle(): int
    {
        $thresholdMinutes = max(config('session.lifetime', 120) + 10, 30);
        $cutoff = now()->subMinutes($thresholdMinutes);

        $this->info("Closing sessions inactive since {$cutoff->toDateTimeString()}...");

        $count = 0;

        EmployeeSession::query()
            ->whereNull('logout_at')
            ->where('last_seen_at', '<', $cutoff)
            ->chunkById(100, function ($sessions) use (&$count, $cutoff) {
                foreach ($sessions as $session) {
                    $session->update([
                        'logout_at' => $session->last_seen_at ?? $cutoff,
                    ]);
                    $count++;
                }
            });

        $this->info("Closed {$count} stale employee sessions.");

        return self::SUCCESS;
    }
}
