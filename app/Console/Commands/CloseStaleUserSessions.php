<?php

namespace App\Console\Commands;

use App\Models\UserSession;
use Illuminate\Console\Command;

class CloseStaleUserSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user-sessions:close-stale';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Close stale user sessions that have been inactive for too long';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            // Calculate threshold: session lifetime + 10 minutes buffer, minimum 30 minutes
            $sessionLifetimeMinutes = (int) config('session.lifetime', 120);
            $thresholdMinutes = max($sessionLifetimeMinutes + 10, 30);
            $cutoffTime = now()->subMinutes($thresholdMinutes);

            // Find and close stale sessions
            $closed = UserSession::where('logout_at', null)
                ->where('last_seen_at', '<', $cutoffTime)
                ->chunkById(100, function ($sessions) {
                    foreach ($sessions as $session) {
                        $session->update(['logout_at' => $session->last_seen_at]);
                    }
                });

            // Count updated records
            $count = UserSession::where('logout_at', null)
                ->where('last_seen_at', '<', $cutoffTime)
                ->count();

            $this->info("Closed {$count} stale user sessions.");
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error closing stale sessions: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
