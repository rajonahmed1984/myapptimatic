<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\CommissionAuditLog;
use App\Models\CommissionEarning;
use App\Models\CommissionPayout;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\SalesRepresentative;
use App\Models\Subscription;
use App\Models\SystemLog;
use App\Models\User;
use App\Models\UserSession;
use App\Services\CommissionService;
use App\Services\SalesRepNotificationService;
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
    public function index(CommissionService $commissionService)
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
            ->withCount([
                'subscriptions',
                'subscriptions as active_subscriptions_count' => function ($query) {
                    $query->where('status', 'active');
                },
                'projects',
                'maintenances',
            ])
            ->orderBy('name')
            ->get();

        $commissionService->ensureProjectEarningsForRepIds($reps->pluck('id')->all());

        $totals = $reps->mapWithKeys(function (SalesRepresentative $rep) use ($commissionService): array {
            $row = $commissionService->computeRepBalance($rep->id);

            return [
                $rep->id => (object) [
                    'total_earned' => (float) ($row['total_earned'] ?? 0),
                    'total_payable' => (float) ($row['payable_balance'] ?? 0),
                    'total_paid' => (float) ($row['total_paid'] ?? 0),
                ],
            ];
        });

        $loginStatuses = $this->resolveRepLoginStatuses($reps);

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
        CommissionService $commissionService
    ): InertiaResponse {
        $salesRep->load(['user:id,name,email', 'employee:id,name']);
        $tab = $request->query('tab', 'profile');
        $allowedTabs = ['profile', 'services', 'invoices', 'emails', 'log', 'earnings', 'payouts', 'projects'];
        if (! in_array($tab, $allowedTabs, true)) {
            $tab = 'profile';
        }

        $commissionService->ensureProjectEarningsForRepIds([$salesRep->id]);
        $balance = $commissionService->computeRepBalance($salesRep->id);
        $payableNet = (float) ($balance['payable_balance'] ?? 0);
        $overpaid = (float) ($balance['overpaid'] ?? 0);
        $outstanding = max(0, (float) ($balance['outstanding'] ?? 0));
        $notYetPayable = max(0, round($outstanding - $payableNet, 2));
        $sourceBaseQuery = CommissionEarning::query()
            ->where('sales_representative_id', $salesRep->id)
            ->whereIn('status', ['pending', 'earned', 'payable', 'paid']);
        $projectEarned = (float) (clone $sourceBaseQuery)
            ->where('source_type', 'project')
            ->sum('commission_amount');
        $serviceEarned = (float) (clone $sourceBaseQuery)
            ->whereIn('source_type', ['maintenance', 'plan'])
            ->sum('commission_amount');
        $payableLabel = $payableNet > 0
            ? 'Company owes rep'
            : ($overpaid > 0 ? 'Rep overpaid / advance taken' : 'Settled');

        $summary = [
            'total_earned' => $balance['total_earned'] ?? 0,
            'payable' => $payableNet,
            'payable_gross' => (float) ($balance['payable_gross'] ?? $payableNet),
            'paid' => (float) ($balance['total_paid'] ?? 0),
            'advance_paid' => (float) ($balance['advance_paid'] ?? 0),
            'overpaid' => $overpaid,
            'outstanding' => $outstanding,
            'not_yet_payable' => $notYetPayable,
            'payable_label' => $payableLabel,
            'project_earned' => $projectEarned,
            'maintenance_earned' => $serviceEarned,
        ];

        $recentEarnings = collect();
        $recentPayouts = collect();
        $subscriptions = collect();
        $invoiceEarnings = collect();
        $invoiceCommissionByInvoice = collect();
        $projectCommissionByProject = collect();
        $projects = collect();
        $advanceSources = collect();
        $emailLogs = collect();
        $activityLogs = collect();
        $projectStatusCounts = collect();
        $projectTaskStatusCounts = collect();
        $projectCommissionTotals = collect();
        $projectPaidCommissionTotals = collect();
        $projectAdvancePaidTotals = collect();
        $advanceSourceLabelByPayoutId = [];

        if ($tab === 'earnings') {
            $recentEarnings = $salesRep->earnings()
                ->with([
                    'project:id,name',
                    'subscription.plan.product',
                    'invoice.project:id,name',
                    'invoice.maintenance.project:id,name',
                ])
                ->whereIn('status', ['pending', 'earned', 'payable', 'paid'])
                ->orderByDesc('earned_at')
                ->orderByDesc('id')
                ->get();
        }

        if ($tab === 'payouts') {
            $recentPayouts = $salesRep->payouts()
                ->with([
                    'project:id,name',
                    'earnings:id,commission_payout_id,source_type',
                ])
                ->latest()
                ->take(10)
                ->get();

            $payoutIds = $recentPayouts->pluck('id')->all();
            if (! empty($payoutIds)) {
                $advanceSourceLabelByPayoutId = CommissionAuditLog::query()
                    ->whereIn('commission_payout_id', $payoutIds)
                    ->where('action', 'advance_payment')
                    ->orderByDesc('id')
                    ->get(['commission_payout_id', 'metadata'])
                    ->unique('commission_payout_id')
                    ->mapWithKeys(function (CommissionAuditLog $log): array {
                        $metadata = (array) ($log->metadata ?? []);
                        $sourceLabel = trim((string) ($metadata['source_label'] ?? ''));

                        return [(int) $log->commission_payout_id => $sourceLabel];
                    })
                    ->all();
            }
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
            $invoiceEarnings = Invoice::query()
                ->with(['customer', 'project', 'maintenance.project', 'subscription.plan.product', 'orders.product', 'orders.plan'])
                ->where(function ($query) use ($salesRep) {
                    $query->whereHas('subscription', function ($inner) use ($salesRep) {
                        $inner->where('sales_rep_id', $salesRep->id);
                    })->orWhereHas('orders', function ($inner) use ($salesRep) {
                        $inner->where('sales_rep_id', $salesRep->id);
                    })->orWhereHas('customer', function ($inner) use ($salesRep) {
                        $inner->where('default_sales_rep_id', $salesRep->id);
                    })->orWhereHas('project.salesRepresentatives', function ($inner) use ($salesRep) {
                        $inner->whereKey($salesRep->id);
                    })->orWhereHas('maintenance.project.salesRepresentatives', function ($inner) use ($salesRep) {
                        $inner->whereKey($salesRep->id);
                    });
                })
                ->latest('issue_date')
                ->latest('id')
                ->get();

            $invoiceIds = $invoiceEarnings->pluck('id')->filter()->values();
            if ($invoiceIds->isNotEmpty()) {
                $invoiceCommissionByInvoice = CommissionEarning::query()
                    ->select('invoice_id', DB::raw('SUM(commission_amount) as total_commission'))
                    ->where('sales_representative_id', $salesRep->id)
                    ->whereIn('status', ['pending', 'earned', 'payable', 'paid'])
                    ->whereNotNull('invoice_id')
                    ->whereIn('invoice_id', $invoiceIds)
                    ->groupBy('invoice_id')
                    ->pluck('total_commission', 'invoice_id');
            }

            $projectIds = $invoiceEarnings
                ->map(function (Invoice $invoice) {
                    if ($invoice->project_id) {
                        return (int) $invoice->project_id;
                    }

                    return $invoice->maintenance?->project_id ? (int) $invoice->maintenance->project_id : null;
                })
                ->filter()
                ->unique()
                ->values();

            if ($projectIds->isNotEmpty()) {
                $projectCommissionByProject = CommissionEarning::query()
                    ->selectRaw('COALESCE(project_id, source_id) as project_ref_id, SUM(commission_amount) as total_commission')
                    ->where('sales_representative_id', $salesRep->id)
                    ->where('source_type', 'project')
                    ->whereIn('status', ['pending', 'earned', 'payable', 'paid'])
                    ->where(function ($query) use ($projectIds) {
                        $query->whereIn('project_id', $projectIds)
                            ->orWhere(function ($inner) use ($projectIds) {
                                $inner->whereNull('project_id')
                                    ->whereIn('source_id', $projectIds);
                            });
                    })
                    ->groupBy(DB::raw('COALESCE(project_id, source_id)'))
                    ->pluck('total_commission', 'project_ref_id');
            }

        }

        if ($tab === 'emails') {
            $targetEmail = strtolower(trim((string) $salesRep->email));

            if ($targetEmail !== '') {
                $emailLogs = SystemLog::query()
                    ->where('category', 'email')
                    ->latest()
                    ->limit(500)
                    ->get(['id', 'level', 'message', 'context', 'created_at'])
                    ->filter(function (SystemLog $log) use ($targetEmail): bool {
                        $to = collect((array) data_get($log->context, 'to', []))
                            ->map(fn ($item) => strtolower(trim((string) $item)))
                            ->filter();

                        return $to->contains($targetEmail);
                    })
                    ->take(100)
                    ->values();
            }
        }

        if ($tab === 'log') {
            $activityLogs = CommissionAuditLog::query()
                ->with('creator:id,name')
                ->where('sales_representative_id', $salesRep->id)
                ->latest()
                ->limit(100)
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
                $activeStatuses = ['pending', 'earned', 'payable', 'paid'];
                $projectCommissionTotals = CommissionEarning::query()
                    ->selectRaw('COALESCE(project_id, source_id) as project_ref_id, SUM(commission_amount) as total_commission')
                    ->where('sales_representative_id', $salesRep->id)
                    ->where('source_type', 'project')
                    ->whereIn('status', $activeStatuses)
                    ->where(function ($query) use ($projectIds) {
                        $query->whereIn('project_id', $projectIds)
                            ->orWhere(function ($inner) use ($projectIds) {
                                $inner->whereNull('project_id')
                                    ->whereIn('source_id', $projectIds);
                            });
                    })
                    ->groupBy(DB::raw('COALESCE(project_id, source_id)'))
                    ->pluck('total_commission', 'project_ref_id');

                $projectPaidCommissionTotals = CommissionEarning::query()
                    ->selectRaw('COALESCE(project_id, source_id) as project_ref_id, SUM(commission_amount) as total_commission')
                    ->where('sales_representative_id', $salesRep->id)
                    ->where('source_type', 'project')
                    ->where('status', 'paid')
                    ->where(function ($query) use ($projectIds) {
                        $query->whereIn('project_id', $projectIds)
                            ->orWhere(function ($inner) use ($projectIds) {
                                $inner->whereNull('project_id')
                                    ->whereIn('source_id', $projectIds);
                            });
                    })
                    ->groupBy(DB::raw('COALESCE(project_id, source_id)'))
                    ->pluck('total_commission', 'project_ref_id');

                $projectAdvanceQuery = CommissionPayout::query()
                    ->select('project_id', DB::raw('SUM(total_amount) as total_paid'))
                    ->where('sales_representative_id', $salesRep->id)
                    ->whereIn('project_id', $projectIds)
                    ->where('status', 'paid');

                if (Schema::hasColumn('commission_payouts', 'type')) {
                    $projectAdvanceQuery->where('type', 'advance');
                }

                $projectAdvancePaidTotals = $projectAdvanceQuery
                    ->groupBy('project_id')
                    ->pluck('total_paid', 'project_id');

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
            ->get(['id', 'name', 'customer_id', 'status', 'currency']);

        $advanceSubscriptions = Subscription::query()
            ->with(['customer:id,name', 'plan.product'])
            ->where('sales_rep_id', $salesRep->id)
            ->orderByDesc('id')
            ->get(['id', 'customer_id', 'plan_id', 'sales_rep_commission_amount', 'status']);

        $activeStatuses = ['pending', 'earned', 'payable', 'paid'];
        $projectCommissionTotals = CommissionEarning::query()
            ->selectRaw('COALESCE(project_id, source_id) as project_ref_id, SUM(commission_amount) as total_commission')
            ->where('sales_representative_id', $salesRep->id)
            ->where('source_type', 'project')
            ->whereIn('status', $activeStatuses)
            ->groupBy(DB::raw('COALESCE(project_id, source_id)'))
            ->pluck('total_commission', 'project_ref_id');

        $subscriptionCommissionTotals = CommissionEarning::query()
            ->select('subscription_id', DB::raw('SUM(commission_amount) as total_commission'))
            ->where('sales_representative_id', $salesRep->id)
            ->whereIn('source_type', ['plan', 'maintenance'])
            ->whereNotNull('subscription_id')
            ->whereIn('status', $activeStatuses)
            ->groupBy('subscription_id')
            ->pluck('total_commission', 'subscription_id');

        $projectAssignedAmounts = DB::table('project_sales_representative')
            ->where('sales_representative_id', $salesRep->id)
            ->pluck('amount', 'project_id');

        $advancePaidBySource = $this->resolveAdvancePaidBySource($salesRep->id);

        $projectSources = $advanceProjects->map(function (Project $project) use ($projectCommissionTotals, $projectAssignedAmounts, $advancePaidBySource) {
            $sourceKey = 'project:'.$project->id;
            $commissionAmount = (float) ($projectCommissionTotals->get($project->id)
                ?? $projectAssignedAmounts->get($project->id)
                ?? 0);
            $advancedAmount = (float) ($advancePaidBySource[$sourceKey] ?? 0);

            return [
                'key' => $sourceKey,
                'type' => 'project',
                'id' => $project->id,
                'label' => (string) $project->name,
                'subtitle' => (string) ($project->customer?->name ?? '--'),
                'currency' => (string) ($project->currency ?? 'BDT'),
                'commission_amount' => $commissionAmount,
                'advanced_amount' => $advancedAmount,
                'remaining_amount' => max(0, round($commissionAmount - $advancedAmount, 2)),
                'overpaid_amount' => max(0, round($advancedAmount - $commissionAmount, 2)),
            ];
        });

        $subscriptionSources = $advanceSubscriptions->map(function (Subscription $subscription) use ($subscriptionCommissionTotals, $advancePaidBySource) {
            $sourceKey = 'subscription:'.$subscription->id;
            $productName = (string) ($subscription->plan?->product?->name ?? '--');
            $planName = (string) ($subscription->plan?->name ?? '--');
            $commissionAmount = (float) ($subscriptionCommissionTotals->get($subscription->id)
                ?? $subscription->sales_rep_commission_amount
                ?? 0);
            $advancedAmount = (float) ($advancePaidBySource[$sourceKey] ?? 0);

            return [
                'key' => $sourceKey,
                'type' => 'subscription',
                'id' => $subscription->id,
                'label' => trim($productName.' > '.$planName),
                'subtitle' => (string) ($subscription->customer?->name ?? '--'),
                'currency' => (string) ($subscription->plan?->currency ?? 'BDT'),
                'commission_amount' => $commissionAmount,
                'advanced_amount' => $advancedAmount,
                'remaining_amount' => max(0, round($commissionAmount - $advancedAmount, 2)),
                'overpaid_amount' => max(0, round($advancedAmount - $commissionAmount, 2)),
            ];
        });

        $advanceSources = $projectSources
            ->concat($subscriptionSources)
            ->values();

        return Inertia::render('Admin/SalesReps/Show', [
            'pageTitle' => $salesRep->name,
            'rep' => [
                'id' => $salesRep->id,
                'name' => $salesRep->name,
                'email' => $salesRep->email,
                'phone' => $salesRep->phone,
                'status' => $salesRep->status,
                'status_label' => ucfirst((string) $salesRep->status),
                'user_name' => $salesRep->user?->name,
                'user_email' => $salesRep->user?->email,
                'employee_name' => $salesRep->employee?->name,
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
            'tab' => $tab,
            'tabs' => [
                ['key' => 'profile', 'label' => 'Profile'],
                ['key' => 'services', 'label' => 'Products / Services'],
                ['key' => 'projects', 'label' => 'Projects'],
                ['key' => 'invoices', 'label' => 'Invoices'],
                ['key' => 'earnings', 'label' => 'Recent Earnings'],
                ['key' => 'payouts', 'label' => 'Recent Payouts'],
                ['key' => 'emails', 'label' => 'Emails'],
                ['key' => 'log', 'label' => 'Log'],
            ],
            'summary' => [
                'total_earned' => (float) ($summary['total_earned'] ?? 0),
                'payable' => (float) ($summary['payable'] ?? 0),
                'paid' => (float) ($summary['paid'] ?? 0),
                'advance_paid' => (float) ($summary['advance_paid'] ?? 0),
                'overpaid' => (float) ($summary['overpaid'] ?? 0),
                'outstanding' => (float) ($summary['outstanding'] ?? 0),
                'payable_label' => (string) ($summary['payable_label'] ?? 'Settled'),
                'project_earned' => (float) ($summary['project_earned'] ?? 0),
                'maintenance_earned' => (float) ($summary['maintenance_earned'] ?? 0),
            ],
            'recentEarnings' => $recentEarnings->map(function (CommissionEarning $earning) {
                $sourceType = (string) ($earning->source_type ?? '');
                $sourceLabel = match ($sourceType) {
                    'project' => 'Project',
                    'plan', 'maintenance' => 'Products / Services',
                    default => ucfirst($sourceType),
                };

                $details = '--';
                if ($earning->project?->name) {
                    $details = (string) $earning->project->name;
                } elseif ($earning->subscription?->plan) {
                    $productName = (string) ($earning->subscription->plan->product?->name ?? '--');
                    $planName = (string) ($earning->subscription->plan->name ?? '--');
                    $details = trim($productName.' > '.$planName);
                } elseif ($earning->invoice?->project?->name) {
                    $details = (string) $earning->invoice->project->name;
                } elseif ($earning->invoice?->maintenance?->project?->name) {
                    $details = (string) $earning->invoice->maintenance->project->name;
                }

                return [
                    'id' => $earning->id,
                    'source_type' => $sourceType,
                    'source_id' => $earning->source_id,
                    'amount' => (float) ($earning->commission_amount ?? $earning->amount ?? 0),
                    'currency' => $earning->currency,
                    'status' => $earning->status,
                    'status_label' => ucfirst((string) $earning->status),
                    'source_label' => $sourceLabel,
                    'details' => $details,
                    'earned_date' => $earning->earned_at?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                    'earned_at' => $earning->earned_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                ];
            })->values(),
            'recentPayouts' => $recentPayouts->map(function (CommissionPayout $payout) use ($advanceSourceLabelByPayoutId) {
                return [
                    'id' => $payout->id,
                    'type' => $payout->type,
                    'status' => $payout->status,
                    'status_label' => ucfirst((string) ($payout->status ?? '--')),
                    'source_label' => $this->resolvePayoutSourceLabel($payout, $advanceSourceLabelByPayoutId),
                    'payout_method' => $payout->payout_method,
                    'total_amount' => (float) $payout->total_amount,
                    'currency' => $payout->currency,
                    'paid_at' => $payout->paid_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                    'reference' => $payout->reference,
                ];
            })->values(),
            'subscriptions' => $subscriptions->map(function (Subscription $subscription) {
                $plan = $subscription->plan;
                $productName = (string) ($plan?->product?->name ?? '--');
                $planName = (string) ($plan?->name ?? '--');
                $intervalLabel = ucfirst((string) ($plan?->interval ?? '--'));
                $currency = (string) ($plan?->currency ?? '');
                $amount = $subscription->subscription_amount !== null
                    ? (float) $subscription->subscription_amount
                    : (float) ($plan?->price ?? 0);
                $commissionAmount = $subscription->sales_rep_commission_amount !== null
                    ? (float) $subscription->sales_rep_commission_amount
                    : null;

                $amountDisplay = trim((string) (($currency ? $currency.' ' : '').number_format($amount, 2)));
                $commissionDisplay = $commissionAmount !== null
                    ? trim((string) (($currency ? $currency.' ' : '').number_format($commissionAmount, 2)))
                    : '--';

                return [
                    'id' => $subscription->id,
                    'customer_name' => $subscription->customer?->name ?? '--',
                    'product_plan' => $productName.' > '.$planName,
                    'interval_amount_commission' => $intervalLabel.' - '.$amountDisplay.' > '.$commissionDisplay,
                    'status' => ucfirst((string) ($subscription->status ?? '--')),
                    'next_invoice_at' => $subscription->next_invoice_at?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                ];
            })->values(),
            'invoiceEarnings' => $invoiceEarnings->map(function (Invoice $invoice) use ($invoiceCommissionByInvoice, $projectCommissionByProject, $salesRep) {
                $details = '--';
                if ($invoice->project?->name) {
                    $details = (string) $invoice->project->name;
                } elseif ($invoice->maintenance?->project?->name) {
                    $details = (string) $invoice->maintenance->project->name;
                } elseif ($invoice->subscription?->plan) {
                    $productName = (string) ($invoice->subscription->plan->product?->name ?? '--');
                    $planName = (string) ($invoice->subscription->plan->name ?? '--');
                    $details = trim($productName.' > '.$planName);
                } elseif ($invoice->orders->isNotEmpty()) {
                    $order = $invoice->orders->first();
                    $productName = (string) ($order?->product?->name ?? '--');
                    $planName = (string) ($order?->plan?->name ?? '--');
                    $details = trim($productName.' > '.$planName);
                }
                $statusValue = (string) ($invoice->status ?? '');
                $commissionAmount = $invoiceCommissionByInvoice->get($invoice->id);
                if ($commissionAmount === null) {
                    $subscriptionCommissionAmount = $invoice->subscription?->sales_rep_commission_amount;
                    $subscriptionSalesRepId = $invoice->subscription?->sales_rep_id;
                    if (
                        $subscriptionCommissionAmount !== null
                        && $subscriptionSalesRepId !== null
                        && (int) $subscriptionSalesRepId === (int) $salesRep->id
                    ) {
                        $commissionAmount = (float) $subscriptionCommissionAmount;
                    }
                }
                if ($commissionAmount === null) {
                    $projectRefId = $invoice->project_id ?: $invoice->maintenance?->project_id;
                    if ($projectRefId !== null) {
                        $projectCommissionAmount = $projectCommissionByProject->get((int) $projectRefId);
                        if ($projectCommissionAmount !== null) {
                            $commissionAmount = (float) $projectCommissionAmount;
                        }
                    }
                }
                $commissionDisplay = $commissionAmount !== null
                    ? trim((string) (((string) ($invoice->currency ?? '') ? ((string) ($invoice->currency ?? '')).' ' : '').number_format((float) $commissionAmount, 2)))
                    : '--';

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice ? '#'.($invoice->number ?? $invoice->id) : '--',
                    'invoice_show_route' => $invoice ? route('admin.invoices.show', $invoice) : null,
                    'customer_name' => $invoice?->customer?->name ?? '--',
                    'project_name' => $details,
                    'status' => ucfirst($statusValue ?: '--'),
                    'status_key' => $statusValue,
                    'total_display' => $invoice ? ($invoice->currency.' '.number_format((float) $invoice->total, 2)) : '--',
                    'commission_display' => $commissionDisplay,
                    'issue_date' => $invoice?->issue_date?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                    'due_date' => $invoice?->due_date?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                ];
            })->values(),
            'projects' => $projects->map(function (Project $project) use ($projectTaskStatusCounts, $projectCommissionTotals, $projectPaidCommissionTotals, $projectAdvancePaidTotals) {
                $taskCounts = collect($projectTaskStatusCounts->get($project->id, []));
                $commissionAmount = (float) ($projectCommissionTotals->get($project->id) ?? 0);
                $paidCommissionAmount = (float) ($projectPaidCommissionTotals->get($project->id) ?? 0);
                $advancePaidAmount = (float) ($projectAdvancePaidTotals->get($project->id) ?? 0);
                $takenAmount = round($paidCommissionAmount + $advancePaidAmount, 2);
                $remainingAmount = max(0, round($commissionAmount - $takenAmount, 2));
                $overpaidAmount = max(0, round($takenAmount - $commissionAmount, 2));

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'customer_name' => $project->customer?->name ?? '--',
                    'status' => ucfirst((string) $project->status),
                    'currency' => (string) ($project->currency ?? 'BDT'),
                    'commission_amount' => $commissionAmount,
                    'taken_amount' => $takenAmount,
                    'taken_commission_amount' => $paidCommissionAmount,
                    'taken_advance_amount' => $advancePaidAmount,
                    'remaining_amount' => $remainingAmount,
                    'overpaid_amount' => $overpaidAmount,
                    'route' => route('admin.projects.show', $project),
                    'tasks' => [
                        'pending' => (int) ($taskCounts->get('pending', 0) + $taskCounts->get('todo', 0)),
                        'in_progress' => (int) $taskCounts->get('in_progress', 0),
                        'blocked' => (int) $taskCounts->get('blocked', 0),
                        'completed' => (int) ($taskCounts->get('completed', 0) + $taskCounts->get('done', 0)),
                    ],
                ];
            })->values(),
            'emailLogs' => $emailLogs->map(function (SystemLog $log) {
                $context = (array) ($log->context ?? []);
                $to = collect((array) ($context['to'] ?? []))
                    ->map(fn ($item) => (string) $item)
                    ->filter()
                    ->values();

                return [
                    'id' => $log->id,
                    'created_at' => $log->created_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                    'subject' => (string) ($context['subject'] ?? '--'),
                    'to' => $to->all(),
                    'to_count' => (int) ($context['to_count'] ?? $to->count()),
                    'category' => (string) ($context['category'] ?? '--'),
                    'mailer' => (string) ($context['mailer'] ?? '--'),
                    'message_id' => (string) ($context['message_id'] ?? '--'),
                    'status' => strtoupper((string) ($log->level ?? 'info')),
                    'event' => (string) $log->message,
                ];
            })->values(),
            'activityLogs' => $activityLogs->map(function (CommissionAuditLog $log) {
                $metadata = (array) ($log->metadata ?? []);

                return [
                    'id' => $log->id,
                    'created_at' => $log->created_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                    'action' => (string) ($log->action ?? '--'),
                    'status_from' => (string) ($log->status_from ?? '--'),
                    'status_to' => (string) ($log->status_to ?? '--'),
                    'description' => (string) ($log->description ?? '--'),
                    'created_by' => (string) ($log->creator?->name ?? 'System'),
                    'amount' => isset($metadata['amount']) ? (float) $metadata['amount'] : null,
                    'currency' => isset($metadata['currency']) ? (string) $metadata['currency'] : null,
                    'project_name' => isset($metadata['project_name']) ? (string) $metadata['project_name'] : null,
                ];
            })->values(),
            'projectStatusCounts' => $projectStatusCounts->all(),
            'advanceProjects' => $advanceProjects->map(function (Project $project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status,
                    'customer_name' => $project->customer?->name,
                ];
            })->values(),
            'advanceSources' => $advanceSources->values(),
            'paymentMethods' => PaymentMethod::commissionPayoutDropdownOptions()
                ->map(fn ($method) => ['code' => $method->code, 'name' => $method->name])
                ->values(),
            'routes' => [
                'index' => route('admin.sales-reps.index'),
                'edit' => route('admin.sales-reps.edit', $salesRep),
                'impersonate' => route('admin.sales-reps.impersonate', $salesRep),
                'advance_payment' => route('admin.sales-reps.advance-payment', $salesRep),
                'show_tab' => route('admin.sales-reps.show', ['sales_rep' => $salesRep->id]),
                'commission_payout_create' => route('admin.commission-payouts.create', ['sales_rep_id' => $salesRep->id]),
            ],
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
            'source_type' => ['nullable', Rule::in(['project', 'subscription'])],
            'source_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'payout_method' => ['nullable', Rule::in(PaymentMethod::allowedCommissionPayoutCodes())],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $activeStatuses = ['pending', 'earned', 'payable', 'paid'];
        $sourceType = (string) ($data['source_type'] ?? '');
        $sourceId = (int) ($data['source_id'] ?? 0);
        if (($sourceType === '' || $sourceId <= 0) && ! empty($data['project_id'])) {
            $sourceType = 'project';
            $sourceId = (int) $data['project_id'];
        }
        if ($sourceType === '' || $sourceId <= 0) {
            if (AjaxResponse::ajaxFromRequest($request)) {
                return AjaxResponse::ajaxError(
                    'Select a valid project or products/services source.',
                    422,
                    ['source_id' => ['Select a valid project or products/services source.']],
                );
            }

            return back()->withErrors(['source_id' => 'Select a valid project or products/services source.'])->withInput();
        }
        $currency = (string) ($data['currency'] ?? 'BDT');
        $project = null;
        $subscription = null;
        $sourceLabel = '--';
        $sourceCommissionAmount = 0.0;

        if ($sourceType === 'project') {
            $project = Project::query()
                ->with('customer:id,name')
                ->whereKey($sourceId)
                ->whereHas('salesRepresentatives', fn ($query) => $query->whereKey($salesRep->id))
                ->first();

            if (! $project) {
                if (AjaxResponse::ajaxFromRequest($request)) {
                    return AjaxResponse::ajaxError(
                        'Select a valid project linked to this sales rep.',
                        422,
                        ['source_id' => ['Select a valid project linked to this sales rep.']],
                    );
                }

                return back()->withErrors(['source_id' => 'Select a valid project linked to this sales rep.'])->withInput();
            }

            $currency = (string) ($data['currency'] ?? ($project->currency ?: 'BDT'));
            $sourceLabel = 'Project: '.$project->name;
            $sourceCommissionAmount = (float) CommissionEarning::query()
                ->where('sales_representative_id', $salesRep->id)
                ->where('source_type', 'project')
                ->where('source_id', $sourceId)
                ->whereIn('status', $activeStatuses)
                ->sum('commission_amount');

            if ($sourceCommissionAmount <= 0) {
                $sourceCommissionAmount = (float) (DB::table('project_sales_representative')
                    ->where('project_id', $sourceId)
                    ->where('sales_representative_id', $salesRep->id)
                    ->value('amount') ?? 0);
            }
        } else {
            $subscription = Subscription::query()
                ->with(['customer:id,name', 'plan.product'])
                ->whereKey($sourceId)
                ->where('sales_rep_id', $salesRep->id)
                ->first();

            if (! $subscription) {
                if (AjaxResponse::ajaxFromRequest($request)) {
                    return AjaxResponse::ajaxError(
                        'Select a valid products/services subscription linked to this sales rep.',
                        422,
                        ['source_id' => ['Select a valid products/services subscription linked to this sales rep.']],
                    );
                }

                return back()->withErrors(['source_id' => 'Select a valid products/services subscription linked to this sales rep.'])->withInput();
            }

            $currency = (string) ($data['currency'] ?? ($subscription->plan?->currency ?: 'BDT'));
            $productName = (string) ($subscription->plan?->product?->name ?? '--');
            $planName = (string) ($subscription->plan?->name ?? '--');
            $sourceLabel = 'Products / Services: '.trim($productName.' > '.$planName);
            $sourceCommissionAmount = (float) CommissionEarning::query()
                ->where('sales_representative_id', $salesRep->id)
                ->whereIn('source_type', ['plan', 'maintenance'])
                ->where('subscription_id', $sourceId)
                ->whereIn('status', $activeStatuses)
                ->sum('commission_amount');

            if ($sourceCommissionAmount <= 0) {
                $sourceCommissionAmount = (float) ($subscription->sales_rep_commission_amount ?? 0);
            }
        }

        $sourceAdvanceMap = $this->resolveAdvancePaidBySource($salesRep->id);
        $sourceKey = $sourceType.':'.$sourceId;
        $sourceAdvancedBefore = (float) ($sourceAdvanceMap[$sourceKey] ?? 0);
        $amount = (float) $data['amount'];
        $sourceAdvancedAfter = round($sourceAdvancedBefore + $amount, 2);
        $sourceRemainingBefore = max(0, round($sourceCommissionAmount - $sourceAdvancedBefore, 2));
        $sourceRemainingAfter = max(0, round($sourceCommissionAmount - $sourceAdvancedAfter, 2));
        $sourceOverpaidAfter = max(0, round($sourceAdvancedAfter - $sourceCommissionAmount, 2));

        $payout = DB::transaction(function () use (
            $data,
            $salesRep,
            $currency,
            $request,
            $project,
            $subscription,
            $sourceType,
            $sourceId,
            $sourceLabel,
            $sourceCommissionAmount,
            $sourceAdvancedBefore,
            $sourceRemainingBefore,
            $sourceRemainingAfter,
            $sourceOverpaidAfter,
            $amount
        ) {
            $payload = [
                'sales_representative_id' => $salesRep->id,
                'type' => 'advance',
                'total_amount' => $amount,
                'currency' => $currency,
                'payout_method' => $data['payout_method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'status' => 'paid',
                'paid_at' => now(),
            ];

            if (Schema::hasColumn('commission_payouts', 'project_id')) {
                $payload['project_id'] = $sourceType === 'project' ? $project?->id : null;
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
                    'amount' => $amount,
                    'currency' => $currency,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'source_label' => $sourceLabel,
                    'source_commission_amount' => $sourceCommissionAmount,
                    'source_advanced_before' => $sourceAdvancedBefore,
                    'source_remaining_before' => $sourceRemainingBefore,
                    'source_remaining_after' => $sourceRemainingAfter,
                    'source_overpaid_after' => $sourceOverpaidAfter,
                    'project_id' => $project?->id,
                    'project_name' => $project?->name,
                    'subscription_id' => $subscription?->id,
                ],
                'created_by' => $request->user()?->id,
            ]);

            return $payout;
        });

        try {
            app(SalesRepNotificationService::class)
                ->sendCommissionPayoutNotification($payout->fresh(), 'paid');
        } catch (\Throwable) {
            // Notification failures should not block advance payout recording.
        }

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

    private function resolvePayoutSourceLabel(CommissionPayout $payout, array $advanceSourceLabelByPayoutId): string
    {
        if ($payout->project?->name) {
            return 'Project: '.$payout->project->name;
        }

        if ((string) ($payout->type ?? '') === 'advance') {
            $sourceLabel = trim((string) ($advanceSourceLabelByPayoutId[(int) $payout->id] ?? ''));

            return $sourceLabel !== '' ? $sourceLabel : 'Advance payment';
        }

        $sourceLabels = $payout->earnings
            ->pluck('source_type')
            ->filter(fn ($sourceType) => is_string($sourceType) && trim($sourceType) !== '')
            ->map(fn ($sourceType) => $this->humanizePayoutSourceType((string) $sourceType))
            ->unique()
            ->values();

        if ($sourceLabels->isEmpty()) {
            return 'Commission earnings';
        }

        if ($sourceLabels->count() === 1) {
            return (string) $sourceLabels->first();
        }

        return 'Mixed: '.$sourceLabels->implode(', ');
    }

    private function humanizePayoutSourceType(string $sourceType): string
    {
        return match ($sourceType) {
            'project' => 'Project',
            'plan', 'maintenance' => 'Products / Services',
            default => ucfirst($sourceType),
        };
    }

    /**
     * @return array<string, float>
     */
    private function resolveAdvancePaidBySource(int $salesRepId): array
    {
        $totals = [];

        CommissionAuditLog::query()
            ->where('sales_representative_id', $salesRepId)
            ->where('action', 'advance_payment')
            ->orderBy('id')
            ->get(['metadata'])
            ->each(function (CommissionAuditLog $log) use (&$totals): void {
                $metadata = (array) ($log->metadata ?? []);
                $amount = round((float) ($metadata['amount'] ?? 0), 2);
                if ($amount <= 0) {
                    return;
                }

                $sourceType = (string) ($metadata['source_type'] ?? '');
                $sourceId = isset($metadata['source_id']) ? (int) $metadata['source_id'] : 0;

                // Backward compatibility: older advance logs only stored project_id.
                if (($sourceType === '' || $sourceId <= 0) && ! empty($metadata['project_id'])) {
                    $sourceType = 'project';
                    $sourceId = (int) $metadata['project_id'];
                }

                if ($sourceType === '' || $sourceId <= 0) {
                    return;
                }

                $key = $sourceType.':'.$sourceId;
                $totals[$key] = round(((float) ($totals[$key] ?? 0)) + $amount, 2);
            });

        return $totals;
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
        $dateTimeFormat = (string) config('app.datetime_format', 'd-m-Y h:i A');

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
