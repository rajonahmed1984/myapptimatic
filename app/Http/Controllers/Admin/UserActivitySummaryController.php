<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Employee;
use App\Models\Customer;
use App\Models\SalesRepresentative;
use App\Models\UserActivityDaily;
use App\Models\UserSession;
use App\Enums\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Builder;

class UserActivitySummaryController
{
    /**
     * Display user activity summary with type filtering.
     */
    public function index(Request $request)
    {
        // Authorization check
        $user = auth('web')->user();
        if (! $user || ! in_array($user->role, Role::adminRoles(), true)) {
            abort(403, 'Unauthorized access to activity summary');
        }
        $type = $request->query('type', 'all'); // all, employee, customer, salesrep, admin
        $from = $request->query('from');
        $to = $request->query('to');
        $userId = $request->query('user_id');

        // Validate type
        if (!in_array($type, ['all', 'employee', 'customer', 'salesrep', 'admin'])) {
            $type = 'all';
        }

        // Parse dates
        $fromDate = $from ? \Carbon\Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? \Carbon\Carbon::parse($to)->endOfDay() : null;

        // Generate cache key based on filters
        $cacheKey = md5(json_encode([
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'user_id' => $userId,
        ]));

        // Try to get from cache first (60 seconds)
        $cached = Cache::get("user_activity_summary:{$cacheKey}");
        if ($cached) {
            return view('admin.users.activity-summary', $cached);
        }

        // Build data
        $data = $this->buildSummaryData($type, $fromDate, $toDate, $userId);

        // Cache for 60 seconds
        Cache::put("user_activity_summary:{$cacheKey}", $data, now()->addSeconds(60));

        return view('admin.users.activity-summary', $data);
    }

    /**
     * Build summary data based on user type.
     */
    private function buildSummaryData(string $type, $fromDate, $toDate, $userId): array
    {
        $today = now()->toDateString();
        $weekStart = now()->startOfWeek()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        switch ($type) {
            case 'all':
                return $this->getAllUsersSummary($today, $weekStart, $monthStart, $fromDate, $toDate, $userId);
            case 'employee':
                return $this->getEmployeeSummary($today, $weekStart, $monthStart, $fromDate, $toDate, $userId);
            case 'customer':
                return $this->getCustomerSummary($today, $weekStart, $monthStart, $fromDate, $toDate, $userId);
            case 'salesrep':
                return $this->getSalesRepSummary($today, $weekStart, $monthStart, $fromDate, $toDate, $userId);
            case 'admin':
                return $this->getAdminUsersSummary($today, $weekStart, $monthStart, $fromDate, $toDate, $userId);
            default:
                return $this->getAllUsersSummary($today, $weekStart, $monthStart, $fromDate, $toDate, $userId);
        }
    }

    /**
     * Get all users activity summary (combined from all user types).
     */
    private function getAllUsersSummary($today, $weekStart, $monthStart, $fromDate, $toDate, $userId): array
    {
        $users = [];

        // Get employees
        $employees = Employee::where('status', 'active');
        if ($userId) {
            $employees = $employees->where('id', $userId);
        }
        $employees = $employees->get();
        $employeePrepared = $this->buildPreparedMetrics($employees, 'employee', $today, $weekStart, $monthStart, $fromDate, $toDate);
        foreach ($employees as $employee) {
            $users[] = $this->getUserMetrics(
                $employee,
                'employee',
                'employee',
                $today,
                $weekStart,
                $monthStart,
                $fromDate,
                $toDate,
                $employeePrepared
            );
        }

        // Get customers (if no specific user filter or if the userId matches a customer)
        if (!$userId) {
            $customers = User::where('role', Role::CLIENT)->with('customer:id,avatar_path')->get();
            $customerPrepared = $this->buildPreparedMetrics($customers, 'web', $today, $weekStart, $monthStart, $fromDate, $toDate);
            foreach ($customers as $user) {
                $users[] = $this->getUserMetrics(
                    $user,
                    'client',
                    'web',
                    $today,
                    $weekStart,
                    $monthStart,
                    $fromDate,
                    $toDate,
                    $customerPrepared
                );
            }
        }

        // Get sales representatives (if no specific user filter or if the userId matches a sales rep)
        if (!$userId) {
            $salesReps = SalesRepresentative::with('user')->get();
            $salesRepUsers = $salesReps->pluck('user')->filter()->values();
            $salesRepPrepared = $this->buildPreparedMetrics($salesRepUsers, 'web', $today, $weekStart, $monthStart, $fromDate, $toDate);
            foreach ($salesReps as $rep) {
                if ($rep->user) {
                    $users[] = $this->getUserMetrics(
                        $rep->user,
                        'sales_rep',
                        'web',
                        $today,
                        $weekStart,
                        $monthStart,
                        $fromDate,
                        $toDate,
                        $salesRepPrepared
                    );
                }
            }
        }

        // Get admin/web users (if no specific user filter or if the userId matches an admin)
        if (!$userId) {
            $adminUsers = User::whereIn('role', Role::adminRoles())->get();
            $adminPrepared = $this->buildPreparedMetrics($adminUsers, 'web', $today, $weekStart, $monthStart, $fromDate, $toDate);
            foreach ($adminUsers as $user) {
                $users[] = $this->getUserMetrics(
                    $user,
                    'admin',
                    'web',
                    $today,
                    $weekStart,
                    $monthStart,
                    $fromDate,
                    $toDate,
                    $adminPrepared
                );
            }
        }

        // Sort by last seen (online users first)
        usort($users, function ($a, $b) {
            $aLastSeen = $a['last_seen_at'] ? strtotime($a['last_seen_at']) : 0;
            $bLastSeen = $b['last_seen_at'] ? strtotime($b['last_seen_at']) : 0;
            return $bLastSeen <=> $aLastSeen;
        });

        // Get all user options for dropdown
        $userOptions = [];
        $userOptions += Employee::where('status', 'active')->pluck('name', 'id')->toArray();
        $userOptions += User::where('role', Role::CLIENT)->pluck('name', 'id')->toArray();
        $userOptions += User::whereIn('role', Role::adminRoles())->pluck('name', 'id')->toArray();

        return [
            'type' => 'all',
            'users' => $users,
            'filters' => [
                'type' => 'all',
                'user_id' => $userId,
                'from' => $fromDate?->toDateString(),
                'to' => $toDate?->toDateString(),
            ],
            'userOptions' => $userOptions,
            'showRange' => $fromDate && $toDate,
        ];
    }

    /**
     * Get employee activity summary.
     */
    private function getEmployeeSummary($today, $weekStart, $monthStart, $fromDate, $toDate, $userId): array
    {
        $employees = Employee::where('status', 'active');
        if ($userId) {
            $employees = $employees->where('id', $userId);
        }
        $employees = $employees->get();
        $prepared = $this->buildPreparedMetrics($employees, 'employee', $today, $weekStart, $monthStart, $fromDate, $toDate);

        $users = [];
        foreach ($employees as $employee) {
            $users[] = $this->getUserMetrics(
                $employee,
                'employee',
                'employee',
                $today,
                $weekStart,
                $monthStart,
                $fromDate,
                $toDate,
                $prepared
            );
        }

        return [
            'type' => 'employee',
            'users' => $users,
            'filters' => [
                'type' => 'employee',
                'user_id' => $userId,
                'from' => $fromDate?->toDateString(),
                'to' => $toDate?->toDateString(),
            ],
            'userOptions' => Employee::where('status', 'active')->pluck('name', 'id'),
            'showRange' => $fromDate && $toDate,
        ];
    }

    /**
     * Get customer activity summary.
     */
    private function getCustomerSummary($today, $weekStart, $monthStart, $fromDate, $toDate, $userId): array
    {
        // Customers are tracked via User model with role 'client'
        $users = User::where('role', Role::CLIENT)->with('customer:id,avatar_path');
        if ($userId) {
            $users = $users->where('id', $userId);
        }
        $users = $users->get();
        $prepared = $this->buildPreparedMetrics($users, 'web', $today, $weekStart, $monthStart, $fromDate, $toDate);

        $summaryUsers = [];
        foreach ($users as $user) {
            $summaryUsers[] = $this->getUserMetrics(
                $user,
                'client',
                'web',
                $today,
                $weekStart,
                $monthStart,
                $fromDate,
                $toDate,
                $prepared
            );
        }

        return [
            'type' => 'customer',
            'users' => $summaryUsers,
            'filters' => [
                'type' => 'customer',
                'user_id' => $userId,
                'from' => $fromDate?->toDateString(),
                'to' => $toDate?->toDateString(),
            ],
            'userOptions' => User::where('role', Role::CLIENT)->pluck('name', 'id'),
            'showRange' => $fromDate && $toDate,
        ];
    }

    /**
     * Get sales rep activity summary.
     */
    private function getSalesRepSummary($today, $weekStart, $monthStart, $fromDate, $toDate, $userId): array
    {
        $reps = SalesRepresentative::where('status', 'active');
        if ($userId) {
            $reps = $reps->where('id', $userId);
        }
        $reps = $reps->get();
        $prepared = $this->buildPreparedMetrics($reps, 'web', $today, $weekStart, $monthStart, $fromDate, $toDate);

        $users = [];
        foreach ($reps as $rep) {
            $users[] = $this->getUserMetrics(
                $rep,
                'salesrep',
                'web',
                $today,
                $weekStart,
                $monthStart,
                $fromDate,
                $toDate,
                $prepared
            );
        }

        return [
            'type' => 'salesrep',
            'users' => $users,
            'filters' => [
                'type' => 'salesrep',
                'user_id' => $userId,
                'from' => $fromDate?->toDateString(),
                'to' => $toDate?->toDateString(),
            ],
            'userOptions' => SalesRepresentative::where('status', 'active')->pluck('name', 'id'),
            'showRange' => $fromDate && $toDate,
        ];
    }

    /**
     * Get admin/web users activity summary.
     */
    private function getAdminUsersSummary($today, $weekStart, $monthStart, $fromDate, $toDate, $userId): array
    {
        $users = User::whereIn('role', [
            Role::ADMIN,
            Role::MASTER_ADMIN,
            Role::SUB_ADMIN,
            Role::SUPPORT,
            Role::SALES,
        ]);
        if ($userId) {
            $users = $users->where('id', $userId);
        }
        $users = $users->get();
        $prepared = $this->buildPreparedMetrics($users, 'web', $today, $weekStart, $monthStart, $fromDate, $toDate);

        $summaryUsers = [];
        foreach ($users as $user) {
            $summaryUsers[] = $this->getUserMetrics(
                $user,
                'web',
                'web',
                $today,
                $weekStart,
                $monthStart,
                $fromDate,
                $toDate,
                $prepared
            );
        }

        return [
            'type' => 'admin',
            'users' => $summaryUsers,
            'filters' => [
                'type' => 'admin',
                'user_id' => $userId,
                'from' => $fromDate?->toDateString(),
                'to' => $toDate?->toDateString(),
            ],
            'userOptions' => User::whereIn('role', [
                Role::ADMIN,
                Role::MASTER_ADMIN,
                Role::SUB_ADMIN,
                Role::SUPPORT,
                Role::SALES,
            ])->pluck('name', 'id'),
            'showRange' => $fromDate && $toDate,
        ];
    }

    /**
     * Calculate metrics for a single user.
     */
    private function getUserMetrics($user, $type, $guard, $today, $weekStart, $monthStart, $fromDate, $toDate, array $prepared = []): array
    {
        $userType = get_class($user);
        $userId = $user->id;

        $todayRecord = $prepared['today'][$userId] ?? ['sessions_count' => 0, 'active_seconds' => 0];
        $weekMetrics = $prepared['week'][$userId] ?? ['sessions_count' => 0, 'active_seconds' => 0];
        $monthMetrics = $prepared['month'][$userId] ?? ['sessions_count' => 0, 'active_seconds' => 0];
        $rangeMetrics = $prepared['range'][$userId] ?? null;
        $lastSession = $prepared['last_sessions'][$userId] ?? null;
        $isOnline = array_key_exists($userId, $prepared['online_ids'] ?? [])
            ? (bool) ($prepared['online_ids'][$userId] ?? false)
            : $user->isOnline();

        return [
            'user' => $user,
            'type' => $type,
            'guard' => $guard,
            'is_online' => $isOnline,
            'today' => [
                'sessions_count' => $todayRecord['sessions_count'] ?? 0,
                'active_seconds' => $todayRecord['active_seconds'] ?? 0,
            ],
            'week' => [
                'sessions_count' => $weekMetrics['sessions_count'] ?? 0,
                'active_seconds' => $weekMetrics['active_seconds'] ?? 0,
            ],
            'month' => [
                'sessions_count' => $monthMetrics['sessions_count'] ?? 0,
                'active_seconds' => $monthMetrics['active_seconds'] ?? 0,
            ],
            'range' => $rangeMetrics ? [
                'sessions_count' => $rangeMetrics['sessions_count'] ?? 0,
                'active_seconds' => $rangeMetrics['active_seconds'] ?? 0,
            ] : null,
            'last_login_at' => $lastSession['login_at'] ?? null,
            'last_seen_at' => $lastSession['last_seen_at'] ?? null,
        ];
    }

    private function buildPreparedMetrics(iterable $users, string $guard, string $today, string $weekStart, string $monthStart, $fromDate, $toDate): array
    {
        $collection = collect($users)->filter();
        if ($collection->isEmpty()) {
            return [
                'today' => [],
                'week' => [],
                'month' => [],
                'range' => [],
                'last_sessions' => [],
                'online_ids' => [],
            ];
        }

        $first = $collection->first();
        $userType = get_class($first);
        $userIds = $collection->pluck('id')->filter()->values()->all();

        $sumByDateRange = function (string $start, string $end) use ($userType, $userIds, $guard) {
            return UserActivityDaily::query()
                ->where('user_type', $userType)
                ->where('guard', $guard)
                ->whereIn('user_id', $userIds)
                ->whereBetween('date', [$start, $end])
                ->selectRaw('user_id, SUM(sessions_count) as sessions_count, SUM(active_seconds) as active_seconds')
                ->groupBy('user_id')
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->user_id => [
                    'sessions_count' => (int) ($row->sessions_count ?? 0),
                    'active_seconds' => (int) ($row->active_seconds ?? 0),
                ]])
                ->all();
        };

        $today = UserActivityDaily::query()
            ->where('user_type', $userType)
            ->where('guard', $guard)
            ->whereIn('user_id', $userIds)
            ->where('date', $today)
            ->select('user_id', 'sessions_count', 'active_seconds')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->user_id => [
                'sessions_count' => (int) ($row->sessions_count ?? 0),
                'active_seconds' => (int) ($row->active_seconds ?? 0),
            ]])
            ->all();

        $week = $sumByDateRange($weekStart, $today);
        $month = $sumByDateRange($monthStart, $today);

        $range = [];
        if ($fromDate && $toDate) {
            $range = $sumByDateRange($fromDate->toDateString(), $toDate->toDateString());
        }

        $lastSessions = UserSession::query()
            ->where('user_type', $userType)
            ->where('guard', $guard)
            ->whereIn('user_id', $userIds)
            ->selectRaw('user_id, MAX(login_at) as login_at, MAX(last_seen_at) as last_seen_at')
            ->groupBy('user_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->user_id => [
                'login_at' => $row->login_at,
                'last_seen_at' => $row->last_seen_at,
            ]])
            ->all();

        $onlineIds = UserSession::query()
            ->where('user_type', $userType)
            ->where('guard', $guard)
            ->whereIn('user_id', $userIds)
            ->whereNull('logout_at')
            ->where('last_seen_at', '>=', now()->subMinutes(2))
            ->distinct()
            ->pluck('user_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();

        return [
            'today' => $today,
            'week' => $week,
            'month' => $month,
            'range' => $range,
            'last_sessions' => $lastSessions,
            'online_ids' => $onlineIds,
        ];
    }
}
