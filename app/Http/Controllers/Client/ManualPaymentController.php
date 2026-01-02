<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Models\PaymentProof;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use App\Services\ClientNotificationService;
use Illuminate\Http\Request;

class ManualPaymentController extends Controller
{
    public function create(Request $request, Invoice $invoice, PaymentAttempt $attempt)
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

        return view('client.invoices.manual', [
            'invoice' => $invoice->load(['items', 'customer', 'subscription.plan.product']),
            'attempt' => $attempt,
            'gateway' => $attempt->paymentGateway,
            'paymentInstructions' => Setting::getValue('payment_instructions'),
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
