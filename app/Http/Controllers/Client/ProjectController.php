<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMessageRead;
use App\Support\TaskSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectController extends Controller
{
    public function index(Request $request): InertiaResponse|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        // Redirect project-specific users directly to their assigned project
        if ($user->isClientProject() && $user->project_id) {
            return redirect()->route('client.projects.show', $user->project_id);
        }

        $query = Project::query()
            ->with(['customer', 'maintenances'])
            ->where('customer_id', $user->customer_id);

        $projects = $query->latest()->paginate(20);

        return Inertia::render('Client/Projects/Index', [
            'projects' => $projects->getCollection()->map(function (Project $project) {
                $projectAmount = $project->total_budget ?? $project->budget_amount;

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status_label' => ucfirst(str_replace('_', ' ', (string) $project->status)),
                    'amount_label' => $projectAmount !== null
                        ? ($project->currency.' '.number_format((float) $projectAmount, 2))
                        : '--',
                    'routes' => [
                        'show' => route('client.projects.show', $project),
                    ],
                    'maintenances' => $project->maintenances->map(function ($maintenance) {
                        return [
                            'id' => $maintenance->id,
                            'title' => $maintenance->title,
                            'status' => $maintenance->status,
                            'status_label' => ucfirst((string) $maintenance->status),
                            'billing_cycle_label' => ucfirst((string) $maintenance->billing_cycle),
                            'amount_label' => $maintenance->currency.' '.number_format((float) $maintenance->amount, 2),
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
            'pagination' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
                'from' => $projects->firstItem(),
                'to' => $projects->lastItem(),
                'prev_page_url' => $projects->previousPageUrl(),
                'next_page_url' => $projects->nextPageUrl(),
            ],
        ]);
    }

    public function show(Request $request, Project $project): InertiaResponse
    {
        $this->authorize('view', $project);

        $project->load(['customer', 'overheads']);

        $tasks = $project->tasks()
            ->where('customer_visible', true)
            ->withCount([
                'subtasks',
                'subtasks as completed_subtasks_count' => fn ($query) => $query->where('is_completed', true),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $chatMessages = $project->messages()
            ->with(['userAuthor', 'employeeAuthor', 'salesRepAuthor'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();

        $currentAuthorType = 'user';
        $currentAuthorId = $request->user()?->id;

        $lastReadId = ProjectMessageRead::query()
            ->where('project_id', $project->id)
            ->where('reader_type', $currentAuthorType)
            ->where('reader_id', $currentAuthorId)
            ->value('last_read_message_id');

        $unreadCount = $project->messages()
            ->when($lastReadId, fn ($query) => $query->where('id', '>', $lastReadId))
            ->count();

        $chatMeta = [
            'messagesUrl' => route('client.projects.chat.messages', $project),
            'postMessagesUrl' => route('client.projects.chat.messages.store', $project),
            'postRoute' => route('client.projects.chat.store', $project),
            'readUrl' => route('client.projects.chat.read', $project),
            'attachmentRouteName' => 'client.projects.chat.messages.attachment',
            'currentAuthorType' => $currentAuthorType,
            'currentAuthorId' => $currentAuthorId,
            'canPost' => Gate::forUser($request->user())->check('view', $project),
            'unreadCount' => (int) $unreadCount,
        ];

        $maintenances = $project->maintenances()
            ->with(['invoices' => fn ($query) => $query->latest('issue_date')])
            ->orderBy('next_billing_date')
            ->get();

        $initialInvoice = $project->invoices()
            ->where('type', 'project_initial_payment')
            ->latest('issue_date')
            ->first();

        $statusCounts = $project->tasks()
            ->where('customer_visible', true)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $budgetBase = $project->total_budget ?? $project->budget_amount;
        $overheadTotal = (float) ($project->overhead_total ?? 0);
        $initialPaymentInvoiced = (float) $project->invoices()
            ->where('type', 'project_initial_payment')
            ->sum('total');
        $initialPayment = $initialPaymentInvoiced > 0
            ? $initialPaymentInvoiced
            : ($project->initial_payment_amount ?? null);
        $budgetWithOverhead = $budgetBase !== null
            ? (float) $budgetBase + $overheadTotal
            : null;

        $isProjectSpecificUser = $request->user()->isClientProject();
        $dateFormat = config('app.date_format', 'd-m-Y');
        $currentUser = $request->user();

        return Inertia::render('Client/Projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status_label' => ucfirst(str_replace('_', ' ', (string) $project->status)),
                'start_date_display' => $project->start_date?->format($dateFormat) ?? '--',
                'expected_end_date_display' => $project->expected_end_date?->format($dateFormat) ?? '--',
                'due_date_display' => $project->due_date?->format($dateFormat) ?? '--',
                'currency' => $project->currency ?? '',
            ],
            'tasks' => $tasks->map(function ($task) use ($project, $currentUser, $dateFormat) {
                $canEditTask = $currentUser?->isMasterAdmin()
                    || ($task->created_by
                        && $currentUser
                        && $task->created_by === $currentUser->id
                        && ! $task->creatorEditWindowExpired($currentUser->id));

                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'task_type' => $task->task_type,
                    'status' => (string) ($task->status ?? 'pending'),
                    'progress' => (int) ($task->progress ?? 0),
                    'subtasks_count' => (int) ($task->subtasks_count ?? 0),
                    'completed_subtasks_count' => (int) ($task->completed_subtasks_count ?? 0),
                    'start_date_display' => $task->start_date?->format($dateFormat) ?? '--',
                    'due_date_display' => $task->due_date?->format($dateFormat) ?? '--',
                    'can_edit' => (bool) $canEditTask,
                    'routes' => [
                        'show' => route('client.projects.tasks.show', [$project, $task]),
                        'edit_anchor' => $canEditTask ? route('client.projects.tasks.show', [$project, $task]).'#task-edit' : null,
                    ],
                ];
            })->values()->all(),
            'maintenances' => $maintenances->map(function ($maintenance) use ($dateFormat) {
                $latestInvoice = $maintenance->invoices->first();

                return [
                    'id' => $maintenance->id,
                    'title' => $maintenance->title,
                    'billing_cycle_label' => ucfirst((string) $maintenance->billing_cycle),
                    'next_billing_date_display' => $maintenance->next_billing_date?->format($dateFormat) ?? '--',
                    'status' => $maintenance->status,
                    'status_label' => ucfirst((string) $maintenance->status),
                    'amount_label' => $maintenance->currency.' '.number_format((float) $maintenance->amount, 2),
                    'invoice_count' => (int) ($maintenance->invoices?->count() ?? 0),
                    'latest_invoice' => $latestInvoice ? [
                        'label' => '#'.($latestInvoice->number ?? $latestInvoice->id),
                        'route' => route('client.invoices.show', $latestInvoice),
                    ] : null,
                ];
            })->values()->all(),
            'initial_invoice' => $initialInvoice ? [
                'label' => '#'.($initialInvoice->number ?? $initialInvoice->id),
                'status_label' => ucfirst((string) $initialInvoice->status),
                'route' => route('client.invoices.show', $initialInvoice),
            ] : null,
            'financials' => [
                'budget' => $budgetBase,
                'initial_payment' => $initialPayment,
                'overhead_total' => $overheadTotal,
                'budget_with_overhead' => $budgetWithOverhead,
            ],
            'taskTypeOptions' => TaskSettings::taskTypeOptions(),
            'priorityOptions' => TaskSettings::priorityOptions(),
            'task_stats' => [
                'total' => (int) $statusCounts->values()->sum(),
                'in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
                'completed' => (int) (($statusCounts['completed'] ?? 0) + ($statusCounts['done'] ?? 0)),
                'unread' => (int) $unreadCount,
            ],
            'chat_messages' => $chatMessages->map(function ($message) use ($dateFormat) {
                return [
                    'id' => $message->id,
                    'author_name' => $message->authorName(),
                    'message' => $message->message,
                    'created_at_display' => $message->created_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                ];
            })->values()->all(),
            'chat_meta' => $chatMeta,
            'is_project_specific_user' => (bool) $isProjectSpecificUser,
            'routes' => [
                'task_store' => route('client.projects.tasks.store', $project),
                'chat' => route('client.projects.chat', $project),
            ],
        ]);
    }
}
