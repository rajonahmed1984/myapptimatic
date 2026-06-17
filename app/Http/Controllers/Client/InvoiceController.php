<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Models\TaxSetting;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class InvoiceController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        return $this->listByStatus($request, null, 'Invoices', 'Review invoices and complete payment for unpaid items.');
    }

    public function paid(Request $request): InertiaResponse
    {
        return $this->listByStatus($request, 'paid', 'Paid Invoices', 'Invoices that have been settled.');
    }

    public function unpaid(Request $request): InertiaResponse
    {
        return $this->listByStatus($request, 'unpaid', 'Unpaid Invoices', 'Invoices awaiting payment.');
    }

    public function overdue(Request $request): InertiaResponse
    {
        return $this->listByStatus($request, 'overdue', 'Overdue Invoices', 'Invoices past the due date.');
    }

    public function cancelled(Request $request): InertiaResponse
    {
        return $this->listByStatus($request, 'cancelled', 'Cancelled Invoices', 'Invoices marked as cancelled.');
    }

    public function refunded(Request $request): InertiaResponse
    {
        return $this->listByStatus($request, 'refunded', 'Refunded Invoices', 'Invoices that have been refunded.');
    }

    public function pay(Request $request, Invoice $invoice): InertiaResponse
    {
        $customerId = $request->user()->customer_id;

        abort_unless($invoice->customer_id === $customerId, 403);

        $invoice->load([
            'items',
            'customer',
            'subscription.plan.product',
            'accountingEntries.paymentGateway',
            'paymentProofs.paymentGateway',
            'paymentProofs.paymentAttempt',
        ]);

        $taxSetting = TaxSetting::current();
        $paymentTotal = (float) $invoice->accountingEntries->where('type', 'payment')->sum('amount');
        $creditTotal = (float) $invoice->accountingEntries->where('type', 'credit')->sum('amount');
        $settledTotal = $paymentTotal + $creditTotal;
        $hasTax = $invoice->tax_amount !== null && $invoice->tax_rate_percent !== null && $invoice->tax_mode;
        $payableAmount = round(max(0, (float) $invoice->total - $settledTotal), 2);
        $effectiveStatus = $this->effectiveInvoiceStatusFromOutstanding((string) $invoice->status, $payableAmount);
        $isPayable = in_array($effectiveStatus, ['unpaid', 'overdue'], true) && $payableAmount > 0.009;
        $displayNumber = is_numeric($invoice->number) ? (string) $invoice->number : (string) $invoice->id;
        $dateFormat = config('app.date_format', 'd-m-Y');
        $portalBranding = (array) (view()->shared('portalBranding') ?? []);
        $pendingProof = $invoice->paymentProofs->firstWhere('status', 'pending');
        $rejectedProof = $invoice->paymentProofs->firstWhere('status', 'rejected');

        $payments = $invoice->accountingEntries
            ->filter(fn ($entry) => in_array($entry->type, ['payment', 'credit'], true))
            ->map(function ($entry) use ($dateFormat) {
                return [
                    'id' => $entry->id,
                    'date_display' => $entry->entry_date?->format($dateFormat) ?? '--',
                    'method' => $entry->paymentGateway?->name ?? ($entry->type === 'credit' ? 'Credit/Adjustment' : 'Manual'),
                    'reference' => $entry->reference ?: '--',
                    'amount_display' => $entry->currency.' '.number_format((float) $entry->amount, 2),
                ];
            })->values()->all();

        $gateways = PaymentGateway::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $latestAttempt = $invoice->paymentAttempts()->latest()->first();
        $selectedGatewayId = $request->query('gateway_id')
            ?: $request->query('payment_gateway_id')
            ?: $request->session()->get('gateway_id')
            ?: ($latestAttempt ? $latestAttempt->payment_gateway_id : null);

        return Inertia::render('Client/Invoices/Pay', [
            'selected_gateway_id' => $selectedGatewayId ? (int) $selectedGatewayId : null,
            'invoice' => [
                'id' => $invoice->id,
                'number_display' => $displayNumber,
                'status' => $effectiveStatus,
                'status_label' => strtoupper($effectiveStatus),
                'status_class' => strtolower($effectiveStatus),
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
                'is_payable' => $isPayable,
                'pending_proof' => (bool) $pendingProof,
                'rejected_proof' => (bool) $rejectedProof,
            ],
            'payments' => $payments,
            'tax' => [
                'label' => $taxSetting->invoice_tax_label ?: 'Tax',
                'note' => $taxSetting->renderNote($invoice->tax_rate_percent),
            ],
            'company' => [
                'name' => $portalBranding['company_name'] ?? config('app.name', 'Apptimatic'),
                'logo_url' => $portalBranding['logo_url'] ?? \App\Support\Branding::url(Setting::getValue('company_logo_path')),
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
                'download' => route('client.invoices.download', $invoice),
            ],
        ]);
    }

    public function show(Request $request, Invoice $invoice): InertiaResponse
    {
        return $this->pay($request, $invoice);
    }

    public function checkout(Request $request, Invoice $invoice, PaymentService $paymentService)
    {
        $customerId = $request->user()->customer_id;

        abort_unless($invoice->customer_id === $customerId, 403);

        $status = (string) $invoice->status;
        $settledAmount = (float) $invoice->accountingEntries()
            ->whereIn('type', ['payment', 'credit'])
            ->sum('amount');
        $outstandingAmount = round(max(0, (float) $invoice->total - $settledAmount), 2);

        if (! in_array($status, ['unpaid', 'overdue'], true) || $outstandingAmount <= 0.009) {
            return redirect()->route('client.invoices.pay', $invoice)
                ->with('status', 'This invoice is already paid.');
        }

        $data = $request->validate([
            'payment_gateway_id' => ['required', 'exists:payment_gateways,id'],
        ]);

        $gateway = PaymentGateway::query()
            ->where('is_active', true)
            ->findOrFail($data['payment_gateway_id']);

        try {
            $attempt = $paymentService->createAttempt($invoice, $gateway);
        } catch (\RuntimeException $exception) {
            session()->flash('gateway_id', $gateway->id);
            return redirect()->route('client.invoices.pay', $invoice)
                ->with('status', $exception->getMessage());
        }

        $result = $paymentService->initiate($attempt);

        if ($result['status'] === 'redirect' && ! empty($result['url'])) {
            return redirect()->away($result['url']);
        }

        if ($result['status'] === 'manual') {
            return redirect()->route('client.invoices.manual', [$invoice, $attempt])
                ->with('status', 'Submit your transfer details to complete verification.');
        }

        session()->flash('gateway_id', $gateway->id);
        return redirect()->route('client.invoices.pay', $invoice)
            ->withErrors(['payment_gateway_id' => $result['message'] ?? 'Unable to start payment.']);
    }

    public function download(Request $request, Invoice $invoice): Response
    {
        $customerId = $request->user()->customer_id;

        abort_unless($invoice->customer_id === $customerId, 403);

        $invoice->load(['items', 'customer', 'subscription.plan.product', 'accountingEntries.paymentGateway']);

        $html = view('client.invoices.pdf', [
            'invoice' => $invoice,
            'payToText' => Setting::getValue('pay_to_text'),
            'companyEmail' => Setting::getValue('company_email'),
        ])->render();

        $pdf = app('dompdf.wrapper')->loadHTML($html);

        return $pdf->download('invoice-'.$invoice->number.'.pdf');
    }

    private function listByStatus(Request $request, ?string $status, string $title, string $subtitle): InertiaResponse
    {
        $customer = $request->user()->customer;
        $dateFormat = config('app.date_format', 'd-m-Y');

        $query = $customer
            ? $customer->invoices()
                ->with([
                    'subscription.plan.product',
                    'project',
                    'paymentProofs.paymentAttempt',
                    'accountingEntries',
                ])
                ->latest('issue_date')
            : null;

        if ($query && $status) {
            if (! in_array($status, ['paid', 'unpaid', 'overdue'], true)) {
                $query->where('status', $status);
            }
        }

        $invoices = $query?->get() ?? collect();
        if ($status) {
            $invoices = $invoices
                ->filter(function (Invoice $invoice) use ($status) {
                    if (! in_array($status, ['paid', 'unpaid', 'overdue'], true)) {
                        return (string) $invoice->status === $status;
                    }

                    $outstandingAmount = $this->invoiceOutstandingAmount($invoice);
                    $effectiveStatus = $this->effectiveInvoiceStatusFromOutstanding((string) $invoice->status, $outstandingAmount);

                    return $effectiveStatus === $status;
                })
                ->values();
        }

        return Inertia::render('Client/Invoices/Index', [
            'customer' => $customer,
            'invoices' => $invoices->map(function (Invoice $invoice) use ($dateFormat) {
                $project = $invoice->project;
                $plan = $invoice->subscription?->plan;
                $product = $plan?->product;
                $serviceName = $product?->name;
                $planName = $plan?->name;
                $serviceLabel = $serviceName && $planName
                    ? "{$serviceName} - {$planName}"
                    : ($serviceName ?: ($planName ?: null));
                $relatedLabel = $project?->name ?: ($serviceLabel ?: '--');
                $relatedUrl = $project
                    ? route('client.projects.show', $project)
                    : ($invoice->subscription ? route('client.services.show', $invoice->subscription) : null);
                $creditTotal = (float) $invoice->accountingEntries->where('type', 'credit')->sum('amount');
                $paidTotal = (float) $invoice->accountingEntries->where('type', 'payment')->sum('amount');
                $paidAmount = $paidTotal + $creditTotal;
                $outstandingAmount = round(max(0, (float) $invoice->total - $paidAmount), 2);
                $effectiveStatus = $this->effectiveInvoiceStatusFromOutstanding((string) $invoice->status, $outstandingAmount);
                $isPayable = in_array($effectiveStatus, ['unpaid', 'overdue'], true) && $outstandingAmount > 0.009;
                $isPartial = $paidAmount > 0 && $paidAmount < (float) $invoice->total;
                $pendingProof = $invoice->paymentProofs->firstWhere('status', 'pending');
                $rejectedProof = $invoice->paymentProofs->firstWhere('status', 'rejected');

                return [
                    'id' => $invoice->id,
                    'number_display' => is_numeric($invoice->number) ? (string) $invoice->number : (string) $invoice->id,
                    'service_or_project' => $relatedLabel,
                    'service_or_project_url' => $relatedUrl,
                    'total_display' => (string) $invoice->currency.' '.number_format((float) $invoice->total, 2),
                    'issue_date_display' => $invoice->issue_date?->format($dateFormat) ?? '--',
                    'due_date_display' => $invoice->due_date?->format($dateFormat) ?? '--',
                    'paid_at_display' => $invoice->paid_at?->format($dateFormat) ?? '--',
                    'status' => $effectiveStatus,
                    'status_label' => ucfirst($effectiveStatus),
                    'status_class' => $this->invoiceStatusClass($effectiveStatus),
                    'is_partial' => (bool) $isPartial,
                    'paid_amount_display' => (string) $invoice->currency.' '.number_format($paidAmount, 2),
                    'has_pending_proof' => (bool) $pendingProof,
                    'has_rejected_proof' => (bool) $rejectedProof,
                    'routes' => [
                        'show' => route('client.invoices.show', $invoice),
                        'pay' => $isPayable
                            ? route('client.invoices.pay', $invoice)
                            : null,
                        'manual' => ($pendingProof && $pendingProof->paymentAttempt)
                            ? route('client.invoices.manual', [$invoice, $pendingProof->paymentAttempt])
                            : null,
                    ],
                ];
            })->values()->all(),
            'title' => $title,
            'subtitle' => $subtitle,
            'statusFilter' => $status,
            'routes' => [
                'dashboard' => route('client.dashboard'),
                'index' => route('client.invoices.index'),
                'paid' => route('client.invoices.paid'),
                'unpaid' => route('client.invoices.unpaid'),
                'overdue' => route('client.invoices.overdue'),
                'cancelled' => route('client.invoices.cancelled'),
                'refunded' => route('client.invoices.refunded'),
            ],
        ]);
    }

    private function invoiceOutstandingAmount(Invoice $invoice): float
    {
        $paidTotal = (float) $invoice->accountingEntries->where('type', 'payment')->sum('amount');
        $creditTotal = (float) $invoice->accountingEntries->where('type', 'credit')->sum('amount');

        return round(max(0, (float) $invoice->total - ($paidTotal + $creditTotal)), 2);
    }

    private function effectiveInvoiceStatusFromOutstanding(string $status, float $outstandingAmount): string
    {
        if (in_array($status, ['unpaid', 'overdue'], true) && $outstandingAmount <= 0.009) {
            return 'paid';
        }

        return $status;
    }

    private function invoiceStatusClass(string $status): string
    {
        return match ($status) {
            'paid' => 'bg-emerald-100 text-emerald-700',
            'unpaid' => 'bg-amber-100 text-amber-700',
            'overdue' => 'bg-rose-100 text-rose-700',
            'refunded' => 'bg-sky-100 text-sky-700',
            'cancelled' => 'bg-slate-100 text-slate-600',
            default => 'bg-slate-100 text-slate-600',
        };
    }
}
