<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMaintenance;
use App\Models\SalesRepresentative;
use App\Models\Setting;
use App\Services\MaintenanceBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectMaintenanceController extends Controller
{
    public function __construct(
        private MaintenanceBillingService $maintenanceBillingService
    )
    {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));

        $maintenances = ProjectMaintenance::query()
            ->with(['project:id,name,currency', 'customer:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', '%' . $search . '%')
                        ->orWhere('billing_cycle', 'like', '%' . $search . '%')
                        ->orWhere('status', 'like', '%' . $search . '%')
                        ->orWhereHas('project', function ($projectQuery) use ($search) {
                            $projectQuery->where('name', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', '%' . $search . '%');
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

        return view('admin.project-maintenances.index', $payload);
    }

    public function create(Request $request): View
    {
        $projects = Project::query()
            ->with('customer:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id', 'currency']);

        return view('admin.project-maintenances.create', [
            'projects' => $projects,
            'selectedProjectId' => $request->integer('project_id') ?: null,
            'salesReps' => SalesRepresentative::where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'status']),
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

    public function edit(ProjectMaintenance $projectMaintenance): View
    {
        $projectMaintenance->load([
            'project:id,name,currency',
            'customer:id,name',
            'invoices' => fn ($query) => $query->latest('issue_date'),
            'salesRepresentatives:id,name,email,status',
        ]);

        return view('admin.project-maintenances.edit', [
            'maintenance' => $projectMaintenance,
            'salesReps' => SalesRepresentative::where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'status']),
        ]);
    }

    public function show(ProjectMaintenance $projectMaintenance): View
    {
        $projectMaintenance->load([
            'project:id,name,currency',
            'customer:id,name',
            'creator:id,name',
            'invoices' => fn ($query) => $query->latest('issue_date'),
        ]);

        return view('admin.project-maintenances.show', [
            'maintenance' => $projectMaintenance,
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
}
