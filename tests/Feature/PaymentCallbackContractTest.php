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

    #[Test]
    public function bkash_api_callback_with_cancel_status_marks_attempt_cancelled(): void
    {
        $attempt = $this->createAttempt('bkash_api', [
            'username' => 'test-user',
            'password' => 'test-pass',
            'app_key' => 'test-key',
            'app_secret' => 'test-secret',
            'sandbox' => true,
        ]);

        $response = $this->post(route('payments.bkash.callback', $attempt), [
            'status' => 'cancel',
            'paymentId' => 'PAY-100',
        ]);

        $response->assertRedirect(route('client.invoices.pay', $attempt->invoice));
        $response->assertSessionHas('status', 'Payment cancelled.');

        $attempt->refresh();
        $this->assertSame('cancelled', $attempt->status);
    }

    #[Test]
    public function bkash_api_callback_success_executes_successfully(): void
    {
        $attempt = $this->createAttempt('bkash_api', [
            'username' => 'test-user',
            'password' => 'test-pass',
            'app_key' => 'test-key',
            'app_secret' => 'test-secret',
            'sandbox' => true,
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://tokenized.sandbox.bka.sh/v2/tokenized-checkout/auth/grant-token' => \Illuminate\Support\Facades\Http::response([
                'id_token' => 'mocked-token',
                'expires_in' => 3600,
            ]),
            'https://tokenized.sandbox.bka.sh/v2/tokenized-checkout/payment/execute' => \Illuminate\Support\Facades\Http::response([
                'transactionStatus' => 'Completed',
                'statusCode' => '0000',
                'trxId' => 'TXN-MOCK-100',
            ]),
        ]);

        $response = $this->post(route('payments.bkash.callback', $attempt), [
            'status' => 'success',
            'paymentId' => 'PAY-100',
        ]);

        $response->assertRedirect(route('client.invoices.pay', $attempt->invoice));
        $response->assertSessionHas('status', 'Payment confirmed. Thank you!');

        $attempt->refresh();
        $this->assertSame('paid', $attempt->status);
        $this->assertSame('TXN-MOCK-100', $attempt->response['response']['trxId'] ?? null);
    }

    #[Test]
    public function bkash_api_callback_execute_timeout_falls_back_to_query_and_succeeds(): void
    {
        $attempt = $this->createAttempt('bkash_api', [
            'username' => 'test-user',
            'password' => 'test-pass',
            'app_key' => 'test-key',
            'app_secret' => 'test-secret',
            'sandbox' => true,
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://tokenized.sandbox.bka.sh/v2/tokenized-checkout/auth/grant-token' => \Illuminate\Support\Facades\Http::response([
                'id_token' => 'mocked-token',
                'expires_in' => 3600,
            ]),
            'https://tokenized.sandbox.bka.sh/v2/tokenized-checkout/payment/execute' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            },
            'https://tokenized.sandbox.bka.sh/v2/tokenized-checkout/query/payment' => \Illuminate\Support\Facades\Http::response([
                'transactionStatus' => 'Completed',
                'statusCode' => '0000',
                'trxId' => 'TXN-FALLBACK-100',
            ]),
        ]);

        $response = $this->post(route('payments.bkash.callback', $attempt), [
            'status' => 'success',
            'paymentId' => 'PAY-100',
        ]);

        $response->assertRedirect(route('client.invoices.pay', $attempt->invoice));
        $response->assertSessionHas('status', 'Payment confirmed. Thank you!');

        $attempt->refresh();
        $this->assertSame('paid', $attempt->status);
        $this->assertSame('TXN-FALLBACK-100', $attempt->response['response']['trxId'] ?? null);
    }

    #[Test]
    public function bkash_api_live_refund_succeeds_and_records_refund_entry(): void
    {
        $attempt = $this->createAttempt('bkash_api', [
            'username' => 'test-user',
            'password' => 'test-pass',
            'app_key' => 'test-key',
            'app_secret' => 'test-secret',
            'sandbox' => true,
        ], [
            'status' => 'paid',
            'external_id' => 'PAY-MOCK-ID',
            'response' => [
                'response' => [
                    'trxId' => 'TXN-MOCK-PAID',
                ]
            ]
        ]);

        $invoice = $attempt->invoice;
        // Make sure invoice has paid entries so it has a refundable balance
        \App\Models\AccountingEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'type' => 'payment',
            'amount' => 150,
            'currency' => 'USD',
            'description' => 'Payment via bKash API',
            'reference' => 'TXN-MOCK-PAID',
            'customer_id' => $attempt->customer_id,
            'invoice_id' => $invoice->id,
            'payment_gateway_id' => $attempt->payment_gateway_id,
        ]);
        $invoice->update(['status' => 'paid']);

        // Mock the token call and refund API call
        \Illuminate\Support\Facades\Http::fake([
            'https://tokenized.sandbox.bka.sh/v2/tokenized-checkout/auth/grant-token' => \Illuminate\Support\Facades\Http::response([
                'id_token' => 'mocked-token',
                'expires_in' => 3600,
            ]),
            'https://tokenized.sandbox.bka.sh/v2/tokenized-checkout/refund/payment/transaction' => \Illuminate\Support\Facades\Http::response([
                'refundTransactionStatus' => 'Completed',
                'statusCode' => '0000',
                'refundTrxId' => 'TXN-REFUND-123',
            ]),
        ]);

        $adminUser = \App\Models\User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin-refund@example.com',
            'password' => bcrypt('password'),
            'role' => \App\Enums\Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($adminUser)->post(route('admin.invoices.add-refund', $invoice), [
            'entry_date' => now()->toDateString(),
            'amount' => 50,
            'payment_gateway_id' => $attempt->payment_gateway_id,
            'description' => 'Partial Refund',
        ]);

        $response->assertRedirect(route('admin.invoices.show', $invoice));
        $response->assertSessionHas('status', 'Refund recorded successfully.');

        // Verify entry in DB
        $refundEntry = \App\Models\AccountingEntry::where('invoice_id', $invoice->id)
            ->where('type', 'refund')
            ->first();

        $this->assertNotNull($refundEntry);
        $this->assertEquals(50.00, $refundEntry->amount);
        $this->assertEquals('TXN-REFUND-123', $refundEntry->reference);
    }
}
