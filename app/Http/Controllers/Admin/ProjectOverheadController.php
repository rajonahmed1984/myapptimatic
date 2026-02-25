<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\ProjectOverhead;
use App\Models\Setting;
use App\Services\BillingService;
use App\Services\InvoiceTaxService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectOverheadController extends Controller
{
    public function index(Project $project): InertiaResponse
    {
        $this->authorize('view', $project);

        $overheads = $project->overheads()->with('invoice')->latest('created_at')->get();

        $unpaidCount = $overheads->whereNull('invoice_id')->count();

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

    public function invoicePending(Request $request, Project $project, BillingService $billingService, InvoiceTaxService $taxService): RedirectResponse
    {
        $this->authorize('view', $project);

        $pending = $project->overheads()->whereNull('invoice_id')->get();
        $subtotal = (float) $pending->sum(fn ($o) => (float) ($o->amount ?? 0));

        if ($pending->isEmpty() || $subtotal <= 0) {
            return redirect()->route('admin.projects.overheads.index', $project)
                ->with('status', 'No pending overheads to invoice.');
        }

        $issueDate = Carbon::today();
        $dueDays = (int) Setting::getValue('invoice_due_days');
        $dueDate = $issueDate->copy()->addDays(max(0, $dueDays));

        $taxData = $taxService->calculateTotals($subtotal, 0.0, $issueDate);

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
}
