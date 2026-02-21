<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\MailCategory;
use App\Models\AccountingEntry;
use App\Models\Customer;
use App\Models\EmailTemplate;
use App\Models\SystemLog;
use App\Models\ProjectMaintenance;
use App\Models\SalesRepresentative;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
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

        if ($request->boolean('partial') || $request->header('HX-Request')) {
            return view('admin.customers.partials.table', compact('customers', 'loginStatuses'));
        }

        return view('admin.customers.index', compact('customers', 'loginStatuses', 'search'));
    }

    public function create()
    {
        return view('admin.customers.create', [
            'salesReps' => SalesRepresentative::orderBy('name')->get(['id', 'name', 'status']),
        ]);
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

    public function edit(Customer $customer)
    {
        $activeServices = $customer->subscriptions()->where('status', 'active')->count();
        $activeProjects = $customer->projects()->whereIn('status', ['ongoing', 'complete'])->count();
        $activeMaintenances = $customer->projectMaintenances()->where('status', 'active')->count();
        $effectiveStatus = ($activeServices > 0 || $activeProjects > 0 || $activeMaintenances > 0)
            ? 'active'
            : $customer->status;

        return view('admin.customers.edit', [
            'customer' => $customer,
            'salesReps' => SalesRepresentative::orderBy('name')->get(['id', 'name', 'status']),
            'projectClients' => $customer->projectUsers()->with('project')->get(),
            'projects' => $customer->projects()->orderBy('name')->get(['id', 'name']),
            'effectiveStatus' => $effectiveStatus,
        ]);
    }

    public function show(Request $request, Customer $customer)
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

        return view('admin.customers.show', [
            'customer' => $customer,
            'tab' => $tab,
            'activityLogs' => $activityLogs,
            'emailLogs' => $emailLogs,
            'invoiceStatusSummary' => $invoiceStatusSummary,
            'grossRevenue' => $grossRevenue,
            'clientExpenses' => $clientExpenses,
            'netIncome' => $netIncome,
            'creditBalance' => $creditBalance,
            'currencyCode' => $currencyCode,
            'currencySymbol' => $currencySymbol,
            'projectClients' => $projectClients,
            'projects' => $projects,
            'salesRepSummaries' => $salesRepSummaries,
            'effectiveStatus' => $effectiveStatus,
        ]);
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
            'avatar' => ['prohibited'],
            'nid_file' => ['prohibited'],
            'cv_file' => ['prohibited'],
        ]);

        $customer->update($data);

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
}
