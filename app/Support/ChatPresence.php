<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
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

        $statusForLastSeen = static function ($lastSeen) use ($activeThreshold, $idleThreshold): string {
            if (! $lastSeen) {
                return 'offline';
            }

            $timestamp = $lastSeen instanceof Carbon ? $lastSeen : Carbon::parse($lastSeen);
            if ($timestamp->greaterThanOrEqualTo($activeThreshold)) {
                return 'active';
            }
            if ($timestamp->greaterThanOrEqualTo($idleThreshold)) {
                return 'idle';
            }

            return 'offline';
        };

        // Direct user participants are mapped 1:1 with user_sessions.
        $userIds = collect($groupedIds['user'] ?? [])->filter()->unique()->values();
        if ($userIds->isNotEmpty()) {
            $userSessions = DB::table('user_sessions')
                ->select('user_id', DB::raw('MAX(CASE WHEN logout_at IS NULL THEN last_seen_at ELSE NULL END) as last_seen_active'))
                ->where('user_type', User::class)
                ->whereIn('user_id', $userIds)
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');

            foreach ($userIds as $id) {
                $fallback['user:' . $id] = $statusForLastSeen($userSessions[$id]->last_seen_active ?? null);
            }
        }

        // Employee participants may be tracked in employee_sessions and/or user_sessions via linked user_id.
        $employeeIds = collect($groupedIds['employee'] ?? [])->filter()->unique()->values();
        if ($employeeIds->isNotEmpty()) {
            $employeeToUser = DB::table('employees')
                ->whereIn('id', $employeeIds)
                ->pluck('user_id', 'id');

            $linkedUserIds = $employeeToUser->filter()->unique()->values();
            $linkedUserSessions = $linkedUserIds->isNotEmpty()
                ? DB::table('user_sessions')
                    ->select('user_id', DB::raw('MAX(CASE WHEN logout_at IS NULL THEN last_seen_at ELSE NULL END) as last_seen_active'))
                    ->where('user_type', User::class)
                    ->whereIn('user_id', $linkedUserIds)
                    ->groupBy('user_id')
                    ->get()
                    ->keyBy('user_id')
                : collect();

            $employeeSessions = DB::table('employee_sessions')
                ->select('employee_id', DB::raw('MAX(CASE WHEN logout_at IS NULL THEN last_seen_at ELSE NULL END) as last_seen_active'))
                ->whereIn('employee_id', $employeeIds)
                ->groupBy('employee_id')
                ->get()
                ->keyBy('employee_id');

            foreach ($employeeIds as $id) {
                $employeeSeen = $employeeSessions[$id]->last_seen_active ?? null;
                $linkedUserId = $employeeToUser[$id] ?? null;
                $userSeen = $linkedUserId ? ($linkedUserSessions[$linkedUserId]->last_seen_active ?? null) : null;

                $lastSeen = null;
                if ($employeeSeen && $userSeen) {
                    $lastSeen = Carbon::parse($employeeSeen)->greaterThan(Carbon::parse($userSeen)) ? $employeeSeen : $userSeen;
                } else {
                    $lastSeen = $employeeSeen ?: $userSeen;
                }

                $fallback['employee:' . $id] = $statusForLastSeen($lastSeen);
            }
        }

        // Sales reps authenticate as linked users; read from user_sessions via user_id.
        $salesRepIds = collect(array_merge($groupedIds['sales_rep'] ?? [], $groupedIds['salesrep'] ?? []))
            ->filter()
            ->unique()
            ->values();
        if ($salesRepIds->isNotEmpty()) {
            $salesRepToUser = DB::table('sales_representatives')
                ->whereIn('id', $salesRepIds)
                ->pluck('user_id', 'id');

            $linkedUserIds = $salesRepToUser->filter()->unique()->values();
            $linkedUserSessions = $linkedUserIds->isNotEmpty()
                ? DB::table('user_sessions')
                    ->select('user_id', DB::raw('MAX(CASE WHEN logout_at IS NULL THEN last_seen_at ELSE NULL END) as last_seen_active'))
                    ->where('user_type', User::class)
                    ->whereIn('user_id', $linkedUserIds)
                    ->groupBy('user_id')
                    ->get()
                    ->keyBy('user_id')
                : collect();

            foreach ($salesRepIds as $id) {
                $linkedUserId = $salesRepToUser[$id] ?? null;
                $status = $statusForLastSeen($linkedUserId ? ($linkedUserSessions[$linkedUserId]->last_seen_active ?? null) : null);

                if (array_key_exists('sales_rep', $groupedIds)) {
                    $fallback['sales_rep:' . $id] = $status;
                }
                if (array_key_exists('salesrep', $groupedIds)) {
                    $fallback['salesrep:' . $id] = $status;
                }
            }
        }

        // Keep old behavior for any unknown future types.
        foreach ($groupedIds as $type => $ids) {
            if (in_array($type, ['user', 'employee', 'sales_rep', 'salesrep'], true)) {
                continue;
            }
            if (! isset($typeMap[$type])) {
                continue;
            }

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
                $fallback[$type . ':' . $id] = $statusForLastSeen($sessions[$id]->last_seen_active ?? null);
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
