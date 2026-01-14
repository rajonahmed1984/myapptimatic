<?php

namespace App\Models\Concerns;

use App\Models\UserSession;
use App\Models\UserActivityDaily;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasActivityTracking
{
    /**
     * Get all user sessions (polymorphic).
     */
    public function sessions(): MorphMany
    {
        // Proxy to the explicit activitySessions relation to avoid name clashes
        return $this->activitySessions();
    }

    /**
     * Get all daily activity records (polymorphic).
     */
    public function activityDaily(): MorphMany
    {
        return $this->activityDailyRecords();
    }

    /**
     * Explicit relation for activity sessions to avoid collisions with legacy relations.
     */
    public function activitySessions(): MorphMany
    {
        return $this->morphMany(UserSession::class, 'user');
    }

    /**
     * Explicit relation for daily activity aggregates.
     */
    public function activityDailyRecords(): MorphMany
    {
        return $this->morphMany(UserActivityDaily::class, 'user');
    }

    /**
     * Check if user is currently online.
     * Returns true if there's an open session with recent activity (within specified minutes).
     */
    public function isOnline(int $minutes = 2): bool
    {
        return $this->activitySessions()
            ->whereNull('logout_at')
            ->where('last_seen_at', '>=', now()->subMinutes($minutes))
            ->exists();
    }
}
