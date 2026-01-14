<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class EmployeeSummaryController extends Controller
{
    public function index(Request $request, CacheRepository $cache): View
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $employeeId = $validated['employee_id'] ?? null;
        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->toDateString() : null;
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->toDateString() : null;

        $now = now();
        $today = $now->toDateString();
        $weekStart = $now->copy()->startOfWeek()->toDateString();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $onlineWindow = $now->copy()->subMinutes(2);

        $cacheKey = 'employee_summary:' . md5(json_encode([
            'employee' => $employeeId,
            'from' => $from,
            'to' => $to,
            'today' => $today,
            'week' => $weekStart,
            'month' => $monthStart,
        ]));

        $employees = $cache->remember($cacheKey, 60, function () use ($employeeId, $today, $weekStart, $monthStart, $from, $to, $onlineWindow) {
            $query = Employee::query()
                ->select(['id', 'name', 'email', 'department', 'designation'])
                ->where('status', 'active');

            if ($employeeId) {
                $query->where('id', $employeeId);
            }

            $query->withExists([
                'sessions as is_online' => function ($q) use ($onlineWindow) {
                    $q->whereNull('logout_at')
                        ->where('last_seen_at', '>=', $onlineWindow);
                },
            ]);

            $query->withMax('sessions as last_seen_at', 'last_seen_at');
            $query->withMax('sessions as last_login_at', 'login_at');

            $query->withSum([
                'activityDaily as today_active_seconds' => function ($q) use ($today) {
                    $q->where('date', $today);
                },
            ], 'active_seconds');

            $query->withSum([
                'activityDaily as today_sessions_count' => function ($q) use ($today) {
                    $q->where('date', $today);
                },
            ], 'sessions_count');

            $query->withSum([
                'activityDaily as week_active_seconds' => function ($q) use ($weekStart, $today) {
                    $q->whereBetween('date', [$weekStart, $today]);
                },
            ], 'active_seconds');

            $query->withSum([
                'activityDaily as week_sessions_count' => function ($q) use ($weekStart, $today) {
                    $q->whereBetween('date', [$weekStart, $today]);
                },
            ], 'sessions_count');

            $query->withSum([
                'activityDaily as month_active_seconds' => function ($q) use ($monthStart, $today) {
                    $q->whereBetween('date', [$monthStart, $today]);
                },
            ], 'active_seconds');

            $query->withSum([
                'activityDaily as month_sessions_count' => function ($q) use ($monthStart, $today) {
                    $q->whereBetween('date', [$monthStart, $today]);
                },
            ], 'sessions_count');

            if ($from && $to) {
                $query->withSum([
                    'activityDaily as range_active_seconds' => function ($q) use ($from, $to) {
                        $q->whereBetween('date', [$from, $to]);
                    },
                ], 'active_seconds');

                $query->withSum([
                    'activityDaily as range_sessions_count' => function ($q) use ($from, $to) {
                        $q->whereBetween('date', [$from, $to]);
                    },
                ], 'sessions_count');
            }

            return $query->orderBy('name')->get();
        });

        $employeeOptions = Employee::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        return view('admin.employees.summary', [
            'employees' => $employees,
            'filters' => [
                'employee_id' => $employeeId,
                'from' => $from,
                'to' => $to,
            ],
            'showRange' => $from && $to,
            'employeeOptions' => $employeeOptions,
        ]);
    }
}
