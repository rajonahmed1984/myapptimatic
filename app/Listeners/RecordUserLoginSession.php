<?php

namespace App\Listeners;

use App\Models\UserSession;
use App\Models\UserActivityDaily;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class RecordUserLoginSession
{
    /**
     * Handle the login event and record a user session.
     */
    public function handle(Login $event): void
    {
        // Only track specific guards
        if (!$this->shouldTrack($event->guard)) {
            return;
        }

        try {
            DB::transaction(function () use ($event) {
                $user = $event->user;
                $guard = $event->guard;
                $sessionId = session()->getId();
                $now = now();

                // Create or update session record
                $sessionRecord = UserSession::firstOrCreate(
                    [
                        'user_type' => get_class($user),
                        'user_id' => $user->id,
                        'guard' => $guard,
                        'session_id' => $sessionId,
                    ],
                    [
                        'login_at' => $now,
                        'logout_at' => null,
                        'last_seen_at' => $now,
                        'active_seconds' => 0,
                        'ip_address' => Request::ip(),
                        'user_agent' => Request::userAgent(),
                    ]
                );

                // Only increment sessions_count if this is a newly created session
                if ($sessionRecord->wasRecentlyCreated) {
                    // Get or create daily activity record
                    $daily = UserActivityDaily::query()
                        ->where('user_type', get_class($user))
                        ->where('user_id', $user->id)
                        ->where('guard', $guard)
                        ->whereDate('date', $now->toDateString())
                        ->first();

                    if (! $daily) {
                        $daily = UserActivityDaily::create([
                            'user_type' => get_class($user),
                            'user_id' => $user->id,
                            'guard' => $guard,
                            'date' => $now->toDateString(),
                            'sessions_count' => 0,
                            'active_seconds' => 0,
                        ]);
                    }

                    // Increment session count
                    $daily->increment('sessions_count');
                    $daily->update([
                        'first_login_at' => $daily->first_login_at ?? $now,
                        'last_seen_at' => $now,
                    ]);
                }
            });
        } catch (\Exception $e) {
            // Silently fail to not impact user login
            \Log::error('Failed to record user login session: ' . $e->getMessage());
        }
    }

    /**
     * Determine if we should track this guard.
     */
    private function shouldTrack(string $guard): bool
    {
        return in_array($guard, ['employee', 'web', 'client', 'rep']);
    }
}
