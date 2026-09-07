<?php

namespace App\Http\Controllers\SalesRep;

use App\Http\Controllers\Controller;
use App\Models\CommissionEarning;
use App\Models\CommissionPayout;
use App\Services\CommissionService;
use App\Services\TaskQueryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CommissionService $commissionService, TaskQueryService $taskQueryService): InertiaResponse
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

        $projects = \App\Models\Project::query()
            ->whereHas('salesRepresentatives', fn ($q) => $q->where('sales_representatives.id', $rep->id))
            ->withCount([
                'tasks as total_tasks_count',
                'tasks as done_tasks_count' => fn ($q) => $q->whereIn('status', ['completed', 'done']),
            ])
            ->latest()
            ->limit(5)
            ->get();
        $projectCount = \App\Models\Project::query()
            ->whereHas('salesRepresentatives', fn ($q) => $q->where('sales_representatives.id', $rep->id))
            ->count();

        $currency = $user->currency ?? config('app.currency', 'BDT');

        return Inertia::render('Rep/Dashboard/Index', [
            'rep' => [
                'id' => $rep->id,
                'name' => $rep->name,
                'email' => $rep->email,
            ],
            'currency' => $currency,
            'balance' => $balance,
            'earned_this_month' => (float) $earnedThisMonth,
            'paid_this_month' => (float) $paidThisMonth,
            'project_count' => $projectCount,
            'projects' => $projects->map(function (\App\Models\Project $project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status,
                    'status_label' => ucfirst((string) $project->status),
                    'total_tasks_count' => (int) $project->total_tasks_count,
                    'done_tasks_count' => (int) $project->done_tasks_count,
                    'routes' => [
                        'show' => route('rep.projects.show', $project),
                    ],
                ];
            })->values()->all(),
            'recent_earnings' => $recentEarnings->map(function (CommissionEarning $earning) {
                return [
                    'id' => $earning->id,
                    'source_type' => ucfirst((string) $earning->source_type),
                    'commission_amount' => (float) $earning->commission_amount,
                    'currency' => $earning->currency,
                    'status_label' => ucfirst((string) $earning->status),
                    'earned_at_display' => $earning->earned_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                ];
            })->values()->all(),
            'recent_payouts' => $recentPayouts->map(function (CommissionPayout $payout) {
                return [
                    'id' => $payout->id,
                    'total_amount' => (float) $payout->total_amount,
                    'currency' => $payout->currency,
                    'status_label' => ucfirst((string) $payout->status),
                    'paid_at_display' => $payout->paid_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? 'Draft',
                ];
            })->values()->all(),
            'tasks_widget' => [
                'show' => $showTasksWidget,
                'summary' => $tasksWidget['summary'] ?? null,
                'open_tasks' => ($tasksWidget['openTasks'] ?? collect())->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'status' => $task->status,
                        'project_name' => $task->project?->name,
                        'routes' => [
                            'show' => $task->project ? route('rep.projects.tasks.show', [$task->project, $task]) : null,
                        ],
                    ];
                })->values()->all(),
                'in_progress_tasks' => ($tasksWidget['inProgressTasks'] ?? collect())->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'status' => $task->status,
                        'project_name' => $task->project?->name,
                        'routes' => [
                            'show' => $task->project ? route('rep.projects.tasks.show', [$task->project, $task]) : null,
                        ],
                    ];
                })->values()->all(),
            ],
            'routes' => [
                'dashboard' => route('rep.dashboard'),
                'earnings' => route('rep.earnings.index'),
                'payouts' => route('rep.payouts.index'),
                'projects' => route('rep.projects.index'),
                'tasks' => route('rep.tasks.index'),
                'chats' => route('rep.chats.index'),
            ],
        ]);
    }
}
