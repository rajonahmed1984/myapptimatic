<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentGatewayActionContractParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function update_validation_and_success_contracts_are_identical_when_react_flag_toggles(): void
    {
        $admin = $this->createMasterAdmin();
        $contracts = [];

        foreach ([false, true] as $enabled) {
            $this->setUiFlag($enabled);
            $gateway = $this->createGateway();

            $validation = $this->actingAs($admin)
                ->from(route('admin.payment-gateways.edit', $gateway))
                ->put(route('admin.payment-gateways.update', $gateway), []);

            $validation->assertRedirect(route('admin.payment-gateways.edit', $gateway));
            $validation->assertSessionHasErrors(['name']);
            $validationErrorKeys = $this->sessionErrorKeys();

            $updatedName = 'Manual Gateway '.uniqid();
            $success = $this->actingAs($admin)
                ->from(route('admin.payment-gateways.edit', $gateway))
                ->put(route('admin.payment-gateways.update', $gateway), [
                    'name' => $updatedName,
                    'sort_order' => 5,
                    'is_active' => '1',
                    'instructions' => 'Send transfer receipt to billing.',
                    'account_name' => 'Acme Billing',
                    'account_number' => '123456789',
                    'bank_name' => 'Demo Bank',
                    'branch' => 'Main',
                    'routing_number' => '987654',
                    'button_label' => 'Pay Now',
                ]);

            $success->assertRedirect(route('admin.payment-gateways.index'));
            $success->assertSessionHas('status', 'Payment gateway updated.');

            $gateway->refresh();
            $this->assertSame($updatedName, $gateway->name);
            $this->assertTrue((bool) $gateway->is_active);

            $contracts[$this->flagKey($enabled)] = [
                'validation' => array_merge(
                    $this->responseContract($validation),
                    ['errors' => $validationErrorKeys]
                ),
                'success' => $this->responseContract($success),
            ];
        }

        $this->assertSame($contracts['off'], $contracts['on']);
    }

    private function createMasterAdmin(): User
    {
        return User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);
    }

    private function createGateway(): PaymentGateway
    {
        return PaymentGateway::query()->create([
            'name' => 'Manual Gateway '.uniqid(),
            'slug' => 'manual-parity-'.uniqid(),
            'driver' => 'manual',
            'is_active' => false,
            'sort_order' => 1,
            'settings' => [
                'instructions' => '',
                'account_name' => '',
                'account_number' => '',
                'bank_name' => '',
                'branch' => '',
                'routing_number' => '',
                'button_label' => '',
            ],
        ]);
    }

    private function setUiFlag(bool $enabled): void
    {
        config()->set('features.admin_payment_gateways_index', $enabled);
    }

    /**
     * @return array{status:int,location_path:string}
     */
    private function responseContract($response): array
    {
        $location = (string) $response->headers->get('Location', '');
        $path = parse_url($location, PHP_URL_PATH);
        $normalizedPath = is_string($path)
            ? (string) preg_replace('#/\\d+(?=/|$)#', '/{id}', $path)
            : '';

        return [
            'status' => $response->getStatusCode(),
            'location_path' => $normalizedPath,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function sessionErrorKeys(): array
    {
        $errors = session('errors');
        if (! $errors) {
            return [];
        }

        $keys = array_keys($errors->getBag('default')->messages());
        sort($keys);

        return $keys;
    }

    private function flagKey(bool $enabled): string
    {
        return $enabled ? 'on' : 'off';
    }
}
