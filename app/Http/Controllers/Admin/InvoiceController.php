<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\SystemLogger;
use App\Services\BillingService;
use App\Services\AdminNotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function __construct(private BillingService $billingService)
    {
    }

    public function index()
    {
        return $this->listByStatus(null, 'All Invoices');
    }

    public function paid()
    {
        return $this->listByStatus('paid', 'Paid Invoices');
    }

    public function unpaid()
    {
        return $this->listByStatus('unpaid', 'Unpaid Invoices');
    }

    public function overdue()
    {
        return $this->listByStatus('overdue', 'Overdue Invoices');
    }

    public function cancelled()
    {
        return $this->listByStatus('cancelled', 'Cancelled Invoices');
    }

    public function refunded()
    {
        return $this->listByStatus('refunded', 'Refunded Invoices');
    }

    public function show(Invoice $invoice)
    {
        return view('admin.invoices.show', [
            'invoice' => $invoice->load([
                'customer',
                'items',
                'accountingEntries.paymentGateway',
                'paymentProofs.paymentGateway',
                'paymentProofs.reviewer',
            ]),
        ]);
    }

    public function markPaid(Request $request, Invoice $invoice, AdminNotificationService $adminNotifications)
    {
        $wasPaid = $invoice->status === 'paid';
        $invoice->update([
            'status' => 'paid',
            'paid_at' => Carbon::now(),
        ]);

        if (! $wasPaid) {
            $adminNotifications->sendInvoicePaid($invoice->fresh('customer'));

            // Check if customer has any remaining unpaid/overdue invoices
            // If not, clear the billing block
            $hasUnpaidInvoices = Invoice::query()
                ->where('customer_id', $invoice->customer_id)
                ->whereIn('status', ['unpaid', 'overdue'])
                ->exists();

            if (! $hasUnpaidInvoices) {
                // Customer has no more unpaid invoices, restore access immediately
                \App\Models\Customer::query()
                    ->where('id', $invoice->customer_id)
                    ->update(['access_override_until' => null]);
            }
        }

        SystemLogger::write('activity', 'Invoice marked as paid.', [
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
        ], $request->user()?->id, $request->ip());

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('status', 'Invoice marked as paid.');
    }

    public function recalculate(Invoice $invoice): RedirectResponse
    {
        if (! in_array($invoice->status, ['unpaid', 'overdue'], true)) {
            return redirect()->route('admin.invoices.show', $invoice)
                ->with('status', 'Only unpaid or overdue invoices can be recalculated.');
        }

        $this->billingService->recalculateInvoice($invoice);

        SystemLogger::write('activity', 'Invoice recalculated.', [
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
        ]);

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('status', 'Invoice recalculated.');
    }

    public function update(Request $request, Invoice $invoice, AdminNotificationService $adminNotifications): RedirectResponse
    {
        $wasPaid = $invoice->status === 'paid';
        $previousStatus = $invoice->status;
        $data = $request->validate([
            'status' => ['required', Rule::in(['unpaid', 'overdue', 'paid', 'cancelled'])],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string'],
        ]);

        $updates = [
            'status' => $data['status'],
            'issue_date' => $data['issue_date'],
            'due_date' => $data['due_date'],
            'notes' => $data['notes'] ?? null,
        ];

        if ($data['status'] === 'paid') {
            $updates['paid_at'] = $invoice->paid_at ?? Carbon::now();
        } else {
            $updates['paid_at'] = null;
        }

        if ($data['status'] === 'overdue') {
            $updates['overdue_at'] = $invoice->overdue_at ?? Carbon::now();
        } else {
            $updates['overdue_at'] = null;
        }

        $invoice->update($updates);

        if (! $wasPaid && $data['status'] === 'paid') {
            $adminNotifications->sendInvoicePaid($invoice->fresh('customer'));

            // Check if customer has any remaining unpaid/overdue invoices
            // If not, clear the billing block
            $hasUnpaidInvoices = Invoice::query()
                ->where('customer_id', $invoice->customer_id)
                ->whereIn('status', ['unpaid', 'overdue'])
                ->exists();

            if (! $hasUnpaidInvoices) {
                // Customer has no more unpaid invoices, restore access immediately
                \App\Models\Customer::query()
                    ->where('id', $invoice->customer_id)
                    ->update(['access_override_until' => null]);
            }
        }

        SystemLogger::write('activity', 'Invoice updated.', [
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'from_status' => $previousStatus,
            'to_status' => $data['status'],
        ], $request->user()?->id, $request->ip());

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('status', 'Invoice updated.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        SystemLogger::write('activity', 'Invoice deleted.', [
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'status' => $invoice->status,
        ]);

        $invoice->delete();

        return redirect()->route('admin.invoices.index')
            ->with('status', 'Invoice deleted.');
    }

    private function listByStatus(?string $status, string $title)
    {
        $query = Invoice::query()
            ->with(['customer', 'paymentProofs'])
            ->latest('issue_date');

        if ($status) {
            $query->where('status', $status);
        }

        return view('admin.invoices.index', [
            'invoices' => $query->get(),
            'title' => $title,
            'statusFilter' => $status,
        ]);
    }
}
