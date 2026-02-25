<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use App\Models\UserActivityDaily;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TrackAuthenticatedUserActivity
{
    /**
     * Constants for activity tracking.
     */
    private const INACTIVITY_CUTOFF_SECONDS = 300; // 5 minutes
    private const HEARTBEAT_THROTTLE_SECONDS = 60; // Only update DB once per minute
    private const LAST_HEARTBEAT_KEY = 'user_activity_last_update';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $guard = 'web'): Response
    {
        $response = $next($request);

        // Check if user is authenticated with this guard
        if (!auth($guard)->check()) {
            return $response;
        }

        // Track activity asynchronously to minimize impact
        try {
            $this->trackUserActivity($request, $guard);
        } catch (\Exception $e) {
            Log::error('Failed to track user activity: ' . $e->getMessage());
        }

        return $response;
    }

    /**
     * Record user activity for the current request.
     */
    private function trackUserActivity(Request $request, string $guard): void
    {
        $user = auth($guard)->user();
        $now = now();
        $sessionId = session()->getId();

        DB::transaction(function () use ($user, $guard, $now, $sessionId, $request) {
            // Find open session
            $session = UserSession::where('user_type', get_class($user))
                ->where('user_id', $user->id)
                ->where('guard', $guard)
                ->whereNull('logout_at')
                ->latest('login_at')
                ->first();

            if (!$session) {
                return; // No open session, skip tracking
            }

            // Check throttle: only update if 60+ seconds since last update
            $lastUpdate = session(self::LAST_HEARTBEAT_KEY, $now);
        $deltaSinceLastUpdate = $lastUpdate->diffInSeconds($now);

            if ($deltaSinceLastUpdate < self::HEARTBEAT_THROTTLE_SECONDS) {
                return; // Too soon, skip update
            }

            // Update throttle timestamp in session
            session([self::LAST_HEARTBEAT_KEY => $now]);

            // Calculate activity delta
            $lastSeen = $session->last_seen_at;
            $delta = $lastSeen->diffInSeconds($now);

            // Only count active time if within inactivity cutoff (0 to 5 minutes)
            if ($delta > 0 && $delta <= self::INACTIVITY_CUTOFF_SECONDS) {
                $session->active_seconds += $delta;
            }

            // Always update last_seen_at
            $session->last_seen_at = $now;
            $session->save();

            // Update daily activity record
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

            // Add active seconds to daily total (same logic as session)
            if ($delta > 0 && $delta <= self::INACTIVITY_CUTOFF_SECONDS) {
                $daily->active_seconds += $delta;
            }

            $daily->last_seen_at = $now;
            $daily->save();
        });
    }
}
