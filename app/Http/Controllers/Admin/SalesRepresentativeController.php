<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\CommissionAuditLog;
use App\Models\CommissionEarning;
use App\Models\CommissionPayout;
use App\Models\Employee;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\SalesRepresentative;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSession;
use App\Services\CommissionService;
use App\Services\SalesRepBalanceService;
use App\Support\AjaxResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SalesRepresentativeController extends Controller
{
    public function index(CommissionService $commissionService, SalesRepBalanceService $salesRepBalanceService)
    {
        $search = trim((string) request()->query('search', ''));

        $reps = SalesRepresentative::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                            $employeeQuery->where('name', 'like', '%'.$search.'%');
                        });
                });
            })
            ->with(['user:id,name,email', 'employee:id,name'])
            ->withCount(['projects', 'maintenances'])
            ->orderBy('name')
            ->get();

        $commissionService->ensureProjectEarningsForRepIds($reps->pluck('id')->all());

        $totals = collect($salesRepBalanceService->breakdownMany($reps->pluck('id')->all()))
            ->map(fn (array $row) => (object) [
                'total_earned' => (float) ($row['total_earned'] ?? 0),
                'total_payable' => (float) ($row['payable_net'] ?? 0),
                'total_paid' => (float) ($row['total_paid_incl_advance'] ?? 0),
            ]);

        $loginStatuses = $this->resolveRepLoginStatuses($reps);

        if (request()->boolean('partial') || request()->header('HX-Request')) {
            return view('admin.sales-reps.partials.table', [
                'reps' => $reps,
                'totals' => $totals,
                'loginStatuses' => $loginStatuses,
            ]);
        }

        return Inertia::render('Admin/SalesReps/Index', [
            'pageTitle' => 'Sales Representatives',
            'filters' => [
                'search' => $search,
            ],
            'reps' => $this->serializeRepIndexRows($reps, $totals, $loginStatuses),
            'routes' => [
                'index' => route('admin.sales-reps.index'),
                'create' => route('admin.sales-reps.create'),
            ],
        ]);
    }

    public function create(): InertiaResponse
    {
        $employees = Employee::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/SalesReps/Form', [
            'pageTitle' => 'Add Sales Representative',
            'is_edit' => false,
            'employees' => $employees->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
            ])->values(),
            'form' => [
                'action' => route('admin.sales-reps.store'),
                'method' => 'POST',
                'fields' => $this->salesRepFormFields(null),
                'documents' => [],
            ],
            'routes' => [
                'index' => route('admin.sales-reps.index'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'employee_id' => ['nullable', 'exists:employees,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'name' => [
                Rule::requiredIf(! $request->filled('user_id')),
                'string',
                'max:255',
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'user_password' => ['nullable', 'string', 'min:8'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'nid_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'cv_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];

        if ($request->filled('user_password')) {
            $rules['email'][] = 'required';
            $rules['email'][] = Rule::unique('users', 'email');
        }

        $data = $request->validate($rules);

        $user = null;
        if ($request->filled('user_id')) {
            $user = User::findOrFail($request->input('user_id'));
        }

        $salesRep = SalesRepresentative::create([
            'employee_id' => $data['employee_id'] ?? null,
            'name' => $data['name'] ?? $user?->name ?? $user?->email ?? 'Sales Representative',
            'email' => $data['email'] ?? $user?->email ?? null,
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
        ]);

        if ($user) {
            $user->update(['role' => Role::SALES]);
        } elseif (! empty($data['user_password'])) {
            $user = User::create([
                'name' => $salesRep->name,
                'email' => $salesRep->email,
                'password' => Hash::make($data['user_password']),
                'role' => Role::SALES,
            ]);
        }

        if ($user) {
            $salesRep->update(['user_id' => $user->id]);
        }

        $uploadPaths = $this->handleUploads($request, $salesRep);
        if (! empty($uploadPaths)) {
            $salesRep->update($uploadPaths);
        }

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                route('admin.sales-reps.index'),
                'Sales representative created.',
            );
        }

        return redirect()
            ->route('admin.sales-reps.index')
            ->with('status', 'Sales representative created.');
    }

    public function edit(SalesRepresentative $salesRep): InertiaResponse
    {
        $employees = Employee::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/SalesReps/Form', [
            'pageTitle' => 'Edit Sales Representative',
            'is_edit' => true,
            'rep' => [
                'id' => $salesRep->id,
                'user_name' => $salesRep->user?->name,
                'user_email' => $salesRep->user?->email,
            ],
            'employees' => $employees->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
            ])->values(),
            'form' => [
                'action' => route('admin.sales-reps.update', $salesRep),
                'method' => 'PUT',
                'fields' => $this->salesRepFormFields($salesRep),
                'documents' => [
                    'avatar_url' => $salesRep->avatar_path ? asset('storage/'.$salesRep->avatar_path) : null,
                    'nid_url' => $salesRep->nid_path
                        ? route('admin.user-documents.show', ['type' => 'sales-rep', 'id' => $salesRep->id, 'doc' => 'nid'], false)
                        : null,
                    'nid_is_image' => $salesRep->nid_path
                        ? Str::endsWith(strtolower($salesRep->nid_path), ['.jpg', '.jpeg', '.png', '.webp'])
                        : false,
                    'cv_url' => $salesRep->cv_path
                        ? route('admin.user-documents.show', ['type' => 'sales-rep', 'id' => $salesRep->id, 'doc' => 'cv'], false)
                        : null,
                ],
            ],
            'routes' => [
                'index' => route('admin.sales-reps.index'),
            ],
        ]);
    }

    public function update(Request $request, SalesRepresentative $salesRep)
    {
        $data = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'nid_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'cv_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $salesRep->update(collect($data)->except(['avatar', 'nid_file', 'cv_file'])->all());

        $uploadPaths = $this->handleUploads($request, $salesRep);
        if (! empty($uploadPaths)) {
            $salesRep->update($uploadPaths);
        }

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                route('admin.sales-reps.edit', $salesRep),
                'Sales representative updated.',
            );
        }

        return redirect()
            ->route('admin.sales-reps.edit', $salesRep)
            ->with('status', 'Sales representative updated.');
    }

    public function show(
        Request $request,
        SalesRepresentative $salesRep,
        CommissionService $commissionService,
        SalesRepBalanceService $salesRepBalanceService
    ) {
        $salesRep->load(['user:id,name,email', 'employee:id,name']);
        $tab = $request->query('tab', 'profile');
        $allowedTabs = ['profile', 'services', 'invoices', 'emails', 'log', 'earnings', 'payouts', 'projects'];
        if (! in_array($tab, $allowedTabs, true)) {
            $tab = 'profile';
        }

        $commissionService->ensureProjectEarningsForRepIds([$salesRep->id]);
        $balance = $salesRepBalanceService->breakdown($salesRep->id);
        $payableNet = (float) ($balance['payable_net'] ?? 0);
        $payableLabel = $payableNet > 0
            ? 'Company owes rep'
            : ($payableNet < 0 ? 'Rep overpaid / advance taken' : 'Settled');

        $earningsQuery = $salesRep->earnings();
        $payoutsQuery = $salesRep->payouts();

        $summary = [
            'total_earned' => $balance['total_earned'] ?? 0,
            'payable' => $payableNet,
            'payable_gross' => $payableNet,
            'paid' => $balance['total_paid_incl_advance'] ?? 0,
            'advance_paid' => CommissionPayout::query()
                ->where('sales_representative_id', $salesRep->id)
                ->where('type', 'advance')
                ->where('status', 'paid')
                ->sum('total_amount'),
            'overpaid' => max(0, -$payableNet),
            'outstanding' => max(0, $payableNet),
            'payable_label' => $payableLabel,
            'project_earned' => $balance['project_earned'] ?? 0,
            'maintenance_earned' => $balance['maintenance_earned'] ?? 0,
        ];

        $recentEarnings = collect();
        $recentPayouts = collect();
        $subscriptions = collect();
        $invoiceEarnings = collect();
        $projects = collect();
        $projectStatusCounts = collect();
        $projectTaskStatusCounts = collect();

        if ($tab === 'earnings') {
            $recentEarnings = $salesRep->earnings()
                ->latest()
                ->take(10)
                ->get();
        }

        if ($tab === 'payouts') {
            $recentPayouts = $salesRep->payouts()
                ->latest()
                ->take(10)
                ->get();
        }

        if ($tab === 'services') {
            $subscriptions = Subscription::query()
                ->with(['plan.product', 'customer'])
                ->where('sales_rep_id', $salesRep->id)
                ->latest()
                ->take(20)
                ->get();
        }

        if ($tab === 'invoices') {
            $invoiceEarnings = CommissionEarning::query()
                ->with(['invoice.customer', 'project'])
                ->where('sales_representative_id', $salesRep->id)
                ->whereNotNull('invoice_id')
                ->latest('earned_at')
                ->take(20)
                ->get();
        }

        if ($tab === 'projects') {
            $projects = Project::query()
                ->with('customer:id,name')
                ->whereHas('salesRepresentatives', fn ($query) => $query->whereKey($salesRep->id))
                ->orderByDesc('id')
                ->get();

            $projectStatusCounts = $projects->countBy('status');
            $projectIds = $projects->pluck('id');

            if ($projectIds->isNotEmpty()) {
                $projectTaskStatusCounts = ProjectTask::query()
                    ->select('project_id', 'status', DB::raw('COUNT(*) as total'))
                    ->whereIn('project_id', $projectIds)
                    ->where(function ($query) use ($salesRep) {
                        $query->where(function ($inner) use ($salesRep) {
                            $inner->where('assigned_type', 'sales_rep')
                                ->where('assigned_id', $salesRep->id);
                        })->orWhereHas('assignments', function ($inner) use ($salesRep) {
                            $inner->where('assignee_type', 'sales_rep')
                                ->where('assignee_id', $salesRep->id);
                        });
                    })
                    ->groupBy('project_id', 'status')
                    ->get()
                    ->groupBy('project_id')
                    ->map(fn ($rows) => $rows->pluck('total', 'status'));
            }
        }

        $advanceProjects = Project::query()
            ->with('customer:id,name')
            ->whereHas('salesRepresentatives', fn ($query) => $query->whereKey($salesRep->id))
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id', 'status']);

        return view('admin.sales-reps.show', [
            'rep' => $salesRep,
            'tab' => $tab,
            'summary' => $summary,
            'recentEarnings' => $recentEarnings,
            'recentPayouts' => $recentPayouts,
            'subscriptions' => $subscriptions,
            'invoiceEarnings' => $invoiceEarnings,
            'projects' => $projects,
            'projectStatusCounts' => $projectStatusCounts,
            'projectTaskStatusCounts' => $projectTaskStatusCounts,
            'advanceProjects' => $advanceProjects,
        ]);
    }

    public function storeAdvancePayment(Request $request, SalesRepresentative $salesRep)
    {
        if (! Schema::hasColumn('commission_payouts', 'type')) {
            if (AjaxResponse::ajaxFromRequest($request)) {
                return AjaxResponse::ajaxError(
                    'Advance payments require the latest commission payout migration. Run database migrations first.',
                    422,
                    ['amount' => ['Advance payments require the latest commission payout migration. Run database migrations first.']],
                );
            }

            return back()->withErrors(['amount' => 'Advance payments require the latest commission payout migration. Run database migrations first.']);
        }

        $data = $request->validate([
            'project_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'payout_method' => ['nullable', Rule::in(PaymentMethod::allowedCommissionPayoutCodes())],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $currency = $data['currency'] ?? 'BDT';
        $project = null;

        if (! empty($data['project_id'])) {
            $project = Project::query()
                ->with('customer:id,name')
                ->whereKey((int) $data['project_id'])
                ->whereHas('salesRepresentatives', fn ($query) => $query->whereKey($salesRep->id))
                ->first();

            if (! $project) {
                if (AjaxResponse::ajaxFromRequest($request)) {
                    return AjaxResponse::ajaxError(
                        'Select a valid project linked to this sales rep.',
                        422,
                        ['project_id' => ['Select a valid project linked to this sales rep.']],
                    );
                }

                return back()->withErrors(['project_id' => 'Select a valid project linked to this sales rep.'])->withInput();
            }
        }

        DB::transaction(function () use ($data, $salesRep, $currency, $request, $project) {
            $payload = [
                'sales_representative_id' => $salesRep->id,
                'type' => 'advance',
                'total_amount' => (float) $data['amount'],
                'currency' => $currency,
                'payout_method' => $data['payout_method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'status' => 'paid',
                'paid_at' => now(),
            ];

            if (Schema::hasColumn('commission_payouts', 'project_id')) {
                $payload['project_id'] = $project?->id;
            }

            $payout = CommissionPayout::create($payload);

            CommissionAuditLog::create([
                'sales_representative_id' => $salesRep->id,
                'commission_payout_id' => $payout->id,
                'action' => 'advance_payment',
                'status_from' => null,
                'status_to' => 'paid',
                'description' => 'Advance payment recorded.',
                'metadata' => [
                    'amount' => (float) $data['amount'],
                    'currency' => $currency,
                    'project_id' => $project?->id,
                    'project_name' => $project?->name,
                ],
                'created_by' => $request->user()?->id,
            ]);
        });

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                route('admin.sales-reps.show', ['sales_rep' => $salesRep->id, 'tab' => 'profile']),
                'Advance payment recorded.',
            );
        }

        return back()->with('status', 'Advance payment recorded.');
    }

    public function impersonate(Request $request, SalesRepresentative $salesRep)
    {
        if ($request->session()->has('impersonator_id')) {
            return back()->withErrors(['impersonate' => 'You are already impersonating another account. Stop impersonation first.']);
        }

        if ($salesRep->status !== 'active') {
            return back()->withErrors(['impersonate' => 'Sales rep access is inactive.']);
        }

        $user = $salesRep->user;
        if (! $user) {
            return back()->withErrors(['impersonate' => 'No linked user found for this sales rep.']);
        }

        $request->session()->put('impersonator_id', $request->user()->id);
        Auth::login($user);
        Auth::guard('sales')->login($user);
        $request->session()->regenerate();

        return redirect()->route('rep.dashboard');
    }

    private function handleUploads(Request $request, SalesRepresentative $salesRep): array
    {
        $paths = [];

        if ($request->hasFile('avatar')) {
            $paths['avatar_path'] = $request->file('avatar')
                ->store('avatars/sales-reps/'.$salesRep->id, 'public');
        }

        if ($request->hasFile('nid_file')) {
            $paths['nid_path'] = $request->file('nid_file')
                ->store('nid/sales-reps/'.$salesRep->id, 'public');
        }

        if ($request->hasFile('cv_file')) {
            $paths['cv_path'] = $request->file('cv_file')
                ->store('cv/sales-reps/'.$salesRep->id, 'public');
        }

        return $paths;
    }

    private function resolveRepLoginStatuses($reps): array
    {
        $userIds = $reps->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        $openSessions = UserSession::query()
            ->whereIn('user_id', $userIds)
            ->where('guard', 'sales')
            ->whereNull('logout_at')
            ->orderByDesc('last_seen_at')
            ->get()
            ->groupBy('user_id');

        $lastLoginByUser = UserSession::query()
            ->whereIn('user_id', $userIds)
            ->where('guard', 'sales')
            ->whereNotNull('login_at')
            ->select('user_id', DB::raw('MAX(login_at) as last_login_at'))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $threshold = now()->subMinutes(2);
        $statuses = [];

        foreach ($reps as $rep) {
            $userId = $rep->user_id;
            if (! $userId) {
                $statuses[$rep->id] = [
                    'status' => 'logout',
                    'last_login_at' => null,
                ];

                continue;
            }

            $session = $openSessions->get($userId)?->first();
            if (! $session) {
                $candidate = $lastLoginByUser->get($userId)?->last_login_at;
                if ($candidate && ! $candidate instanceof Carbon) {
                    $candidate = Carbon::parse($candidate);
                }
                $statuses[$rep->id] = [
                    'status' => 'logout',
                    'last_login_at' => $candidate,
                ];

                continue;
            }

            $lastSeen = $session->last_seen_at;
            $candidate = $lastLoginByUser->get($userId)?->last_login_at;
            if ($candidate && ! $candidate instanceof Carbon) {
                $candidate = Carbon::parse($candidate);
            }
            $statuses[$rep->id] = [
                'status' => $lastSeen && $lastSeen->greaterThanOrEqualTo($threshold) ? 'login' : 'idle',
                'last_login_at' => $candidate,
            ];
        }

        return $statuses;
    }

    /**
     * @param  array<int, array{status:string,last_login_at:Carbon|null}>  $loginStatuses
     * @return array<int, array<string, mixed>>
     */
    private function serializeRepIndexRows(Collection $reps, BaseCollection $totals, array $loginStatuses): array
    {
        $dateTimeFormat = (string) config('app.date_format', 'Y-m-d').' H:i';

        return $reps->map(function (SalesRepresentative $rep) use ($totals, $loginStatuses, $dateTimeFormat): array {
            $repTotals = $totals[$rep->id] ?? null;
            $loginMeta = $loginStatuses[$rep->id] ?? ['status' => 'logout', 'last_login_at' => null];
            $loginStatus = is_array($loginMeta) ? (string) ($loginMeta['status'] ?? 'logout') : 'logout';
            $lastLoginAt = is_array($loginMeta) ? ($loginMeta['last_login_at'] ?? null) : null;
            if ($lastLoginAt && ! $lastLoginAt instanceof Carbon) {
                $lastLoginAt = Carbon::parse($lastLoginAt);
            }

            return [
                'id' => $rep->id,
                'name' => $rep->name,
                'email' => $rep->email,
                'employee_name' => $rep->employee?->name,
                'active_subscriptions_count' => (int) ($rep->active_subscriptions_count ?? 0),
                'subscriptions_count' => (int) ($rep->subscriptions_count ?? 0),
                'projects_count' => (int) ($rep->projects_count ?? 0),
                'maintenances_count' => (int) ($rep->maintenances_count ?? 0),
                'last_login_label' => $lastLoginAt ? $lastLoginAt->format($dateTimeFormat) : '--',
                'total_earned' => number_format((float) ($repTotals->total_earned ?? 0), 2),
                'total_payable' => number_format((float) ($repTotals->total_payable ?? 0), 2),
                'total_paid' => number_format((float) ($repTotals->total_paid ?? 0), 2),
                'status' => $rep->status,
                'status_label' => ucfirst((string) $rep->status),
                'routes' => [
                    'show' => route('admin.sales-reps.show', $rep),
                ],
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function salesRepFormFields(?SalesRepresentative $salesRep): array
    {
        return [
            'employee_id' => (string) old('employee_id', (string) ($salesRep?->employee_id ?? '')),
            'name' => (string) old('name', $salesRep?->name ?? ''),
            'email' => (string) old('email', $salesRep?->email ?? ''),
            'phone' => (string) old('phone', $salesRep?->phone ?? ''),
            'status' => (string) old('status', $salesRep?->status ?? 'active'),
        ];
    }
}
