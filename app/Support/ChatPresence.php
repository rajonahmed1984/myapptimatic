<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChatPresence
{
    private const CACHE_PREFIX = 'chat.presence.';
    private const CACHE_TTL_SECONDS = 300;
    private const OFFLINE_GRACE_SECONDS = 30;

    public static function reportPresence(string $type, int $id, string $status): void
    {
        $type = strtolower(trim($type));
        if ($type === '' || $id <= 0) {
            return;
        }

        $normalizedStatus = $status === 'idle' ? 'idle' : 'active';

        cache()->put(self::cacheKey($type, $id), [
            'status' => $normalizedStatus,
            'last_seen' => now()->timestamp,
        ], self::CACHE_TTL_SECONDS);
    }

    public static function statusForKeys(array $keys): array
    {
        $now = now();
        $statuses = [];

        foreach ($keys as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $payload = cache()->get(self::cacheKeyFromKey($key));
            if (! is_array($payload) || empty($payload['last_seen'])) {
                continue;
            }

            $lastSeen = \Illuminate\Support\Carbon::createFromTimestamp((int) $payload['last_seen']);
            if ($lastSeen->lessThan($now->copy()->subSeconds(self::OFFLINE_GRACE_SECONDS))) {
                $statuses[$key] = 'offline';
                continue;
            }

            $statuses[$key] = ($payload['status'] ?? 'active') === 'idle' ? 'idle' : 'active';
        }

        return $statuses;
    }

    public static function participantStatuses(array $keys): array
    {
        $statuses = self::statusForKeys($keys);
        $missingKeys = array_filter($keys, fn ($key) => ! array_key_exists($key, $statuses));

        if (empty($missingKeys)) {
            return $statuses;
        }

        $typeMap = [
            'user' => User::class,
            'employee' => Employee::class,
            'sales_rep' => SalesRepresentative::class,
            'salesrep' => SalesRepresentative::class,
        ];

        $fallback = self::fallbackStatusesFromSessions($missingKeys, $typeMap);

        return array_merge($statuses, $fallback);
    }

    public static function authorStatusesForMessages($messages): array
    {
        $messages = $messages instanceof Collection ? $messages : collect($messages);
        if ($messages->isEmpty()) {
            return [];
        }

        $typeMap = [
            'user' => User::class,
            'employee' => Employee::class,
            'sales_rep' => SalesRepresentative::class,
            'salesrep' => SalesRepresentative::class,
        ];

        $grouped = $messages->groupBy('author_type');
        $authorKeys = [];

        foreach ($typeMap as $authorType => $className) {
            $ids = $grouped->get($authorType, collect())
                ->pluck('author_id')
                ->filter()
                ->unique()
                ->values();

            foreach ($ids as $id) {
                $authorKeys[] = $authorType . ':' . $id;
            }
        }

        $statuses = self::statusForKeys($authorKeys);

        $missingKeys = array_filter($authorKeys, fn ($key) => ! array_key_exists($key, $statuses));
        if (empty($missingKeys)) {
            return $statuses;
        }

        $fallbackStatuses = self::fallbackStatusesFromSessions($missingKeys, $typeMap);

        return array_merge($statuses, $fallbackStatuses);
    }

    private static function fallbackStatusesFromSessions(array $keys, array $typeMap): array
    {
        $groupedIds = [];

        foreach ($keys as $key) {
            [$type, $id] = array_pad(explode(':', $key, 2), 2, null);
            if (! $type || ! $id || ! isset($typeMap[$type])) {
                continue;
            }
            $groupedIds[$type][] = (int) $id;
        }

        if (empty($groupedIds)) {
            return [];
        }

        $now = now();
        $activeThreshold = $now->copy()->subSeconds(120);
        $idleThreshold = $now->copy()->subMinutes(15);

        $fallback = [];

        foreach ($groupedIds as $type => $ids) {
            $uniqueIds = collect($ids)->filter()->unique()->values();
            if ($uniqueIds->isEmpty()) {
                continue;
            }

            $sessions = DB::table('user_sessions')
                ->select('user_id', DB::raw('MAX(CASE WHEN logout_at IS NULL THEN last_seen_at ELSE NULL END) as last_seen_active'))
                ->where('user_type', $typeMap[$type])
                ->whereIn('user_id', $uniqueIds)
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');

            foreach ($uniqueIds as $id) {
                $lastSeenActive = $sessions[$id]->last_seen_active ?? null;
                $status = 'offline';
                if ($lastSeenActive) {
                    $lastSeenActive = \Illuminate\Support\Carbon::parse($lastSeenActive);
                    if ($lastSeenActive->greaterThanOrEqualTo($activeThreshold)) {
                        $status = 'active';
                    } elseif ($lastSeenActive->greaterThanOrEqualTo($idleThreshold)) {
                        $status = 'idle';
                    }
                }

                $fallback[$type . ':' . $id] = $status;
            }
        }

        return $fallback;
    }

    private static function cacheKey(string $type, int $id): string
    {
        return self::CACHE_PREFIX . $type . '.' . $id;
    }

    private static function cacheKeyFromKey(string $key): string
    {
        $key = strtolower($key);
        $key = str_replace(':', '.', $key);
        return self::CACHE_PREFIX . $key;
    }
}
