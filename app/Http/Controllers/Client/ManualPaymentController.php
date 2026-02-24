<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Models\PaymentProof;
use App\Models\Setting;
use App\Services\ClientNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ManualPaymentController extends Controller
{
    public function create(Request $request, Invoice $invoice, PaymentAttempt $attempt): InertiaResponse|RedirectResponse
    {
        $customerId = $request->user()->customer_id;

        if ($invoice->customer_id !== $customerId || $attempt->invoice_id !== $invoice->id) {
            abort(403);
        }

        if ($invoice->status === 'paid') {
            return redirect()->route('client.invoices.pay', $invoice)
                ->with('status', 'This invoice is already paid.');
        }

        $attempt->load(['paymentGateway', 'proofs']);

        if ($attempt->paymentGateway?->driver !== 'manual') {
            abort(404);
        }

        $invoice->load(['items', 'customer', 'subscription.plan.product']);
        $dateFormat = config('app.date_format', 'd-m-Y');

        return Inertia::render('Client/Invoices/Manual', [
            'invoice' => [
                'id' => $invoice->id,
                'number_display' => is_numeric($invoice->number) ? (string) $invoice->number : (string) $invoice->id,
                'currency' => (string) $invoice->currency,
                'total_display' => (string) $invoice->currency.' '.number_format((float) $invoice->total, 2),
                'due_date_display' => $invoice->due_date?->format($dateFormat) ?? '--',
                'service_name' => $invoice->subscription?->plan?->product?->name ?? 'Service',
            ],
            'attempt' => [
                'uuid' => (string) $attempt->uuid,
                'proofs' => $attempt->proofs->map(function ($proof) use ($invoice) {
                    return [
                        'id' => $proof->id,
                        'status_label' => ucfirst((string) $proof->status),
                        'amount_display' => (string) $invoice->currency.' '.number_format((float) $proof->amount, 2),
                        'reference' => $proof->reference ?: '--',
                        'notes' => $proof->notes,
                        'attachment_url' => $proof->attachment_url,
                        'has_attachment_path' => (bool) $proof->attachment_path,
                    ];
                })->values()->all(),
            ],
            'gateway' => [
                'account_name' => $attempt->paymentGateway->settings['account_name'] ?? '--',
                'account_number' => $attempt->paymentGateway->settings['account_number'] ?? '--',
                'bank_name' => $attempt->paymentGateway->settings['bank_name'] ?? '--',
                'branch' => $attempt->paymentGateway->settings['branch'] ?? '--',
                'routing_number' => $attempt->paymentGateway->settings['routing_number'] ?? '--',
                'instructions' => $attempt->paymentGateway->settings['instructions'] ?? '',
            ],
            'payment_instructions' => Setting::getValue('payment_instructions'),
            'form' => [
                'reference' => old('reference'),
                'amount' => old('amount', number_format((float) $invoice->total, 2, '.', '')),
                'paid_at' => old('paid_at'),
                'notes' => old('notes'),
            ],
            'routes' => [
                'back' => route('client.invoices.pay', $invoice),
                'store' => route('client.invoices.manual.store', [$invoice, $attempt]),
            ],
        ]);
    }

    public function store(Request $request, Invoice $invoice, PaymentAttempt $attempt, ClientNotificationService $clientNotifications): RedirectResponse
    {
        $customerId = $request->user()->customer_id;

        if ($invoice->customer_id !== $customerId || $attempt->invoice_id !== $invoice->id) {
            abort(403);
        }

        if ($invoice->status === 'paid') {
            return redirect()->route('client.invoices.pay', $invoice)
                ->with('status', 'This invoice is already paid.');
        }

        $attempt->load('paymentGateway');

        if ($attempt->paymentGateway?->driver !== 'manual') {
            abort(404);
        }

        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['nullable', 'image', 'max:4096'],
        ]);

        $existing = PaymentProof::query()
            ->where('payment_attempt_id', $attempt->id)
            ->where('status', 'pending')
            ->exists();

        if ($existing) {
            return redirect()->route('client.invoices.manual', [$invoice, $attempt])
                ->with('status', 'A payment submission is already pending review.');
        }

        $path = null;
        if ($request->hasFile('receipt')) {
            $path = $request->file('receipt')->store('payment-proofs', 'public');
        }

        $paymentProof = PaymentProof::create([
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $customerId,
            'payment_gateway_id' => $attempt->payment_gateway_id,
            'reference' => $data['reference'] ?? null,
            'amount' => $data['amount'],
            'paid_at' => $data['paid_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'attachment_path' => $path,
            'status' => 'pending',
        ]);

        $attempt->update([
            'status' => 'submitted',
        ]);

        $clientNotifications->sendManualPaymentSubmission($paymentProof);

        return redirect()->route('client.invoices.pay', $invoice)
            ->with('status', 'Payment submitted. Our team will verify it shortly.');
    }
}
