<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectMessageRead;
use App\Models\ProjectTaskSubtask;
use App\Support\TaskSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user(); // guard: employee (returns User model)
        $employee = $user?->employee; // Get the associated Employee model
        $employeeId = $employee?->id;

        $projects = Project::query()
            ->with(['customer'])
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn ($query) => $query->whereIn('status', ['completed', 'done']),
            ])
            ->addSelect([
                'subtasks_count' => ProjectTaskSubtask::query()
                    ->selectRaw('count(*)')
                    ->join('project_tasks', 'project_tasks.id', '=', 'project_task_subtasks.project_task_id')
                    ->whereColumn('project_tasks.project_id', 'projects.id'),
                'completed_subtasks_count' => ProjectTaskSubtask::query()
                    ->selectRaw('count(*)')
                    ->join('project_tasks', 'project_tasks.id', '=', 'project_task_subtasks.project_task_id')
                    ->whereColumn('project_tasks.project_id', 'projects.id')
                    ->where('project_task_subtasks.is_completed', true),
            ])
            ->whereHas('employees', fn ($q) => $q->whereKey($employeeId))
            ->latest()
            ->paginate(20);

        return Inertia::render('Employee/Projects/Index', [
            'projects' => $projects->getCollection()->map(function (Project $project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status_label' => ucfirst(str_replace('_', ' ', (string) $project->status)),
                    'tasks_count' => (int) ($project->tasks_count ?? 0),
                    'completed_tasks_count' => (int) ($project->completed_tasks_count ?? 0),
                    'subtasks_count' => (int) ($project->subtasks_count ?? 0),
                    'completed_subtasks_count' => (int) ($project->completed_subtasks_count ?? 0),
                    'routes' => [
                        'show' => route('employee.projects.show', $project),
                    ],
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
        $user = $request->user(); // guard: employee (returns User model)
        $this->authorize('view', $project);

        $project->load(['customer']);

        $tasks = $project->tasks()
            ->with(['assignments'])
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

        $employee = $request->attributes->get('employee');
        $currentAuthorType = $employee ? 'employee' : 'user';
        $currentAuthorId = $employee?->id ?? $request->user()?->id;

        $lastReadId = ProjectMessageRead::query()
            ->where('project_id', $project->id)
            ->where('reader_type', $currentAuthorType)
            ->where('reader_id', $currentAuthorId)
            ->value('last_read_message_id');

        $unreadCount = $project->messages()
            ->when($lastReadId, fn ($query) => $query->where('id', '>', $lastReadId))
            ->count();

        $chatMeta = [
            'messagesUrl' => route('employee.projects.chat.messages', $project),
            'postMessagesUrl' => route('employee.projects.chat.messages.store', $project),
            'postRoute' => route('employee.projects.chat.store', $project),
            'readUrl' => route('employee.projects.chat.read', $project),
            'attachmentRouteName' => 'employee.projects.chat.messages.attachment',
            'currentAuthorType' => $currentAuthorType,
            'currentAuthorId' => $currentAuthorId,
            'canPost' => Gate::forUser($request->user())->check('view', $project),
            'unreadCount' => (int) $unreadCount,
        ];

        $employees = Employee::where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $salesReps = []; // employees do not assign sales reps; left empty

        $initialInvoice = $project->invoices()
            ->where('type', 'project_initial_payment')
            ->latest('issue_date')
            ->first();

        $statusCounts = $project->tasks()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $dateFormat = config('app.date_format', 'd-m-Y');
        $currentUser = $request->user();
        $employeeId = $currentUser?->employee?->id;

        return Inertia::render('Employee/Projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status_label' => ucfirst(str_replace('_', ' ', (string) $project->status)),
                'start_date_display' => $project->start_date?->format($dateFormat) ?? '--',
                'expected_end_date_display' => $project->expected_end_date?->format($dateFormat) ?? '--',
                'due_date_display' => $project->due_date?->format($dateFormat) ?? '--',
            ],
            'tasks' => $tasks->map(function ($task) use ($project, $currentUser, $employeeId, $dateFormat) {
                $currentStatus = (string) ($task->status ?? 'pending');
                $hasSubtasks = (int) ($task->subtasks_count ?? 0) > 0;
                $isAssigned = $employeeId && (
                    ($task->assigned_type === 'employee' && (int) $task->assigned_id === (int) $employeeId)
                    || ($task->assignee_id && (int) $task->assignee_id === (int) ($currentUser?->id))
                    || $task->assignments
                        ->where('assignee_type', 'employee')
                        ->pluck('assignee_id')
                        ->map(fn ($id) => (int) $id)
                        ->contains((int) $employeeId)
                );
                $canChangeStatus = $currentUser?->isMasterAdmin()
                    || $isAssigned
                    || ($task->created_by
                        && $currentUser
                        && $task->created_by === $currentUser->id
                        && ! $task->creatorEditWindowExpired($currentUser->id));

                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'task_type' => $task->task_type,
                    'customer_visible' => (bool) $task->customer_visible,
                    'status' => $currentStatus,
                    'progress' => (int) ($task->progress ?? 0),
                    'subtasks_count' => (int) ($task->subtasks_count ?? 0),
                    'completed_subtasks_count' => (int) ($task->completed_subtasks_count ?? 0),
                    'start_date_display' => $task->start_date?->format($dateFormat) ?? '--',
                    'due_date_display' => $task->due_date?->format($dateFormat) ?? '--',
                    'completed_at_display' => $task->completed_at?->format($dateFormat),
                    'can_start' => $canChangeStatus && ! $hasSubtasks && in_array($currentStatus, ['pending', 'todo'], true),
                    'can_complete' => $canChangeStatus && ! $hasSubtasks && ! in_array($currentStatus, ['completed', 'done'], true),
                    'routes' => [
                        'show' => route('employee.projects.tasks.show', [$project, $task]),
                        'start' => route('employee.projects.tasks.start', [$project, $task]),
                        'update' => route('employee.projects.tasks.update', [$project, $task]),
                    ],
                ];
            })->values()->all(),
            'initial_invoice' => $initialInvoice ? [
                'label' => '#'.($initialInvoice->number ?? $initialInvoice->id),
                'status_label' => ucfirst((string) $initialInvoice->status),
            ] : null,
            'task_type_options' => TaskSettings::taskTypeOptions(),
            'priority_options' => TaskSettings::priorityOptions(),
            'chat_messages' => $chatMessages->map(function ($message) use ($dateFormat) {
                return [
                    'id' => $message->id,
                    'author_name' => $message->authorName(),
                    'message' => $message->message,
                    'created_at_display' => $message->created_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                ];
            })->values()->all(),
            'chat_meta' => $chatMeta,
            'task_stats' => [
                'total' => (int) $statusCounts->values()->sum(),
                'in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
                'completed' => (int) (($statusCounts['completed'] ?? 0) + ($statusCounts['done'] ?? 0)),
                'unread' => (int) $unreadCount,
            ],
            'permissions' => [
                'can_create_task' => $request->user()->can('createTask', $project),
            ],
            'routes' => [
                'chat' => route('employee.projects.chat', $project),
                'task_store' => route('employee.projects.tasks.store', $project),
            ],
            'employees' => $employees->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
            ])->values()->all(),
            'sales_reps' => $salesReps,
        ]);
    }
}
