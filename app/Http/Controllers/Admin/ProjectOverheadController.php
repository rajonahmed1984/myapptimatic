<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectOverhead;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;
use App\Services\BillingService;

class ProjectOverheadController extends Controller
{
    public function index(Project $project)
    {
        $this->authorize('view', $project);

        $overheads = $project->overheads()->with('invoice')->latest('created_at')->get();

        return view('admin.projects.overheads.index', [
            'project' => $project,
            'overheads' => $overheads,
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

    public function invoicePending(Request $request, Project $project, BillingService $billingService): RedirectResponse
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

        $invoice = Invoice::create([
            'customer_id' => $project->customer_id,
            'project_id' => $project->id,
            'number' => $billingService->nextInvoiceNumber(),
            'status' => 'unpaid',
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'subtotal' => $subtotal,
            'late_fee' => 0,
            'total' => $subtotal,
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
