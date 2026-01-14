<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionEarning;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\SalesRepresentative;
use App\Models\Subscription;
use App\Models\UserSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Enums\Role;

class SalesRepresentativeController extends Controller
{
    public function index()
    {
        $reps = SalesRepresentative::query()
            ->with(['user:id,name,email', 'employee:id,name'])
            ->withCount('projects')
            ->orderBy('name')
            ->get();

        $totals = CommissionEarning::query()
            ->selectRaw('sales_representative_id, SUM(commission_amount) as total_earned')
            ->selectRaw('SUM(CASE WHEN status = "payable" THEN commission_amount ELSE 0 END) as total_payable')
            ->selectRaw('SUM(CASE WHEN status = "paid" THEN commission_amount ELSE 0 END) as total_paid')
            ->groupBy('sales_representative_id')
            ->get()
            ->keyBy('sales_representative_id');

        $loginStatuses = $this->resolveRepLoginStatuses($reps);

        return view('admin.sales-reps.index', [
            'reps' => $reps,
            'totals' => $totals,
            'loginStatuses' => $loginStatuses,
        ]);
    }

    public function create()
    {
        $employees = Employee::orderBy('name')->get(['id', 'name']);

        return view('admin.sales-reps.create', [
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'employee_id' => ['nullable', 'exists:employees,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'user_password' => ['nullable', 'string', 'min:8'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'nid_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'cv_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];

        if ($request->filled('user_password')) {
            $rules['email'][] = 'required';
            $rules['email'][] = Rule::unique('users', 'email');
        }

        $data = $request->validate($rules);

        $salesRep = SalesRepresentative::create([
            'employee_id' => $data['employee_id'] ?? null,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
        ]);

        if (! empty($data['user_password'])) {
            $user = User::create([
                'name' => $salesRep->name,
                'email' => $salesRep->email,
                'password' => Hash::make($data['user_password']),
                'role' => Role::SALES,
            ]);

            $salesRep->update(['user_id' => $user->id]);
        }

        $uploadPaths = $this->handleUploads($request, $salesRep);
        if (! empty($uploadPaths)) {
            $salesRep->update($uploadPaths);
        }

        return redirect()
            ->route('admin.sales-reps.index')
            ->with('status', 'Sales representative created.');
    }

    public function edit(SalesRepresentative $salesRep)
    {
        $employees = Employee::orderBy('name')->get(['id', 'name']);

        return view('admin.sales-reps.edit', [
            'rep' => $salesRep,
            'employees' => $employees,
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
            'nid_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'cv_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $salesRep->update(collect($data)->except(['avatar', 'nid_file', 'cv_file'])->all());

        $uploadPaths = $this->handleUploads($request, $salesRep);
        if (! empty($uploadPaths)) {
            $salesRep->update($uploadPaths);
        }

        return redirect()
            ->route('admin.sales-reps.index')
            ->with('status', 'Sales representative updated.');
    }

    public function show(Request $request, SalesRepresentative $salesRep)
    {
        $salesRep->load(['user:id,name,email', 'employee:id,name']);
        $tab = $request->query('tab', 'profile');
        $allowedTabs = ['profile', 'services', 'invoices', 'emails', 'log', 'earnings', 'payouts', 'projects'];
        if (! in_array($tab, $allowedTabs, true)) {
            $tab = 'profile';
        }

        $earningsQuery = $salesRep->earnings();
        $payoutsQuery = $salesRep->payouts();

        $summary = [
            'total_earned' => (float) $earningsQuery->sum('commission_amount'),
            'payable' => (float) $earningsQuery->where('status', 'payable')->sum('commission_amount'),
            'paid' => (float) $earningsQuery->where('status', 'paid')->sum('commission_amount'),
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
        ]);
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
                ->store('avatars/sales-reps/' . $salesRep->id, 'public');
        }

        if ($request->hasFile('nid_file')) {
            $paths['nid_path'] = $request->file('nid_file')
                ->store('nid/sales-reps/' . $salesRep->id, 'public');
        }

        if ($request->hasFile('cv_file')) {
            $paths['cv_path'] = $request->file('cv_file')
                ->store('cv/sales-reps/' . $salesRep->id, 'public');
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

        $threshold = now()->subMinutes(2);
        $statuses = [];

        foreach ($reps as $rep) {
            $userId = $rep->user_id;
            if (! $userId) {
                $statuses[$rep->id] = 'logout';
                continue;
            }

            $session = $openSessions->get($userId)?->first();
            if (! $session) {
                $statuses[$rep->id] = 'logout';
                continue;
            }

            $lastSeen = $session->last_seen_at;
            $statuses[$rep->id] = $lastSeen && $lastSeen->greaterThanOrEqualTo($threshold)
                ? 'login'
                : 'idle';
        }

        return $statuses;
    }
}
