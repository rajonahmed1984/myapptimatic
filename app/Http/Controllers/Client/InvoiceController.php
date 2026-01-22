<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        return $this->listByStatus($request, null, 'Invoices', 'Review invoices and complete payment for unpaid items.');
    }

    public function paid(Request $request)
    {
        return $this->listByStatus($request, 'paid', 'Paid Invoices', 'Invoices that have been settled.');
    }

    public function unpaid(Request $request)
    {
        return $this->listByStatus($request, 'unpaid', 'Unpaid Invoices', 'Invoices awaiting payment.');
    }

    public function overdue(Request $request)
    {
        return $this->listByStatus($request, 'overdue', 'Overdue Invoices', 'Invoices past the due date.');
    }

    public function cancelled(Request $request)
    {
        return $this->listByStatus($request, 'cancelled', 'Cancelled Invoices', 'Invoices marked as cancelled.');
    }

    public function refunded(Request $request)
    {
        return $this->listByStatus($request, 'refunded', 'Refunded Invoices', 'Invoices that have been refunded.');
    }

    public function pay(Request $request, Invoice $invoice)
    {
        $customerId = $request->user()->customer_id;

        abort_unless($invoice->customer_id === $customerId, 403);

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

    public function show(Request $request, Invoice $invoice)
    {
        return $this->pay($request, $invoice);
    }

    public function checkout(Request $request, Invoice $invoice, PaymentService $paymentService)
    {
        $customerId = $request->user()->customer_id;

        abort_unless($invoice->customer_id === $customerId, 403);

        if (! in_array($invoice->status, ['unpaid', 'overdue'], true)) {
            return redirect()->route('client.invoices.pay', $invoice)
                ->with('status', 'This invoice is already paid.');
        }

        $data = $request->validate([
            'payment_gateway_id' => ['required', 'exists:payment_gateways,id'],
        ]);

        $gateway = PaymentGateway::query()
            ->where('is_active', true)
            ->findOrFail($data['payment_gateway_id']);

        $attempt = $paymentService->createAttempt($invoice, $gateway);
        $result = $paymentService->initiate($attempt);

        if ($result['status'] === 'redirect' && ! empty($result['url'])) {
            return redirect()->away($result['url']);
        }

        if ($result['status'] === 'manual') {
            return redirect()->route('client.invoices.manual', [$invoice, $attempt])
                ->with('status', 'Submit your transfer details to complete verification.');
        }

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

    private function listByStatus(Request $request, ?string $status, string $title, string $subtitle)
    {
        $customer = $request->user()->customer;

        $query = $customer
            ? $customer->invoices()
                ->with([
                    'subscription.plan.product',
                    'project',
                    'clientRequests',
                    'paymentProofs.paymentAttempt',
                    'accountingEntries',
                ])
                ->latest('issue_date')
            : null;

        if ($query && $status) {
            $query->where('status', $status);
        }

        return view('client.invoices.index', [
            'customer' => $customer,
            'invoices' => $query?->get() ?? collect(),
            'title' => $title,
            'subtitle' => $subtitle,
            'statusFilter' => $status,
        ]);
    }
}
