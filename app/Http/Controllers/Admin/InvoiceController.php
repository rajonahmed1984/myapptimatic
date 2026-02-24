<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentGateway;
use App\Models\Project;
use App\Models\Setting;
use App\Services\AdminNotificationService;
use App\Services\BillingService;
use App\Services\CommissionService;
use App\Services\InvoiceTaxService;
use App\Support\AjaxResponse;
use App\Support\SystemLogger;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
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
            'items',
            'accountingEntries.paymentGateway',
            'paymentProofs.paymentGateway',
            'paymentProofs.reviewer',
        ]);

        return Inertia::render('Admin/Invoices/Show', [
            'pageTitle' => 'Invoice Details',
            'invoice' => $this->serializeInvoiceDetails($invoice),
            'routes' => [
                'index' => route('admin.invoices.index'),
                'client_view' => route('admin.invoices.client-view', $invoice),
                'download' => route('admin.invoices.download', $invoice),
                'update' => route('admin.invoices.update', $invoice),
                'recalculate' => route('admin.invoices.recalculate', $invoice),
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

    public function clientView(Invoice $invoice)
    {
        return view('client.invoices.pay', [
            'invoice' => $invoice->load([
                'customer',
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

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk('Invoice marked as paid.', $this->showPatches($invoice), closeModal: false);
        }

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('status', 'Invoice marked as paid.');
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
            return AjaxResponse::ajaxOk('Invoice recalculated.', $this->showPatches($invoice), closeModal: false);
        }

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('status', 'Invoice recalculated.');
    }

    public function update(
        Request $request,
        Invoice $invoice,
        AdminNotificationService $adminNotifications,
        CommissionService $commissionService
    ): RedirectResponse|JsonResponse {
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

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk('Invoice updated.', $this->showPatches($invoice), closeModal: false);
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
            return AjaxResponse::ajaxOk('Invoice deleted.', $this->listPatchesFromRequest($request), closeModal: false);
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

    private function showPatches(Invoice $invoice): array
    {
        $invoice = $invoice->fresh([
            'customer',
            'items',
            'accountingEntries.paymentGateway',
            'paymentProofs.paymentGateway',
            'paymentProofs.reviewer',
        ]);

        return [
            [
                'action' => 'replace',
                'selector' => '#invoiceShowWrap',
                'html' => view('admin.invoices.partials.show-main', [
                    'invoice' => $invoice,
                ])->render(),
            ],
        ];
    }

    private function listPatchesFromRequest(Request $request): array
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

        $listUrl = $this->statusListUrl($status, $project);
        $paginator = $query->paginate(25);
        $paginator->withPath($listUrl);
        if ($search !== '') {
            $paginator->appends(['search' => $search]);
        }

        return [
            [
                'action' => 'replace',
                'selector' => '#invoicesTableWrap',
                'html' => view('admin.invoices.partials.table', [
                    'invoices' => $paginator,
                    'statusFilter' => $status,
                    'search' => $search,
                    'project' => $project,
                    'title' => $this->statusTitle($status),
                ])->render(),
            ],
        ];
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
            'paid_at_display' => $invoice->paid_at?->format((string) config('app.date_format', 'Y-m-d')) ?? '--',
            'due_date_display' => $invoice->due_date?->format((string) config('app.date_format', 'Y-m-d')) ?? '--',
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

        return [
            'id' => $invoice->id,
            'number_display' => is_numeric($invoice->number) ? (string) $invoice->number : (string) $invoice->id,
            'status' => (string) $invoice->status,
            'status_label' => strtoupper((string) $invoice->status),
            'status_class' => $this->statusClass((string) $invoice->status),
            'issue_date_display' => $invoice->issue_date?->format((string) config('app.date_format', 'Y-m-d')) ?? '--',
            'due_date_display' => $invoice->due_date?->format((string) config('app.date_format', 'Y-m-d')) ?? '--',
            'paid_at_display' => $invoice->paid_at?->format((string) config('app.date_format', 'Y-m-d')) ?? null,
            'issue_date_value' => (string) old('issue_date', $invoice->issue_date?->format('Y-m-d')),
            'due_date_value' => (string) old('due_date', $invoice->due_date?->format('Y-m-d')),
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
                'email' => $companyEmail !== '' ? $companyEmail : '--',
                'pay_to_text' => $payToText !== '' ? $payToText : 'Billing Department',
            ],
            'items' => $invoice->items->map(fn (InvoiceItem $item) => [
                'id' => $item->id,
                'description' => $item->description,
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
            'accounting_entries' => $invoice->accountingEntries->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'type_label' => ucfirst((string) $entry->type),
                    'entry_date_display' => $entry->entry_date?->format((string) config('app.date_format', 'Y-m-d')) ?? '--',
                    'gateway_name' => $entry->paymentGateway?->name,
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
                    'paid_at_display' => $proof->paid_at?->format((string) config('app.date_format', 'Y-m-d')),
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
