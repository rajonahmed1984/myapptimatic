<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\SalesRepresentative;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SystemLogger;
use App\Services\CommissionService;
use App\Services\BillingService;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    private const STATUSES = ['draft', 'active', 'on_hold', 'completed', 'cancelled'];
    private const TYPES = ['software', 'website', 'other'];
    private const TASK_STATUSES = ['pending', 'in_progress', 'blocked', 'completed'];

    public function index(Request $request)
    {
        $statusFilter = $request->query('status');
        $typeFilter = $request->query('type');
        $user = $request->user();
        $isAdmin = $user?->isAdmin();
        $employeeId = $user?->employee?->id;
        $salesRepId = \App\Models\SalesRepresentative::where('user_id', $user?->id)->value('id');

        $projects = Project::query()
            ->with(['customer', 'order', 'subscription'])
            ->withCount([
                'tasks as open_tasks_count' => fn ($q) => $q->whereIn('status', ['pending', 'in_progress', 'blocked', 'todo']),
                'tasks as done_tasks_count' => fn ($q) => $q->whereIn('status', ['completed', 'done']),
            ])
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->when($typeFilter, fn ($q) => $q->where('type', $typeFilter))
            ->when(! $isAdmin && $user?->isClient(), fn ($q) => $q->where('customer_id', $user->customer_id))
            ->when(! $isAdmin && $user?->isEmployee() && $employeeId, fn ($q) => $q->whereHas('employees', fn ($sub) => $sub->whereKey($employeeId)))
            ->when(! $isAdmin && $user?->isSales() && $salesRepId, fn ($q) => $q->whereHas('salesRepresentatives', fn ($sub) => $sub->whereKey($salesRepId)))
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
            'salesReps' => SalesRepresentative::where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'defaultCurrency' => strtoupper((string) Setting::getValue('currency')),
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
            'salesReps' => SalesRepresentative::where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request, BillingService $billingService): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'customer_id' => ['required', 'exists:customers,id'],
            'order_id' => ['nullable', 'exists:orders,id'],
            'subscription_id' => ['nullable', 'exists:subscriptions,id'],
            'advance_invoice_id' => ['nullable', 'exists:invoices,id'],
            'final_invoice_id' => ['nullable', 'exists:invoices,id'],
            'type' => ['required', 'in:software,website,other'],
            'status' => ['required', 'in:draft,active,on_hold,completed,cancelled'],
            'start_date' => ['nullable', 'date'],
            'expected_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'total_budget' => ['required', 'numeric', 'min:0'],
            'initial_payment_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'planned_hours' => ['nullable', 'numeric', 'min:0'],
            'hourly_cost' => ['nullable', 'numeric', 'min:0'],
            'actual_hours' => ['nullable', 'numeric', 'min:0'],
            'sales_rep_ids' => ['array'],
            'sales_rep_ids.*' => ['exists:sales_representatives,id'],
            'employee_ids' => ['array'],
            'employee_ids.*' => ['exists:employees,id'],
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.title' => ['required', 'string', 'max:255'],
            'tasks.*.descriptions' => ['nullable', 'array'],
            'tasks.*.descriptions.*' => ['nullable', 'string'],
            'tasks.*.start_date' => ['required', 'date'],
            'tasks.*.due_date' => ['required', 'date'],
            'tasks.*.assignee' => ['required', 'string'], // format: type:id
            'tasks.*.customer_visible' => ['nullable', 'boolean'],
        ]);

        foreach ($data['tasks'] as $task) {
            if (isset($task['start_date'], $task['due_date'])) {
                $start = Carbon::parse($task['start_date']);
                $due = Carbon::parse($task['due_date']);
                if ($due->lt($start)) {
                    return back()
                        ->withErrors(['tasks' => 'Task due date must be on or after start date.'])
                        ->withInput();
                }
            }
        }

        $project = DB::transaction(function () use ($data, $request, $billingService) {
            $project = Project::create([
                'name' => $data['name'],
                'customer_id' => $data['customer_id'],
                'order_id' => $data['order_id'] ?? null,
                'subscription_id' => $data['subscription_id'] ?? null,
                'advance_invoice_id' => $data['advance_invoice_id'] ?? null,
                'final_invoice_id' => $data['final_invoice_id'] ?? null,
                'type' => $data['type'],
                'status' => $data['status'],
                'start_date' => $data['start_date'] ?? null,
                'expected_end_date' => $data['expected_end_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'total_budget' => $data['total_budget'],
                'initial_payment_amount' => $data['initial_payment_amount'],
                'currency' => strtoupper($data['currency']),
                'sales_rep_ids' => $data['sales_rep_ids'] ?? [],
                'budget_amount' => $data['budget_amount'] ?? null,
                'planned_hours' => $data['planned_hours'] ?? null,
                'hourly_cost' => $data['hourly_cost'] ?? null,
                'actual_hours' => $data['actual_hours'] ?? null,
            ]);

            if (! empty($data['employee_ids'])) {
                $project->employees()->sync($data['employee_ids']);
            }

            if (! empty($data['sales_rep_ids'])) {
                $project->salesRepresentatives()->sync($data['sales_rep_ids']);
            }

            foreach ($data['tasks'] as $task) {
                [$assignedType, $assignedId] = $this->parseAssignee($task['assignee']);

                // Combine multiple descriptions with newlines
                $descriptions = $task['descriptions'] ?? [];
                $description = implode("\n", array_filter($descriptions));

                ProjectTask::create([
                    'project_id' => $project->id,
                    'title' => $task['title'],
                    'description' => $description ?: null,
                    'status' => 'pending',
                    'start_date' => $task['start_date'],
                    'due_date' => $task['due_date'],
                    'assigned_type' => $assignedType,
                    'assigned_id' => $assignedId,
                    'customer_visible' => (bool) ($task['customer_visible'] ?? false),
                    'created_by' => $request->user()?->id,
                ]);
            }

            $issueDate = Carbon::today();
            $dueDays = (int) Setting::getValue('invoice_due_days');
            $dueDate = $issueDate->copy()->addDays($dueDays);

            $invoice = Invoice::create([
                'customer_id' => $project->customer_id,
                'project_id' => $project->id,
                'number' => $billingService->nextInvoiceNumber(),
                'status' => 'unpaid',
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'subtotal' => $project->initial_payment_amount,
                'late_fee' => 0,
                'total' => $project->initial_payment_amount,
                'currency' => $project->currency,
                'type' => 'project_initial_payment',
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => sprintf('Initial payment for project %s', $project->name),
                'quantity' => 1,
                'unit_price' => $project->initial_payment_amount,
                'line_total' => $project->initial_payment_amount,
            ]);

            SystemLogger::write('activity', 'Project created.', [
                'project_id' => $project->id,
                'customer_id' => $project->customer_id,
                'order_id' => $project->order_id,
                'invoice_id' => $invoice->id,
            ], $request->user()?->id, $request->ip());

            return $project;
        });

        return redirect()->route('admin.projects.show', $project)
            ->with('status', 'Project created with initial tasks and invoice.');
    }

    public function show(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $project->load([
            'customer',
            'order.invoice',
            'subscription',
            'advanceInvoice',
            'finalInvoice',
            'tasks.assignee',
        ]);

        $user = $request->user();
        $employeeId = $user?->employee?->id;
        $salesRepId = SalesRepresentative::where('user_id', $user?->id)->value('id');

        $tasksQuery = $project->tasks()->orderBy('id');

        $tasksQuery->when($user?->isClient(), fn ($q) => $q->where('customer_visible', true))
            ->when($user?->isEmployee() && $employeeId, fn ($q) => $q->where(function ($sub) use ($employeeId) {
                $sub->where(function ($qq) use ($employeeId) {
                    $qq->where('assigned_type', 'employee')->where('assigned_id', $employeeId);
                })->orWhere('customer_visible', true);
            }))
            ->when($user?->isSales() && $salesRepId, fn ($q) => $q->where(function ($sub) use ($salesRepId) {
                $sub->where(function ($qq) use ($salesRepId) {
                    $qq->where('assigned_type', 'sales_rep')->where('assigned_id', $salesRepId);
                })->orWhere('customer_visible', true);
            }));

        $tasks = $tasksQuery->get();

        $initialInvoice = $project->invoices()
            ->where('type', 'project_initial_payment')
            ->latest('issue_date')
            ->first();

        return view('admin.projects.show', [
            'project' => $project,
            'statuses' => self::STATUSES,
            'types' => self::TYPES,
            'taskStatuses' => self::TASK_STATUSES,
            'employees' => Employee::where('status', 'active')->orderBy('name')->get(['id', 'name', 'designation']),
            'salesReps' => SalesRepresentative::where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']),
            'financials' => $this->financials($project),
            'tasks' => $tasks,
            'initialInvoice' => $initialInvoice,
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
            'status' => ['required', 'in:draft,active,on_hold,completed,cancelled'],
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

    private function parseAssignee(string $value): array
    {
        [$type, $id] = array_pad(explode(':', $value, 2), 2, null);
        $id = $id ? (int) $id : null;

        if (! $type || ! $id) {
            abort(422, 'Invalid assignee');
        }

        if (! in_array($type, ['employee', 'sales_rep'], true)) {
            abort(422, 'Invalid assignee type');
        }

        if ($type === 'employee' && ! Employee::whereKey($id)->exists()) {
            abort(422, 'Employee not found');
        }

        if ($type === 'sales_rep' && ! SalesRepresentative::whereKey($id)->exists()) {
            abort(422, 'Sales representative not found');
        }

        return [$type, $id];
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
