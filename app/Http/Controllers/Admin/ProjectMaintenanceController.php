<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMaintenance;
use App\Models\SalesRepresentative;
use App\Models\Setting;
use App\Services\MaintenanceBillingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectMaintenanceController extends Controller
{
    public function __construct(
        private MaintenanceBillingService $maintenanceBillingService
    ) {}

    public function index(Request $request): View|InertiaResponse
    {
        $search = trim((string) $request->input('search', ''));

        $maintenances = ProjectMaintenance::query()
            ->with(['project:id,name,currency', 'customer:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', '%'.$search.'%')
                        ->orWhere('billing_cycle', 'like', '%'.$search.'%')
                        ->orWhere('status', 'like', '%'.$search.'%')
                        ->orWhereHas('project', function ($projectQuery) use ($search) {
                            $projectQuery->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', '%'.$search.'%');
                        });

                    if (is_numeric($search)) {
                        $inner->orWhere('id', (int) $search);
                    }
                });
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $payload = [
            'maintenances' => $maintenances,
            'search' => $search,
        ];

        if ($request->header('HX-Request')) {
            return view('admin.project-maintenances.partials.table', $payload);
        }

        return Inertia::render('Admin/ProjectMaintenances/Index', [
            'pageTitle' => 'Project Maintenance',
            'filters' => [
                'search' => $search,
            ],
            'maintenances' => $maintenances->getCollection()
                ->map(fn (ProjectMaintenance $maintenance) => $this->serializeMaintenanceListItem($maintenance))
                ->values(),
            'pagination' => $this->paginationPayload($maintenances),
            'routes' => [
                'index' => route('admin.project-maintenances.index'),
                'create' => route('admin.project-maintenances.create'),
            ],
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $projects = Project::query()
            ->with('customer:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id', 'currency']);

        $salesReps = SalesRepresentative::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'status']);

        return Inertia::render('Admin/ProjectMaintenances/Form', [
            'pageTitle' => 'Add Maintenance',
            'is_edit' => false,
            'projects' => $this->serializeProjects($projects),
            'sales_reps' => $this->serializeSalesReps($salesReps),
            'form' => [
                'action' => route('admin.project-maintenances.store'),
                'method' => 'POST',
                'fields' => $this->maintenanceFormFields(
                    null,
                    $request->integer('project_id') ?: null,
                    $salesReps
                ),
            ],
            'routes' => [
                'index' => route('admin.project-maintenances.index'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'start_date' => ['required', 'date'],
            'auto_invoice' => ['nullable', 'boolean'],
            'sales_rep_visible' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:active,paused,cancelled'],
            'sales_rep_ids' => ['nullable', 'array'],
            'sales_rep_ids.*' => ['exists:sales_representatives,id'],
            'sales_rep_amounts' => ['nullable', 'array'],
            'sales_rep_amounts.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $project = Project::query()->findOrFail($data['project_id']);
        $currency = $project->currency ?: strtoupper((string) Setting::getValue('currency'));

        $maintenance = ProjectMaintenance::create([
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'title' => $data['title'],
            'amount' => $data['amount'],
            'currency' => $currency,
            'billing_cycle' => $data['billing_cycle'],
            'start_date' => $data['start_date'],
            'next_billing_date' => $data['start_date'],
            'status' => $data['status'] ?? 'active',
            'auto_invoice' => (bool) ($data['auto_invoice'] ?? true),
            'sales_rep_visible' => (bool) ($data['sales_rep_visible'] ?? false),
            'created_by' => $request->user()?->id,
        ]);

        $salesRepSync = [];
        foreach ($data['sales_rep_ids'] ?? [] as $repId) {
            $amount = (float) ($data['sales_rep_amounts'][$repId] ?? 0);
            $salesRepSync[(int) $repId] = ['amount' => $amount];
        }
        if (! empty($salesRepSync)) {
            $maintenance->salesRepresentatives()->sync($salesRepSync);
        }

        $invoice = $this->maintenanceBillingService->createInvoiceForMaintenance($maintenance);
        $statusMessage = $invoice
            ? sprintf('Maintenance plan created. Invoice #%s generated.', $invoice->number ?? $invoice->id)
            : 'Maintenance plan created.';

        return redirect()
            ->route('admin.project-maintenances.index')
            ->with('status', $statusMessage);
    }

    public function edit(ProjectMaintenance $projectMaintenance): InertiaResponse
    {
        $projectMaintenance->load([
            'project:id,name,currency',
            'customer:id,name',
            'invoices' => fn ($query) => $query->latest('issue_date'),
            'salesRepresentatives:id,name,email,status',
        ]);

        $salesReps = SalesRepresentative::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'status']);

        return Inertia::render('Admin/ProjectMaintenances/Form', [
            'pageTitle' => 'Edit Maintenance',
            'is_edit' => true,
            'maintenance' => $this->serializeMaintenanceDetail($projectMaintenance),
            'sales_reps' => $this->serializeSalesReps($salesReps),
            'invoices' => $this->serializeInvoices($projectMaintenance->invoices),
            'form' => [
                'action' => route('admin.project-maintenances.update', $projectMaintenance),
                'method' => 'PATCH',
                'fields' => $this->maintenanceFormFields(
                    $projectMaintenance,
                    null,
                    $salesReps
                ),
            ],
            'routes' => [
                'index' => route('admin.project-maintenances.index'),
            ],
        ]);
    }

    public function show(ProjectMaintenance $projectMaintenance): InertiaResponse
    {
        $projectMaintenance->load([
            'project:id,name,currency',
            'customer:id,name',
            'creator:id,name',
            'invoices' => fn ($query) => $query->latest('issue_date'),
        ]);

        return Inertia::render('Admin/ProjectMaintenances/Show', [
            'pageTitle' => 'Maintenance #'.$projectMaintenance->id,
            'maintenance' => $this->serializeMaintenanceDetail($projectMaintenance),
            'invoices' => $this->serializeInvoices($projectMaintenance->invoices),
        ]);
    }

    public function update(Request $request, ProjectMaintenance $projectMaintenance): RedirectResponse
    {
        if ($request->boolean('quick_status')) {
            $data = $request->validate([
                'status' => ['required', 'in:active,paused,cancelled'],
            ]);

            if ($projectMaintenance->status === 'cancelled' && $data['status'] !== 'cancelled') {
                return back()
                    ->withErrors(['status' => 'Cancelled maintenance plans cannot be reactivated.']);
            }

            $projectMaintenance->update([
                'status' => $data['status'],
            ]);

            return back()->with('status', 'Maintenance status updated.');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'start_date' => ['required', 'date'],
            'auto_invoice' => ['nullable', 'boolean'],
            'sales_rep_visible' => ['nullable', 'boolean'],
            'status' => ['required', 'in:active,paused,cancelled'],
            'sales_rep_ids' => ['nullable', 'array'],
            'sales_rep_ids.*' => ['exists:sales_representatives,id'],
            'sales_rep_amounts' => ['nullable', 'array'],
            'sales_rep_amounts.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($projectMaintenance->status === 'cancelled' && $data['status'] !== 'cancelled') {
            return back()
                ->withErrors(['status' => 'Cancelled maintenance plans cannot be reactivated.'])
                ->withInput();
        }

        $nextBillingDate = $projectMaintenance->next_billing_date;
        if (
            $projectMaintenance->last_billed_at === null &&
            ($data['start_date'] !== $projectMaintenance->start_date?->toDateString()
                || $data['billing_cycle'] !== $projectMaintenance->billing_cycle)
        ) {
            $nextBillingDate = $data['start_date'];
        }

        $projectMaintenance->update([
            'title' => $data['title'],
            'amount' => $data['amount'],
            'billing_cycle' => $data['billing_cycle'],
            'start_date' => $data['start_date'],
            'next_billing_date' => $nextBillingDate,
            'status' => $data['status'],
            'auto_invoice' => (bool) ($data['auto_invoice'] ?? false),
            'sales_rep_visible' => (bool) ($data['sales_rep_visible'] ?? false),
        ]);

        $salesRepSync = [];
        foreach ($data['sales_rep_ids'] ?? [] as $repId) {
            $amount = (float) ($data['sales_rep_amounts'][$repId] ?? 0);
            $salesRepSync[(int) $repId] = ['amount' => $amount];
        }
        $projectMaintenance->salesRepresentatives()->sync($salesRepSync);

        return redirect()
            ->route('admin.project-maintenances.edit', $projectMaintenance)
            ->with('status', 'Maintenance plan updated.');
    }

    private function serializeMaintenanceListItem(ProjectMaintenance $maintenance): array
    {
        return [
            'id' => $maintenance->id,
            'project_name' => $maintenance->project?->name,
            'project_route' => $maintenance->project ? route('admin.projects.show', $maintenance->project) : null,
            'customer_name' => $maintenance->customer?->name,
            'customer_route' => $maintenance->customer ? route('admin.customers.show', $maintenance->customer) : null,
            'title' => $maintenance->title,
            'billing_cycle_label' => ucfirst((string) $maintenance->billing_cycle),
            'next_billing_date' => $this->displayDate($maintenance->next_billing_date),
            'status' => $maintenance->status,
            'status_label' => ucfirst((string) $maintenance->status),
            'amount_display' => sprintf(
                '%s %s',
                (string) $maintenance->currency,
                number_format((float) $maintenance->amount, 2)
            ),
            'can_pause' => $maintenance->status === 'active',
            'can_resume' => $maintenance->status === 'paused',
            'can_cancel' => $maintenance->status !== 'cancelled',
            'routes' => [
                'show' => route('admin.project-maintenances.show', $maintenance),
                'edit' => route('admin.project-maintenances.edit', $maintenance),
                'update' => route('admin.project-maintenances.update', $maintenance),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMaintenanceDetail(ProjectMaintenance $maintenance): array
    {
        return [
            'id' => $maintenance->id,
            'title' => $maintenance->title,
            'status' => $maintenance->status,
            'status_label' => ucfirst((string) $maintenance->status),
            'amount_display' => sprintf(
                '%s %s',
                (string) $maintenance->currency,
                number_format((float) $maintenance->amount, 2)
            ),
            'amount' => (float) $maintenance->amount,
            'currency' => $maintenance->currency,
            'billing_cycle' => $maintenance->billing_cycle,
            'billing_cycle_label' => ucfirst((string) $maintenance->billing_cycle),
            'next_billing_date' => $this->displayDate($maintenance->next_billing_date),
            'start_date' => $this->displayDate($maintenance->start_date),
            'last_billed_at' => $this->displayDateTime($maintenance->last_billed_at),
            'auto_invoice' => (bool) $maintenance->auto_invoice,
            'sales_rep_visible' => (bool) $maintenance->sales_rep_visible,
            'project_id' => $maintenance->project_id,
            'project_name' => $maintenance->project?->name,
            'project_route' => $maintenance->project ? route('admin.projects.show', $maintenance->project_id) : null,
            'customer_name' => $maintenance->customer?->name,
            'customer_route' => $maintenance->customer ? route('admin.customers.show', $maintenance->customer_id) : null,
            'creator_name' => $maintenance->creator?->name,
            'routes' => [
                'index' => route('admin.project-maintenances.index'),
                'edit' => route('admin.project-maintenances.edit', $maintenance),
                'invoices' => route('admin.invoices.index', ['maintenance_id' => $maintenance->id]),
            ],
        ];
    }

    /**
     * @return array<int, array{id:int,name:string,customer_name:string,currency:string}>
     */
    private function serializeProjects(Collection $projects): array
    {
        return $projects->map(fn (Project $project) => [
            'id' => $project->id,
            'name' => (string) $project->name,
            'customer_name' => (string) ($project->customer?->name ?? 'No customer'),
            'currency' => (string) ($project->currency ?? ''),
        ])->values()->all();
    }

    /**
     * @return array<int, array{id:int,name:string,email:string,status:string}>
     */
    private function serializeSalesReps(Collection $salesReps): array
    {
        return $salesReps->map(fn (SalesRepresentative $salesRep) => [
            'id' => $salesRep->id,
            'name' => (string) $salesRep->name,
            'email' => (string) ($salesRep->email ?? ''),
            'status' => (string) $salesRep->status,
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function paginationPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'previous_url' => $paginator->previousPageUrl(),
            'next_url' => $paginator->nextPageUrl(),
            'has_pages' => $paginator->hasPages(),
        ];
    }

    /**
     * @return array<int, array{id:int,number:string,issue_date:string,due_date:string,status:string,status_label:string,total_display:string,routes:array{show:string,mark_paid:string}}>
     */
    private function serializeInvoices(Collection $invoices): array
    {
        return $invoices->map(function ($invoice): array {
            $number = $invoice->number ?? $invoice->id;

            return [
                'id' => (int) $invoice->id,
                'number' => (string) $number,
                'issue_date' => $this->displayDate($invoice->issue_date),
                'due_date' => $this->displayDate($invoice->due_date),
                'status' => (string) $invoice->status,
                'status_label' => ucfirst((string) $invoice->status),
                'total_display' => sprintf(
                    '%s %s',
                    (string) $invoice->currency,
                    number_format((float) $invoice->total, 2)
                ),
                'routes' => [
                    'show' => route('admin.invoices.show', $invoice),
                    'mark_paid' => route('admin.invoices.mark-paid', $invoice),
                ],
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function maintenanceFormFields(
        ?ProjectMaintenance $maintenance,
        ?int $selectedProjectId,
        Collection $salesReps
    ): array {
        $selectedSalesRepIds = collect(old(
            'sales_rep_ids',
            $maintenance?->salesRepresentatives->pluck('id')->all() ?? []
        ))
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();

        $defaultAmounts = [];
        if ($maintenance) {
            foreach ($maintenance->salesRepresentatives as $salesRepresentative) {
                $defaultAmounts[(int) $salesRepresentative->id] = (string) (float) ($salesRepresentative->pivot?->amount ?? 0);
            }
        }

        $oldAmounts = old('sales_rep_amounts', $defaultAmounts);
        $salesRepAmounts = [];
        foreach ($salesReps as $salesRep) {
            $salesRepAmounts[(int) $salesRep->id] = (string) (float) ($oldAmounts[$salesRep->id] ?? 0);
        }

        return [
            'project_id' => (string) old('project_id', $selectedProjectId ?? $maintenance?->project_id ?? ''),
            'title' => (string) old('title', $maintenance?->title ?? ''),
            'amount' => (string) old('amount', $maintenance ? (string) (float) $maintenance->amount : ''),
            'billing_cycle' => (string) old('billing_cycle', $maintenance?->billing_cycle ?? 'monthly'),
            'start_date' => (string) old(
                'start_date',
                $maintenance?->start_date?->format(config('app.date_format', 'd-m-Y')) ?? ''
            ),
            'status' => (string) old('status', $maintenance?->status ?? 'active'),
            'auto_invoice' => (string) old(
                'auto_invoice',
                $maintenance ? ($maintenance->auto_invoice ? '1' : '0') : '1'
            ) === '1',
            'sales_rep_visible' => (string) old(
                'sales_rep_visible',
                $maintenance ? ($maintenance->sales_rep_visible ? '1' : '0') : '0'
            ) === '1',
            'selected_sales_rep_ids' => $selectedSalesRepIds,
            'sales_rep_amounts' => $salesRepAmounts,
        ];
    }

    private function displayDate(Carbon|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '--';
        }

        return Carbon::parse($value)->format((string) config('app.date_format', 'd-m-Y'));
    }

    private function displayDateTime(Carbon|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '--';
        }

        return Carbon::parse($value)->format((string) config('app.datetime_format', 'd-m-Y h:i A'));
    }
}
