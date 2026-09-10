<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use App\Services\Sms\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceSmsNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const API_URL = 'https://sms.example.test/api/smsSendApi';

    private array $gatewayResponse = ['status' => 'Success', 'message' => 'SMS sent'];

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(fn () => Http::response($this->gatewayResponse));
    }

    #[Test]
    public function invoice_created_sends_reminder_sms_to_customer(): void
    {
        $this->configureGateway();
        $customer = Customer::create(['name' => 'Rahim', 'phone' => '+880 1712-345678']);

        $invoice = $this->createInvoice($customer, 'unpaid');

        Http::assertSentCount(1);
        Http::assertSent(function (HttpRequest $request) use ($invoice) {
            return $request->url() === self::API_URL
                && $request['customer_id'] === '1681'
                && $request['api_key'] === 'test-key'
                && $request['mobile_no'] === '8801712345678'
                && str_contains($request['message'], 'Dear Rahim')
                && str_contains($request['message'], '#'.$invoice->number)
                && str_contains($request['message'], 'BDT 1,500.00');
        });
    }

    #[Test]
    public function invoice_paid_sends_confirmation_sms_once(): void
    {
        $this->configureGateway(['sms_invoice_created_enabled' => '0']);
        $customer = Customer::create(['name' => 'Karim', 'phone' => '01812345678']);
        $invoice = $this->createInvoice($customer, 'unpaid');

        Http::assertNothingSent();

        $invoice->update(['status' => 'paid', 'paid_at' => now()]);
        $invoice->update(['notes' => 'touched again']);

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request) => $request['mobile_no'] === '8801812345678'
            && str_contains($request['message'], 'received your payment'));
    }

    #[Test]
    public function no_sms_when_disabled_or_phone_missing(): void
    {
        $this->configureGateway(['sms_enabled' => '0']);
        $this->createInvoice(Customer::create(['name' => 'Off', 'phone' => '01712345678']), 'unpaid');

        $this->configureGateway();
        $this->createInvoice(Customer::create(['name' => 'No Phone']), 'unpaid');
        $this->createInvoice(Customer::create(['name' => 'Foreign', 'phone' => '+44 7911 123456']), 'unpaid');

        Http::assertNothingSent();
    }

    #[Test]
    public function normalizes_bangladeshi_mobile_numbers(): void
    {
        $sms = app(SmsService::class);

        $this->assertSame('8801712345678', $sms->normalizeMobile('01712345678'));
        $this->assertSame('8801712345678', $sms->normalizeMobile('+8801712345678'));
        $this->assertSame('8801712345678', $sms->normalizeMobile('008801712345678'));
        $this->assertSame('8801712345678', $sms->normalizeMobile('1712345678'));
        $this->assertSame('8801912345678', $sms->normalizeMobile('abc, 01912-345678'));
        $this->assertNull($sms->normalizeMobile('01212345678'));
        $this->assertNull($sms->normalizeMobile(''));
    }

    #[Test]
    public function admin_can_open_sms_settings_and_send_test_sms(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $this->actingAs($admin)
            ->get(route('admin.settings.edit', ['tab' => 'sms']))
            ->assertOk();

        $this->configureGateway();

        $this->actingAs($admin)
            ->postJson(route('admin.settings.sms-test'), ['mobile' => '01712345678'])
            ->assertOk()
            ->assertJson(['success' => true, 'mobile' => '8801712345678']);

        $this->gatewayResponse = ['status' => 'Failed', 'message' => 'IP is not whitelisted'];

        $this->actingAs($admin)
            ->postJson(route('admin.settings.sms-test'), ['mobile' => '01712345678'])
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'IP is not whitelisted']);
    }

    private function configureGateway(array $overrides = []): void
    {
        $values = array_merge([
            'company_name' => 'Apptimatic',
            'sms_enabled' => '1',
            'sms_api_url' => self::API_URL,
            'sms_customer_id' => '1681',
            'sms_api_key' => 'test-key',
            'sms_invoice_created_enabled' => '1',
            'sms_invoice_paid_enabled' => '1',
        ], $overrides);

        foreach ($values as $key => $value) {
            Setting::setValue($key, $value);
        }
    }

    private function createInvoice(Customer $customer, string $status): Invoice
    {
        static $sequence = 0;
        $sequence++;

        return Invoice::create([
            'customer_id' => $customer->id,
            'number' => (string) (5000 + $sequence),
            'status' => $status,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 1500,
            'late_fee' => 0,
            'total' => 1500,
            'currency' => 'BDT',
            'type' => 'project_initial_payment',
        ]);
    }
}
