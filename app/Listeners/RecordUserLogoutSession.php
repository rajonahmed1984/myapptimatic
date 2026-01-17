<?php

namespace App\Listeners;

use App\Models\UserSession;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\DB;

class RecordUserLogoutSession
{
    /**
     * Handle the logout event and close the user session.
     */
    public function handle(Logout $event): void
    {
        // Only track specific guards
        if (!$this->shouldTrack($event->guard)) {
            return;
        }

        if (! $event->user) {
            return;
        }

        try {
            DB::transaction(function () use ($event) {
                $user = $event->user;
                $guard = $event->guard;
                $sessionId = session()->getId();
                $now = now();

                // Find and close the open session
                UserSession::where('user_type', get_class($user))
                    ->where('user_id', $user->id)
                    ->where('guard', $guard)
                    ->where('session_id', $sessionId)
                    ->whereNull('logout_at')
                    ->update(['logout_at' => $now]);
            });
        } catch (\Exception $e) {
            // Silently fail to not impact user logout
            \Log::error('Failed to record user logout session: ' . $e->getMessage());
        }
    }

    /**
     * Determine if we should track this guard.
     */
    private function shouldTrack(string $guard): bool
    {
        return in_array($guard, ['employee', 'web', 'sales', 'support'], true);
    }
}
