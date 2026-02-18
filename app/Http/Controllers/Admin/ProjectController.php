<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectMaintenance;
use App\Models\ProjectTask;
use App\Models\ProjectMessageRead;
use App\Models\SalesRepresentative;
use App\Models\Subscription;
use App\Models\User;
use App\Services\TaskQueryService;
use App\Support\AjaxResponse;
use App\Support\SystemLogger;
use App\Support\TaskActivityLogger;
use App\Support\TaskAssignmentManager;
use App\Support\TaskAssignees;
use App\Support\Currency;
use App\Support\TaskCompletionManager;
use App\Support\TaskSettings;
use App\Services\TaskStatusNotificationService;
use App\Services\CommissionService;
use App\Services\BillingService;
use App\Services\InvoiceTaxService;
use App\Services\GeminiService;
use App\Services\ProjectStatusAiService;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    private const STATUSES = ['ongoing', 'hold', 'complete', 'cancel'];
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
            ->with(['customer', 'order', 'subscription', 'employees', 'salesRepresentatives'])
            ->withCount([
                'tasks as open_tasks_count' => fn ($q) => $q->whereIn('status', ['pending', 'in_progress', 'blocked', 'todo']),
                'tasks as done_tasks_count' => fn ($q) => $q->whereIn('status', ['completed', 'done']),
                'subtasks as open_subtasks_count' => fn ($q) => $q->where('is_completed', false),
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
        $defaultCurrency = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($defaultCurrency)) {
            $defaultCurrency = Currency::DEFAULT;
        }

        return view('admin.projects.create', [
            'statuses' => self::STATUSES,
            'types' => self::TYPES,
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'company_name']),
            'orders' => Order::latest()->limit(50)->get(['id', 'order_number']),
            'subscriptions' => Subscription::latest()->limit(50)->get(['id']),
            'invoices' => Invoice::latest('issue_date')->limit(50)->get(['id', 'number', 'total']),
            'employees' => Employee::where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'designation', 'employment_type']),
            'salesReps' => SalesRepresentative::where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'defaultCurrency' => $defaultCurrency,
            'currencyOptions' => Currency::allowed(),
            'taskTypeOptions' => TaskSettings::taskTypeOptions(),
            'priorityOptions' => TaskSettings::priorityOptions(),
        ]);
    }

    public function edit(Project $project)
    {
        return view('admin.projects.edit', [
            'project' => $project,
            'statuses' => self::STATUSES,
            'types' => self::TYPES,
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'company_name']),
            'orders' => Order::latest()->limit(50)->get(['id', 'order_number']),
            'subscriptions' => Subscription::latest()->limit(50)->get(['id']),
            'invoices' => Invoice::latest('issue_date')->limit(50)->get(['id', 'number', 'total']),
            'employees' => Employee::where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'designation', 'employment_type']),
            'salesReps' => SalesRepresentative::where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'currencyOptions' => Currency::allowed(),
        ]);
    }

    public function store(Request $request, BillingService $billingService, InvoiceTaxService $taxService, CommissionService $commissionService): RedirectResponse
    {
        $taskTypeOptions = array_keys(TaskSettings::taskTypeOptions());
        $priorityOptions = array_keys(TaskSettings::priorityOptions());
        $maxMb = TaskSettings::uploadMaxMb();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'customer_id' => ['required', 'exists:customers,id'],
            'order_id' => ['nullable', 'exists:orders,id'],
            'subscription_id' => ['nullable', 'exists:subscriptions,id'],
            'advance_invoice_id' => ['nullable', 'exists:invoices,id'],
            'final_invoice_id' => ['nullable', 'exists:invoices,id'],
            'type' => ['required', 'in:software,website,other'],
            'status' => ['required', 'in:ongoing,hold,complete,cancel'],
            'start_date' => ['nullable', 'date'],
            'expected_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'total_budget' => ['required', 'numeric', 'min:0'],
            'initial_payment_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3', Rule::in(Currency::allowed())],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'software_overhead' => ['nullable', 'numeric', 'min:0'],
            'website_overhead' => ['nullable', 'numeric', 'min:0'],
            'overheads' => ['array'],
            'overheads.*.short_details' => ['nullable', 'string', 'max:255'],
            'overheads.*.amount' => ['nullable', 'numeric', 'min:0'],
            'contract_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:10240'],
            'proposal_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:10240'],
            'sales_rep_ids' => ['array'],
            'sales_rep_ids.*' => ['exists:sales_representatives,id'],
            'sales_rep_amounts' => ['array'],
            'sales_rep_amounts.*' => ['nullable', 'numeric', 'min:0'],
            'employee_ids' => ['array'],
            'employee_ids.*' => ['exists:employees,id'],
            'contract_employee_amounts' => ['array'],
            'contract_employee_amounts.*' => ['nullable', 'numeric', 'min:0'],
            'maintenances' => ['nullable', 'array'],
            'maintenances.*.title' => ['required', 'string', 'max:255'],
            'maintenances.*.amount' => ['required', 'numeric', 'min:0.01'],
            'maintenances.*.billing_cycle' => ['required', 'in:monthly,yearly'],
            'maintenances.*.start_date' => ['required', 'date'],
            'maintenances.*.auto_invoice' => ['nullable', 'boolean'],
            'maintenances.*.sales_rep_visible' => ['nullable', 'boolean'],
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.title' => ['required', 'string', 'max:255'],
            'tasks.*.descriptions' => ['nullable', 'array'],
            'tasks.*.descriptions.*' => ['nullable', 'string'],
            'tasks.*.task_type' => ['required', Rule::in($taskTypeOptions)],
            'tasks.*.priority' => ['nullable', Rule::in($priorityOptions)],
            'tasks.*.time_estimate_minutes' => ['nullable', 'integer', 'min:0'],
            'tasks.*.tags' => ['nullable', 'string'],
            'tasks.*.relationship_ids' => ['nullable', 'string'],
            'tasks.*.start_date' => ['required', 'date'],
            'tasks.*.due_date' => ['required', 'date'],
            'tasks.*.assignee' => ['required', 'string'], // format: type:id
            'tasks.*.attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,docx,xlsx', 'max:' . ($maxMb * 1024)],
            'tasks.*.customer_visible' => ['nullable', 'boolean'],
        ]);

        foreach ($data['tasks'] as $index => $task) {
            if (isset($task['start_date'], $task['due_date'])) {
                $start = Carbon::parse($task['start_date']);
                $due = Carbon::parse($task['due_date']);
                if ($due->lt($start)) {
                    return back()
                        ->withErrors(['tasks' => 'Task due date must be on or after start date.'])
                        ->withInput();
                }
            }

            if (($task['task_type'] ?? null) === 'upload' && ! $request->file("tasks.{$index}.attachment")) {
                return back()
                    ->withErrors(['tasks' => 'Upload tasks require at least one file.'])
                    ->withInput();
            }
        }

        $salesRepSync = [];
        if (! empty($data['sales_rep_ids'])) {
            $salesRepSync = $this->buildSalesRepSyncData($request, $data['sales_rep_ids']);
            $totalSalesRepAmount = array_sum(array_column($salesRepSync, 'amount'));
            if ($totalSalesRepAmount > (float) $data['total_budget']) {
                return back()
                    ->withErrors(['sales_rep_amounts' => 'Total sales rep amounts cannot exceed total budget.'])
                    ->withInput();
            }
        }

        $contractEmployeeTotal = null;
        $contractEmployeePayable = null;
        $contractEmployeePayoutStatus = null;

        $contractEmployeeIds = [];
        if (! empty($data['employee_ids'])) {
            $contractEmployeeIds = Employee::whereIn('id', $data['employee_ids'])
                ->where('employment_type', 'contract')
                ->pluck('id')
                ->all();
        }

        if (! empty($contractEmployeeIds)) {
            $amounts = $request->input('contract_employee_amounts', []);
            $errors = [];
            $contractEmployeeTotal = 0.0;

            foreach ($contractEmployeeIds as $employeeId) {
                $amount = $amounts[$employeeId] ?? null;
                if ($amount === null || $amount === '') {
                    $errors["contract_employee_amounts.{$employeeId}"] = 'Amount is required for contract employees.';
                    continue;
                }
                if (! is_numeric($amount) || (float) $amount < 0) {
                    $errors["contract_employee_amounts.{$employeeId}"] = 'Amount must be at least 0.';
                    continue;
                }

                $contractEmployeeTotal += (float) $amount;
            }

            if (! empty($errors)) {
                return back()->withErrors($errors)->withInput();
            }

            $isComplete = $data['status'] === 'complete';
            $contractEmployeePayable = $isComplete ? $contractEmployeeTotal : 0.0;
            $contractEmployeePayoutStatus = $isComplete ? 'payable' : 'earned';
        }

        $project = DB::transaction(function () use ($data, $request, $billingService, $taxService, $salesRepSync, $commissionService, $contractEmployeeTotal, $contractEmployeePayable, $contractEmployeePayoutStatus) {
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
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'total_budget' => $data['total_budget'],
                'initial_payment_amount' => $data['initial_payment_amount'],
                'currency' => strtoupper($data['currency']),
                'sales_rep_ids' => $data['sales_rep_ids'] ?? [],
                'budget_amount' => $data['budget_amount'] ?? null,
                'software_overhead' => $data['software_overhead'] ?? null,
                'website_overhead' => $data['website_overhead'] ?? null,
                'contract_amount' => $contractEmployeeTotal,
                'contract_employee_total_earned' => $contractEmployeeTotal,
                'contract_employee_payable' => $contractEmployeePayable,
                'contract_employee_payout_status' => $contractEmployeePayoutStatus,
            ]);

            if (! empty($data['employee_ids'])) {
                $project->employees()->sync($data['employee_ids']);
            }

            if (! empty($data['sales_rep_ids'])) {
                $project->salesRepresentatives()->sync($salesRepSync);
                $commissionService->syncProjectEarnings($project, $salesRepSync);
            }

            $this->createProjectOverheads($project, $data['overheads'] ?? [], $request->user());

            foreach ($data['tasks'] as $index => $task) {
                $assignees = TaskAssignees::parse([$task['assignee']]);
                if (empty($assignees)) {
                    [$assignedType, $assignedId] = $this->parseAssignee($task['assignee']);
                    $assignees = [['type' => $assignedType, 'id' => $assignedId]];
                } else {
                    $assignedType = $assignees[0]['type'];
                    $assignedId = $assignees[0]['id'];
                }

                // Combine multiple descriptions with newlines
                $descriptions = $task['descriptions'] ?? [];
                $description = implode("\n", array_filter($descriptions));

                $projectTask = ProjectTask::create([
                    'project_id' => $project->id,
                    'title' => $task['title'],
                    'description' => $description ?: null,
                    'task_type' => $task['task_type'],
                    'status' => 'pending',
                    'priority' => $task['priority'] ?? 'medium',
                    'start_date' => $task['start_date'],
                    'due_date' => $task['due_date'],
                    'assigned_type' => $assignedType,
                    'assigned_id' => $assignedId,
                    'customer_visible' => (bool) ($task['customer_visible'] ?? false),
                    'created_by' => $request->user()?->id,
                    'time_estimate_minutes' => $task['time_estimate_minutes'] ?? null,
                    'tags' => $this->parseTags($task['tags'] ?? null),
                    'relationship_ids' => $this->parseRelationships($task['relationship_ids'] ?? null),
                ]);

                TaskAssignmentManager::sync($projectTask, $assignees);
                TaskActivityLogger::record($projectTask, $request, 'system', 'Task created.');

                $attachment = $request->file("tasks.{$index}.attachment");
                if ($attachment) {
                    $path = $this->storeTaskAttachment($attachment, $projectTask);
                    TaskActivityLogger::record($projectTask, $request, 'upload', null, [], $path);
                }
            }

            $issueDate = Carbon::today();
            $dueDays = (int) Setting::getValue('invoice_due_days');
            $dueDate = $issueDate->copy()->addDays($dueDays);

            $overheadItems = $project->overheads()->whereNull('invoice_id')->get();
            $columnOverheadTotal = (float) ($project->software_overhead ?? 0) + (float) ($project->website_overhead ?? 0);
            $overheadTotal = (float) $overheadItems->sum('amount') + $columnOverheadTotal;
            $initialPayment = (float) $project->initial_payment_amount;
            $invoiceSubtotal = $initialPayment + $overheadTotal;

            $taxData = $taxService->calculateTotals($invoiceSubtotal, 0.0, $issueDate);

            $invoice = Invoice::create([
                'customer_id' => $project->customer_id,
                'project_id' => $project->id,
                'number' => $billingService->nextInvoiceNumber(),
                'status' => 'unpaid',
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'subtotal' => $invoiceSubtotal,
                'tax_rate_percent' => $taxData['tax_rate_percent'],
                'tax_mode' => $taxData['tax_mode'],
                'tax_amount' => $taxData['tax_amount'],
                'late_fee' => 0,
                'total' => $taxData['total'],
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

            $this->createColumnOverheadInvoiceItems($invoice, $project);

            foreach ($overheadItems as $overhead) {
                if ((float) $overhead->amount <= 0) {
                    continue;
                }

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => sprintf(
                        'Overhead: %s',
                        $overhead->short_details ?: 'Overhead fee',
                    ),
                    'quantity' => 1,
                    'unit_price' => $overhead->amount,
                    'line_total' => $overhead->amount,
                ]);

                $overhead->update(['invoice_id' => $invoice->id]);
            }

            foreach ($data['maintenances'] ?? [] as $maintenance) {
                ProjectMaintenance::create([
                    'project_id' => $project->id,
                    'customer_id' => $project->customer_id,
                    'title' => $maintenance['title'],
                    'amount' => $maintenance['amount'],
                    'currency' => $project->currency,
                    'billing_cycle' => $maintenance['billing_cycle'],
                    'start_date' => $maintenance['start_date'],
                    'next_billing_date' => $maintenance['start_date'],
                    'status' => 'active',
                    'auto_invoice' => (bool) ($maintenance['auto_invoice'] ?? true),
                    'sales_rep_visible' => (bool) ($maintenance['sales_rep_visible'] ?? false),
                    'created_by' => $request->user()?->id,
                ]);
            }

            SystemLogger::write('activity', 'Project created.', [
                'project_id' => $project->id,
                'customer_id' => $project->customer_id,
                'order_id' => $project->order_id,
                'invoice_id' => $invoice->id,
            ], $request->user()?->id, $request->ip());

            $this->storeProjectFile($project, $request->file('contract_file'), 'contract');
            $this->storeProjectFile($project, $request->file('proposal_file'), 'proposal');

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
            'overheads.invoice',
            'employees',
            'salesRepresentatives',
            'maintenances' => fn ($query) => $query->withCount('invoices')->orderBy('next_billing_date'),
        ]);

        $user = $request->user();

        $statusCounts = $project->tasks()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $totalTasks = (int) $statusCounts->values()->sum();
        $inProgressTasks = (int) ($statusCounts['in_progress'] ?? 0);
        $completedTasks = (int) (($statusCounts['completed'] ?? 0) + ($statusCounts['done'] ?? 0));

        $initialInvoice = $project->invoices()
            ->where('type', 'project_initial_payment')
            ->latest('issue_date')
            ->first();

        $remainingBudgetInvoices = $project->invoices()
            ->where('type', 'project_remaining_budget')
            ->latest('issue_date')
            ->get();

        $readerType = 'user';
        $readerId = $user?->id;
        $lastReadId = null;
        if ($readerId) {
            $lastReadId = ProjectMessageRead::query()
                ->where('project_id', $project->id)
                ->where('reader_type', $readerType)
                ->where('reader_id', $readerId)
                ->value('last_read_message_id');
        }

        $projectChatUnreadCount = $project->messages()
            ->when($lastReadId, fn ($query) => $query->where('id', '>', $lastReadId))
            ->count();

        $tasks = app(TaskQueryService::class)->visibleTasksForUser($user)
            ->where('project_id', $project->id)
            ->with(['assignments.employee', 'assignments.salesRep', 'creator'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.projects.show', [
            'project' => $project,
            'statuses' => self::STATUSES,
            'types' => self::TYPES,
            'taskStatuses' => self::TASK_STATUSES,
            'tasks' => $tasks,
            'financials' => $this->financials($project),
            'initialInvoice' => $initialInvoice,
            'remainingBudgetInvoices' => $remainingBudgetInvoices,
            'projectChatUnreadCount' => $projectChatUnreadCount,
            'aiReady' => (bool) config('google_ai.api_key'),
            'taskStats' => [
                'total' => $totalTasks,
                'in_progress' => $inProgressTasks,
                'completed' => $completedTasks,
                'unread' => (int) $projectChatUnreadCount,
            ],
        ]);
    }

    public function aiSummary(
        Request $request,
        Project $project,
        ProjectStatusAiService $aiService,
        GeminiService $geminiService
    ): JsonResponse {
        $this->authorize('view', $project);

        if (! config('google_ai.api_key')) {
            return response()->json(['error' => 'Missing GOOGLE_AI_API_KEY.'], 422);
        }

        try {
            $result = $aiService->analyze($project, $geminiService);

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function tasks(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $user = $request->user();
        $statusFilter = (string) $request->query('status', '');
        $statusFilter = in_array($statusFilter, ['pending', 'in_progress', 'blocked', 'completed'], true)
            ? $statusFilter
            : null;
        $tasksQuery = $project->tasks()
            ->with(['assignments.employee', 'assignments.salesRep', 'creator'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $tasksQuery->when($user?->isClient(), fn ($q) => $q->where('customer_visible', true));
        $tasksQuery->when($statusFilter, function ($query, $status) {
            if ($status === 'completed') {
                $query->whereIn('status', ['completed', 'done']);
                return;
            }

            $query->where('status', $status);
        });

        $tasks = $tasksQuery->paginate(25)->withQueryString();

        $baseSummary = $project->tasks()
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count")
            ->selectRaw("SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked_count")
            ->selectRaw("SUM(CASE WHEN status IN ('completed', 'done') THEN 1 ELSE 0 END) as completed_count")
            ->first();

        $summary = [
            'total' => (int) $project->tasks()->count(),
            'pending' => (int) ($baseSummary->pending_count ?? 0),
            'in_progress' => (int) ($baseSummary->in_progress_count ?? 0),
            'blocked' => (int) ($baseSummary->blocked_count ?? 0),
            'completed' => (int) ($baseSummary->completed_count ?? 0),
        ];

        $readerType = 'user';
        $readerId = $user?->id;
        $lastReadId = null;
        if ($readerId) {
            $lastReadId = ProjectMessageRead::query()
                ->where('project_id', $project->id)
                ->where('reader_type', $readerType)
                ->where('reader_id', $readerId)
                ->value('last_read_message_id');
        }

        $projectChatUnreadCount = $project->messages()
            ->when($lastReadId, fn ($query) => $query->where('id', '>', $lastReadId))
            ->count();

        return view('admin.projects.tasks', [
            'project' => $project,
            'taskTypeOptions' => TaskSettings::taskTypeOptions(),
            'priorityOptions' => TaskSettings::priorityOptions(),
            'employees' => Employee::where('status', 'active')->orderBy('name')->get(['id', 'name', 'designation']),
            'salesReps' => SalesRepresentative::where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']),
            'tasks' => $tasks,
            'summary' => $summary,
            'statusFilter' => $statusFilter,
            'projectChatUnreadCount' => $projectChatUnreadCount,
        ]);
    }

    public function invoiceRemainingBudget(
        Request $request,
        Project $project,
        BillingService $billingService,
        InvoiceTaxService $taxService
    ): RedirectResponse {
        $this->authorize('view', $project);

        $remainingBudget = (float) ($this->financials($project)['remaining_budget_invoiceable'] ?? 0);
        if ($remainingBudget <= 0) {
            return back()->with('status', 'No remaining budget available to invoice.');
        }

        $data = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'lte:' . $remainingBudget,
            ],
        ]);

        $amount = (float) $data['amount'];
        $issueDate = Carbon::today();
        $dueDays = (int) Setting::getValue('invoice_due_days');
        $dueDate = $issueDate->copy()->addDays($dueDays);

        $taxData = $taxService->calculateTotals($amount, 0.0, $issueDate);

        $invoice = Invoice::create([
            'customer_id' => $project->customer_id,
            'project_id' => $project->id,
            'number' => $billingService->nextInvoiceNumber(),
            'status' => 'unpaid',
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'subtotal' => $amount,
            'tax_rate_percent' => $taxData['tax_rate_percent'],
            'tax_mode' => $taxData['tax_mode'],
            'tax_amount' => $taxData['tax_amount'],
            'late_fee' => 0,
            'total' => $taxData['total'],
            'currency' => $project->currency,
            'type' => 'project_remaining_budget',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => sprintf('Remaining budget for project %s', $project->name),
            'quantity' => 1,
            'unit_price' => $amount,
            'line_total' => $amount,
        ]);

        SystemLogger::write('activity', 'Project remaining budget invoiced.', [
            'project_id' => $project->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
        ], $request->user()?->id, $request->ip());

        return redirect()->route('admin.projects.show', $project)
            ->with('status', sprintf('Invoice #%s created for %s %s.', $invoice->number ?? $invoice->id, $project->currency, number_format($amount, 2)));
    }

    public function update(Request $request, Project $project, CommissionService $commissionService): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'customer_id' => ['required', 'exists:customers,id'],
            'order_id' => ['nullable', 'exists:orders,id'],
            'subscription_id' => ['nullable', 'exists:subscriptions,id'],
            'type' => ['required', 'in:software,website,other'],
            'status' => ['required', 'in:ongoing,hold,complete,cancel'],
            'start_date' => ['nullable', 'date'],
            'expected_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'advance_invoice_id' => ['nullable', 'exists:invoices,id'],
            'final_invoice_id' => ['nullable', 'exists:invoices,id'],
            'total_budget' => ['required', 'numeric', 'min:0'],
            'initial_payment_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3', Rule::in(Currency::allowed())],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'software_overhead' => ['nullable', 'numeric', 'min:0'],
            'website_overhead' => ['nullable', 'numeric', 'min:0'],
            'contract_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:10240'],
            'proposal_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:10240'],
            'sales_rep_ids' => ['array'],
            'sales_rep_ids.*' => ['exists:sales_representatives,id'],
            'sales_rep_amounts' => ['array'],
            'sales_rep_amounts.*' => ['nullable', 'numeric', 'min:0'],
            'employee_ids' => ['array'],
            'employee_ids.*' => ['exists:employees,id'],
            'contract_employee_amounts' => ['array'],
            'contract_employee_amounts.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $salesRepSync = $this->buildSalesRepSyncData($request, $data['sales_rep_ids'] ?? []);
        $totalSalesRepAmount = array_sum(array_column($salesRepSync, 'amount'));
        if ($totalSalesRepAmount > (float) $data['total_budget']) {
            return back()
                ->withErrors(['sales_rep_amounts' => 'Total sales rep amounts cannot exceed total budget.'])
                ->withInput();
        }

        $contractEmployeeTotal = null;
        $contractEmployeePayable = null;
        $contractEmployeePayoutStatus = null;
        $contractEmployeePayoutReference = null;

        $contractEmployeeIds = [];
        if (! empty($data['employee_ids'])) {
            $contractEmployeeIds = Employee::whereIn('id', $data['employee_ids'])
                ->where('employment_type', 'contract')
                ->pluck('id')
                ->all();
        }

        if (! empty($contractEmployeeIds)) {
            $amounts = $request->input('contract_employee_amounts', []);
            $errors = [];
            $contractEmployeeTotal = 0.0;

            foreach ($contractEmployeeIds as $employeeId) {
                $amount = $amounts[$employeeId] ?? null;
                if ($amount === null || $amount === '') {
                    $errors["contract_employee_amounts.{$employeeId}"] = 'Amount is required for contract employees.';
                    continue;
                }
                if (! is_numeric($amount) || (float) $amount < 0) {
                    $errors["contract_employee_amounts.{$employeeId}"] = 'Amount must be at least 0.';
                    continue;
                }

                $contractEmployeeTotal += (float) $amount;
            }

            if (! empty($errors)) {
                return back()->withErrors($errors)->withInput();
            }

            $isComplete = $data['status'] === 'complete';
            $contractEmployeePayable = $isComplete ? $contractEmployeeTotal : 0.0;
            $contractEmployeePayoutStatus = $isComplete ? 'payable' : 'earned';
        } else {
            $contractEmployeePayoutReference = null;
        }

        if (! empty($contractEmployeeIds)) {
            $contractPayload = [
                'contract_amount' => $contractEmployeeTotal,
                'contract_employee_total_earned' => $contractEmployeeTotal,
                'contract_employee_payable' => $contractEmployeePayable,
                'contract_employee_payout_status' => $contractEmployeePayoutStatus,
            ];
        } else {
            $contractPayload = [
                'contract_amount' => null,
                'contract_employee_total_earned' => null,
                'contract_employee_payable' => null,
                'contract_employee_payout_status' => null,
                'contract_employee_payout_reference' => $contractEmployeePayoutReference,
            ];
        }

        $previousStatus = $project->status;
        $previousCurrency = $project->currency;
        $data['currency'] = strtoupper($data['currency']);
        $project->update(array_merge($data, $contractPayload));

        if ($previousCurrency !== $project->currency) {
            $project->maintenances()->update([
                'currency' => $project->currency,
            ]);
        }

        $project->employees()->sync($data['employee_ids'] ?? []);

        $project->salesRepresentatives()->sync($salesRepSync);
        $project->update([
            'sales_rep_ids' => $data['sales_rep_ids'] ?? [],
        ]);
        $commissionService->syncProjectEarnings($project, $salesRepSync);

        SystemLogger::write('activity', 'Project updated.', [
            'project_id' => $project->id,
            'status' => $project->status,
        ], $request->user()?->id, $request->ip());

        if ($previousStatus !== 'complete' && $project->status === 'complete') {
            if ($project->contract_employee_total_earned !== null) {
                $totalEarned = (float) $project->contract_employee_total_earned;
                $currentPayable = (float) ($project->contract_employee_payable ?? 0);
                $updates = [];

                if ($currentPayable < $totalEarned) {
                    $updates['contract_employee_payable'] = $totalEarned;
                }

                if ($project->contract_employee_payout_status !== 'payable') {
                    $updates['contract_employee_payout_status'] = 'payable';
                }

                if (! empty($updates)) {
                    $project->update($updates);
                }
            }

            try {
                $commissionService->markEarningPayableOnProjectCompleted($project);
            } catch (\Throwable $e) {
                SystemLogger::write('module', 'Commission payable transition failed on project completion.', [
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                ], level: 'error');
            }
        }

        $this->storeProjectFile($project, $request->file('contract_file'), 'contract');
        $this->storeProjectFile($project, $request->file('proposal_file'), 'proposal');

        return back()->with('status', 'Project updated.');
    }

    public function markComplete(Request $request, Project $project, CommissionService $commissionService): RedirectResponse
    {
        if ($project->status === 'complete') {
            return back()->with('status', 'Project is already completed.');
        }

        $previousStatus = $project->status;
        $project->update(['status' => 'complete']);

        SystemLogger::write('activity', 'Project marked complete.', [
            'project_id' => $project->id,
            'previous_status' => $previousStatus,
            'status' => $project->status,
        ], $request->user()?->id, $request->ip());

        if ($previousStatus !== 'complete') {
            if ($project->contract_employee_total_earned !== null) {
                $totalEarned = (float) $project->contract_employee_total_earned;
                $currentPayable = (float) ($project->contract_employee_payable ?? 0);
                $updates = [];

                if ($currentPayable < $totalEarned) {
                    $updates['contract_employee_payable'] = $totalEarned;
                }

                if ($project->contract_employee_payout_status !== 'payable') {
                    $updates['contract_employee_payout_status'] = 'payable';
                }

                if (! empty($updates)) {
                    $project->update($updates);
                }
            }

            try {
                $commissionService->markEarningPayableOnProjectCompleted($project);
            } catch (\Throwable $e) {
                SystemLogger::write('module', 'Commission payable transition failed on project completion.', [
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                ], level: 'error');
            }
        }

        return back()->with('status', 'Project marked as complete.');
    }

    public function downloadFile(Project $project, string $type)
    {
        $this->authorize('view', $project);

        if (! in_array($type, ['contract', 'proposal'], true)) {
            abort(404);
        }

        $pathColumn = "{$type}_file_path";
        $nameColumn = "{$type}_original_name";

        $path = $project->{$pathColumn};
        if (! $path || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $originalName = $project->{$nameColumn} ?: ucfirst($type);

        return Storage::disk('public')->download($path, $originalName);
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
            'created_by' => $request->user()?->id,
        ]);

        $task = $project->tasks()->create($taskData);

        SystemLogger::write('activity', 'Project task created.', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'status' => $task->status,
        ], $request->user()?->id, $request->ip());

        app(TaskStatusNotificationService::class)->notifyTaskOpened($task);

        return back()->with('status', 'Task added.');
    }

    public function updateTask(Request $request, Project $project, ProjectTask $task): RedirectResponse|JsonResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('update', $task);

        if (! $request->user()?->isMasterAdmin() && $task->creatorEditWindowExpired($request->user()?->id)) {
            $message = 'You can only edit this task within 24 hours of creation.';
            if ($request->expectsJson()) {
                return AjaxResponse::ajaxError($message, 403);
            }
            return back()->withErrors(['task' => $message]);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:todo,in_progress,blocked,done'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! $request->user()?->isMasterAdmin()
            && $data['status'] === 'done'
            && TaskCompletionManager::hasSubtasks($task)
            && ! TaskCompletionManager::allSubtasksCompleted($task)) {
            return back()->withErrors(['status' => 'Complete all subtasks before completing this task.']);
        }

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
        $budget = (float) ($project->total_budget ?? 0);
        $overhead = $project->overhead_total;
        $budgetWithOverhead = $budget + $overhead;
        $salesRepTotal = (float) ($project->sales_rep_total ?? 0);
        $employeeSalaryTotal = (float) ($project->contract_amount ?? $project->contract_employee_total_earned ?? 0);
        $payoutsTotal = $salesRepTotal + $employeeSalaryTotal;
        $initialPayment = (float) ($project->initial_payment_amount ?? 0);
        $paidPayment = (float) $project->invoices()
            ->whereIn('type', ['project_initial_payment', 'project_remaining_budget'])
            ->where('status', 'paid')
            ->sum('total');
        $pendingRemainingBudgetInvoiced = (float) $project->invoices()
            ->where('type', 'project_remaining_budget')
            ->whereIn('status', ['unpaid', 'overdue'])
            ->sum('total');
        $remainingBudget = $budgetWithOverhead - $paidPayment;
        $remainingBudgetInvoiceable = max(0, $remainingBudget - $pendingRemainingBudgetInvoiced);
        $profit = $budgetWithOverhead - $salesRepTotal - $employeeSalaryTotal;

        return [
            'budget' => $budget,
            'overhead_total' => $overhead,
            'budget_with_overhead' => $budgetWithOverhead,
            'initial_payment' => $initialPayment,
            'paid_payment' => $paidPayment,
            'employee_salary_total' => $employeeSalaryTotal,
            'sales_rep_total' => $salesRepTotal,
            'payouts_total' => $payoutsTotal,
            'remaining_budget' => $remainingBudget,
            'pending_remaining_budget_invoiced' => $pendingRemainingBudgetInvoiced,
            'remaining_budget_invoiceable' => $remainingBudgetInvoiceable,
            'profit' => $profit,
            'profitable' => $profit >= 0,
        ];
    }

    private function buildSalesRepSyncData(Request $request, array $repIds): array
    {
        $amounts = $request->input('sales_rep_amounts', []);
        $syncData = [];

        foreach ($repIds as $repId) {
            $amount = isset($amounts[$repId]) && $amounts[$repId] !== ''
                ? (float) $amounts[$repId]
                : 0.0;
            $syncData[$repId] = ['amount' => $amount];
        }

        return $syncData;
    }

    private function parseTags(?string $tags, array $fallback = []): array
    {
        if ($tags === null) {
            return $fallback;
        }

        $parsed = array_filter(array_map('trim', explode(',', $tags)));
        return array_values(array_unique($parsed));
    }

    private function parseRelationships(?string $relationships, array $fallback = []): array
    {
        if ($relationships === null) {
            return $fallback;
        }

        $ids = array_filter(array_map('trim', explode(',', $relationships)));
        $ids = array_values(array_unique(array_filter($ids, fn ($id) => is_numeric($id))));
        return array_map('intval', $ids);
    }

    private function storeTaskAttachment($attachment, ProjectTask $task): string
    {
        $name = pathinfo((string) $attachment->getClientOriginalName(), PATHINFO_FILENAME);
        $name = $name !== '' ? Str::slug($name) : 'attachment';
        $extension = $attachment->getClientOriginalExtension();
        $fileName = $name . '-' . Str::random(8) . '.' . $extension;

        return $attachment->storeAs('project-task-activities/' . $task->id, $fileName, 'public');
    }

    private function storeProjectFile(Project $project, ?UploadedFile $file, string $type): void
    {
        if (! $file) {
            return;
        }

        $columnPath = "{$type}_file_path";
        $columnOriginalName = "{$type}_original_name";
        $previousPath = $project->{$columnPath};

        if ($previousPath) {
            Storage::disk('public')->delete($previousPath);
        }

        $fileName = Str::slug("{$type}-{$project->id}-" . time());
        $fileName .= '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs("projects/{$project->id}", $fileName, 'public');

        $project->update([
            $columnPath => $path,
            $columnOriginalName => $file->getClientOriginalName(),
        ]);
    }

    private function createColumnOverheadInvoiceItems(Invoice $invoice, Project $project): void
    {
        foreach (['software', 'website'] as $type) {
            $column = "{$type}_overhead";
            $amount = (float) ($project->{$column} ?? 0);
            if ($amount <= 0) {
                continue;
            }

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => sprintf('%s overhead for project %s', ucfirst($type), $project->name),
                'quantity' => 1,
                'unit_price' => $amount,
                'line_total' => $amount,
            ]);
        }
    }

    private function createProjectOverheads(Project $project, array $rows, ?User $creator): void
    {
        $filtered = collect($rows)
            ->map(fn ($row) => [
                'short_details' => trim((string) ($row['short_details'] ?? '')),
                'amount' => isset($row['amount']) ? (float) $row['amount'] : 0.0,
            ])
            ->filter(fn ($row) => $row['short_details'] !== '' && $row['amount'] > 0)
            ->values();

        if ($filtered->isEmpty()) {
            return;
        }

        foreach ($filtered as $entry) {
            $project->overheads()->create([
                'short_details' => $entry['short_details'],
                'amount' => $entry['amount'],
                'created_by' => $creator?->id,
            ]);
        }

        $project->loadMissing('overheads');
    }
}
