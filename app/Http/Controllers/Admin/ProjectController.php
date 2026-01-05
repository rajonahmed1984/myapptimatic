<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SystemLogger;
use App\Services\CommissionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    private const STATUSES = ['active', 'on_hold', 'completed', 'cancelled'];
    private const TYPES = ['software', 'website', 'other'];
    private const TASK_STATUSES = ['todo', 'in_progress', 'blocked', 'done'];

    public function index(Request $request)
    {
        $statusFilter = $request->query('status');
        $typeFilter = $request->query('type');

        $projects = Project::query()
            ->with(['customer', 'order', 'subscription'])
            ->withCount([
                'tasks as open_tasks_count' => fn ($q) => $q->whereIn('status', ['todo', 'in_progress', 'blocked']),
                'tasks as done_tasks_count' => fn ($q) => $q->where('status', 'done'),
            ])
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->when($typeFilter, fn ($q) => $q->where('type', $typeFilter))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.projects.index', [
            'projects' => $projects,
            'statuses' => self::STATUSES,
            'types' => self::TYPES,
            'statusFilter' => $statusFilter,
            'typeFilter' => $typeFilter,
        ]);
    }

    public function create()
    {
        return view('admin.projects.create', [
            'statuses' => self::STATUSES,
            'types' => self::TYPES,
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'orders' => Order::latest()->limit(50)->get(['id', 'order_number']),
            'subscriptions' => Subscription::latest()->limit(50)->get(['id']),
            'invoices' => Invoice::latest('issue_date')->limit(50)->get(['id', 'number', 'total']),
            'employees' => Employee::where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'designation']),
        ]);
    }

    public function edit(Project $project)
    {
        return view('admin.projects.edit', [
            'project' => $project,
            'statuses' => self::STATUSES,
            'types' => self::TYPES,
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'orders' => Order::latest()->limit(50)->get(['id', 'order_number']),
            'subscriptions' => Subscription::latest()->limit(50)->get(['id']),
            'invoices' => Invoice::latest('issue_date')->limit(50)->get(['id', 'number', 'total']),
            'employees' => Employee::where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'designation']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'customer_id' => ['required', 'exists:customers,id'],
            'order_id' => ['nullable', 'exists:orders,id'],
            'subscription_id' => ['nullable', 'exists:subscriptions,id'],
            'advance_invoice_id' => ['nullable', 'exists:invoices,id'],
            'final_invoice_id' => ['nullable', 'exists:invoices,id'],
            'type' => ['required', 'in:software,website,other'],
            'status' => ['required', 'in:active,on_hold,completed,cancelled'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'planned_hours' => ['nullable', 'numeric', 'min:0'],
            'hourly_cost' => ['nullable', 'numeric', 'min:0'],
            'actual_hours' => ['nullable', 'numeric', 'min:0'],
        ]);

        $project = Project::create($data);

        SystemLogger::write('activity', 'Project created.', [
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'order_id' => $project->order_id,
        ], $request->user()?->id, $request->ip());

        return redirect()->route('admin.projects.show', $project)
            ->with('status', 'Project created.');
    }

    public function show(Project $project)
    {
        $project->load([
            'customer',
            'order.invoice',
            'subscription',
            'advanceInvoice',
            'finalInvoice',
            'tasks.assignee',
        ]);

        return view('admin.projects.show', [
            'project' => $project,
            'statuses' => self::STATUSES,
            'types' => self::TYPES,
            'taskStatuses' => self::TASK_STATUSES,
            'assignees' => User::orderBy('name')->get(['id', 'name']),
            'financials' => $this->financials($project),
        ]);
    }

    public function update(Request $request, Project $project, CommissionService $commissionService): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'customer_id' => ['required', 'exists:customers,id'],
            'order_id' => ['nullable', 'exists:orders,id'],
            'subscription_id' => ['nullable', 'exists:subscriptions,id'],
            'type' => ['required', 'in:software,website,other'],
            'status' => ['required', 'in:active,on_hold,completed,cancelled'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'advance_invoice_id' => ['nullable', 'exists:invoices,id'],
            'final_invoice_id' => ['nullable', 'exists:invoices,id'],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'planned_hours' => ['nullable', 'numeric', 'min:0'],
            'hourly_cost' => ['nullable', 'numeric', 'min:0'],
            'actual_hours' => ['nullable', 'numeric', 'min:0'],
        ]);

        $previousStatus = $project->status;
        $project->update($data);

        SystemLogger::write('activity', 'Project updated.', [
            'project_id' => $project->id,
            'status' => $project->status,
        ], $request->user()?->id, $request->ip());

        if ($previousStatus !== 'completed' && $project->status === 'completed') {
            try {
                $commissionService->markEarningPayableOnProjectCompleted($project);
            } catch (\Throwable $e) {
                SystemLogger::write('module', 'Commission payable transition failed on project completion.', [
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                ], level: 'error');
            }
        }

        return back()->with('status', 'Project updated.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        SystemLogger::write('activity', 'Project deleted.', [
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
        ]);

        return redirect()->route('admin.projects.index')->with('status', 'Project deleted.');
    }

    public function storeTask(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:todo,in_progress,blocked,done'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $taskData = array_merge($data, [
            'completed_at' => $data['status'] === 'done' ? Carbon::now() : null,
        ]);

        $task = $project->tasks()->create($taskData);

        SystemLogger::write('activity', 'Project task created.', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'status' => $task->status,
        ], $request->user()?->id, $request->ip());

        return back()->with('status', 'Task added.');
    }

    public function updateTask(Request $request, Project $project, ProjectTask $task): RedirectResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:todo,in_progress,blocked,done'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $payload = array_merge($data, [
            'completed_at' => $data['status'] === 'done'
                ? ($task->completed_at ?: Carbon::now())
                : null,
        ]);

        $task->update($payload);

        SystemLogger::write('activity', 'Project task updated.', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'status' => $task->status,
        ], $request->user()?->id, $request->ip());

        return back()->with('status', 'Task updated.');
    }

    public function destroyTask(Project $project, ProjectTask $task): RedirectResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);

        $task->delete();

        SystemLogger::write('activity', 'Project task deleted.', [
            'project_id' => $project->id,
            'task_id' => $task->id,
        ]);

        return back()->with('status', 'Task deleted.');
    }

    private function ensureTaskBelongsToProject(Project $project, ProjectTask $task): void
    {
        if ($task->project_id !== $project->id) {
            abort(404);
        }
    }

    private function financials(Project $project): array
    {
        $budget = (float) ($project->budget_amount ?? 0);
        $plannedHours = (float) ($project->planned_hours ?? 0);
        $actualHours = (float) ($project->actual_hours ?? $plannedHours);
        $hourlyCost = (float) ($project->hourly_cost ?? 0);

        $plannedCost = $hourlyCost * $plannedHours;
        $actualCost = $hourlyCost * $actualHours;
        $profit = $budget - $actualCost;

        return [
            'budget' => $budget,
            'planned_hours' => $plannedHours,
            'actual_hours' => $actualHours,
            'hourly_cost' => $hourlyCost,
            'planned_cost' => $plannedCost,
            'actual_cost' => $actualCost,
            'profit' => $profit,
            'profitable' => $profit >= 0,
        ];
    }
}
