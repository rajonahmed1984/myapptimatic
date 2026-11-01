<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\ProjectOverhead;
use App\Models\Setting;
use App\Services\BillingService;
use App\Services\InvoiceVatService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectOverheadController extends Controller
{
    public function index(Project $project): InertiaResponse
    {
        $this->authorize('view', $project);

        $overheads = $project->overheads()->with('invoice')->latest('created_at')->get();

        $unpaidCount = $overheads->filter(fn (ProjectOverhead $overhead) => ! $overhead->invoice)->count();

        return Inertia::render('Admin/Projects/Overheads/Index', [
            'pageTitle' => 'Overhead fees',
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status_label' => ucfirst(str_replace('_', ' ', (string) $project->status)),
                'currency' => $project->currency,
                'remaining_budget_display' => $project->remaining_budget !== null
                    ? $project->currency.' '.number_format((float) $project->remaining_budget, 2)
                    : '--',
            ],
            'overheads' => $overheads->map(function (ProjectOverhead $overhead) use ($project) {
                $invoice = $overhead->invoice;
                $status = 'Unpaid';
                if (! $invoice) {
                    $status = 'Not invoiced';
                } elseif ($invoice->status === 'paid') {
                    $status = 'Paid';
                } elseif ($invoice->status === 'cancelled') {
                    $status = 'Cancelled';
                }

                return [
                    'id' => $overhead->id,
                    'invoice_number' => $invoice ? '#'.($invoice->number ?? $invoice->id) : '--',
                    'invoice_show_route' => $invoice ? route('admin.invoices.show', ['invoice' => $invoice->id]) : null,
                    'details' => $overhead->short_details,
                    'amount_display' => $project->currency.' '.number_format((float) $overhead->amount, 2),
                    'date' => $overhead->created_at?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                    'status_label' => $status,
                ];
            })->values(),
            'unpaid_count' => $unpaidCount,
            'routes' => [
                'project_show' => route('admin.projects.show', $project),
                'store' => route('admin.projects.overheads.store', $project),
                'invoice_pending' => route('admin.projects.overheads.invoice', $project),
            ],
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('view', $project);

        $validated = $request->validate([
            'short_details' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $project->overheads()->create($validated);

        return redirect()->route('admin.projects.overheads.index', $project)
            ->with('success', 'Overhead added successfully.');
    }

    public function destroy(Project $project, ProjectOverhead $overhead): RedirectResponse
    {
        $this->authorize('view', $project);

        if ($overhead->project_id !== $project->id) {
            abort(404);
        }

        $overhead->delete();

        return redirect()->route('admin.projects.overheads.index', $project)
            ->with('success', 'Overhead deleted successfully.');
    }

    public function invoicePending(Request $request, Project $project, BillingService $billingService, InvoiceVatService $vatService): RedirectResponse
    {
        $this->authorize('view', $project);

        $pending = $project->overheads()->whereDoesntHave('invoice')->get();
        $subtotal = (float) $pending->sum(fn ($o) => (float) ($o->amount ?? 0));

        if ($pending->isEmpty() || $subtotal <= 0) {
            return redirect()->route('admin.projects.overheads.index', $project)
                ->with('status', 'No pending overheads to invoice.');
        }

        if ($subtotal > $this->unbilledAmount($project)) {
            return redirect()->route('admin.projects.overheads.index', $project)
                ->with('error', 'No invoice created: the project budget and overheads are already fully billed, so these fees would be charged twice.');
        }

        $issueDate = Carbon::today();
        $dueDays = (int) Setting::getValue('invoice_due_days');
        $dueDate = $issueDate->copy()->addDays(max(0, $dueDays));

        $taxData = $vatService->calculateTotals($subtotal, 0.0, $issueDate);

        $invoice = Invoice::create([
            'customer_id' => $project->customer_id,
            'project_id' => $project->id,
            'number' => $billingService->nextInvoiceNumber(),
            'status' => 'unpaid',
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'subtotal' => $subtotal,
            'tax_rate_percent' => $taxData['tax_rate_percent'],
            'tax_mode' => $taxData['tax_mode'],
            'tax_amount' => $taxData['tax_amount'],
            'late_fee' => 0,
            'total' => $taxData['total'],
            'currency' => $project->currency,
            'type' => 'project_overhead',
        ]);

        foreach ($pending as $overhead) {
            $amount = (float) ($overhead->amount ?? 0);
            if ($amount <= 0) {
                continue;
            }

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => sprintf('Overhead: %s', $overhead->short_details ?: 'Overhead fee'),
                'quantity' => 1,
                'unit_price' => $amount,
                'line_total' => $amount,
            ]);

            $overhead->update(['invoice_id' => $invoice->id]);
        }

        return redirect()->route('admin.projects.overheads.index', $project)
            ->with('success', 'Invoice created for pending overheads.');
    }

    public function invoiceOne(Project $project, ProjectOverhead $overhead, BillingService $billingService, InvoiceVatService $vatService): RedirectResponse
    {
        $this->authorize('view', $project);

        if ($overhead->project_id !== $project->id) {
            abort(404);
        }

        $result = DB::transaction(function () use ($project, $overhead, $billingService, $vatService): array {
            $lockedOverhead = $project->overheads()
                ->whereKey($overhead->id)
                ->lockForUpdate()
                ->firstOrFail();

            // An invoice_id can outlive its invoice, which then counts as not invoiced.
            if ($lockedOverhead->invoice) {
                return ['invoice' => $lockedOverhead->invoice, 'created' => false];
            }

            $subtotal = (float) ($lockedOverhead->amount ?? 0);
            abort_if($subtotal <= 0, 422, 'The overhead amount must be greater than zero.');

            if ($subtotal > $this->unbilledAmount($project)) {
                return ['invoice' => null, 'created' => false, 'already_billed' => true];
            }

            $issueDate = Carbon::today();
            $dueDays = (int) Setting::getValue('invoice_due_days');
            $dueDate = $issueDate->copy()->addDays(max(0, $dueDays));
            $taxData = $vatService->calculateTotals($subtotal, 0.0, $issueDate);

            $invoice = Invoice::create([
                'customer_id' => $project->customer_id,
                'project_id' => $project->id,
                'number' => $billingService->nextInvoiceNumber(),
                'status' => 'unpaid',
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'subtotal' => $subtotal,
                'tax_rate_percent' => $taxData['tax_rate_percent'],
                'tax_mode' => $taxData['tax_mode'],
                'tax_amount' => $taxData['tax_amount'],
                'late_fee' => 0,
                'total' => $taxData['total'],
                'currency' => $project->currency,
                'type' => 'project_overhead',
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => sprintf('Overhead: %s', $lockedOverhead->short_details ?: 'Overhead fee'),
                'quantity' => 1,
                'unit_price' => $subtotal,
                'line_total' => $subtotal,
            ]);

            $lockedOverhead->update(['invoice_id' => $invoice->id]);

            return ['invoice' => $invoice, 'created' => true];
        });

        if ($result['already_billed'] ?? false) {
            return redirect()->route('admin.projects.show', $project)
                ->with('error', 'No invoice created: the project budget and overheads are already fully billed, so this fee would be charged twice.');
        }

        $message = $result['created']
            ? 'Invoice created for overhead fee.'
            : 'This overhead fee already has an invoice.';

        return redirect()->route('admin.projects.show', $project)->with('success', $message);
    }

    /**
     * Budget plus overheads that no paid or open invoice has billed yet.
     */
    private function unbilledAmount(Project $project): float
    {
        $billed = (float) $project->invoices()
            ->whereIn('type', ['project_initial_payment', 'project_remaining_budget', 'project_overhead'])
            ->whereIn('status', ['paid', 'unpaid', 'overdue'])
            ->sum('subtotal');

        return round((float) ($project->total_budget ?? 0) + (float) $project->overhead_total - $billed, 2);
    }
}
