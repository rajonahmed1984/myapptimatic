<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\MailCategory;
use App\Models\AccountingEntry;
use App\Models\Customer;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\License;
use App\Models\Order;
use App\Models\Plan;
use App\Models\SystemLog;
use App\Models\ProjectMaintenance;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\SalesRepresentative;
use App\Models\Subscription;
use App\Models\Employee;
use App\Models\User;
use App\Models\UserSession;
use App\Models\Setting;
use App\Http\Requests\StoreClientUserRequest;
use App\Enums\Role;
use App\Support\Branding;
use App\Support\Currency;
use App\Support\SystemLogger;
use App\Support\UrlResolver;
use App\Services\Mail\MailSender;
use App\Services\BillingService;
use App\Services\InvoiceTaxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CustomerController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $search = trim((string) $request->query('search', ''));

        $customers = Customer::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('company_name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            })
            ->with(['users:id,customer_id,role'])
            ->withCount('subscriptions')
            ->withCount(['subscriptions as active_subscriptions_count' => function ($query) {
                $query->where('status', 'active');
            }])
            ->withCount('projects')
            ->withCount(['projects as active_projects_count' => function ($query) {
                $query->whereIn('status', ['ongoing', 'complete']);
            }])
            ->withCount('projectMaintenances')
            ->withCount(['projectMaintenances as active_project_maintenances_count' => function ($query) {
                $query->where('status', 'active');
            }])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $loginStatuses = $this->resolveCustomerLoginStatuses($customers);

        return Inertia::render(
            'Admin/Customers/Index',
            $this->indexInertiaProps($customers, $loginStatuses, $search)
        );
    }

    public function create(): InertiaResponse
    {
        $salesReps = SalesRepresentative::orderBy('name')->get(['id', 'name', 'status']);

        return Inertia::render(
            'Admin/Customers/Form',
            $this->formInertiaProps(null, $salesReps)
        );
    }

    public function store(StoreClientUserRequest $request)
    {
        $data = $request->validated();

        $customer = Customer::create([
            'name' => $data['name'],
            'company_name' => $data['company_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => $data['status'],
            'access_override_until' => $data['access_override_until'] ?? null,
            'notes' => $data['notes'] ?? null,
            'default_sales_rep_id' => $data['default_sales_rep_id'] ?? null,
        ]);

        if (! empty($data['user_password'])) {
            if (empty($customer->email)) {
                return redirect()->route('admin.customers.create')
                    ->withErrors(['email' => 'Email is required to create login.'])
                    ->withInput();
            }

            User::create([
                'name' => $customer->name,
                'email' => $customer->email,
                'password' => Hash::make($data['user_password']),
                'role' => Role::CLIENT,
                'customer_id' => $customer->id,
            ]);

            if ($request->boolean('send_account_message')) {
                $this->sendAccountMessage($customer);
            }
        }

        SystemLogger::write('activity', 'Customer created.', [
            'customer_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
        ], $request->user()?->id, $request->ip());

        return redirect()->route('admin.customers.index')
            ->with('status', 'Customer created.');
    }

    private function sendAccountMessage(Customer $customer): void
    {
        if (! $customer->email) {
            return;
        }

        $template = EmailTemplate::query()
            ->where('key', 'client_signup_email')
            ->first();

        $companyName = Setting::getValue('company_name', config('app.name'));
        $loginUrl = UrlResolver::portalUrl().'/login';

        $subject = $template?->subject ?: "Welcome to {$companyName}";
        $body = $template?->body ?: "Hi {{client_name}},\n\nYour account for {{company_name}} is ready. You can sign in here: {{login_url}}.\n\nThank you,\n{{company_name}}";
        $replacements = [
            '{{client_name}}' => $customer->name,
            '{{company_name}}' => $companyName,
            '{{login_url}}' => $loginUrl,
            '{{client_email}}' => $customer->email,
        ];

        $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
        $body = str_replace(array_keys($replacements), array_values($replacements), $body);
        $bodyHtml = $this->formatEmailBody($body);
        $logoUrl = Branding::url(Setting::getValue('company_logo_path'));
        $portalUrl = UrlResolver::portalUrl();
        $portalLoginUrl = UrlResolver::portalUrl().'/login';

        try {
            app(MailSender::class)->sendView(MailCategory::SYSTEM, $customer->email, 'emails.generic', [
                'subject' => $subject,
                'companyName' => $companyName,
                'logoUrl' => $logoUrl,
                'portalUrl' => $portalUrl,
                'portalLoginUrl' => $portalLoginUrl,
                'portalLoginLabel' => 'log in to the client area',
                'bodyHtml' => new HtmlString($bodyHtml),
            ], $subject);
        } catch (\Throwable $e) {
            Log::warning('Failed to send account info email.', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function formatEmailBody(string $body): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return '';
        }

        $looksLikeHtml = Str::contains($trimmed, ['<p', '<br', '<div', '<table', '<a ', '<strong', '<em', '<ul', '<ol', '<li']);

        if ($looksLikeHtml) {
            return $trimmed;
        }

        return nl2br(e($trimmed));
    }

    public function edit(Customer $customer): InertiaResponse
    {
        $activeServices = $customer->subscriptions()->where('status', 'active')->count();
        $activeProjects = $customer->projects()->whereIn('status', ['ongoing', 'complete'])->count();
        $activeMaintenances = $customer->projectMaintenances()->where('status', 'active')->count();
        $effectiveStatus = ($activeServices > 0 || $activeProjects > 0 || $activeMaintenances > 0)
            ? 'active'
            : $customer->status;

        $salesReps = SalesRepresentative::orderBy('name')->get(['id', 'name', 'status']);

        return Inertia::render(
            'Admin/Customers/Form',
            $this->formInertiaProps($customer, $salesReps, $effectiveStatus)
        );
    }

    public function show(Request $request, Customer $customer): InertiaResponse
    {
        $tab = $request->query('tab', 'summary');
        $allowedTabs = ['summary', 'project-specific', 'services', 'projects', 'invoices', 'tickets', 'emails', 'log'];

        if (! in_array($tab, $allowedTabs, true)) {
            $tab = 'summary';
        }

        $customer->load([
            'subscriptions.plan.product',
            'subscriptions.latestOrder',
            'projects',
            'invoices' => function ($query) {
                $query->latest('issue_date');
            },
            'supportTickets' => function ($query) {
                $query->latest('updated_at');
            },
        ]);

        $activityLogs = collect();
        $emailLogs = collect();
        $projectClients = collect();
        $projects = collect();
        $projectMaintenances = collect();
        $projectTaskSummary = [
            'total' => 0,
            'projects' => (int) $customer->projects->count(),
            'pending' => 0,
            'in_progress' => 0,
            'blocked' => 0,
            'completed' => 0,
            'other' => 0,
        ];
        $projectSubtaskSummary = [
            'total' => 0,
            'completed' => 0,
            'pending' => 0,
        ];
        $projectTaskProgress = [
            'percent' => 0,
        ];
        if ($tab === 'log') {
            $userIds = $customer->users()->pluck('id');
            $activityLogs = SystemLog::query()
                ->where(function ($query) use ($customer, $userIds) {
                    $query->where('context->customer_id', $customer->id);
                    if ($userIds->isNotEmpty()) {
                        $query->orWhereIn('user_id', $userIds);
                    }
                })
                ->latest()
                ->take(200)
                ->get();
        }
        if ($tab === 'emails' && $customer->email) {
            $emailLogs = SystemLog::query()
                ->where('category', 'email')
                ->whereJsonContains('context->to', strtolower($customer->email))
                ->latest()
                ->take(200)
                ->get();
        }
        if ($tab === 'project-specific') {
            $projectClients = $customer->projectUsers()->with('project')->get();
            $projects = $customer->projects()->orderBy('name')->get(['id', 'name']);
        }
        if ($tab === 'projects') {
            $projectMaintenances = $customer->projectMaintenances()
                ->with('project:id,name')
                ->withCount('invoices')
                ->latest('id')
                ->get();

            $projectIds = $customer->projects->pluck('id')->filter()->values();
            if ($projectIds->isNotEmpty()) {
                $taskBaseQuery = ProjectTask::query()->whereIn('project_id', $projectIds);
                $taskStatusCounts = (clone $taskBaseQuery)
                    ->select('status', DB::raw('COUNT(*) as total'))
                    ->groupBy('status')
                    ->pluck('total', 'status');

                $taskTotal = (int) $taskStatusCounts->sum();
                $completedTasks = (int) (($taskStatusCounts['completed'] ?? 0) + ($taskStatusCounts['done'] ?? 0) + ($taskStatusCounts['complete'] ?? 0));
                $projectTaskSummary = [
                    'total' => $taskTotal,
                    'projects' => (int) $projectIds->count(),
                    'pending' => (int) ($taskStatusCounts['pending'] ?? 0),
                    'in_progress' => (int) ($taskStatusCounts['in_progress'] ?? 0),
                    'blocked' => (int) ($taskStatusCounts['blocked'] ?? 0),
                    'completed' => $completedTasks,
                    'other' => (int) $taskStatusCounts->except(['pending', 'in_progress', 'blocked', 'completed', 'complete', 'done'])->sum(),
                ];
                $projectTaskProgress = [
                    'percent' => $taskTotal > 0 ? (int) round(($completedTasks / $taskTotal) * 100) : 0,
                ];

                $subtaskCounts = ProjectTaskSubtask::query()
                    ->select('is_completed', DB::raw('COUNT(*) as total'))
                    ->whereIn('project_task_id', (clone $taskBaseQuery)->select('id'))
                    ->groupBy('is_completed')
                    ->pluck('total', 'is_completed');

                $subtaskTotal = (int) $subtaskCounts->sum();
                $subtaskCompleted = (int) ($subtaskCounts[1] ?? 0);
                $projectSubtaskSummary = [
                    'total' => $subtaskTotal,
                    'completed' => $subtaskCompleted,
                    'pending' => max(0, $subtaskTotal - $subtaskCompleted),
                ];
            }
        }

        $servicePlans = collect();
        $serviceSalesReps = collect();
        if ($tab === 'services') {
            $servicePlans = Plan::query()
                ->with('product')
                ->where('is_active', true)
                ->whereHas('product', fn ($query) => $query->where('status', 'active'))
                ->orderBy('name')
                ->get();

            $serviceSalesReps = SalesRepresentative::query()
                ->orderBy('name')
                ->get(['id', 'name', 'status']);
        }

        $currencyCode = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($currencyCode)) {
            $currencyCode = Currency::DEFAULT;
        }
        $currencySymbol = Currency::symbol($currencyCode);

        $statusDefinitions = [
            ['key' => 'paid', 'label' => 'Paid', 'statuses' => ['paid']],
            ['key' => 'draft', 'label' => 'Draft', 'statuses' => ['draft']],
            ['key' => 'unpaid_due', 'label' => 'Unpaid/Due', 'statuses' => ['unpaid', 'overdue']],
            ['key' => 'cancelled', 'label' => 'Cancelled', 'statuses' => ['cancelled']],
            ['key' => 'refunded', 'label' => 'Refunded', 'statuses' => ['refunded']],
            ['key' => 'collections', 'label' => 'Collections', 'statuses' => ['collections']],
        ];

        $invoiceStatusSummary = [];
        foreach ($statusDefinitions as $definition) {
            $filtered = $customer->invoices->whereIn('status', $definition['statuses']);
            $invoiceStatusSummary[] = [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'count' => $filtered->count(),
                'amount' => (float) $filtered->sum('total'),
            ];
        }

        $grossRevenue = (float) AccountingEntry::query()
            ->where('customer_id', $customer->id)
            ->where('type', 'payment')
            ->sum('amount');
        $clientExpenses = (float) AccountingEntry::query()
            ->where('customer_id', $customer->id)
            ->where('type', 'expense')
            ->sum('amount');
        $creditBalance = (float) AccountingEntry::query()
            ->where('customer_id', $customer->id)
            ->where('type', 'credit')
            ->sum('amount');
        $netIncome = $grossRevenue - $clientExpenses;

        $salesRepSummaries = collect();
        $projectIds = $customer->projects->pluck('id')->filter()->values();
        if ($projectIds->isNotEmpty()) {
                $salesReps = SalesRepresentative::query()
                    ->whereHas('projects', fn ($query) => $query->whereIn('projects.id', $projectIds))
                    ->with(['projects' => function ($query) use ($projectIds) {
                        $query->whereIn('projects.id', $projectIds)
                            ->with(['maintenances' => fn ($maintenanceQuery) => $maintenanceQuery
                                ->where('sales_rep_visible', true)
                                ->with('salesRepresentatives')]);
                    }])
                    ->get();

            $salesRepSummaries = $salesReps->map(function (SalesRepresentative $rep) {
                    $projects = $rep->projects->map(function ($project) use ($rep) {
                        $projectAmount = (float) ($project->pivot?->amount ?? 0);
                        $maintenanceAmount = (float) $project->maintenances
                            ->where('sales_rep_visible', true)
                            ->sum(function ($maintenance) use ($rep) {
                                if ($maintenance->salesRepresentatives?->isNotEmpty()) {
                                    $linked = $maintenance->salesRepresentatives->firstWhere('id', $rep->id);
                                    return (float) ($linked?->pivot?->amount ?? 0);
                                }

                                return (float) ($maintenance->amount ?? 0);
                            });

                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'project_amount' => $projectAmount,
                        'maintenance_amount' => $maintenanceAmount,
                    ];
                })->values();

                return [
                    'id' => $rep->id,
                    'name' => $rep->name,
                    'phone' => $rep->phone,
                    'projects' => $projects,
                    'total_project_amount' => (float) $projects->sum('project_amount'),
                    'total_maintenance_amount' => (float) $projects->sum('maintenance_amount'),
                ];
            })->values();
        }

        $activeServices = $customer->subscriptions->where('status', 'active')->count();
        $activeProjects = $customer->projects
            ->whereIn('status', ['ongoing', 'complete'])
            ->count();
        $activeMaintenances = ProjectMaintenance::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->count();
        $effectiveStatus = ($activeServices > 0 || $activeProjects > 0 || $activeMaintenances > 0)
            ? 'active'
            : $customer->status;

        $dateFormat = config('app.date_format', 'd-m-Y');
        $dateTimeFormat = config('app.datetime_format', 'd-m-Y h:i A');

        return Inertia::render('Admin/Customers/Show', [
            'pageTitle' => 'Customer Details',
            'tab' => $tab,
            'tabs' => collect($allowedTabs)->map(function (string $key) use ($customer) {
                $label = match ($key) {
                    'summary' => 'Summary',
                    'project-specific' => 'Project Logins',
                    'services' => 'Products/Services',
                    'projects' => 'Projects',
                    'invoices' => 'Invoices',
                    'tickets' => 'Tickets',
                    'emails' => 'Emails',
                    'log' => 'Log',
                    default => ucfirst(str_replace('-', ' ', $key)),
                };

                return [
                    'key' => $key,
                    'label' => $label,
                    'href' => route('admin.customers.show', ['customer' => $customer, 'tab' => $key], false),
                ];
            })->values()->all(),
            'customer' => [
                'id' => $customer->id,
                'name' => (string) $customer->name,
                'email' => (string) ($customer->email ?? ''),
                'phone' => (string) ($customer->phone ?? ''),
                'address' => (string) ($customer->address ?? ''),
                'company_name' => (string) ($customer->company_name ?? ''),
                'status' => (string) ($customer->status ?? 'active'),
                'effective_status' => (string) $effectiveStatus,
                'created_at_display' => $customer->created_at?->format($dateFormat) ?? '--',
                'subscriptions_count' => (int) $customer->subscriptions->count(),
                'active_subscriptions_count' => (int) $customer->subscriptions->where('status', 'active')->count(),
                'projects_count' => (int) $customer->projects->count(),
                'invoices_count' => (int) $customer->invoices->count(),
                'tickets_count' => (int) $customer->supportTickets->count(),
            ],
            'currency' => [
                'code' => $currencyCode,
                'symbol' => $currencySymbol,
            ],
            'metrics' => [
                'invoice_status_summary' => $invoiceStatusSummary,
                'gross_revenue' => (float) $grossRevenue,
                'client_expenses' => (float) $clientExpenses,
                'net_income' => (float) $netIncome,
                'credit_balance' => (float) $creditBalance,
            ],
            'sales_rep_summaries' => $salesRepSummaries->values()->all(),
            'subscriptions' => $customer->subscriptions->map(function (Subscription $subscription) use ($dateFormat) {
                return [
                    'id' => $subscription->id,
                    'product_name' => (string) ($subscription->plan?->product?->name ?? '--'),
                    'plan_name' => (string) ($subscription->plan?->name ?? '--'),
                    'status' => (string) ($subscription->status ?? ''),
                    'status_label' => ucfirst((string) ($subscription->status ?? '--')),
                    'order_number' => (string) ($subscription->latestOrder?->order_number ?? '--'),
                    'next_invoice_display' => $subscription->next_invoice_at?->format($dateFormat) ?? '--',
                    'period_end_display' => $subscription->current_period_end?->format($dateFormat) ?? '--',
                    'manage_url' => route('admin.subscriptions.edit', $subscription, false),
                ];
            })->values()->all(),
            'project_clients' => $projectClients->map(function (User $projectUser) use ($dateFormat, $customer) {
                return [
                    'id' => $projectUser->id,
                    'name' => (string) ($projectUser->name ?? '--'),
                    'email' => (string) ($projectUser->email ?? '--'),
                    'status' => (string) ($projectUser->status ?? 'active'),
                    'project_id' => $projectUser->project_id,
                    'project_name' => (string) ($projectUser->project?->name ?? '--'),
                    'created_at_display' => $projectUser->created_at?->format($dateFormat) ?? '--',
                    'routes' => [
                        'update' => route('admin.customers.project-users.update', ['customer' => $customer, 'user' => $projectUser], false),
                        'destroy' => route('admin.customers.project-users.destroy', ['customer' => $customer, 'user' => $projectUser], false),
                    ],
                ];
            })->values()->all(),
            'project_options' => $projects->map(fn ($project) => [
                'id' => $project->id,
                'name' => (string) ($project->name ?? '--'),
            ])->values()->all(),
            'projects' => $customer->projects->map(function ($project) use ($dateFormat) {
                return [
                    'id' => $project->id,
                    'name' => (string) ($project->name ?? '--'),
                    'type' => (string) ($project->type ?? '--'),
                    'status' => (string) ($project->status ?? '--'),
                    'start_date_display' => $project->start_date?->format($dateFormat) ?? '--',
                    'due_date_display' => $project->due_date?->format($dateFormat) ?? '--',
                    'currency' => (string) ($project->currency ?? ''),
                    'total_budget' => (float) ($project->total_budget ?? 0),
                    'show_url' => route('admin.projects.show', $project, false),
                    'edit_url' => route('admin.projects.edit', $project, false),
                ];
            })->values()->all(),
            'project_maintenances' => $projectMaintenances->map(function (ProjectMaintenance $maintenance) use ($dateFormat) {
                return [
                    'id' => $maintenance->id,
                    'title' => (string) ($maintenance->title ?? '--'),
                    'project_name' => (string) ($maintenance->project?->name ?? '--'),
                    'status' => (string) ($maintenance->status ?? '--'),
                    'amount' => (float) ($maintenance->amount ?? 0),
                    'currency' => (string) ($maintenance->currency ?? ''),
                    'billing_cycle' => (string) ($maintenance->billing_cycle ?? '--'),
                    'next_billing_date_display' => $maintenance->next_billing_date?->format($dateFormat) ?? '--',
                    'invoices_count' => (int) ($maintenance->invoices_count ?? 0),
                    'show_url' => route('admin.project-maintenances.show', $maintenance, false),
                    'edit_url' => route('admin.project-maintenances.edit', $maintenance, false),
                ];
            })->values()->all(),
            'project_task_summary' => $projectTaskSummary,
            'project_subtask_summary' => $projectSubtaskSummary,
            'project_task_progress' => $projectTaskProgress,
            'invoices' => $customer->invoices->map(function ($invoice) use ($dateFormat) {
                return [
                    'id' => $invoice->id,
                    'number' => (string) ($invoice->number ?: $invoice->id),
                    'status' => (string) ($invoice->status ?? ''),
                    'status_label' => ucfirst((string) ($invoice->status ?? '--')),
                    'issue_date_display' => $invoice->issue_date?->format($dateFormat) ?? '--',
                    'due_date_display' => $invoice->due_date?->format($dateFormat) ?? '--',
                    'total' => (float) ($invoice->total ?? 0),
                    'currency' => (string) ($invoice->currency ?? ''),
                    'show_url' => route('admin.invoices.show', $invoice, false),
                ];
            })->values()->all(),
            'tickets' => $customer->supportTickets->map(function ($ticket) use ($dateTimeFormat) {
                return [
                    'id' => $ticket->id,
                    'subject' => (string) ($ticket->subject ?? '--'),
                    'status' => (string) ($ticket->status ?? '--'),
                    'status_label' => ucfirst(str_replace('_', ' ', (string) ($ticket->status ?? '--'))),
                    'last_reply_display' => $ticket->last_reply_at?->format($dateTimeFormat) ?? '--',
                    'show_url' => route('admin.support-tickets.show', $ticket, false),
                ];
            })->values()->all(),
            'email_logs' => $emailLogs->map(function (SystemLog $log) use ($dateTimeFormat, $customer) {
                return [
                    'id' => $log->id,
                    'created_at_display' => $log->created_at?->format($dateTimeFormat) ?? '--',
                    'subject' => (string) ($log->context['subject'] ?? $log->message ?? '--'),
                    'resend_url' => route('admin.logs.email.resend', ['systemLog' => $log, 'customer' => $customer], false),
                ];
            })->values()->all(),
            'activity_logs' => $activityLogs->map(function (SystemLog $log) use ($dateTimeFormat) {
                return [
                    'id' => $log->id,
                    'created_at_display' => $log->created_at?->format($dateTimeFormat) ?? '--',
                    'category' => ucfirst((string) ($log->category ?? '')),
                    'level' => strtoupper((string) ($log->level ?? '')),
                    'message' => (string) ($log->message ?? '--'),
                    'context' => $log->context,
                ];
            })->values()->all(),
            'service_plans' => $servicePlans->map(fn (Plan $plan) => [
                'id' => (string) $plan->id,
                'product_name' => (string) ($plan->product?->name ?? '--'),
                'name' => (string) $plan->name,
                'interval' => (string) ($plan->interval ?? ''),
                'price' => (float) ($plan->price ?? 0),
                'currency' => (string) ($plan->currency ?: $currencyCode),
            ])->values()->all(),
            'service_sales_reps' => $serviceSalesReps->map(fn (SalesRepresentative $rep) => [
                'id' => (string) $rep->id,
                'name' => (string) $rep->name,
                'status' => (string) $rep->status,
            ])->values()->all(),
            'forms' => [
                'project_user' => [
                    'project_id' => (string) old('project_id', ''),
                    'name' => (string) old('name', ''),
                    'email' => (string) old('email', ''),
                ],
                'service' => [
                    'plan_id' => (string) old('plan_id', ''),
                    'start_date' => (string) old('start_date', now()->toDateString()),
                    'sales_rep_id' => (string) old('sales_rep_id', (string) ($customer->default_sales_rep_id ?? '')),
                    'sales_rep_commission_amount' => (string) old('sales_rep_commission_amount', ''),
                ],
            ],
            'routes' => [
                'index' => route('admin.customers.index', [], false),
                'show' => route('admin.customers.show', $customer, false),
                'edit' => route('admin.customers.edit', $customer, false),
                'impersonate' => route('admin.customers.impersonate', $customer, false),
                'create_invoice' => route('admin.invoices.create', ['customer_id' => $customer->id], false),
                'create_ticket' => route('admin.support-tickets.create', ['customer_id' => $customer->id], false),
                'project_user_store' => route('admin.customers.project-users.store', $customer, false),
                'service_store' => route('admin.customers.services.store', $customer, false),
                'create_project' => route('admin.projects.create', [], false),
                'create_maintenance' => route('admin.project-maintenances.create', [], false),
            ],
        ]);
    }

    public function storeService(
        Request $request,
        Customer $customer,
        BillingService $billingService,
        InvoiceTaxService $taxService
    ) {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'start_date' => ['required', 'date'],
            'sales_rep_id' => ['nullable', 'exists:sales_representatives,id'],
            'sales_rep_commission_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $plan = Plan::query()->with('product')->findOrFail($data['plan_id']);
        if (! $plan->is_active || ! $plan->product || $plan->product->status !== 'active') {
            return redirect()
                ->route('admin.customers.show', ['customer' => $customer, 'tab' => 'services'])
                ->withErrors(['plan_id' => 'This plan is not available for ordering.'])
                ->withInput();
        }

        $startDate = Carbon::parse((string) $data['start_date'])->startOfDay();
        $periodEnd = $plan->interval === 'monthly'
            ? $startDate->copy()->endOfMonth()
            : $startDate->copy()->addYear();

        $result = DB::transaction(function () use ($request, $customer, $plan, $startDate, $periodEnd, $data, $billingService, $taxService) {
            $subtotal = $this->calculateServiceSubtotal($plan->interval, (float) $plan->price, $startDate, $periodEnd);
            $currency = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
            $taxData = $taxService->calculateTotals($subtotal, 0.0, $startDate);
            $salesRepId = $data['sales_rep_id'] ?? null;

            $subscription = Subscription::create([
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'sales_rep_id' => $salesRepId,
                'sales_rep_commission_amount' => $salesRepId
                    ? ($data['sales_rep_commission_amount'] ?? null)
                    : null,
                'status' => 'pending',
                'start_date' => $startDate->toDateString(),
                'current_period_start' => $startDate->toDateString(),
                'current_period_end' => $periodEnd->toDateString(),
                'next_invoice_at' => $this->nextServiceInvoiceAt((string) $plan->interval, $periodEnd, $startDate)->toDateString(),
                'auto_renew' => true,
                'cancel_at_period_end' => false,
            ]);

            $invoice = Invoice::create([
                'customer_id' => $customer->id,
                'subscription_id' => $subscription->id,
                'number' => $billingService->nextInvoiceNumber(),
                'status' => 'unpaid',
                'issue_date' => $startDate->toDateString(),
                'due_date' => $startDate->toDateString(),
                'subtotal' => $subtotal,
                'tax_rate_percent' => $taxData['tax_rate_percent'],
                'tax_mode' => $taxData['tax_mode'],
                'tax_amount' => $taxData['tax_amount'],
                'late_fee' => 0,
                'total' => $taxData['total'],
                'currency' => $currency,
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => sprintf(
                    '%s (%s) %s to %s',
                    $plan->name,
                    $plan->interval,
                    $startDate->format(config('app.date_format', 'd-m-Y')),
                    $periodEnd->format(config('app.date_format', 'd-m-Y'))
                ),
                'quantity' => 1,
                'unit_price' => $subtotal,
                'line_total' => $subtotal,
            ]);

            License::create([
                'subscription_id' => $subscription->id,
                'product_id' => $plan->product_id,
                'license_key' => $this->uniqueServiceLicenseKey(),
                'status' => 'pending',
                'starts_at' => $startDate->toDateString(),
                'max_domains' => 1,
            ]);

            $order = Order::create([
                'order_number' => Order::nextNumber(),
                'customer_id' => $customer->id,
                'user_id' => $request->user()?->id,
                'product_id' => $plan->product_id,
                'plan_id' => $plan->id,
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice->id,
                'sales_rep_id' => $salesRepId,
                'status' => 'pending',
            ]);

            return [
                'subscription' => $subscription,
                'invoice' => $invoice,
                'order' => $order,
            ];
        });

        SystemLogger::write('activity', 'Customer service order created.', [
            'customer_id' => $customer->id,
            'subscription_id' => $result['subscription']->id ?? null,
            'invoice_id' => $result['invoice']->id ?? null,
            'order_id' => $result['order']->id ?? null,
            'plan_id' => $plan->id,
        ], $request->user()?->id, $request->ip());

        return redirect()
            ->route('admin.customers.show', ['customer' => $customer, 'tab' => 'services'])
            ->with('status', 'Product/service added for customer. Review and approve it from Orders if needed.');
    }

    public function impersonate(Request $request, Customer $customer)
    {
        // Prevent chaining impersonations
        if ($request->session()->has('impersonator_id')) {
            return back()->withErrors(['impersonate' => 'You are already impersonating another account. Stop impersonation first.']);
        }

        $user = $customer->users()
            ->whereIn('role', [Role::CLIENT, 'customer'])
            ->orderBy('id')
            ->first();

        if (! $user && $customer->email) {
            $existing = User::query()
                ->where('email', $customer->email)
                ->first();

            if ($existing && ! in_array($existing->role, [Role::CLIENT, 'customer'], true)) {
                return back()->withErrors(['impersonate' => 'Customer email is already used by a non-client account.']);
            }

            if ($existing) {
                $user = $existing;
            } else {
                $user = User::create([
                    'name' => $customer->name ?: 'Client '.$customer->id,
                    'email' => $customer->email,
                    'password' => Hash::make(Str::random(32)),
                    'role' => Role::CLIENT,
                    'customer_id' => $customer->id,
                ]);
            }

            if ($user && ! $user->customer_id) {
                $user->customer_id = $customer->id;
            }
        }

        if (! $user) {
            return back()->withErrors(['impersonate' => 'No client login exists for this customer.']);
        }

        if ($user->role !== Role::CLIENT) {
            $user->role = Role::CLIENT;
        }

        if ($user->isDirty()) {
            $user->save();
        }

        $request->session()->put('impersonator_id', $request->user()->id);
        Auth::guard('web')->login($user);
        // Enable employee-guard access when impersonating an employee account (same provider).
        if (Employee::where('user_id', $user->id)->exists()) {
            Auth::guard('employee')->login($user);
        } else {
            Auth::guard('employee')->logout();
        }
        $request->session()->regenerate();

        return redirect()->to($this->impersonationRedirectPath($user));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'access_override_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'default_sales_rep_id' => ['nullable', 'exists:sales_representatives,id'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'nid_file' => ['prohibited'],
            'cv_file' => ['prohibited'],
        ]);

        $customer->update(collect($data)->except(['avatar', 'nid_file', 'cv_file'])->all());

        if ($request->hasFile('avatar')) {
            $path = $this->storeCustomerAvatar($request->file('avatar'), $customer);
            $customer->forceFill(['avatar_path' => $path])->save();
        }

        SystemLogger::write('activity', 'Customer updated.', [
            'customer_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
        ], $request->user()?->id, $request->ip());

        return redirect()->route('admin.customers.edit', $customer)
            ->with('status', 'Customer updated.');
    }

    public function destroy(Customer $customer)
    {
        SystemLogger::write('activity', 'Customer deleted.', [
            'customer_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
        ], auth()->id(), request()->ip());

        User::where('customer_id', $customer->id)->delete();
        $customer->delete();

        return redirect()->route('admin.customers.index')
            ->with('status', 'Customer deleted.');
    }

    /**
     * Decide where to send an impersonated session based on the target user's roles.
     * Priority: active sales rep -> employee -> client dashboard.
     */
    private function impersonationRedirectPath(User $user): string
    {
        $rep = SalesRepresentative::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($rep) {
            return route('rep.dashboard');
        }

        $isEmployee = Employee::query()->where('user_id', $user->id)->exists();

        if ($isEmployee) {
            return route('employee.dashboard');
        }

        return route('client.dashboard');
    }

    private function resolveCustomerLoginStatuses($customers): array
    {
        $userIds = $customers->pluck('users')
            ->flatten()
            ->filter(fn ($user) => $user && $user->role === Role::CLIENT)
            ->pluck('id')
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        $openSessions = UserSession::query()
            ->whereIn('user_id', $userIds)
            ->where('guard', 'web')
            ->whereNull('logout_at')
            ->orderByDesc('last_seen_at')
            ->get()
            ->groupBy('user_id');

        $lastLoginByUser = UserSession::query()
            ->whereIn('user_id', $userIds)
            ->where('guard', 'web')
            ->whereNotNull('login_at')
            ->select('user_id', DB::raw('MAX(login_at) as last_login_at'))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $threshold = now()->subMinutes(2);
        $statuses = [];

        foreach ($customers as $customer) {
            $status = 'logout';
            $lastLoginAt = null;
            foreach ($customer->users->where('role', Role::CLIENT) as $user) {
                $session = $openSessions->get($user->id)?->first();
                if (! $session) {
                    $candidate = $lastLoginByUser->get($user->id)?->last_login_at;
                    if ($candidate) {
                        $candidate = $candidate instanceof Carbon ? $candidate : Carbon::parse($candidate);
                        if (! $lastLoginAt || $candidate->greaterThan($lastLoginAt)) {
                            $lastLoginAt = $candidate;
                        }
                    }
                    continue;
                }

                $lastSeen = $session->last_seen_at;
                if ($lastSeen && $lastSeen->greaterThanOrEqualTo($threshold)) {
                    $status = 'login';
                    $candidate = $lastLoginByUser->get($user->id)?->last_login_at;
                    if ($candidate) {
                        $candidate = $candidate instanceof Carbon ? $candidate : Carbon::parse($candidate);
                        if (! $lastLoginAt || $candidate->greaterThan($lastLoginAt)) {
                            $lastLoginAt = $candidate;
                        }
                    }
                    break;
                }

                $status = 'idle';
                $candidate = $lastLoginByUser->get($user->id)?->last_login_at;
                if ($candidate) {
                    $candidate = $candidate instanceof Carbon ? $candidate : Carbon::parse($candidate);
                    if (! $lastLoginAt || $candidate->greaterThan($lastLoginAt)) {
                        $lastLoginAt = $candidate;
                    }
                }
            }

            $statuses[$customer->id] = [
                'status' => $status,
                'last_login_at' => $lastLoginAt,
            ];
        }

        return $statuses;
    }

    private function indexInertiaProps($customers, array $loginStatuses, string $search): array
    {
        $dateFormat = (string) config('app.date_format', 'd-m-Y');
        $dateTimeFormat = (string) config('app.datetime_format', 'd-m-Y h:i A');

        return [
            'pageTitle' => 'Customers',
            'search' => $search,
            'routes' => [
                'index' => route('admin.customers.index'),
                'create' => route('admin.customers.create'),
            ],
            'customers' => $customers->getCollection()
                ->values()
                ->map(function (Customer $customer) use ($loginStatuses, $dateFormat, $dateTimeFormat) {
                    $loginMeta = $loginStatuses[$customer->id] ?? ['status' => 'logout', 'last_login_at' => null];
                    $loginStatus = is_array($loginMeta) ? ($loginMeta['status'] ?? 'logout') : 'logout';
                    $lastLoginAt = is_array($loginMeta) ? ($loginMeta['last_login_at'] ?? null) : null;
                    if ($lastLoginAt && ! $lastLoginAt instanceof Carbon) {
                        $lastLoginAt = Carbon::parse($lastLoginAt);
                    }

                    $loginLabel = match ($loginStatus) {
                        'login' => 'Login',
                        'idle' => 'Idle',
                        default => 'Logout',
                    };
                    $loginClasses = match ($loginStatus) {
                        'login' => 'border-emerald-200 text-emerald-700 bg-emerald-50',
                        'idle' => 'border-amber-200 text-amber-700 bg-amber-50',
                        default => 'border-rose-200 text-rose-700 bg-rose-50',
                    };

                    $hasActiveService = ($customer->active_subscriptions_count ?? 0) > 0;
                    $hasActiveProject = ($customer->active_projects_count ?? 0) > 0;
                    $hasActiveMaintenance = ($customer->active_project_maintenances_count ?? 0) > 0;
                    $effectiveStatus = ($hasActiveService || $hasActiveProject || $hasActiveMaintenance)
                        ? 'active'
                        : (string) $customer->status;
                    $statusClasses = match ($effectiveStatus) {
                        'active' => 'bg-emerald-100 text-emerald-700',
                        'inactive' => 'bg-slate-200 text-slate-700',
                        default => 'bg-amber-100 text-amber-700',
                    };

                    return [
                        'id' => $customer->id,
                        'name' => (string) $customer->name,
                        'company_name' => (string) ($customer->company_name ?: '--'),
                        'email' => (string) ($customer->email ?: '--'),
                        'phone' => (string) ($customer->phone ?: '--'),
                        'mobile' => (string) ($customer->phone ?: '--'),
                        'avatar_url' => $customer->avatar_path ? asset('storage/'.$customer->avatar_path) : null,
                        'active_subscriptions_count' => (int) ($customer->active_subscriptions_count ?? 0),
                        'subscriptions_count' => (int) ($customer->subscriptions_count ?? 0),
                        'projects_count' => (int) ($customer->projects_count ?? 0),
                        'project_maintenances_count' => (int) ($customer->project_maintenances_count ?? 0),
                        'created_at' => $customer->created_at?->format($dateFormat) ?? '--',
                        'login' => [
                            'status' => (string) $loginStatus,
                            'label' => $loginLabel,
                            'classes' => $loginClasses,
                            'last_login_at' => $lastLoginAt?->format($dateTimeFormat) ?? '--',
                        ],
                        'status' => [
                            'value' => $effectiveStatus,
                            'label' => ucfirst($effectiveStatus),
                            'classes' => $statusClasses,
                        ],
                        'routes' => [
                            'show' => route('admin.customers.show', $customer),
                            'edit' => route('admin.customers.edit', $customer),
                        ],
                    ];
                })
                ->all(),
            'pagination' => [
                'has_pages' => $customers->hasPages(),
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'previous_url' => $customers->previousPageUrl(),
                'next_url' => $customers->nextPageUrl(),
            ],
        ];
    }

    private function calculateServiceSubtotal(string $interval, float $price, Carbon $periodStart, Carbon $periodEnd): float
    {
        if ($interval !== 'monthly') {
            return round($price, 2);
        }

        if ($periodStart->isSameMonth($periodEnd) && $periodEnd->isLastOfMonth() && $periodStart->day !== 1) {
            $daysInPeriod = $periodStart->copy()->startOfDay()
                ->diffInDays($periodEnd->copy()->startOfDay()) + 1;
            $daysInMonth = $periodStart->daysInMonth;
            $ratio = $daysInMonth > 0 ? ($daysInPeriod / $daysInMonth) : 1;

            return round($price * min(1, $ratio), 2);
        }

        return round($price, 2);
    }

    private function nextServiceInvoiceAt(string $interval, Carbon $periodEnd, Carbon $startDate): Carbon
    {
        if ($interval === 'monthly') {
            return $periodEnd->copy()->addDay();
        }

        $invoiceGenerationDays = (int) Setting::getValue('invoice_generation_days');
        $nextInvoiceAt = $invoiceGenerationDays > 0
            ? $periodEnd->copy()->subDays($invoiceGenerationDays)
            : $periodEnd->copy();

        if ($nextInvoiceAt->lessThan($startDate)) {
            $nextInvoiceAt = $periodEnd->copy();
        }

        return $nextInvoiceAt;
    }

    private function uniqueServiceLicenseKey(): string
    {
        do {
            $key = License::generateKey();
        } while (License::query()->where('license_key', $key)->exists());

        return $key;
    }

    private function formInertiaProps(?Customer $customer, $salesReps, ?string $effectiveStatus = null): array
    {
        $isEdit = $customer !== null;

        return [
            'pageTitle' => $isEdit ? 'Edit Customer' : 'New Customer',
            'is_edit' => $isEdit,
            'effective_status' => $effectiveStatus,
            'customer_id' => $customer?->id,
            'created_at' => $customer?->created_at?->toDateString(),
            'sales_reps' => $salesReps->values()->map(fn (SalesRepresentative $rep) => [
                'id' => $rep->id,
                'name' => (string) $rep->name,
                'status' => (string) $rep->status,
            ])->all(),
            'form' => [
                'action' => $isEdit
                    ? route('admin.customers.update', $customer)
                    : route('admin.customers.store'),
                'method' => $isEdit ? 'PUT' : 'POST',
                'avatar_url' => $isEdit && $customer?->avatar_path ? asset('storage/'.$customer->avatar_path) : null,
                'fields' => [
                    'name' => (string) old('name', (string) ($customer?->name ?? '')),
                    'company_name' => (string) old('company_name', (string) ($customer?->company_name ?? '')),
                    'email' => (string) old('email', (string) ($customer?->email ?? '')),
                    'phone' => (string) old('phone', (string) ($customer?->phone ?? '')),
                    'status' => (string) old('status', (string) ($effectiveStatus ?? $customer?->status ?? 'active')),
                    'default_sales_rep_id' => (string) old('default_sales_rep_id', (string) ($customer?->default_sales_rep_id ?? '')),
                    'access_override_until' => (string) old(
                        'access_override_until',
                        $customer?->access_override_until?->format(config('app.date_format', 'd-m-Y')) ?? ''
                    ),
                    'address' => (string) old('address', (string) ($customer?->address ?? '')),
                    'notes' => (string) old('notes', (string) ($customer?->notes ?? '')),
                    'send_account_message' => (bool) old('send_account_message', false),
                ],
            ],
            'routes' => [
                'index' => route('admin.customers.index'),
                'show' => $isEdit ? route('admin.customers.show', $customer) : null,
                'destroy' => $isEdit ? route('admin.customers.destroy', $customer) : null,
            ],
        ];
    }

    private function storeCustomerAvatar(UploadedFile $file, Customer $customer): string
    {
        $disk = Storage::disk('public');

        if ($customer->avatar_path && $disk->exists($customer->avatar_path)) {
            $disk->delete($customer->avatar_path);
        }

        $name = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $name = $name !== '' ? Str::slug($name) : 'customer-logo';
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg'));
        $filename = $name.'-'.Str::random(8).'.'.$extension;

        return $file->storeAs('avatars/customers/'.$customer->id, $filename, 'public');
    }
}
