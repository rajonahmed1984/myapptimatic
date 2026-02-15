<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectMaintenance;
use App\Models\PaymentGateway;
use App\Support\SystemLogger;
use App\Services\BillingService;
use App\Services\AdminNotificationService;
use App\Services\CommissionService;
use App\Services\InvoiceTaxService;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    public function projectInvoices(Project $project)
    {
        return $this->listByStatus(null, 'Project Invoices: '.$project->name, $project);
    }

    public function create(Request $request)
    {
        $customers = Customer::query()
            ->orderBy('name')
            ->get(['id', 'name', 'company_name', 'email']);

        $selectedCustomerId = $request->query('customer_id');
        $issueDate = now()->toDateString();
        $dueDays = (int) Setting::getValue('invoice_due_days', 0);
        $dueDate = $dueDays > 0 ? now()->addDays($dueDays)->toDateString() : $issueDate;

        return view('admin.invoices.create', [
            'customers' => $customers,
            'selectedCustomerId' => $selectedCustomerId,
            'issueDate' => $issueDate,
            'dueDate' => $dueDate,
        ]);
    }

    public function store(Request $request, InvoiceTaxService $taxService): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $items = collect($data['items'])
            ->map(function ($item) {
                $quantity = (int) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                return [
                    'description' => $item['description'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($quantity * $unitPrice, 2),
                ];
            })
            ->values();

        $subtotal = (float) $items->sum('line_total');
        if ($subtotal <= 0) {
            return back()->withErrors(['items' => 'Invoice total must be greater than zero.'])->withInput();
        }

        $issueDate = Carbon::parse($data['issue_date']);
        $taxData = $taxService->calculateTotals($subtotal, 0.0, $issueDate);
        $currency = strtoupper((string) Setting::getValue('currency'));

        $invoice = Invoice::create([
            'customer_id' => $data['customer_id'],
            'number' => $this->billingService->nextInvoiceNumber(),
            'status' => 'unpaid',
            'issue_date' => $data['issue_date'],
            'due_date' => $data['due_date'],
            'subtotal' => $subtotal,
            'tax_rate_percent' => $taxData['tax_rate_percent'],
            'tax_mode' => $taxData['tax_mode'],
            'tax_amount' => $taxData['tax_amount'],
            'late_fee' => 0,
            'total' => $taxData['total'],
            'currency' => $currency,
            'notes' => $data['notes'] ?? null,
            'type' => 'manual',
        ]);

        foreach ($items as $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $item['line_total'],
            ]);
        }

        SystemLogger::write('activity', 'Manual invoice created.', [
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'total' => $invoice->total,
            'currency' => $invoice->currency,
        ], $request->user()?->id, $request->ip());

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('status', 'Invoice created.');
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

    public function clientView(Invoice $invoice)
    {
        return view('client.invoices.pay', [
            'invoice' => $invoice->load([
                'items',
                'customer',
                'subscription.plan.product',
                'accountingEntries.paymentGateway',
                'paymentProofs.paymentGateway',
                'paymentProofs.paymentAttempt',
            ]),
            'paymentInstructions' => Setting::getValue('payment_instructions'),
            'gateways' => PaymentGateway::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'payToText' => Setting::getValue('pay_to_text'),
            'companyEmail' => Setting::getValue('company_email'),
        ]);
    }

    public function download(Invoice $invoice): Response
    {
        $invoice->load(['items', 'customer', 'subscription.plan.product', 'accountingEntries.paymentGateway']);

        $html = view('client.invoices.pdf', [
            'invoice' => $invoice,
            'payToText' => Setting::getValue('pay_to_text'),
            'companyEmail' => Setting::getValue('company_email'),
        ])->render();

        $pdf = app('dompdf.wrapper')->loadHTML($html);

        return $pdf->download('invoice-'.($invoice->number ?? $invoice->id).'.pdf');
    }

    public function markPaid(
        Request $request,
        Invoice $invoice,
        AdminNotificationService $adminNotifications,
        CommissionService $commissionService
    )
    {
        $wasPaid = $invoice->status === 'paid';
        $previousStatus = $invoice->status;
        $invoice->update([
            'status' => 'paid',
            'paid_at' => Carbon::now(),
        ]);

        \App\Models\StatusAuditLog::logChange(
            Invoice::class,
            $invoice->id,
            $previousStatus,
            'paid',
            'manual_mark_paid',
            $request->user()?->id
        );

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

        // Commission earning creation (idempotent).
        try {
            $commissionService->createOrUpdateEarningOnInvoicePaid($invoice->fresh('subscription.customer'));
        } catch (\Throwable $e) {
            SystemLogger::write('module', 'Commission earning failed on manual mark paid.', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ], level: 'error');
        }

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

    public function update(
        Request $request,
        Invoice $invoice,
        AdminNotificationService $adminNotifications,
        CommissionService $commissionService
    ): RedirectResponse
    {
        $wasPaid = $invoice->status === 'paid';
        $previousStatus = $invoice->status;
        $data = $request->validate([
            'status' => ['required', Rule::in(['unpaid', 'overdue', 'paid', 'cancelled', 'refunded'])],
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

        \App\Models\StatusAuditLog::logChange(
            Invoice::class,
            $invoice->id,
            $previousStatus,
            $data['status'],
            'admin_update',
            $request->user()?->id
        );

        // Commission creation or reversal based on status transitions.
        try {
            if (! $wasPaid && $data['status'] === 'paid') {
                $commissionService->createOrUpdateEarningOnInvoicePaid($invoice->fresh('subscription.customer'));
            }

            if (in_array($data['status'], ['cancelled', 'refunded'], true)) {
                $commissionService->reverseEarningsOnRefund($invoice);
            }
        } catch (\Throwable $e) {
            SystemLogger::write('module', 'Commission update failed on invoice admin update.', [
                'invoice_id' => $invoice->id,
                'from_status' => $previousStatus,
                'to_status' => $data['status'],
                'error' => $e->getMessage(),
            ], level: 'error');
        }

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

    private function listByStatus(?string $status, string $title, ?Project $project = null)
    {
        $search = trim((string) request()->query('search', ''));

        $query = Invoice::query()
            ->with(['customer', 'paymentProofs', 'subscription.plan.product', 'maintenance.project', 'accountingEntries'])
            ->latest('issue_date');

        if ($project) {
            $query->where('project_id', $project->id);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('number', 'like', '%' . $search . '%')
                    ->orWhere('status', 'like', '%' . $search . '%')
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('subscription.plan', function ($planQuery) use ($search) {
                        $planQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhereHas('product', function ($productQuery) use ($search) {
                                $productQuery->where('name', 'like', '%' . $search . '%');
                            });
                    })
                    ->orWhereHas('maintenance', function ($maintenanceQuery) use ($search) {
                        $maintenanceQuery->where('title', 'like', '%' . $search . '%');
                    });

                if (is_numeric($search)) {
                    $inner->orWhere('id', (int) $search);
                }
            });
        }

        $payload = [
            'invoices' => $query->paginate(25)->withQueryString(),
            'title' => $title,
            'statusFilter' => $status,
            'search' => $search,
            'project' => $project,
        ];

        if (request()->header('HX-Request')) {
            return view('admin.invoices.partials.table', $payload);
        }

        return view('admin.invoices.index', $payload);
    }
}
