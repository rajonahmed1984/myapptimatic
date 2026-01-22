<?php

namespace App\Http\Controllers\SalesRep;

use App\Http\Controllers\Controller;
use App\Models\CommissionEarning;
use App\Models\CommissionPayout;
use App\Services\CommissionService;
use App\Services\TaskQueryService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CommissionService $commissionService, TaskQueryService $taskQueryService)
    {
        $rep = $request->attributes->get('salesRep');
        $balance = $commissionService->computeRepBalance($rep->id);

        $startOfMonth = Carbon::now()->startOfMonth();

        $earnedThisMonth = CommissionEarning::query()
            ->where('sales_representative_id', $rep->id)
            ->whereDate('earned_at', '>=', $startOfMonth)
            ->sum('commission_amount');

        $paidThisMonth = CommissionPayout::query()
            ->where('sales_representative_id', $rep->id)
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', '>=', $startOfMonth)
            ->sum('total_amount');

        $recentEarnings = CommissionEarning::query()
            ->where('sales_representative_id', $rep->id)
            ->latest('earned_at')
            ->limit(5)
            ->get();

        $recentPayouts = CommissionPayout::query()
            ->where('sales_representative_id', $rep->id)
            ->latest()
            ->limit(5)
            ->get();

        $user = $request->user();
        $showTasksWidget = $taskQueryService->canViewTasks($user);
        $tasksWidget = $showTasksWidget ? $taskQueryService->dashboardTasksForUser($user) : null;

        return view('rep.dashboard', [
            'rep' => $rep,
            'balance' => $balance,
            'earnedThisMonth' => $earnedThisMonth,
            'paidThisMonth' => $paidThisMonth,
            'recentEarnings' => $recentEarnings,
            'recentPayouts' => $recentPayouts,
            'showTasksWidget' => $showTasksWidget,
            'taskSummary' => $tasksWidget['summary'] ?? null,
            'openTasks' => $tasksWidget['openTasks'] ?? collect(),
            'inProgressTasks' => $tasksWidget['inProgressTasks'] ?? collect(),
        ]);
    }
}
