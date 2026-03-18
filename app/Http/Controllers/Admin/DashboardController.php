<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AutomationStatusService;
use App\Services\BusinessStatusSummaryService;
use App\Services\DashboardMetricsService;
use App\Services\ExpenseEntryService;
use App\Services\GeminiService;
use App\Services\IncomeEntryService;
use App\Services\TaskQueryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function __construct(
        private AutomationStatusService $automationStatusService,
        private DashboardMetricsService $dashboardMetricsService
    ) {
    }

    public function index(
        Request $request,
        TaskQueryService $taskQueryService,
        BusinessStatusSummaryService $businessStatusSummaryService,
        IncomeEntryService $incomeEntryService,
        ExpenseEntryService $expenseEntryService,
        GeminiService $geminiService
    ): InertiaResponse
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

        $user = $request->user();
        $showTasksWidget = $taskQueryService->canViewTasks($user);
        $tasksWidget = $showTasksWidget ? $taskQueryService->dashboardTasksForUser($user) : null;
        $forceAiRefresh = $request->query('ai') === 'refresh';
        [$businessPulseAi, $businessPulseAiError] = $this->buildBusinessPulseAiSummary(
            $request,
            $taskQueryService,
            $businessStatusSummaryService,
            $incomeEntryService,
            $expenseEntryService,
            $geminiService,
            $forceAiRefresh
        );

        $clientActivity = $metrics['clientActivity'];
        $clientActivity['recentClients'] = collect($clientActivity['recentClients'] ?? [])->values()->all();

        return Inertia::render('Admin/Dashboard', array_merge(
            $metrics['counts'],
            [
                'pageTitle' => 'Admin Dashboard',
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
                'clientActivity' => $clientActivity,
                'projectMaintenance' => $metrics['projectMaintenance'],
                'hrStats' => $metrics['hrStats'],
                'showTasksWidget' => $showTasksWidget,
                'taskSummary' => $tasksWidget['summary'] ?? null,
                'openTasks' => $this->taskRows($tasksWidget['openTasks'] ?? collect()),
                'inProgressTasks' => $this->taskRows($tasksWidget['inProgressTasks'] ?? collect()),
                'businessPulseAi' => array_merge($businessPulseAi, [
                    'error' => $businessPulseAiError,
                ]),
                'routes' => [
                    'dashboard_refresh_ai' => route('admin.dashboard', array_merge($request->query(), ['ai' => 'refresh'])),
                    'customers_index' => route('admin.customers.index'),
                    'customers_show_template' => route('admin.customers.show', ['customer' => '__CUSTOMER__']),
                    'subscriptions_index' => route('admin.subscriptions.index'),
                    'licenses_index' => route('admin.licenses.index'),
                    'invoices_unpaid' => route('admin.invoices.unpaid'),
                    'invoices_overdue' => route('admin.invoices.overdue'),
                    'expenses_dashboard' => route('admin.expenses.dashboard'),
                    'orders_index' => route('admin.orders.index'),
                    'support_tickets_index' => route('admin.support-tickets.index'),
                    'projects_index' => route('admin.projects.index'),
                    'projects_all' => route('admin.projects.all'),
                    'project_maintenances_index' => route('admin.project-maintenances.index'),
                    'commission_payouts_index' => route('admin.commission-payouts.index'),
                    'hr_employees_index' => route('admin.hr.employees.index'),
                    'hr_timesheets_index' => route('admin.hr.timesheets.index'),
                    'hr_payroll_index' => route('admin.hr.payroll.index'),
                    'automation_status' => route('admin.automation-status'),
                    'tasks_show_template' => route('admin.projects.tasks.show', ['project' => '__PROJECT__', 'task' => '__TASK__']),
                ],
            ]
        ));
    }

    private function buildBusinessPulseAiSummary(
        Request $request,
        TaskQueryService $taskQueryService,
        BusinessStatusSummaryService $businessStatusSummaryService,
        IncomeEntryService $incomeEntryService,
        ExpenseEntryService $expenseEntryService,
        GeminiService $geminiService,
        bool $forceRefresh = false
    ): array {
        if (! config('google_ai.enabled')) {
            return [[
                'verdict' => null,
                'score' => null,
                'confidence' => null,
                'reason' => null,
                'action' => null,
            ], 'Google AI is disabled.'];
        }

        $startDate = now()->subDays(30)->startOfDay();
        $endDate = now()->endOfDay();
        $projectionDays = 30;
        $user = $request->user();

        $cacheKey = 'ai:admin-dashboard:business-pulse:' . md5(json_encode([
            'user_id' => $user?->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'projection_days' => $projectionDays,
        ]));

        try {
            $builder = function () use (
                $startDate,
                $endDate,
                $projectionDays,
                $user,
                $incomeEntryService,
                $expenseEntryService,
                $taskQueryService,
                $businessStatusSummaryService,
                $geminiService
            ) {
                $metrics = $businessStatusSummaryService->buildMetrics(
                    $startDate,
                    $endDate,
                    $projectionDays,
                    $user,
                    $incomeEntryService,
                    $expenseEntryService,
                    $taskQueryService
                );

                return $businessStatusSummaryService->summarizeDashboard($metrics, $geminiService);
            };

            if ($forceRefresh) {
                $summary = $builder();
                Cache::put($cacheKey, $summary, now()->addMinutes(10));
            } else {
                $summary = Cache::remember($cacheKey, now()->addMinutes(10), $builder);
            }

            return [$summary, null];
        } catch (\Throwable $e) {
            return [[
                'verdict' => null,
                'score' => null,
                'confidence' => null,
                'reason' => null,
                'action' => null,
            ], $e->getMessage()];
        }
    }

    private function taskRows(iterable $tasks): array
    {
        return collect($tasks)->map(function ($task) {
            $dueDate = $task->due_date ?? null;
            if ($dueDate instanceof \DateTimeInterface) {
                $dueDate = $dueDate->format(config('app.date_format', 'd-m-Y'));
            }

            return [
                'id' => (int) ($task->id ?? 0),
                'project_id' => (int) ($task->project_id ?? 0),
                'project_name' => (string) ($task->project->name ?? 'Project'),
                'title' => (string) ($task->title ?? 'Untitled task'),
                'status' => (string) ($task->status ?? 'pending'),
                'due_date' => $dueDate,
                'subtasks_count' => (int) ($task->subtasks_count ?? 0),
                'can_edit' => (bool) ($task->can_edit ?? false),
                'can_start' => (bool) ($task->can_start ?? false),
                'can_complete' => (bool) ($task->can_complete ?? false),
            ];
        })->values()->all();
    }
}
