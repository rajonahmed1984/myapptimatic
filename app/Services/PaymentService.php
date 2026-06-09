<?php

namespace App\Services;

use App\Models\AccountingEntry;
use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Models\PaymentGateway;
use App\Services\Currency\CurrencyService;
use App\Support\SystemLogger;
use App\Support\Currency;
use App\Models\StatusAuditLog;
use App\Services\Payment\Gateways\GatewayDriverInterface;
use Carbon\Carbon;

class PaymentService
{
    public function createAttempt(Invoice $invoice, PaymentGateway $gateway): PaymentAttempt
    {
        $currency = strtoupper($invoice->currency ?? Currency::DEFAULT);
        $settledAmount = (float) $invoice->accountingEntries()
            ->whereIn('type', ['payment', 'credit'])
            ->sum('amount');
        $payableAmount = round(max(0, (float) $invoice->total - $settledAmount), 2);

        if ($payableAmount <= 0.009) {
            throw new \RuntimeException('Invoice has no outstanding balance.');
        }
        
        // Ensure currency is allowed
        if (!Currency::isAllowed($currency)) {
            $currency = Currency::DEFAULT;
        }

        $attempt = PaymentAttempt::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'payment_gateway_id' => $gateway->id,
            'status' => 'pending',
            'amount' => $payableAmount,
            'currency' => $currency,
            'gateway_reference' => null,
        ]);

        $attempt->update([
            'gateway_reference' => sprintf('%s-%06d', $invoice->number, $attempt->id),
        ]);

        SystemLogger::write('module', 'Payment attempt created.', [
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $attempt->invoice_id,
            'invoice_number' => $invoice->number,
            'customer_id' => $invoice->customer_id,
            'gateway' => $gateway->name,
            'driver' => $gateway->driver,
            'amount' => $attempt->amount,
            'currency' => $attempt->currency,
        ]);

        return $attempt;
    }

    public function initiate(PaymentAttempt $attempt): array
    {
        $gateway = $attempt->paymentGateway;

        SystemLogger::write('module', 'Payment initiation started via gateway.', [
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $attempt->invoice_id,
            'gateway' => $gateway?->name,
            'driver' => $gateway?->driver,
            'amount' => $attempt->amount,
            'currency' => $attempt->currency,
        ]);

        return $this->getDriver($gateway?->driver ?? 'manual')->start($attempt);
    }

    public function getDriver(string $driverName): GatewayDriverInterface
    {
        return match ($driverName) {
            'paypal' => app(\App\Services\Payment\Gateways\PayPal\PayPalGateway::class),
            'sslcommerz' => app(\App\Services\Payment\Gateways\SslCommerz\SslCommerzGateway::class),
            'bkash' => app(\App\Services\Payment\Gateways\Bkash\BkashGateway::class),
            'bkash_api' => app(\App\Services\Payment\Gateways\BkashApi\BkashApiGateway::class),
            default => app(\App\Services\Payment\Gateways\Manual\ManualGateway::class),
        };
    }

    public function markPaid(PaymentAttempt $attempt, string $reference, array $meta = []): void
    {
        if ($attempt->status === 'paid') {
            return;
        }

        $attempt->update([
            'status' => 'paid',
            'processed_at' => Carbon::now(),
            'response' => $this->mergeMeta($attempt->response, $meta),
        ]);

        SystemLogger::write('module', 'Payment successful via gateway.', [
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $attempt->invoice_id,
            'gateway' => $attempt->paymentGateway?->name,
            'driver' => $attempt->paymentGateway?->driver,
            'amount' => $attempt->amount,
            'currency' => $attempt->currency,
            'reference' => $reference,
        ]);

        AccountingEntry::create([
            'entry_date' => Carbon::today(),
            'type' => 'payment',
            'amount' => $attempt->amount,
            'currency' => $attempt->currency,
            'description' => sprintf('Payment via %s', $attempt->paymentGateway->name),
            'reference' => $reference,
            'customer_id' => $attempt->customer_id,
            'invoice_id' => $attempt->invoice_id,
            'payment_gateway_id' => $attempt->payment_gateway_id,
            'created_by' => null,
        ]);

        $invoice = $attempt->invoice;
        if ($invoice && $invoice->status !== 'paid') {
            $previousStatus = $invoice->status;
            $invoice->update([
                'status' => 'paid',
                'paid_at' => $invoice->paid_at ?? Carbon::now(),
            ]);

            StatusAuditLog::logChange(
                Invoice::class,
                $invoice->id,
                $previousStatus,
                'paid',
                'payment_received'
            );

            // Access restoration check
            $subscription = $invoice->subscription;
            if ($subscription) {
                $customerId = $invoice->customer_id;
                
                // Unsuspend subscription if it was suspended
                if ($subscription->status === 'suspended') {
                    $subscription->update([
                        'status' => 'active',
                        'suspended_at' => null,
                        'suspension_reason' => null,
                    ]);
                    StatusAuditLog::logChange(
                        \App\Models\Subscription::class,
                        $subscription->id,
                        'suspended',
                        'active',
                        'auto_unsuspend_on_payment'
                    );
                }

                $hasOverdue = Invoice::query()
                    ->where('customer_id', $customerId)
                    ->where('status', 'overdue')
                    ->exists();

                if (! $hasOverdue) {
                    \App\Models\Customer::query()
                        ->where('id', $customerId)
                        ->update(['access_override_until' => null]);
                }
            }
        }

        SystemLogger::write('activity', 'Invoice marked as paid.', [
            'invoice_id' => $attempt->invoice_id,
            'payment_attempt_id' => $attempt->id,
            'gateway' => $attempt->paymentGateway?->driver,
            'reference' => $reference,
        ]);

        $this->notifyInvoicePaymentUpdate($attempt, 'paid', $reference);
    }

    public function markFailed(PaymentAttempt $attempt, string $message, array $meta = []): void
    {
        $attempt->update([
            'status' => 'failed',
            'processed_at' => Carbon::now(),
            'response' => $this->mergeMeta($attempt->response, array_merge($meta, [
                'message' => $message,
            ])),
        ]);

        SystemLogger::write('module', 'Payment failed.', [
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $attempt->invoice_id,
            'gateway' => $attempt->paymentGateway?->driver,
            'message' => $message,
        ], level: 'error');

        $this->notifyInvoicePaymentUpdate($attempt, 'failed', (string) ($attempt->gateway_reference ?? ''));
    }

    public function markCancelled(PaymentAttempt $attempt, string $message, array $meta = []): void
    {
        $attempt->update([
            'status' => 'cancelled',
            'processed_at' => Carbon::now(),
            'response' => $this->mergeMeta($attempt->response, array_merge($meta, [
                'message' => $message,
            ])),
        ]);

        SystemLogger::write('module', 'Payment cancelled via gateway.', [
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $attempt->invoice_id,
            'gateway' => $attempt->paymentGateway?->name,
            'driver' => $attempt->paymentGateway?->driver,
            'message' => $message,
        ]);

        $this->notifyInvoicePaymentUpdate($attempt, 'cancelled', (string) ($attempt->gateway_reference ?? ''));
    }

    /**
     * Backward compatibility wrappers to delegate execution to driver classes.
     */
    public function capturePayPal(PaymentAttempt $attempt, string $orderId): bool
    {
        return $this->getDriver('paypal')->confirm($attempt, ['order_id' => $orderId]);
    }

    public function confirmSslcommerz(PaymentAttempt $attempt, array $payload): bool
    {
        return $this->getDriver('sslcommerz')->confirm($attempt, $payload);
    }

    public function confirmBkash(PaymentAttempt $attempt, array $payload): bool
    {
        $driverName = $attempt->paymentGateway?->driver ?? 'bkash';
        return $this->getDriver($driverName)->confirm($attempt, $payload);
    }

    public function refundBkash(PaymentAttempt $attempt, float $amount, string $reason): array
    {
        return $this->getDriver('bkash_api')->refund($attempt, $amount, $reason);
    }

    public function resolveGatewayAmount(PaymentAttempt $attempt, array $settings): array
    {
        $processingCurrency = strtoupper((string) ($settings['processing_currency'] ?? $attempt->currency));
        if (! Currency::isAllowed($processingCurrency)) {
            $processingCurrency = Currency::DEFAULT;
        }

        $sourceCurrency = strtoupper((string) $attempt->currency);
        if (! Currency::isAllowed($sourceCurrency)) {
            $sourceCurrency = Currency::DEFAULT;
        }

        $amount = (float) $attempt->amount;
        if ($processingCurrency !== $sourceCurrency) {
            $amount = app(CurrencyService::class)->convert($amount, $sourceCurrency, $processingCurrency);
        }

        return [$amount, $processingCurrency];
    }

    private function mergeMeta(?array $existing, array $meta): array
    {
        return array_merge($existing ?? [], $meta);
    }

    private function notifyInvoicePaymentUpdate(PaymentAttempt $attempt, string $paymentEvent, ?string $reference = null): void
    {
        $invoice = $attempt->invoice?->fresh();
        if (! $invoice) {
            return;
        }

        try {
            app(ClientNotificationService::class)
                ->sendInvoicePaymentStatusNotification($invoice, $paymentEvent, $reference);
        } catch (\Throwable $e) {
            SystemLogger::write('module', 'Client payment status notification failed.', [
                'invoice_id' => $invoice->id,
                'payment_attempt_id' => $attempt->id,
                'payment_event' => $paymentEvent,
                'error' => $e->getMessage(),
            ], level: 'error');
        }

        try {
            app(SalesRepNotificationService::class)
                ->sendInvoicePaymentStatusToRelatedSalesReps($invoice, $paymentEvent, $reference);
        } catch (\Throwable $e) {
            SystemLogger::write('module', 'Sales rep payment status notification failed.', [
                'invoice_id' => $invoice->id,
                'payment_attempt_id' => $attempt->id,
                'payment_event' => $paymentEvent,
                'error' => $e->getMessage(),
            ], level: 'error');
        }
    }
}
