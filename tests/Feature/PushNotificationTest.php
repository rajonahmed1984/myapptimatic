<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\PushDevice;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private string $credentialsPath;

    protected function setUp(): void
    {
        parent::setUp();

        // A throwaway key generated for this test only; it signs the fake OAuth
        // assertion and is never accepted by Google.
        $privateKey = file_get_contents(base_path('tests/Fixtures/fcm-test-private-key.pem'));

        $this->credentialsPath = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($this->credentialsPath, json_encode([
            'type' => 'service_account',
            'project_id' => 'demo-project',
            'client_email' => 'push@demo-project.iam.gserviceaccount.com',
            'private_key' => $privateKey,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));

        config(['services.fcm.credentials' => $this->credentialsPath]);
    }

    protected function tearDown(): void
    {
        @unlink($this->credentialsPath);

        parent::tearDown();
    }

    #[Test]
    public function signed_in_user_can_register_and_move_a_device_token(): void
    {
        $first = User::factory()->create(['role' => Role::CLIENT]);
        $second = User::factory()->create(['role' => Role::CLIENT]);

        $this->actingAs($first)
            ->postJson(route('push-devices.store'), ['token' => 'device-token-1', 'platform' => 'android'])
            ->assertOk();

        $this->assertDatabaseHas('push_devices', ['token' => 'device-token-1', 'user_id' => $first->id]);

        // The same install signed in as someone else now belongs to them.
        $this->actingAs($second)
            ->postJson(route('push-devices.store'), ['token' => 'device-token-1', 'platform' => 'android'])
            ->assertOk();

        $this->assertSame(1, PushDevice::query()->count());
        $this->assertDatabaseHas('push_devices', ['token' => 'device-token-1', 'user_id' => $second->id]);
    }

    #[Test]
    public function guests_cannot_register_a_device(): void
    {
        $this->postJson(route('push-devices.store'), ['token' => 'device-token-1'])
            ->assertUnauthorized();
    }

    #[Test]
    public function logout_detaches_the_device_registered_in_that_session(): void
    {
        $user = User::factory()->create(['role' => Role::CLIENT]);

        $this->actingAs($user)
            ->postJson(route('push-devices.store'), ['token' => 'device-token-1'])
            ->assertOk();

        $this->post(route('logout'))->assertRedirect();

        $this->assertDatabaseMissing('push_devices', ['token' => 'device-token-1']);
    }

    #[Test]
    public function customer_push_reaches_its_users_and_drops_stale_tokens(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'access-123', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => function (Request $request) {
                return $request['message']['token'] === 'stale-token'
                    ? Http::response(['error' => ['code' => 404, 'status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404)
                    : Http::response(['name' => 'projects/demo-project/messages/1']);
            },
        ]);

        $customer = Customer::create(['name' => 'Push Client']);
        $user = User::factory()->create(['role' => Role::CLIENT, 'customer_id' => $customer->id]);
        $outsider = User::factory()->create(['role' => Role::CLIENT]);

        PushDevice::create(['user_id' => $user->id, 'token' => 'live-token']);
        PushDevice::create(['user_id' => $user->id, 'token' => 'stale-token']);
        PushDevice::create(['user_id' => $outsider->id, 'token' => 'outsider-token']);

        app(PushNotificationService::class)->toCustomer($customer, 'New invoice #12', 'Tap to pay.', 'https://example.test/client/invoices/12/pay');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'fcm.googleapis.com/v1/projects/demo-project/messages:send')
            && $request['message']['token'] === 'live-token'
            && $request['message']['notification']['title'] === 'New invoice #12'
            && ((array) $request['message']['data'])['url'] === '/client/invoices/12/pay'
            && $request->hasHeader('Authorization', 'Bearer access-123'));
        Http::assertNotSent(fn (Request $request) => ($request['message']['token'] ?? null) === 'outsider-token');

        $this->assertDatabaseMissing('push_devices', ['token' => 'stale-token']);
        $this->assertDatabaseHas('push_devices', ['token' => 'live-token']);
    }

    #[Test]
    public function nothing_is_sent_when_firebase_is_not_configured(): void
    {
        Http::fake();
        config(['services.fcm.credentials' => storage_path('app/firebase/missing.json')]);

        $customer = Customer::create(['name' => 'Push Client']);
        $user = User::factory()->create(['role' => Role::CLIENT, 'customer_id' => $customer->id]);
        PushDevice::create(['user_id' => $user->id, 'token' => 'live-token']);

        app(PushNotificationService::class)->toCustomer($customer, 'Title', 'Body');

        Http::assertNothingSent();
    }
}
