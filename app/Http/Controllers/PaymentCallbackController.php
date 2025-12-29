<?php

namespace App\Http\Controllers;

use App\Models\PaymentAttempt;
use App\Services\PaymentService;
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
            $paymentService->markFailed($attempt, 'PayPal order ID missing.');

            return $this->redirectWithStatus('Payment could not be verified.');
        }

        $success = $paymentService->capturePayPal($attempt, $orderId);

        return $success
            ? $this->redirectWithStatus('Payment confirmed. Thank you!', $attempt)
            : $this->redirectWithStatus('Payment could not be verified.', $attempt);
    }

    public function paypalCancel(PaymentAttempt $attempt, PaymentService $paymentService)
    {
        $paymentService->markCancelled($attempt, 'PayPal payment cancelled.');

        return $this->redirectWithStatus('Payment cancelled.', $attempt);
    }

    public function sslcommerzSuccess(Request $request, PaymentAttempt $attempt, PaymentService $paymentService)
    {
        $success = $paymentService->confirmSslcommerz($attempt, $request->all());

        return $success
            ? $this->redirectWithStatus('Payment confirmed. Thank you!', $attempt)
            : $this->redirectWithStatus('Payment could not be verified.', $attempt);
    }

    public function sslcommerzFail(PaymentAttempt $attempt, PaymentService $paymentService)
    {
        $paymentService->markFailed($attempt, 'SSLCommerz payment failed.');

        return $this->redirectWithStatus('Payment failed.', $attempt);
    }

    public function sslcommerzCancel(PaymentAttempt $attempt, PaymentService $paymentService)
    {
        $paymentService->markCancelled($attempt, 'SSLCommerz payment cancelled.');

        return $this->redirectWithStatus('Payment cancelled.', $attempt);
    }

    public function bkashCallback(Request $request, PaymentAttempt $attempt, PaymentService $paymentService)
    {
        $success = $paymentService->confirmBkash($attempt, $request->all());

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
