<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingEntry;
use App\Models\CommissionAuditLog;
use App\Models\CommissionPayout;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Models\Setting;
use App\Models\TaxSetting;
use App\Services\AdminNotificationService;
use App\Services\BillingService;
use App\Services\ClientNotificationService;
use App\Services\CommissionService;
use App\Services\InvoiceTaxService;
use App\Services\SalesRepNotificationService;
use App\Support\AjaxResponse;
use App\Support\Branding;
use App\Support\SystemLogger;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class InvoiceController extends Controller
{
    public function __construct(private BillingService $billingService) {}

    public function index()
    {
        return $this->listByStatus(null, 'All Invoices');
    }

    public function projectInvoices(Project $project)
    {
        return $this->listByStatus(null, 'Project Invoices: '.$project->name, $project);
    }

    public function create(Request $request): InertiaResponse
    {
        $customers = Customer::query()
            ->orderBy('name')
            ->get(['id', 'name', 'company_name', 'email']);

        $selectedCustomerId = $request->query('customer_id');
        $issueDate = now()->toDateString();
        $dueDays = (int) Setting::getValue('invoice_due_days', 0);
        $dueDate = $dueDays > 0 ? now()->addDays($dueDays)->toDateString() : $issueDate;

        return Inertia::render('Admin/Invoices/Create', [
            'pageTitle' => 'Create Invoice',
            'customers' => $customers->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'company_name' => $customer->company_name,
                'email' => $customer->email,
                'label' => $this->customerLabel($customer),
            ])->values(),
            'form' => [
                'action' => route('admin.invoices.store'),
                'method' => 'POST',
                'fields' => [
                    'customer_id' => (string) old('customer_id', (string) ($selectedCustomerId ?? '')),
                    'issue_date' => (string) old('issue_date', $issueDate),
                    'due_date' => (string) old('due_date', $dueDate),
                    'notes' => (string) old('notes', ''),
                    'items' => collect(old('items', [['description' => '', 'quantity' => 1, 'unit_price' => 0]]))
                        ->map(fn ($item) => [
                            'description' => (string) ($item['description'] ?? ''),
                            'quantity' => (int) ($item['quantity'] ?? 1),
                            'unit_price' => (string) ($item['unit_price'] ?? 0),
                        ])
                        ->values(),
                ],
            ],
            'routes' => [
                'index' => route('admin.invoices.index'),
            ],
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

    public function show(Invoice $invoice): InertiaResponse
    {
        $invoice->load([
            'customer',
            'customer.defaultSalesRep:id,name,email,status',
            'items',
            'accountingEntries.paymentGateway',
            'subscription.salesRep:id,name,email,status',
            'orders.salesRep:id,name,email,status',
            'project.salesRepresentatives:id,name,email,status',
            'maintenance.salesRepresentatives:id,name,email,status',
            'maintenance.project.salesRepresentatives:id,name,email,status',
            'paymentProofs.paymentGateway',
            'paymentProofs.reviewer',
        ]);

        return Inertia::render('Admin/Invoices/Show', [
            'pageTitle' => 'Invoice Details',
            'invoice' => $this->serializeInvoiceDetails($invoice),
            'sales_rep_collection_options' => $this->invoiceSalesRepOptions($invoice)
                ->map(fn (SalesRepresentative $salesRep) => [
                    'id' => $salesRep->id,
                    'name' => $salesRep->name,
                    'email' => $salesRep->email,
                    'label' => trim($salesRep->name.' '.($salesRep->email ? '('.$salesRep->email.')' : '')),
                ])
                ->values()
                ->all(),
            'payment_methods' => PaymentMethod::commissionPayoutDropdownOptions()
                ->map(fn ($method) => ['code' => $method->code, 'name' => $method->name])
                ->values()
                ->all(),
            'routes' => [
                'index' => route('admin.invoices.index'),
                'client_view' => route('admin.invoices.client-view', $invoice),
                'download' => route('admin.invoices.download', $invoice),
                'update' => route('admin.invoices.update', $invoice),
                'recalculate' => route('admin.invoices.recalculate', $invoice),
                'collect_by_sales_rep' => route('admin.invoices.collect-by-sales-rep', $invoice),
                'record_payment' => route('admin.accounting.create', [
                    'type' => 'payment',
                    'invoice_id' => $invoice->id,
                    'scope' => 'ledger',
                ]),
                'record_refund' => route('admin.accounting.create', [
                    'type' => 'refund',
                    'invoice_id' => $invoice->id,
                    'scope' => 'ledger',
                ]),
            ],
            'status_options' => ['unpaid', 'overdue', 'paid', 'cancelled', 'refunded'],
        ]);
    }

    public function clientView(Request $request, Invoice $invoice): InertiaResponse
    {
        return Inertia::render('Client/Invoices/Pay', $this->invoicePayProps($request, $invoice, 'admin.invoices.download'));
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
        ClientNotificationService $clientNotifications,
        CommissionService $commissionService,
        SalesRepNotificationService $salesRepNotifications
    ): RedirectResponse|JsonResponse {
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
            $freshInvoice = $invoice->fresh('customer');
            $adminNotifications->sendInvoicePaid($freshInvoice);
            try {
                $clientNotifications->sendInvoicePaymentStatusNotification($freshInvoice, 'paid', null);
            } catch (\Throwable $e) {
                SystemLogger::write('module', 'Client invoice paid notification failed on manual mark paid.', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ], level: 'error');
            }

            try {
                $salesRepNotifications->sendInvoicePaymentStatusToRelatedSalesReps($freshInvoice, 'paid', null);
            } catch (\Throwable $e) {
                SystemLogger::write('module', 'Sales rep invoice paid notification failed on manual mark paid.', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ], level: 'error');
            }

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

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                route('admin.invoices.show', $invoice),
                'Invoice marked as paid.'
            );
        }

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('status', 'Invoice marked as paid.');
    }

    public function collectBySalesRep(
        Request $request,
        Invoice $invoice,
        AdminNotificationService $adminNotifications,
        ClientNotificationService $clientNotifications,
        CommissionService $commissionService,
        SalesRepNotificationService $salesRepNotifications
    ): RedirectResponse|JsonResponse {
        $invoice->loadMissing([
            'customer.defaultSalesRep:id,name,email,status',
            'subscription.salesRep:id,name,email,status',
            'orders.salesRep:id,name,email,status',
            'project.salesRepresentatives:id,name,email,status',
            'maintenance.salesRepresentatives:id,name,email,status',
            'maintenance.project.salesRepresentatives:id,name,email,status',
            'accountingEntries',
        ]);

        if ($invoice->status === 'paid') {
            if (AjaxResponse::ajaxFromRequest($request)) {
                return AjaxResponse::ajaxError('Invoice is already marked as paid.', 422);
            }

            return redirect()->route('admin.invoices.show', $invoice)
                ->with('error', 'Invoice is already marked as paid.');
        }

        if (! Schema::hasColumn('accounting_entries', 'metadata')) {
            if (AjaxResponse::ajaxFromRequest($request)) {
                return AjaxResponse::ajaxError('Collected-by-sales-rep flow requires the latest accounting entry migration.', 422);
            }

            return back()
                ->withErrors(['sales_rep_id' => 'Collected-by-sales-rep flow requires the latest accounting entry migration.'])
                ->withInput();
        }

        $data = $request->validate([
            'sales_rep_id' => ['required', 'integer'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'retained_amount' => ['nullable', 'numeric', 'min:0'],
            'payout_method' => ['nullable', Rule::in(PaymentMethod::allowedCommissionPayoutCodes())],
        ]);

        $salesRep = $this->invoiceSalesRepOptions($invoice)
            ->first(fn (SalesRepresentative $item) => $item->id === (int) $data['sales_rep_id']);

        if (! $salesRep) {
            if (AjaxResponse::ajaxFromRequest($request)) {
                return AjaxResponse::ajaxError('Select a valid sales representative for this invoice.', 422, [
                    'sales_rep_id' => ['Select a valid sales representative for this invoice.'],
                ]);
            }

            return back()
                ->withErrors(['sales_rep_id' => 'Select a valid sales representative for this invoice.'])
                ->withInput();
        }

        $creditTotal = (float) $invoice->accountingEntries->where('type', 'credit')->sum('amount');
        $paymentAmount = round(max(0, (float) $invoice->total - $creditTotal), 2);
        $retainedAmount = round((float) ($data['retained_amount'] ?? 0), 2);
        $reference = trim((string) ($data['reference'] ?? ''));
        $note = trim((string) ($data['note'] ?? ''));
        $payoutMethod = trim((string) ($data['payout_method'] ?? ''));

        if ($paymentAmount <= 0) {
            if (AjaxResponse::ajaxFromRequest($request)) {
                return AjaxResponse::ajaxError('No payable amount remains on this invoice.', 422);
            }

            return back()->withErrors(['sales_rep_id' => 'No payable amount remains on this invoice.'])->withInput();
        }

        if ($retainedAmount > $paymentAmount) {
            if (AjaxResponse::ajaxFromRequest($request)) {
                return AjaxResponse::ajaxError('Retained amount cannot exceed the collected invoice amount.', 422, [
                    'retained_amount' => ['Retained amount cannot exceed the collected invoice amount.'],
                ]);
            }

            return back()
                ->withErrors(['retained_amount' => 'Retained amount cannot exceed the collected invoice amount.'])
                ->withInput();
        }

        if ($retainedAmount > 0 && ! Schema::hasColumn('commission_payouts', 'type')) {
            if (AjaxResponse::ajaxFromRequest($request)) {
                return AjaxResponse::ajaxError('Sales rep retained collection requires the latest commission payout migration.', 422, [
                    'retained_amount' => ['Sales rep retained collection requires the latest commission payout migration.'],
                ]);
            }

            return back()
                ->withErrors(['retained_amount' => 'Sales rep retained collection requires the latest commission payout migration.'])
                ->withInput();
        }

        $invoiceNumber = is_numeric($invoice->number) ? (string) $invoice->number : (string) $invoice->id;
        $previousStatus = (string) $invoice->status;

        DB::transaction(function () use (
            $request,
            $invoice,
            $salesRep,
            $paymentAmount,
            $creditTotal,
            $retainedAmount,
            $reference,
            $note,
            $payoutMethod,
            $invoiceNumber,
            $previousStatus
        ): void {
            AccountingEntry::create([
                'entry_date' => Carbon::today(),
                'type' => 'payment',
                'amount' => $paymentAmount,
                'currency' => (string) $invoice->currency,
                'description' => 'Collected by sales representative: '.$salesRep->name,
                'reference' => $reference !== '' ? $reference : 'sales-rep-collection-'.$invoiceNumber,
                'customer_id' => $invoice->customer_id,
                'invoice_id' => $invoice->id,
                'payment_gateway_id' => null,
                'created_by' => $request->user()?->id,
                'metadata' => [
                    'payment_mode' => 'sales_rep_collection',
                    'collected_by_sales_rep_id' => $salesRep->id,
                    'collected_by_sales_rep_name' => $salesRep->name,
                    'invoice_number' => $invoiceNumber,
                    'invoice_total' => (float) $invoice->total,
                    'credit_total' => $creditTotal,
                    'collected_amount' => $paymentAmount,
                    'retained_amount' => $retainedAmount,
                    'note' => $note !== '' ? $note : null,
                ],
            ]);

            $invoice->update([
                'status' => 'paid',
                'paid_at' => $invoice->paid_at ?? Carbon::now(),
            ]);

            \App\Models\StatusAuditLog::logChange(
                Invoice::class,
                $invoice->id,
                $previousStatus,
                'paid',
                'sales_rep_collection',
                $request->user()?->id,
                [
                    'sales_rep_id' => $salesRep->id,
                    'sales_rep_name' => $salesRep->name,
                    'reference' => $reference !== '' ? $reference : null,
                    'retained_amount' => $retainedAmount,
                ]
            );

            if ($retainedAmount <= 0) {
                return;
            }

            $payoutPayload = [
                'sales_representative_id' => $salesRep->id,
                'type' => 'advance',
                'total_amount' => $retainedAmount,
                'currency' => (string) $invoice->currency,
                'payout_method' => $payoutMethod !== '' ? $payoutMethod : null,
                'reference' => $reference !== '' ? $reference : 'invoice-'.$invoiceNumber.'-collection',
                'note' => $note !== '' ? $note : 'Retained from invoice collection.',
                'status' => 'paid',
                'paid_at' => Carbon::now(),
            ];

            if (Schema::hasColumn('commission_payouts', 'project_id')) {
                $payoutPayload['project_id'] = $invoice->project_id ?: $invoice->maintenance?->project_id;
            }

            $payout = CommissionPayout::create($payoutPayload);

            CommissionAuditLog::create([
                'sales_representative_id' => $salesRep->id,
                'commission_payout_id' => $payout->id,
                'action' => 'advance_payment',
                'status_from' => null,
                'status_to' => 'paid',
                'description' => 'Invoice collection retained by sales representative.',
                'metadata' => [
                    'amount' => $retainedAmount,
                    'currency' => (string) $invoice->currency,
                    'source_type' => 'invoice',
                    'source_id' => $invoice->id,
                    'source_label' => 'Invoice #'.$invoiceNumber.' collection',
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoiceNumber,
                    'invoice_total' => (float) $invoice->total,
                    'collected_amount' => $paymentAmount,
                    'sales_rep_id' => $salesRep->id,
                    'sales_rep_name' => $salesRep->name,
                    'reference' => $reference !== '' ? $reference : null,
                    'note' => $note !== '' ? $note : null,
                ],
                'created_by' => $request->user()?->id,
            ]);
        });

        $freshInvoice = $invoice->fresh('customer');

        $this->runInvoicePaidPostProcessing(
            $request,
            $freshInvoice,
            $adminNotifications,
            $clientNotifications,
            $commissionService,
            $salesRepNotifications,
            $reference !== '' ? $reference : null
        );

        SystemLogger::write('activity', 'Invoice marked as paid via sales representative collection.', [
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'sales_representative_id' => $salesRep->id,
            'payment_amount' => $paymentAmount,
            'retained_amount' => $retainedAmount,
            'currency' => $invoice->currency,
        ], $request->user()?->id, $request->ip());

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                route('admin.invoices.show', $invoice),
                'Invoice marked as paid via sales representative.'
            );
        }

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('status', 'Invoice marked as paid via sales representative.');
    }

    public function recalculate(Request $request, Invoice $invoice): RedirectResponse|JsonResponse
    {
        if (! in_array($invoice->status, ['unpaid', 'overdue'], true)) {
            if (AjaxResponse::ajaxFromRequest($request)) {
                return AjaxResponse::ajaxError('Only unpaid or overdue invoices can be recalculated.', 422);
            }

            return redirect()->route('admin.invoices.show', $invoice)
                ->with('status', 'Only unpaid or overdue invoices can be recalculated.');
        }

        $this->billingService->recalculateInvoice($invoice);

        SystemLogger::write('activity', 'Invoice recalculated.', [
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
        ]);

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                route('admin.invoices.show', $invoice),
                'Invoice recalculated.'
            );
        }

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('status', 'Invoice recalculated.');
    }

    public function update(
        Request $request,
        Invoice $invoice,
        AdminNotificationService $adminNotifications,
        ClientNotificationService $clientNotifications,
        CommissionService $commissionService,
        InvoiceTaxService $taxService,
        SalesRepNotificationService $salesRepNotifications
    ): RedirectResponse|JsonResponse {
        $invoice->loadMissing('items');
        $wasPaid = $invoice->status === 'paid';
        $previousStatus = $invoice->status;
        $data = $request->validate([
            'status' => ['required', Rule::in(['unpaid', 'overdue', 'paid', 'cancelled', 'refunded'])],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0'],
            'new_items' => ['nullable', 'array'],
            'new_items.*.description' => ['nullable', 'string', 'max:255'],
            'new_items.*.amount' => ['nullable', 'numeric', 'min:0'],
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

        $itemInputs = is_array($request->input('items')) ? $request->input('items') : [];
        $newItemInputs = is_array($request->input('new_items')) ? $request->input('new_items') : [];
        $shouldSyncItems = $request->has('items') || $request->has('new_items');

        $itemUpdates = [];
        $itemCreates = [];
        $validationErrors = [];

        if ($shouldSyncItems) {
            $subtotal = 0.0;

            foreach ($invoice->items as $item) {
                $row = $itemInputs[$item->id] ?? $itemInputs[(string) $item->id] ?? [];
                if (! is_array($row)) {
                    $row = [];
                }

                $description = trim((string) ($row['description'] ?? $item->description ?? ''));
                $amountRaw = $row['amount'] ?? $item->line_total;

                if ($description === '') {
                    $validationErrors["items.{$item->id}.description"] = 'Description is required.';
                }
                if ($amountRaw === null || trim((string) $amountRaw) === '') {
                    $validationErrors["items.{$item->id}.amount"] = 'Amount is required.';
                    $amountRaw = 0;
                }

                $amount = round((float) $amountRaw, 2);
                if ($amount < 0) {
                    $validationErrors["items.{$item->id}.amount"] = 'Amount must be 0 or greater.';
                }

                $itemUpdates[] = [
                    'model' => $item,
                    'description' => $description,
                    'amount' => $amount,
                ];
                $subtotal += $amount;
            }

            foreach ($newItemInputs as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $description = trim((string) ($row['description'] ?? ''));
                $amountRaw = $row['amount'] ?? null;
                $amountProvided = $amountRaw !== null && trim((string) $amountRaw) !== '';
                $hasAnyValue = $description !== '' || $amountProvided;

                if (! $hasAnyValue) {
                    continue;
                }

                if ($description === '') {
                    $validationErrors["new_items.{$index}.description"] = 'Description is required for new line item.';
                }
                if (! $amountProvided) {
                    $validationErrors["new_items.{$index}.amount"] = 'Amount is required for new line item.';
                    $amountRaw = 0;
                }

                $amount = round((float) $amountRaw, 2);
                if ($amount < 0) {
                    $validationErrors["new_items.{$index}.amount"] = 'Amount must be 0 or greater.';
                }

                $itemCreates[] = [
                    'description' => $description,
                    'amount' => $amount,
                ];
                $subtotal += $amount;
            }

            if ($subtotal <= 0) {
                $validationErrors['items'] = 'Invoice total must be greater than zero.';
            }

            if ($validationErrors !== []) {
                throw ValidationException::withMessages($validationErrors);
            }

            $taxData = $taxService->calculateTotals($subtotal, 0.0, Carbon::parse($data['issue_date']));
            $updates['subtotal'] = $subtotal;
            $updates['tax_rate_percent'] = $taxData['tax_rate_percent'];
            $updates['tax_mode'] = $taxData['tax_mode'];
            $updates['tax_amount'] = $taxData['tax_amount'];
            $updates['total'] = $taxData['total'];
        }

        DB::transaction(function () use ($invoice, $updates, $itemUpdates, $itemCreates, $shouldSyncItems) {
            if ($shouldSyncItems) {
                foreach ($itemUpdates as $itemUpdate) {
                    /** @var InvoiceItem $itemModel */
                    $itemModel = $itemUpdate['model'];
                    $amount = (float) $itemUpdate['amount'];
                    $itemModel->update([
                        'description' => $itemUpdate['description'],
                        'quantity' => 1,
                        'unit_price' => $amount,
                        'line_total' => $amount,
                    ]);
                }

                foreach ($itemCreates as $itemCreate) {
                    $amount = (float) $itemCreate['amount'];
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'description' => $itemCreate['description'],
                        'quantity' => 1,
                        'unit_price' => $amount,
                        'line_total' => $amount,
                    ]);
                }
            }

            $invoice->update($updates);
        });

        $statusChanged = $previousStatus !== $data['status'];
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

        if ($statusChanged) {
            $freshInvoice = $invoice->fresh('customer');
            try {
                $clientNotifications->sendInvoicePaymentStatusNotification($freshInvoice, 'admin_update', null);
            } catch (\Throwable $e) {
                SystemLogger::write('module', 'Client invoice payment status notification failed on admin invoice update.', [
                    'invoice_id' => $invoice->id,
                    'status' => $data['status'],
                    'error' => $e->getMessage(),
                ], level: 'error');
            }

            try {
                $salesRepNotifications->sendInvoicePaymentStatusToRelatedSalesReps($freshInvoice, 'admin_update', null);
            } catch (\Throwable $e) {
                SystemLogger::write('module', 'Sales rep invoice payment status notification failed on admin invoice update.', [
                    'invoice_id' => $invoice->id,
                    'status' => $data['status'],
                    'error' => $e->getMessage(),
                ], level: 'error');
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

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                route('admin.invoices.show', $invoice),
                'Invoice updated.'
            );
        }

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('status', 'Invoice updated.');
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse|JsonResponse
    {
        SystemLogger::write('activity', 'Invoice deleted.', [
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'status' => $invoice->status,
        ]);

        $invoice->delete();

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                $this->listRedirectFromRequest($request),
                'Invoice deleted.'
            );
        }

        return redirect()->route('admin.invoices.index')
            ->with('status', 'Invoice deleted.');
    }

    private function listByStatus(?string $status, string $title, ?Project $project = null): InertiaResponse
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
                $inner->where('number', 'like', '%'.$search.'%')
                    ->orWhere('status', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('subscription.plan', function ($planQuery) use ($search) {
                        $planQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhereHas('product', function ($productQuery) use ($search) {
                                $productQuery->where('name', 'like', '%'.$search.'%');
                            });
                    })
                    ->orWhereHas('maintenance', function ($maintenanceQuery) use ($search) {
                        $maintenanceQuery->where('title', 'like', '%'.$search.'%');
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

        /** @var LengthAwarePaginator $paginator */
        $paginator = $payload['invoices'];

        return Inertia::render('Admin/Invoices/Index', [
            'pageTitle' => $title,
            'statusFilter' => $status,
            'filters' => [
                'search' => $search,
            ],
            'project' => $project ? [
                'id' => $project->id,
                'name' => $project->name,
            ] : null,
            'invoices' => $paginator->getCollection()
                ->map(fn (Invoice $invoice) => $this->serializeInvoiceListItem($invoice))
                ->values(),
            'pagination' => $this->paginationPayload($paginator),
            'routes' => [
                'index' => route('admin.invoices.index'),
                'paid' => route('admin.invoices.paid'),
                'unpaid' => route('admin.invoices.unpaid'),
                'overdue' => route('admin.invoices.overdue'),
                'cancelled' => route('admin.invoices.cancelled'),
                'refunded' => route('admin.invoices.refunded'),
                'create' => route('admin.invoices.create'),
                'current' => url()->current(),
            ],
        ]);
    }

    private function listRedirectFromRequest(Request $request): string
    {
        $statusFilter = trim((string) $request->input('status_filter', ''));
        $status = in_array($statusFilter, ['paid', 'unpaid', 'overdue', 'cancelled', 'refunded'], true)
            ? $statusFilter
            : null;
        $search = trim((string) $request->input('search', ''));
        $project = null;
        $projectId = (int) $request->input('project_id', 0);
        if ($projectId > 0) {
            $project = Project::query()->find($projectId);
        }
        $listUrl = $this->statusListUrl($status, $project);
        if ($search === '') {
            return $listUrl;
        }

        $separator = str_contains($listUrl, '?') ? '&' : '?';

        return $listUrl.$separator.http_build_query(['search' => $search]);
    }

    private function customerLabel(Customer $customer): string
    {
        $label = $customer->name;

        if ($customer->company_name) {
            $label .= ' - '.$customer->company_name;
        }

        if ($customer->email) {
            $label .= ' ('.$customer->email.')';
        }

        return $label;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInvoiceListItem(Invoice $invoice): array
    {
        $creditTotal = (float) $invoice->accountingEntries->where('type', 'credit')->sum('amount');
        $paidTotal = (float) $invoice->accountingEntries->where('type', 'payment')->sum('amount');
        $paidAmount = $paidTotal + $creditTotal;
        $isPartial = $paidAmount > 0 && $paidAmount < (float) $invoice->total;

        return [
            'id' => $invoice->id,
            'number_display' => is_numeric($invoice->number) ? (string) $invoice->number : (string) $invoice->id,
            'customer_name' => $invoice->customer?->name,
            'customer_route' => $invoice->customer ? route('admin.customers.show', $invoice->customer) : null,
            'total_display' => sprintf('%s %s', (string) $invoice->currency, number_format((float) $invoice->total, 2)),
            'paid_at_display' => $invoice->paid_at?->format((string) config('app.date_format', 'd-m-Y')) ?? '--',
            'due_date_display' => $invoice->due_date?->format((string) config('app.date_format', 'd-m-Y')) ?? '--',
            'status' => (string) $invoice->status,
            'status_label' => ucfirst((string) $invoice->status),
            'status_class' => $this->statusClass((string) $invoice->status),
            'has_pending_proof' => (bool) $invoice->paymentProofs->firstWhere('status', 'pending'),
            'has_rejected_proof' => (bool) $invoice->paymentProofs->firstWhere('status', 'rejected'),
            'is_partial' => $isPartial,
            'paid_amount_display' => sprintf('%s %s', (string) $invoice->currency, number_format($paidAmount, 2)),
            'routes' => [
                'show' => route('admin.invoices.show', $invoice),
                'destroy' => route('admin.invoices.destroy', $invoice),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInvoiceDetails(Invoice $invoice): array
    {
        $creditTotal = (float) $invoice->accountingEntries->where('type', 'credit')->sum('amount');
        $taxSetting = \App\Models\TaxSetting::current();
        $taxLabel = $taxSetting->invoice_tax_label ?: 'Tax';
        $taxNote = $taxSetting->renderNote($invoice->tax_rate_percent);
        $hasTax = $invoice->tax_amount !== null && $invoice->tax_rate_percent !== null && $invoice->tax_mode;
        $discountAmount = $creditTotal;
        $payableAmount = max(0, (float) $invoice->total - $discountAmount);
        $companyName = (string) Setting::getValue('company_name', config('app.name', 'MyApptimatic'));
        $companyEmail = (string) Setting::getValue('company_email');
        $payToText = (string) Setting::getValue('pay_to_text');
        $companyLogoUrl = Branding::url(Setting::getValue('company_logo_path'));
        $salesRepCollection = $this->resolveSalesRepCollectionSummary($invoice);

        return [
            'id' => $invoice->id,
            'number_display' => is_numeric($invoice->number) ? (string) $invoice->number : (string) $invoice->id,
            'status' => (string) $invoice->status,
            'status_label' => strtoupper((string) $invoice->status),
            'status_class' => $this->statusClass((string) $invoice->status),
            'issue_date_display' => $invoice->issue_date?->format((string) config('app.date_format', 'd-m-Y')) ?? '--',
            'due_date_display' => $invoice->due_date?->format((string) config('app.date_format', 'd-m-Y')) ?? '--',
            'paid_at_display' => $invoice->paid_at?->format((string) config('app.date_format', 'd-m-Y')) ?? null,
            'issue_date_value' => (string) old('issue_date', $invoice->issue_date?->format(config('app.date_format', 'd-m-Y'))),
            'due_date_value' => (string) old('due_date', $invoice->due_date?->format(config('app.date_format', 'd-m-Y'))),
            'notes_value' => (string) old('notes', $invoice->notes ?? ''),
            'selected_status' => (string) old('status', $invoice->status),
            'currency' => (string) $invoice->currency,
            'customer' => [
                'name' => $invoice->customer?->name ?? '--',
                'email' => $invoice->customer?->email ?? '--',
                'address' => $invoice->customer?->address ?? '--',
            ],
            'company' => [
                'name' => $companyName,
                'logo_url' => $companyLogoUrl,
                'email' => $companyEmail !== '' ? $companyEmail : '--',
                'pay_to_text' => $payToText !== '' ? $payToText : 'Billing Department',
            ],
            'items' => $invoice->items->map(fn (InvoiceItem $item) => [
                'id' => $item->id,
                'description' => $item->description,
                'line_total_value' => number_format((float) $item->line_total, 2, '.', ''),
                'line_total_display' => sprintf(
                    '%s %s',
                    (string) $invoice->currency,
                    number_format((float) $item->line_total, 2)
                ),
            ])->values(),
            'totals' => [
                'subtotal_display' => sprintf('%s %s', (string) $invoice->currency, number_format((float) $invoice->subtotal, 2)),
                'has_tax' => $hasTax,
                'tax_label' => $taxLabel,
                'tax_rate_display' => rtrim(rtrim(number_format((float) $invoice->tax_rate_percent, 2, '.', ''), '0'), '.'),
                'tax_mode' => $invoice->tax_mode,
                'tax_amount_display' => sprintf('%s %s', (string) $invoice->currency, number_format((float) $invoice->tax_amount, 2)),
                'tax_note' => $taxNote,
                'discount_display' => '- '.(string) $invoice->currency.' '.number_format($discountAmount, 2),
                'payable_display' => sprintf('%s %s', (string) $invoice->currency, number_format($payableAmount, 2)),
            ],
            'notes' => $invoice->notes,
            'sales_rep_collection' => $salesRepCollection,
            'accounting_entries' => $invoice->accountingEntries->map(function ($entry) {
                $metadata = is_array($entry->metadata) ? $entry->metadata : [];
                $collectedBySalesRepName = trim((string) ($metadata['collected_by_sales_rep_name'] ?? ''));
                $retainedAmount = (float) ($metadata['retained_amount'] ?? 0);

                return [
                    'id' => $entry->id,
                    'type_label' => ucfirst((string) $entry->type),
                    'entry_date_display' => $entry->entry_date?->format((string) config('app.date_format', 'd-m-Y')) ?? '--',
                    'gateway_name' => $entry->paymentGateway?->name,
                    'description' => $entry->description,
                    'sales_rep_collection' => $collectedBySalesRepName !== '' ? [
                        'sales_rep_name' => $collectedBySalesRepName,
                        'retained_amount_display' => sprintf('%s %s', (string) $entry->currency, number_format($retainedAmount, 2)),
                        'reference' => $entry->reference ?: '--',
                        'note' => (string) ($metadata['note'] ?? ''),
                    ] : null,
                    'amount_display' => sprintf(
                        '%s%s %s',
                        $entry->isOutflow() ? '-' : '+',
                        (string) $entry->currency,
                        number_format((float) $entry->amount, 2)
                    ),
                    'amount_class' => $entry->isOutflow() ? 'text-rose-600' : 'text-emerald-600',
                ];
            })->values(),
            'payment_proofs' => $invoice->paymentProofs->map(function ($proof) use ($invoice) {
                return [
                    'id' => $proof->id,
                    'gateway_name' => $proof->paymentGateway?->name ?? 'Manual',
                    'amount_display' => sprintf('%s %s', (string) $invoice->currency, number_format((float) $proof->amount, 2)),
                    'reference' => $proof->reference ?: '--',
                    'status' => $proof->status,
                    'status_label' => ucfirst((string) $proof->status),
                    'paid_at_display' => $proof->paid_at?->format((string) config('app.date_format', 'd-m-Y')),
                    'notes' => $proof->notes,
                    'reviewer_name' => $proof->reviewer?->name,
                    'attachment_url' => $proof->attachment_url,
                    'attachment_path' => $proof->attachment_path,
                    'can_review' => $proof->status === 'pending',
                    'routes' => [
                        'approve' => route('admin.payment-proofs.approve', $proof),
                        'reject' => route('admin.payment-proofs.reject', $proof),
                    ],
                ];
            })->values(),
            'can_record_payment' => $invoice->status !== 'paid',
            'can_record_refund' => $invoice->status === 'paid',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayProps(Request $request, Invoice $invoice, string $downloadRouteName): array
    {
        $invoice->load([
            'items',
            'customer',
            'subscription.plan.product',
            'accountingEntries.paymentGateway',
            'paymentProofs.paymentGateway',
            'paymentProofs.paymentAttempt',
        ]);

        $taxSetting = TaxSetting::current();
        $creditTotal = (float) $invoice->accountingEntries->where('type', 'credit')->sum('amount');
        $hasTax = $invoice->tax_amount !== null && $invoice->tax_rate_percent !== null && $invoice->tax_mode;
        $payableAmount = max(0, (float) $invoice->total - $creditTotal);
        $displayNumber = is_numeric($invoice->number) ? (string) $invoice->number : (string) $invoice->id;
        $dateFormat = config('app.date_format', 'd-m-Y');
        $portalBranding = (array) $request->attributes->get('portalBranding', []);
        $companyName = (string) ($portalBranding['company_name'] ?? Setting::getValue('company_name', config('app.name', 'Apptimatic')));
        $companyLogoUrl = $portalBranding['logo_url'] ?? Branding::url(Setting::getValue('company_logo_path'));
        $pendingProof = $invoice->paymentProofs->firstWhere('status', 'pending');
        $rejectedProof = $invoice->paymentProofs->firstWhere('status', 'rejected');

        $gateways = PaymentGateway::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return [
            'invoice' => [
                'id' => $invoice->id,
                'number_display' => $displayNumber,
                'status' => (string) $invoice->status,
                'status_label' => strtoupper((string) $invoice->status),
                'status_class' => strtolower((string) $invoice->status),
                'issue_date_display' => $invoice->issue_date?->format($dateFormat) ?? '--',
                'due_date_display' => $invoice->due_date?->format($dateFormat) ?? '--',
                'paid_at_display' => $invoice->paid_at?->format($dateFormat),
                'currency' => (string) $invoice->currency,
                'customer' => [
                    'name' => $invoice->customer?->name ?? '--',
                    'email' => $invoice->customer?->email ?? '--',
                    'address' => $invoice->customer?->address ?? '--',
                ],
                'items' => $invoice->items->map(function ($item) use ($invoice) {
                    return [
                        'id' => $item->id,
                        'description' => (string) $item->description,
                        'line_total_display' => (string) $invoice->currency.' '.number_format((float) $item->line_total, 2),
                    ];
                })->values()->all(),
                'subtotal_display' => (string) $invoice->currency.' '.number_format((float) $invoice->subtotal, 2),
                'has_tax' => (bool) $hasTax,
                'tax_mode' => $invoice->tax_mode,
                'tax_amount_display' => (string) $invoice->currency.' '.number_format((float) $invoice->tax_amount, 2),
                'tax_rate_percent_display' => $invoice->tax_rate_percent !== null
                    ? rtrim(rtrim(number_format((float) $invoice->tax_rate_percent, 2, '.', ''), '0'), '.')
                    : null,
                'discount_display' => (string) $invoice->currency.' '.number_format($creditTotal, 2),
                'payable_amount_display' => (string) $invoice->currency.' '.number_format($payableAmount, 2),
                'is_payable' => in_array($invoice->status, ['unpaid', 'overdue'], true),
                'pending_proof' => (bool) $pendingProof,
                'rejected_proof' => (bool) $rejectedProof,
            ],
            'tax' => [
                'label' => $taxSetting->invoice_tax_label ?: 'Tax',
                'note' => $taxSetting->renderNote($invoice->tax_rate_percent),
            ],
            'company' => [
                'name' => $companyName,
                'logo_url' => $companyLogoUrl,
                'email' => Setting::getValue('company_email') ?: 'support@example.com',
                'pay_to' => Setting::getValue('pay_to_text') ?: 'Billing Department',
            ],
            'payment_instructions' => Setting::getValue('payment_instructions'),
            'gateways' => $gateways->map(function ($gateway) {
                return [
                    'id' => $gateway->id,
                    'name' => (string) $gateway->name,
                    'driver' => (string) $gateway->driver,
                    'payment_url' => (string) ($gateway->settings['payment_url'] ?? ''),
                    'instructions' => (string) ($gateway->settings['instructions'] ?? ''),
                    'button_label' => (string) ($gateway->settings['button_label'] ?? ''),
                ];
            })->values()->all(),
            'routes' => [
                'checkout' => route('client.invoices.checkout', $invoice),
                'download' => route($downloadRouteName, $invoice),
            ],
        ];
    }

    private function runInvoicePaidPostProcessing(
        Request $request,
        Invoice $invoice,
        AdminNotificationService $adminNotifications,
        ClientNotificationService $clientNotifications,
        CommissionService $commissionService,
        SalesRepNotificationService $salesRepNotifications,
        ?string $reference = null
    ): void {
        $freshInvoice = $invoice->fresh('customer');
        if (! $freshInvoice) {
            return;
        }

        $adminNotifications->sendInvoicePaid($freshInvoice);

        try {
            $clientNotifications->sendInvoicePaymentStatusNotification($freshInvoice, 'paid', $reference);
        } catch (\Throwable $e) {
            SystemLogger::write('module', 'Client invoice paid notification failed.', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ], level: 'error');
        }

        try {
            $salesRepNotifications->sendInvoicePaymentStatusToRelatedSalesReps($freshInvoice, 'paid', $reference);
        } catch (\Throwable $e) {
            SystemLogger::write('module', 'Sales rep invoice paid notification failed.', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ], level: 'error');
        }

        $hasUnpaidInvoices = Invoice::query()
            ->where('customer_id', $invoice->customer_id)
            ->whereIn('status', ['unpaid', 'overdue'])
            ->exists();

        if (! $hasUnpaidInvoices) {
            \App\Models\Customer::query()
                ->where('id', $invoice->customer_id)
                ->update(['access_override_until' => null]);
        }

        try {
            $commissionService->createOrUpdateEarningOnInvoicePaid($freshInvoice->fresh('subscription.customer'));
        } catch (\Throwable $e) {
            SystemLogger::write('module', 'Commission earning failed on paid invoice.', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ], level: 'error');
        }
    }

    /**
     * @return Collection<int, SalesRepresentative>
     */
    private function invoiceSalesRepOptions(Invoice $invoice): Collection
    {
        $salesReps = collect();

        if ($invoice->customer?->defaultSalesRep instanceof SalesRepresentative) {
            $salesReps->push($invoice->customer->defaultSalesRep);
        }

        if ($invoice->subscription?->salesRep instanceof SalesRepresentative) {
            $salesReps->push($invoice->subscription->salesRep);
        }

        foreach ($invoice->orders ?? [] as $order) {
            if ($order->salesRep instanceof SalesRepresentative) {
                $salesReps->push($order->salesRep);
            }
        }

        foreach ($invoice->project?->salesRepresentatives ?? [] as $salesRep) {
            if ($salesRep instanceof SalesRepresentative) {
                $salesReps->push($salesRep);
            }
        }

        foreach ($invoice->maintenance?->salesRepresentatives ?? [] as $salesRep) {
            if ($salesRep instanceof SalesRepresentative) {
                $salesReps->push($salesRep);
            }
        }

        foreach ($invoice->maintenance?->project?->salesRepresentatives ?? [] as $salesRep) {
            if ($salesRep instanceof SalesRepresentative) {
                $salesReps->push($salesRep);
            }
        }

        $resolved = $salesReps
            ->filter(fn ($salesRep) => $salesRep instanceof SalesRepresentative && (string) $salesRep->status === 'active')
            ->unique('id')
            ->values();

        if ($resolved->isNotEmpty()) {
            return $resolved;
        }

        return SalesRepresentative::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'status']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveSalesRepCollectionSummary(Invoice $invoice): ?array
    {
        $entry = $invoice->accountingEntries
            ->filter(fn (AccountingEntry $item) => $item->type === 'payment')
            ->sortByDesc('id')
            ->first(function (AccountingEntry $item) {
                $metadata = is_array($item->metadata) ? $item->metadata : [];

                return (string) ($metadata['payment_mode'] ?? '') === 'sales_rep_collection';
            });

        if (! $entry) {
            return null;
        }

        $metadata = is_array($entry->metadata) ? $entry->metadata : [];
        $salesRepName = trim((string) ($metadata['collected_by_sales_rep_name'] ?? ''));
        if ($salesRepName === '') {
            return null;
        }

        $retainedAmount = (float) ($metadata['retained_amount'] ?? 0);

        return [
            'sales_rep_name' => $salesRepName,
            'reference' => $entry->reference ?: '--',
            'collected_amount_display' => sprintf('%s %s', (string) $entry->currency, number_format((float) $entry->amount, 2)),
            'retained_amount_display' => sprintf('%s %s', (string) $entry->currency, number_format($retainedAmount, 2)),
            'note' => (string) ($metadata['note'] ?? ''),
        ];
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

    private function statusClass(string $status): string
    {
        return match ($status) {
            'paid' => 'bg-emerald-100 text-emerald-700',
            'overdue' => 'bg-rose-100 text-rose-700',
            'unpaid' => 'bg-amber-100 text-amber-700',
            'cancelled', 'refunded' => 'bg-slate-100 text-slate-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    private function statusTitle(?string $status): string
    {
        return match ($status) {
            'paid' => 'Paid Invoices',
            'unpaid' => 'Unpaid Invoices',
            'overdue' => 'Overdue Invoices',
            'cancelled' => 'Cancelled Invoices',
            'refunded' => 'Refunded Invoices',
            default => 'All Invoices',
        };
    }

    private function statusListUrl(?string $status, ?Project $project = null): string
    {
        if ($project) {
            return route('admin.projects.invoices', $project);
        }

        return match ($status) {
            'paid' => route('admin.invoices.paid'),
            'unpaid' => route('admin.invoices.unpaid'),
            'overdue' => route('admin.invoices.overdue'),
            'cancelled' => route('admin.invoices.cancelled'),
            'refunded' => route('admin.invoices.refunded'),
            default => route('admin.invoices.index'),
        };
    }
}
