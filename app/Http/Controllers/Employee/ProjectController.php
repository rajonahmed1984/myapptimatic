<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\ProjectTaskSubtask;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Models\Employee;
use App\Support\TaskSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function index(Request $request)
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

        return view('employee.projects.index', compact('projects'));
    }

    public function show(Request $request, Project $project)
    {
        $user = $request->user(); // guard: employee (returns User model)
        $this->authorize('view', $project);

        $project->load(['customer']);

        $tasks = $project->tasks()
            ->orderBy('id')
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

        $chatMeta = [
            'messagesUrl' => route('employee.projects.chat.messages', $project),
            'postMessagesUrl' => route('employee.projects.chat.messages.store', $project),
            'postRoute' => route('employee.projects.chat.store', $project),
            'readUrl' => route('employee.projects.chat.read', $project),
            'attachmentRouteName' => 'employee.projects.chat.messages.attachment',
            'currentAuthorType' => $currentAuthorType,
            'currentAuthorId' => $currentAuthorId,
            'canPost' => Gate::forUser($request->user())->check('view', $project),
        ];

        $employees = Employee::where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $salesReps = []; // employees do not assign sales reps; left empty

        $initialInvoice = $project->invoices()
            ->where('type', 'project_initial_payment')
            ->latest('issue_date')
            ->first();

        return view('employee.projects.show', [
            'project' => $project,
            'tasks' => $tasks,
            'employees' => $employees,
            'salesReps' => $salesReps,
            'initialInvoice' => $initialInvoice,
            'taskTypeOptions' => TaskSettings::taskTypeOptions(),
            'priorityOptions' => TaskSettings::priorityOptions(),
            'chatMessages' => $chatMessages,
            'chatMeta' => $chatMeta,
        ]);
    }
}
