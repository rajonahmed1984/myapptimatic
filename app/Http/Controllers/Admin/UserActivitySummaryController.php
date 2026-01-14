<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Employee;
use App\Models\Customer;
use App\Models\SalesRepresentative;
use App\Models\UserActivityDaily;
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
        $type = $request->query('type', 'employee'); // employee, customer, salesrep, admin
        $from = $request->query('from');
        $to = $request->query('to');
        $userId = $request->query('user_id');

        // Validate type
        if (!in_array($type, ['employee', 'customer', 'salesrep', 'admin'])) {
            $type = 'employee';
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
            case 'employee':
                return $this->getEmployeeSummary($today, $weekStart, $monthStart, $fromDate, $toDate, $userId);
            case 'customer':
                return $this->getCustomerSummary($today, $weekStart, $monthStart, $fromDate, $toDate, $userId);
            case 'salesrep':
                return $this->getSalesRepSummary($today, $weekStart, $monthStart, $fromDate, $toDate, $userId);
            case 'admin':
                return $this->getAdminUsersSummary($today, $weekStart, $monthStart, $fromDate, $toDate, $userId);
            default:
                return $this->getEmployeeSummary($today, $weekStart, $monthStart, $fromDate, $toDate, $userId);
        }
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
                $toDate
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
                $toDate
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
                $toDate
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
                $toDate
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
    private function getUserMetrics($user, $type, $guard, $today, $weekStart, $monthStart, $fromDate, $toDate): array
    {
        $userType = get_class($user);
        $userId = $user->id;

        // Today's metrics
        $todayRecord = UserActivityDaily::where('user_type', $userType)
            ->where('user_id', $userId)
            ->where('guard', $guard)
            ->where('date', $today)
            ->first();

        // Week metrics
        $weekMetrics = UserActivityDaily::where('user_type', $userType)
            ->where('user_id', $userId)
            ->where('guard', $guard)
            ->whereBetween('date', [$weekStart, $today])
            ->selectRaw('SUM(sessions_count) as sessions_count, SUM(active_seconds) as active_seconds')
            ->first();

        // Month metrics
        $monthMetrics = UserActivityDaily::where('user_type', $userType)
            ->where('user_id', $userId)
            ->where('guard', $guard)
            ->whereBetween('date', [$monthStart, $today])
            ->selectRaw('SUM(sessions_count) as sessions_count, SUM(active_seconds) as active_seconds')
            ->first();

        // Range metrics (if provided)
        $rangeMetrics = null;
        if ($fromDate && $toDate) {
            $rangeMetrics = UserActivityDaily::where('user_type', $userType)
                ->where('user_id', $userId)
                ->where('guard', $guard)
                ->whereBetween('date', [$fromDate->toDateString(), $toDate->toDateString()])
                ->selectRaw('SUM(sessions_count) as sessions_count, SUM(active_seconds) as active_seconds')
                ->first();
        }

        // Get latest session for online status and timestamps
        $lastSession = $user->activitySessions()
            ->where('guard', $guard)
            ->latest('last_seen_at')
            ->first();

        return [
            'user' => $user,
            'type' => $type,
            'guard' => $guard,
            'is_online' => $user->isOnline(),
            'today' => [
                'sessions_count' => $todayRecord?->sessions_count ?? 0,
                'active_seconds' => $todayRecord?->active_seconds ?? 0,
            ],
            'week' => [
                'sessions_count' => $weekMetrics?->sessions_count ?? 0,
                'active_seconds' => $weekMetrics?->active_seconds ?? 0,
            ],
            'month' => [
                'sessions_count' => $monthMetrics?->sessions_count ?? 0,
                'active_seconds' => $monthMetrics?->active_seconds ?? 0,
            ],
            'range' => $rangeMetrics ? [
                'sessions_count' => $rangeMetrics->sessions_count ?? 0,
                'active_seconds' => $rangeMetrics->active_seconds ?? 0,
            ] : null,
            'last_login_at' => $lastSession?->login_at,
            'last_seen_at' => $lastSession?->last_seen_at,
        ];
    }
}
