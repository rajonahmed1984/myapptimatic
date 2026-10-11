<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use App\Models\WebPushSubscription;
use App\Services\Push\WebPushClient;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    use RefreshDatabase;

    private const APPLE_ENDPOINT = 'https://web.push.apple.com/QGuXb-live-device';

    /** @var array{public_key: string, private_key: string} */
    private array $vapid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vapid = WebPushClient::generateVapidKeys();
        config([
            'services.webpush.public_key' => $this->vapid['public_key'],
            'services.webpush.private_key' => $this->vapid['private_key'],
            'services.webpush.subject' => 'mailto:push@example.test',
            // Only the browser channel is under test here.
            'services.fcm.credentials' => storage_path('app/firebase/missing.json'),
        ]);
    }

    #[Test]
    public function generated_vapid_keys_are_a_p256_pair(): void
    {
        $this->assertSame(65, strlen(WebPushClient::base64UrlDecode($this->vapid['public_key'])));
        $this->assertSame("\x04", WebPushClient::base64UrlDecode($this->vapid['public_key'])[0]);
        $this->assertSame(32, strlen(WebPushClient::base64UrlDecode($this->vapid['private_key'])));
        $this->assertTrue(app(WebPushClient::class)->isConfigured());
    }

    #[Test]
    public function the_payload_decrypts_the_way_a_browser_would_read_it(): void
    {
        // Stand-in for the browser's subscription keys.
        $browser = WebPushClient::generateVapidKeys();
        $browserPublic = WebPushClient::base64UrlDecode($browser['public_key']);
        $authSecret = random_bytes(16);

        $body = app(WebPushClient::class)->encrypt('{"title":"Invoice #7"}', $browserPublic, $authSecret);

        $this->assertSame('{"title":"Invoice #7"}', $this->decrypt($body, WebPushClient::base64UrlDecode($browser['private_key']), $browserPublic, $authSecret));
    }

    #[Test]
    public function signed_in_user_can_register_and_move_a_browser_subscription(): void
    {
        $first = User::factory()->create(['role' => Role::CLIENT]);
        $second = User::factory()->create(['role' => Role::CLIENT]);
        $payload = $this->subscriptionPayload(self::APPLE_ENDPOINT);

        $this->actingAs($first)->postJson(route('web-push-subscriptions.store'), $payload)->assertOk();
        $this->assertDatabaseHas('web_push_subscriptions', ['user_id' => $first->id, 'endpoint' => self::APPLE_ENDPOINT]);

        // The same browser signed in as someone else now belongs to them.
        $this->actingAs($second)->postJson(route('web-push-subscriptions.store'), $payload)->assertOk();

        $this->assertSame(1, WebPushSubscription::query()->count());
        $this->assertDatabaseHas('web_push_subscriptions', ['user_id' => $second->id]);
    }

    #[Test]
    public function endpoints_outside_the_known_push_services_are_refused(): void
    {
        $user = User::factory()->create(['role' => Role::CLIENT]);

        $this->actingAs($user)
            ->postJson(route('web-push-subscriptions.store'), $this->subscriptionPayload('https://internal.example.test/hook'))
            ->assertUnprocessable();
        $this->actingAs($user)
            ->postJson(route('web-push-subscriptions.store'), $this->subscriptionPayload('http://fcm.googleapis.com/fcm/send/abc'))
            ->assertUnprocessable();

        $this->assertSame(0, WebPushSubscription::query()->count());
    }

    #[Test]
    public function guests_cannot_register_a_subscription(): void
    {
        $this->postJson(route('web-push-subscriptions.store'), $this->subscriptionPayload(self::APPLE_ENDPOINT))
            ->assertUnauthorized();
    }

    #[Test]
    public function logout_detaches_the_subscription_registered_in_that_session(): void
    {
        $user = User::factory()->create(['role' => Role::CLIENT]);

        $this->actingAs($user)
            ->postJson(route('web-push-subscriptions.store'), $this->subscriptionPayload(self::APPLE_ENDPOINT))
            ->assertOk();

        $this->post(route('logout'))->assertRedirect();

        $this->assertSame(0, WebPushSubscription::query()->count());
    }

    #[Test]
    public function customer_push_reaches_their_browsers_and_drops_expired_subscriptions(): void
    {
        Http::fake([
            'web.push.apple.com/QGuXb-live-device' => Http::response('', 201),
            'fcm.googleapis.com/*' => Http::response('', 410),
            '*' => Http::response('', 500),
        ]);

        $customer = Customer::create(['name' => 'Push Client']);
        $user = User::factory()->create(['role' => Role::CLIENT, 'customer_id' => $customer->id]);
        $outsider = User::factory()->create(['role' => Role::CLIENT]);

        $this->subscribe($user, self::APPLE_ENDPOINT);
        $this->subscribe($user, 'https://fcm.googleapis.com/fcm/send/expired');
        $this->subscribe($outsider, 'https://updates.push.services.mozilla.com/wpush/v2/outsider');

        app(PushNotificationService::class)->toCustomer($customer, 'New invoice #12', 'Tap to pay.', 'https://example.test/client/invoices/12/pay');

        $vapidPublic = $this->vapid['public_key'];
        Http::assertSent(function (Request $request) use ($vapidPublic) {
            if ($request->url() !== self::APPLE_ENDPOINT) {
                return false;
            }

            preg_match('/^vapid t=([^,]+), k=(.+)$/', $request->header('Authorization')[0] ?? '', $matches);
            [$header, $claims, $signature] = explode('.', $matches[1] ?? '..');
            $claims = json_decode(WebPushClient::base64UrlDecode($claims), true);

            return $request->header('Content-Encoding')[0] === 'aes128gcm'
                && ($matches[2] ?? null) === $vapidPublic
                && $claims['aud'] === 'https://web.push.apple.com'
                && $claims['sub'] === 'mailto:push@example.test'
                && $this->verifyEs256("{$header}.".explode('.', $matches[1])[1], WebPushClient::base64UrlDecode($signature), WebPushClient::base64UrlDecode($vapidPublic));
        });
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'mozilla.com'));

        $this->assertDatabaseMissing('web_push_subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/expired']);
        $this->assertDatabaseHas('web_push_subscriptions', ['endpoint' => self::APPLE_ENDPOINT]);
    }

    #[Test]
    public function nothing_is_sent_to_browsers_without_vapid_keys(): void
    {
        Http::fake();
        config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);

        $customer = Customer::create(['name' => 'Push Client']);
        $user = User::factory()->create(['role' => Role::CLIENT, 'customer_id' => $customer->id]);
        $this->subscribe($user, self::APPLE_ENDPOINT);

        app(PushNotificationService::class)->toCustomer($customer, 'Title', 'Body');

        Http::assertNothingSent();
    }

    private function subscribe(User $user, string $endpoint): void
    {
        $keys = $this->subscriptionPayload($endpoint)['keys'];

        WebPushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => WebPushSubscription::hashEndpoint($endpoint),
            'public_key' => $keys['p256dh'],
            'auth_token' => $keys['auth'],
        ]);
    }

    private function subscriptionPayload(string $endpoint): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => WebPushClient::generateVapidKeys()['public_key'],
                'auth' => WebPushClient::base64UrlEncode(random_bytes(16)),
            ],
        ];
    }

    /** RFC 8291 decryption, as the browser does it. */
    private function decrypt(string $body, string $browserSecret, string $browserPublic, string $authSecret): string
    {
        $salt = substr($body, 0, 16);
        $keyIdLength = ord($body[20]);
        $serverPublic = substr($body, 21, $keyIdLength);
        $record = substr($body, 21 + $keyIdLength);

        $privateKey = openssl_pkey_get_private($this->pem('EC PRIVATE KEY', hex2bin('30310201010420').$browserSecret.hex2bin('a00a06082a8648ce3d030107')));
        $sharedSecret = openssl_pkey_derive($this->publicKey($serverPublic), $privateKey, 32);

        $ikm = hash_hkdf('sha256', $sharedSecret, 32, "WebPush: info\0".$browserPublic.$serverPublic, $authSecret);
        $contentKey = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        $plaintext = openssl_decrypt(substr($record, 0, -16), 'aes-128-gcm', $contentKey, OPENSSL_RAW_DATA, $nonce, substr($record, -16));
        $this->assertNotFalse($plaintext, 'The record did not authenticate.');
        $this->assertSame("\x02", substr($plaintext, -1));

        return substr($plaintext, 0, -1);
    }

    private function verifyEs256(string $data, string $rawSignature, string $publicKey): bool
    {
        $integer = function (string $value) {
            $value = ltrim($value, "\0");
            if (ord($value[0]) & 0x80) {
                $value = "\0".$value;
            }

            return "\x02".chr(strlen($value)).$value;
        };
        $sequence = $integer(substr($rawSignature, 0, 32)).$integer(substr($rawSignature, 32));

        return openssl_verify($data, "\x30".chr(strlen($sequence)).$sequence, $this->publicKey($publicKey), OPENSSL_ALGO_SHA256) === 1;
    }

    private function publicKey(string $point): \OpenSSLAsymmetricKey
    {
        return openssl_pkey_get_public($this->pem('PUBLIC KEY', hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$point));
    }

    private function pem(string $label, string $der): string
    {
        return "-----BEGIN {$label}-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END {$label}-----\n";
    }
}
