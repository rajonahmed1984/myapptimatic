<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BkashBridgeApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function token_bridge_api_requires_valid_bearer_token(): void
    {
        config(['services.bkash.api_key' => 'secret-bridge-key']);

        // No token
        $response = $this->getJson(route('api.bkash.token'));
        $response->assertStatus(401);

        // Invalid token
        $response = $this->withHeaders(['Authorization' => 'Bearer wrong-key'])
            ->getJson(route('api.bkash.token'));
        $response->assertStatus(401);
    }

    #[Test]
    public function token_bridge_api_returns_bkash_id_token_and_caches_it(): void
    {
        config([
            'services.bkash.api_key' => 'secret-bridge-key',
            'services.bkash.username' => 'test-user',
            'services.bkash.password' => 'test-pass',
            'services.bkash.app_key' => 'test-key',
            'services.bkash.app_secret' => 'test-secret',
            'services.bkash.sandbox' => true,
        ]);

        Cache::forget('bkash_token_bridge_master');

        Http::fake([
            'https://tokenized.sandbox.bka.sh/v2/tokenized-checkout/auth/grant-token' => Http::response([
                'id_token' => 'mocked-bkash-token-123',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer secret-bridge-key'])
            ->getJson(route('api.bkash.token'));

        $response->assertStatus(200);
        $response->assertJson(['id_token' => 'mocked-bkash-token-123']);

        // Check it is cached
        $this->assertEquals('mocked-bkash-token-123', Cache::get('bkash_token_bridge_master'));

        // Subsequent request should not hit the HTTP client
        Http::fake([
            'https://tokenized.sandbox.bka.sh/v2/tokenized-checkout/auth/grant-token' => Http::response([], 500),
        ]);

        $response2 = $this->withHeaders(['Authorization' => 'Bearer secret-bridge-key'])
            ->getJson(route('api.bkash.token'));

        $response2->assertStatus(200);
        $response2->assertJson(['id_token' => 'mocked-bkash-token-123']);
    }

    #[Test]
    public function bkash_payment_creation_uses_customer_as_payer_reference(): void
    {
        Http::fake([
            'https://tokenized.sandbox.bka.sh/v2/tokenized-checkout/auth/grant-token' => Http::response([
                'id_token' => 'mocked-token',
            ], 200),
            'https://tokenized.sandbox.bka.sh/v2/tokenized-checkout/payment/create' => Http::response([
                'paymentID' => 'PAY-123',
                'bkashURL' => 'https://mock.bkash.url',
            ], 200),
        ]);

        $customer = Customer::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '01712345678',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-TEST-123',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 500,
            'late_fee' => 0,
            'total' => 500,
            'currency' => 'BDT',
            'type' => 'project_initial_payment',
        ]);

        $gateway = PaymentGateway::query()->updateOrCreate(
            ['slug' => 'bkash_api'],
            [
                'name' => 'bKash API',
                'driver' => 'bkash_api',
                'settings' => [
                    'username' => 'user',
                    'password' => 'pass',
                    'app_key' => 'key',
                    'app_secret' => 'secret',
                    'sandbox' => true,
                ],
                'status' => 'active',
            ]
        );

        $attempt = PaymentAttempt::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'payment_gateway_id' => $gateway->id,
            'status' => 'pending',
            'amount' => 500,
            'currency' => 'BDT',
            'gateway_reference' => 'TXN-123',
        ]);

        $gatewayDriver = app(\App\Services\Payment\Gateways\BkashApi\BkashApiGateway::class);
        $result = $gatewayDriver->start($attempt);

        $this->assertEquals('redirect', $result['status']);
        $this->assertEquals('https://mock.bkash.url', $result['url']);

        // Check sent payload
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return $request->url() === 'https://tokenized.sandbox.bka.sh/v2/tokenized-checkout/payment/create'
                && $request['payerReference'] === 'Customer';
        });
    }
}
