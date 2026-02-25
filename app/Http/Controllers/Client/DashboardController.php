<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\LicenseDomain;
use App\Models\Project;
use App\Models\ProjectMaintenance;
use App\Models\ProjectMessageRead;
use App\Models\Setting;
use App\Services\TaskQueryService;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function index(Request $request, TaskQueryService $taskQueryService): InertiaResponse
    {
        $user = $request->user();

        // Show project-specific dashboard for project users
        if ($user->isClientProject() && $user->project_id) {
            return $this->projectSpecificDashboard($request, $user, $taskQueryService);
        }

        $customer = $user->customer;
        $subscriptions = $customer?->subscriptions()->with('plan.product')->get() ?? collect();
        $invoices = $customer?->invoices()->latest('issue_date')->limit(5)->get() ?? collect();
        $licenses = $customer?->licenses()
            ->with(['product', 'subscription.plan', 'domains'])
            ->get() ?? collect();
        $openInvoices = $customer
            ? $customer->invoices()
                ->whereIn('status', ['unpaid', 'overdue'])
                ->orderBy('due_date')
                ->get()
            : collect();
        $overdueInvoices = $customer
            ? $customer->invoices()
                ->where('status', 'overdue')
                ->orderBy('due_date')
                ->get()
            : collect();
        $openInvoiceCount = $openInvoices->count();
        $overdueInvoiceCount = $overdueInvoices->count();
        $openInvoiceBalance = $openInvoices->sum('total');
        $overdueInvoiceBalance = $overdueInvoices->sum('total');
        $nextOverdueInvoice = $overdueInvoices->first();
        $nextOpenInvoice = $openInvoices->first();
        $ticketOpenCount = $customer
            ? $customer->supportTickets()
                ->whereIn('status', ['open', 'customer_reply'])
                ->count()
            : 0;
        $recentTickets = $customer
            ? $customer->supportTickets()
                ->latest('updated_at')
                ->limit(4)
                ->get()
            : collect();
        $domainCount = $customer
            ? LicenseDomain::query()
                ->where('status', 'active')
                ->whereHas('license.subscription', function ($query) use ($customer) {
                    $query->where('customer_id', $customer->id);
                })
                ->count()
            : 0;
        $expiringLicenses = $customer
            ? $customer->licenses()
                ->with('product')
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [Carbon::today(), Carbon::today()->addDays(45)])
                ->orderBy('expires_at')
                ->limit(4)
                ->get()
            : collect();
        $projects = collect();
        $projectCount = 0;
        if ($customer) {
            $projectQuery = Project::where('customer_id', $customer->id)
                ->withCount([
                    'tasks as open_tasks_count' => fn ($q) => $q->whereIn('status', ['todo', 'in_progress', 'blocked']),
                    'tasks as done_tasks_count' => fn ($q) => $q->where('status', 'done'),
                ]);

            if ($request->user()->isClientProject() && $request->user()->project_id) {
                $projectQuery->whereKey($request->user()->project_id);
            }

            $projectCount = (clone $projectQuery)->count();

            $projects = $projectQuery
                ->latest()
                ->limit(5)
                ->get();
        }
        $maintenanceRenewal = $customer
            ? ProjectMaintenance::query()
                ->with('project')
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->orderByRaw('COALESCE(next_billing_date, start_date)')
                ->first()
            : null;

        $showTasksWidget = $taskQueryService->canViewTasks($user);
        $tasksWidget = $showTasksWidget ? $taskQueryService->dashboardTasksForUser($user) : null;
        $dateFormat = config('app.date_format', 'd-m-Y');

        return Inertia::render('Client/Dashboard/Index', [
            'customer' => $customer ? [
                'name' => $customer->name,
                'email' => $customer->email,
            ] : null,
            'subscriptions' => $subscriptions->map(function ($subscription) use ($dateFormat) {
                return [
                    'id' => $subscription->id,
                    'product_name' => $subscription->plan?->product?->name ?? 'Service',
                    'plan_name' => $subscription->plan?->name ?? '--',
                    'status_label' => ucfirst((string) $subscription->status),
                    'next_invoice_display' => $subscription->next_invoice_at?->format($dateFormat) ?? '--',
                ];
            })->values()->all(),
            'invoices' => $invoices->map(function ($invoice) use ($dateFormat) {
                return [
                    'id' => $invoice->id,
                    'number' => is_numeric($invoice->number) ? $invoice->number : $invoice->id,
                    'currency' => $invoice->currency,
                    'total' => (float) $invoice->total,
                    'due_date_display' => $invoice->due_date?->format($dateFormat) ?? '--',
                    'status' => $invoice->status,
                    'status_label' => ucfirst((string) $invoice->status),
                    'can_pay' => in_array($invoice->status, ['unpaid', 'overdue'], true),
                    'routes' => [
                        'pay' => route('client.invoices.pay', $invoice),
                    ],
                ];
            })->values()->all(),
            'serviceCount' => $subscriptions->count(),
            'projectCount' => $projectCount,
            'domainCount' => $domainCount,
            'ticketOpenCount' => $ticketOpenCount,
            'openInvoiceCount' => $openInvoiceCount,
            'openInvoiceBalance' => $openInvoiceBalance,
            'overdueInvoiceCount' => $overdueInvoiceCount,
            'overdueInvoiceBalance' => $overdueInvoiceBalance,
            'nextOverdueInvoice' => $nextOverdueInvoice ? [
                'id' => $nextOverdueInvoice->id,
                'due_date_display' => $nextOverdueInvoice->due_date?->format($dateFormat) ?? 'N/A',
            ] : null,
            'nextOpenInvoice' => $nextOpenInvoice ? [
                'id' => $nextOpenInvoice->id,
                'due_date_display' => $nextOpenInvoice->due_date?->format($dateFormat) ?? 'N/A',
                'route_pay' => route('client.invoices.pay', $nextOpenInvoice),
            ] : null,
            'recentTickets' => $recentTickets->map(function ($ticket) use ($dateFormat) {
                return [
                    'id' => $ticket->id,
                    'subject' => $ticket->subject,
                    'updated_at_display' => $ticket->updated_at?->format($dateFormat) ?? '--',
                    'status_label' => str_replace('_', ' ', ucfirst((string) $ticket->status)),
                ];
            })->values()->all(),
            'expiringLicenses' => $expiringLicenses->map(function ($license) use ($dateFormat) {
                return [
                    'id' => $license->id,
                    'product_name' => $license->product?->name ?? 'Service',
                    'expires_at_display' => $license->expires_at?->format($dateFormat) ?? '--',
                ];
            })->values()->all(),
            'currency' => strtoupper((string) Setting::getValue('currency', Currency::DEFAULT)),
            'projects' => $projects->map(function ($project) {
                $done = (int) ($project->done_tasks_count ?? 0);
                $open = (int) ($project->open_tasks_count ?? 0);
                $totalTasks = max(0, $done + $open);

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status,
                    'status_label' => ucfirst(str_replace('_', ' ', (string) $project->status)),
                    'done_tasks_count' => $done,
                    'total_tasks_count' => $totalTasks,
                    'routes' => [
                        'show' => route('client.projects.show', $project),
                    ],
                ];
            })->values()->all(),
            'maintenanceRenewal' => $maintenanceRenewal ? [
                'next_renewal_display' => ($maintenanceRenewal->next_billing_date ?? $maintenanceRenewal->start_date)?->format($dateFormat) ?? '--',
                'plan' => $maintenanceRenewal->title ?? 'Maintenance',
                'project_name' => $maintenanceRenewal->project?->name,
            ] : null,
            'showTasksWidget' => (bool) $showTasksWidget,
            'taskSummary' => $tasksWidget['summary'] ?? null,
            'openTasks' => $this->formatWidgetTasks($tasksWidget['openTasks'] ?? collect()),
            'inProgressTasks' => $this->formatWidgetTasks($tasksWidget['inProgressTasks'] ?? collect()),
            'routes' => [
                'orders_index' => route('client.orders.index'),
                'support_create' => route('client.support-tickets.create'),
                'licenses_index' => route('client.licenses.index'),
                'projects_index' => route('client.projects.index'),
                'support_index' => route('client.support-tickets.index'),
                'invoices_index' => route('client.invoices.index'),
            ],
        ]);
    }

    private function projectSpecificDashboard(Request $request, $user, TaskQueryService $taskQueryService): InertiaResponse
    {
        if (! $user->project_id) {
            abort(404, 'Project assignment not found.');
        }

        $project = Project::with([
            'customer',
            'tasks' => function ($query) {
                $query->latest()->limit(10);
            },
            'tasks.assignments',
            'messages' => function ($query) {
                $query->latest('id')->limit(8);
            },
        ])->findOrFail($user->project_id);

        $this->authorize('view', $project);

        // Task statistics
        $totalTasks = $project->tasks()->count();
        $todoTasks = $project->tasks()->where('status', 'todo')->count();
        $inProgressTaskCount = $project->tasks()->where('status', 'in_progress')->count();
        $completedTasks = $project->tasks()->where('status', 'completed')->count();
        $blockedTasks = $project->tasks()->where('status', 'blocked')->count();

        // Recent activity (last 10 tasks)
        $recentTasks = $project->tasks()
            ->with('assignments')
            ->latest('updated_at')
            ->limit(10)
            ->get();

        // Unread chat count for project-specific client user
        $lastReadId = ProjectMessageRead::query()
            ->where('project_id', $project->id)
            ->where('reader_type', 'user')
            ->where('reader_id', $user->id)
            ->value('last_read_message_id');

        $unreadMessagesCount = $project->messages()
            ->when($lastReadId, fn ($query) => $query->where('id', '>', $lastReadId))
            ->count();

        // Recent chat messages
        $recentMessages = $project->messages()
            ->latest('id')
            ->limit(8)
            ->get();

        $showTasksWidget = $taskQueryService->canViewTasks($user);
        $tasksWidget = $showTasksWidget ? $taskQueryService->dashboardTasksForUser($user) : null;
        $dateFormat = config('app.date_format', 'd-m-Y');

        return Inertia::render('Client/Dashboard/ProjectMinimal', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'user' => [
                'name' => $user->name,
            ],
            'totalTasks' => $totalTasks,
            'todoTasks' => $todoTasks,
            'inProgressTaskCount' => $inProgressTaskCount,
            'completedTasks' => $completedTasks,
            'blockedTasks' => $blockedTasks,
            'recentTasks' => $recentTasks->map(function ($task) use ($project) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status_label' => ucfirst(str_replace('_', ' ', (string) $task->status)),
                    'route' => route('client.projects.tasks.show', [$project, $task]),
                ];
            })->values()->all(),
            'unreadMessagesCount' => $unreadMessagesCount,
            'recentMessages' => $recentMessages->map(function ($message) use ($dateFormat) {
                return [
                    'id' => $message->id,
                    'author_name' => $message->authorName(),
                    'message' => \Illuminate\Support\Str::limit((string) ($message->message ?? 'Attachment'), 120),
                    'created_at_display' => $message->created_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                ];
            })->values()->all(),
            'showTasksWidget' => (bool) $showTasksWidget,
            'taskSummary' => $tasksWidget['summary'] ?? null,
            'openTasks' => $this->formatWidgetTasks($tasksWidget['openTasks'] ?? collect()),
            'inProgressTasks' => $this->formatWidgetTasks($tasksWidget['inProgressTasks'] ?? collect()),
            'routes' => [
                'project_show' => route('client.projects.show', $project),
                'chat' => route('client.projects.chat', $project),
                'tasks_index' => route('client.tasks.index'),
            ],
        ]);
    }

    private function formatWidgetTasks($tasks): array
    {
        return collect($tasks)->map(function ($task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'status_label' => ucfirst(str_replace('_', ' ', (string) $task->status)),
                'project' => $task->project ? [
                    'id' => $task->project->id,
                    'name' => $task->project->name,
                    'route_show' => route('client.projects.show', $task->project),
                    'route_task_show' => route('client.projects.tasks.show', [$task->project, $task]),
                    'route_task_update' => route('client.projects.tasks.update', [$task->project, $task]),
                ] : null,
                'can_start' => (bool) ($task->can_start ?? false),
                'can_complete' => (bool) ($task->can_complete ?? false),
            ];
        })->values()->all();
    }
}
