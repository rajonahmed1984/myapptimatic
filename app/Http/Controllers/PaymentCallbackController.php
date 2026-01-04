<?php

namespace App\Http\Controllers;

use App\Models\PaymentAttempt;
use App\Services\PaymentService;
use App\Support\SystemLogger;
use Illuminate\Http\Request;

class PaymentCallbackController extends Controller
{
    public function paypalReturn(Request $request, PaymentAttempt $attempt, PaymentService $paymentService)
    {
        $settings = $attempt->paymentGateway?->settings ?? [];
        $clientId = $settings['client_id'] ?? null;
        $clientSecret = $settings['client_secret'] ?? null;

        if (! $clientId || ! $clientSecret) {
            return $this->redirectWithStatus('Payment submitted. We will confirm it shortly.', $attempt);
        }

        $orderId = $request->query('token') ?? $attempt->external_id;

        if (! $orderId) {
            SystemLogger::write('module', 'PayPal return missing order ID.', [
                'payment_attempt_id' => $attempt->id,
                'invoice_id' => $attempt->invoice_id,
                'query' => $request->query(),
            ], level: 'error');
            $paymentService->markFailed($attempt, 'PayPal order ID missing.');

            return $this->redirectWithStatus('Payment could not be verified.');
        }

        $success = $paymentService->capturePayPal($attempt, $orderId);

        SystemLogger::write('module', $success ? 'PayPal return captured.' : 'PayPal return capture failed.', [
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $attempt->invoice_id,
            'order_id' => $orderId,
            'query' => $request->query(),
        ], level: $success ? 'info' : 'error');

        return $success
            ? $this->redirectWithStatus('Payment confirmed. Thank you!', $attempt)
            : $this->redirectWithStatus('Payment could not be verified.', $attempt);
    }

    public function paypalCancel(PaymentAttempt $attempt, PaymentService $paymentService)
    {
        $paymentService->markCancelled($attempt, 'PayPal payment cancelled.');
        SystemLogger::write('module', 'PayPal payment cancelled by user.', [
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $attempt->invoice_id,
        ]);

        return $this->redirectWithStatus('Payment cancelled.', $attempt);
    }

    public function sslcommerzSuccess(Request $request, PaymentAttempt $attempt, PaymentService $paymentService)
    {
        $success = $paymentService->confirmSslcommerz($attempt, $request->all());

        SystemLogger::write('module', $success ? 'SSLCommerz success callback.' : 'SSLCommerz success callback failed.', [
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $attempt->invoice_id,
            'payload' => $request->all(),
        ], level: $success ? 'info' : 'error');

        return $success
            ? $this->redirectWithStatus('Payment confirmed. Thank you!', $attempt)
            : $this->redirectWithStatus('Payment could not be verified.', $attempt);
    }

    public function sslcommerzFail(PaymentAttempt $attempt, PaymentService $paymentService)
    {
        $paymentService->markFailed($attempt, 'SSLCommerz payment failed.');
        SystemLogger::write('module', 'SSLCommerz fail callback.', [
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $attempt->invoice_id,
        ], level: 'error');

        return $this->redirectWithStatus('Payment failed.', $attempt);
    }

    public function sslcommerzCancel(PaymentAttempt $attempt, PaymentService $paymentService)
    {
        $paymentService->markCancelled($attempt, 'SSLCommerz payment cancelled.');
        SystemLogger::write('module', 'SSLCommerz cancel callback.', [
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $attempt->invoice_id,
        ]);

        return $this->redirectWithStatus('Payment cancelled.', $attempt);
    }

    public function bkashCallback(Request $request, PaymentAttempt $attempt, PaymentService $paymentService)
    {
        $success = $paymentService->confirmBkash($attempt, $request->all());

        SystemLogger::write('module', $success ? 'bKash callback success.' : 'bKash callback failed.', [
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $attempt->invoice_id,
            'payload' => $request->all(),
        ], level: $success ? 'info' : 'error');

        return $success
            ? $this->redirectWithStatus('Payment confirmed. Thank you!', $attempt)
            : $this->redirectWithStatus('Payment could not be verified.', $attempt);
    }

    private function redirectWithStatus(string $message, ?PaymentAttempt $attempt = null)
    {
        if ($attempt && $attempt->invoice) {
            return redirect()
                ->route('client.invoices.pay', $attempt->invoice)
                ->with('status', $message);
        }

        return redirect()->route('login')->with('status', $message);
    }
}
