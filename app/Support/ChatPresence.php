<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChatPresence
{
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
        $now = now();
        $onlineThreshold = $now->copy()->subMinutes(2);
        $awayThreshold = $now->copy()->subMinutes(15);

        $statuses = [];

        foreach ($typeMap as $authorType => $className) {
            $ids = $grouped->get($authorType, collect())
                ->pluck('author_id')
                ->filter()
                ->unique()
                ->values();

            if ($ids->isEmpty()) {
                continue;
            }

            $sessions = DB::table('user_sessions')
                ->select('user_id', DB::raw('MAX(CASE WHEN logout_at IS NULL THEN last_seen_at ELSE NULL END) as last_seen_active'))
                ->where('user_type', $className)
                ->whereIn('user_id', $ids)
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');

            foreach ($ids as $id) {
                $lastSeenActive = $sessions[$id]->last_seen_active ?? null;
                $status = 'offline';
                if ($lastSeenActive) {
                    $lastSeenActive = \Illuminate\Support\Carbon::parse($lastSeenActive);
                    if ($lastSeenActive->greaterThanOrEqualTo($onlineThreshold)) {
                        $status = 'online';
                    } elseif ($lastSeenActive->greaterThanOrEqualTo($awayThreshold)) {
                        $status = 'away';
                    }
                }

                $statuses[$authorType . ':' . $id] = $status;
            }
        }

        return $statuses;
    }
}
