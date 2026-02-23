<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentCallbackContractTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function paypal_cancel_marks_attempt_cancelled_and_redirects_to_invoice_pay_page(): void
    {
        $attempt = $this->createAttempt('paypal');

        $response = $this->get(route('payments.paypal.cancel', $attempt));

        $response->assertRedirect(route('client.invoices.pay', $attempt->invoice));
        $response->assertSessionHas('status', 'Payment cancelled.');

        $attempt->refresh();
        $this->assertSame('cancelled', $attempt->status);
    }

    #[Test]
    public function sslcommerz_fail_marks_attempt_failed_and_redirects_to_invoice_pay_page(): void
    {
        $attempt = $this->createAttempt('sslcommerz');

        $response = $this->post(route('payments.sslcommerz.fail', $attempt));

        $response->assertRedirect(route('client.invoices.pay', $attempt->invoice));
        $response->assertSessionHas('status', 'Payment failed.');

        $attempt->refresh();
        $this->assertSame('failed', $attempt->status);
    }

    #[Test]
    public function sslcommerz_cancel_marks_attempt_cancelled_and_redirects_to_invoice_pay_page(): void
    {
        $attempt = $this->createAttempt('sslcommerz');

        $response = $this->post(route('payments.sslcommerz.cancel', $attempt));

        $response->assertRedirect(route('client.invoices.pay', $attempt->invoice));
        $response->assertSessionHas('status', 'Payment cancelled.');

        $attempt->refresh();
        $this->assertSame('cancelled', $attempt->status);
    }

    #[Test]
    public function paypal_return_without_credentials_keeps_attempt_pending_and_returns_submitted_message(): void
    {
        $attempt = $this->createAttempt('paypal', []);

        $response = $this->get(route('payments.paypal.return', $attempt));

        $response->assertRedirect(route('client.invoices.pay', $attempt->invoice));
        $response->assertSessionHas('status', 'Payment submitted. We will confirm it shortly.');

        $attempt->refresh();
        $this->assertSame('pending', $attempt->status);
    }

    #[Test]
    public function paypal_return_with_credentials_but_missing_order_id_marks_attempt_failed(): void
    {
        $attempt = $this->createAttempt('paypal', [
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'sandbox' => true,
        ], [
            'external_id' => null,
        ]);

        $response = $this->get(route('payments.paypal.return', $attempt));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Payment could not be verified.');

        $attempt->refresh();
        $this->assertSame('failed', $attempt->status);
    }

    #[Test]
    public function sslcommerz_success_without_credentials_marks_attempt_failed_and_redirects_with_error_message(): void
    {
        $attempt = $this->createAttempt('sslcommerz');

        $response = $this->post(route('payments.sslcommerz.success', $attempt), [
            'status' => 'VALID',
            'val_id' => 'VAL-100',
        ]);

        $response->assertRedirect(route('client.invoices.pay', $attempt->invoice));
        $response->assertSessionHas('status', 'Payment could not be verified.');

        $attempt->refresh();
        $this->assertSame('failed', $attempt->status);
    }

    #[Test]
    public function bkash_callback_without_credentials_marks_attempt_failed_and_redirects_with_error_message(): void
    {
        $attempt = $this->createAttempt('bkash');

        $response = $this->post(route('payments.bkash.callback', $attempt), [
            'status' => 'success',
            'paymentID' => 'PAY-100',
        ]);

        $response->assertRedirect(route('client.invoices.pay', $attempt->invoice));
        $response->assertSessionHas('status', 'Payment could not be verified.');

        $attempt->refresh();
        $this->assertSame('failed', $attempt->status);
    }

    private function createAttempt(
        string $driver,
        array $gatewaySettings = [],
        array $attemptOverrides = []
    ): PaymentAttempt {
        $customer = Customer::query()->create([
            'name' => 'Callback Customer',
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'number' => 'INV-CB-'.uniqid(),
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 150,
            'late_fee' => 0,
            'total' => 150,
            'currency' => 'USD',
            'type' => 'project_initial_payment',
        ]);

        $gateway = $this->gatewayForDriver($driver, $gatewaySettings);

        return PaymentAttempt::query()->create(array_merge([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'payment_gateway_id' => $gateway->id,
            'status' => 'pending',
            'amount' => 150,
            'currency' => 'USD',
            'gateway_reference' => 'ATTEMPT-'.uniqid(),
            'external_id' => null,
            'payload' => null,
            'response' => null,
            'processed_at' => null,
        ], $attemptOverrides));
    }

    private function gatewayForDriver(string $driver, array $settings): PaymentGateway
    {
        $gateway = PaymentGateway::query()
            ->where('driver', $driver)
            ->orderBy('id')
            ->first();

        if (! $gateway) {
            $gateway = PaymentGateway::query()->create([
                'name' => strtoupper($driver).' Gateway',
                'slug' => $driver.'-'.uniqid(),
                'driver' => $driver,
                'is_active' => true,
                'sort_order' => 1,
                'settings' => [],
            ]);
        }

        $gateway->update([
            'is_active' => true,
            'settings' => $settings,
        ]);

        return $gateway->fresh();
    }
}
