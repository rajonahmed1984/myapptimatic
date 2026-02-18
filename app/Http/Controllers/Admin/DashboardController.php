<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AutomationStatusService;
use App\Services\DashboardMetricsService;
use App\Services\TaskQueryService;

class DashboardController extends Controller
{
    public function __construct(
        private AutomationStatusService $automationStatusService,
        private DashboardMetricsService $dashboardMetricsService
    ) {
    }

    public function index(TaskQueryService $taskQueryService)
    {
        $automationStatusPayload = $this->automationStatusService->getStatusPayload();
        $automationSummary = [
            'lastCompletionText' => $automationStatusPayload['lastCompletionText'] ?? 'Never',
            'statusLabel' => $automationStatusPayload['statusLabel'] ?? 'Pending',
            'statusClasses' => $automationStatusPayload['statusClasses'] ?? 'bg-slate-100 text-slate-600',
            'statusUrl' => route('admin.automation-status'),
        ];

        $systemOverview = [
            'automation_last_run' => $automationStatusPayload['lastCompletionText'] ?? 'Never',
            'automation_cards' => [
                'status_badge' => $automationStatusPayload['statusLabel'] ?? 'Pending',
            ],
        ];

        $metrics = $this->dashboardMetricsService->getMetrics();

        $user = request()->user();
        $showTasksWidget = $taskQueryService->canViewTasks($user);
        $tasksWidget = $showTasksWidget ? $taskQueryService->dashboardTasksForUser($user) : null;

        return view('admin.dashboard', array_merge(
            $metrics['counts'],
            [
                'businessPulse' => $metrics['businessPulse'],
                'automation' => $metrics['automation'],
                'automationRuns' => $metrics['automationRuns'],
                'automationMetrics' => $metrics['automationMetrics'],
                'automationSummary' => $automationSummary,
                'systemOverview' => $systemOverview,
                'periodMetrics' => $metrics['periodMetrics'],
                'periodSeries' => $metrics['periodSeries'],
                'incomeStatement' => $metrics['incomeStatement'],
                'billingAmounts' => $metrics['billingAmounts'],
                'currency' => $metrics['currency'],
                'clientActivity' => $metrics['clientActivity'],
                'projectMaintenance' => $metrics['projectMaintenance'],
                'hrStats' => $metrics['hrStats'],
                'showTasksWidget' => $showTasksWidget,
                'taskSummary' => $tasksWidget['summary'] ?? null,
                'openTasks' => $tasksWidget['openTasks'] ?? collect(),
                'inProgressTasks' => $tasksWidget['inProgressTasks'] ?? collect(),
            ]
        ));
    }
}
