<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinanceVatActionContractParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function settings_update_validation_and_success_contracts_are_identical_when_react_flag_toggles(): void
    {
        $admin = $this->createMasterAdmin();
        $contracts = [];

        foreach ([false, true] as $enabled) {
            $this->setUiFlag($enabled);

            $validation = $this->actingAs($admin)
                ->from(route('admin.finance.vat.index'))
                ->put(route('admin.finance.vat.update'), []);

            $validation->assertRedirect(route('admin.finance.vat.index'));
            $validation->assertSessionHasErrors(['tax_mode_default']);
            $validationErrorKeys = $this->sessionErrorKeys();

            $defaultRate = $this->createRate();
            $success = $this->actingAs($admin)
                ->from(route('admin.finance.vat.index'))
                ->put(route('admin.finance.vat.update'), [
                    'enabled' => '1',
                    'tax_mode_default' => 'inclusive',
                    'default_tax_rate_id' => (string) $defaultRate->id,
                    'invoice_tax_note_template' => 'VAT ({rate}%)',
                ]);

            $success->assertRedirect(route('admin.finance.vat.index'));
            $success->assertSessionHas('status', 'VAT settings updated.');

            $settings = TaxSetting::current();
            $this->assertTrue((bool) $settings->enabled);
            $this->assertSame('inclusive', (string) $settings->tax_mode_default);
            $this->assertSame($defaultRate->id, (int) $settings->default_tax_rate_id);
            $this->assertSame('VAT', (string) $settings->invoice_tax_label);

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

    #[Test]
    public function store_rate_validation_and_success_contracts_are_identical_when_react_flag_toggles(): void
    {
        $admin = $this->createMasterAdmin();
        $contracts = [];

        foreach ([false, true] as $enabled) {
            $this->setUiFlag($enabled);

            $validation = $this->actingAs($admin)
                ->from(route('admin.finance.vat.index'))
                ->post(route('admin.finance.vat.rates.store'), []);

            $validation->assertRedirect(route('admin.finance.vat.index'));
            $validation->assertSessionHasErrors(['name', 'rate_percent', 'effective_from']);
            $validationErrorKeys = $this->sessionErrorKeys();

            $name = 'VAT Rate '.uniqid();
            $success = $this->actingAs($admin)
                ->from(route('admin.finance.vat.index'))
                ->post(route('admin.finance.vat.rates.store'), [
                    'name' => $name,
                    'rate_percent' => '15.50',
                    'effective_from' => '2026-06-01',
                    'effective_to' => '2026-12-31',
                    'is_active' => '1',
                ]);

            $success->assertRedirect(route('admin.finance.vat.index'));
            $success->assertSessionHas('status', 'VAT rate created.');
            $this->assertDatabaseHas('tax_rates', [
                'name' => $name,
                'rate_percent' => 15.50,
            ]);

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

    #[Test]
    public function destroy_rate_contract_is_identical_when_react_flag_toggles(): void
    {
        $admin = $this->createMasterAdmin();
        $contracts = [];

        foreach ([false, true] as $enabled) {
            $this->setUiFlag($enabled);

            $rate = $this->createRate();
            $response = $this->actingAs($admin)
                ->from(route('admin.finance.vat.index'))
                ->delete(route('admin.finance.vat.rates.destroy', $rate));

            $response->assertRedirect(route('admin.finance.vat.index'));
            $response->assertSessionHas('status', 'VAT rate deleted.');
            $this->assertDatabaseMissing('tax_rates', ['id' => $rate->id]);

            $contracts[$this->flagKey($enabled)] = $this->responseContract($response);
        }

        $this->assertSame($contracts['off'], $contracts['on']);
    }

    private function createMasterAdmin(): User
    {
        return User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);
    }

    private function createRate(array $overrides = []): TaxRate
    {
        return TaxRate::query()->create(array_merge([
            'name' => 'Rate '.uniqid(),
            'rate_percent' => 10,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'is_active' => true,
        ], $overrides));
    }

    private function setUiFlag(bool $enabled): void
    {
        config()->set('features.admin_finance_vat_index', $enabled);
    }

    /**
     * @return array{status:int,location_path:string}
     */
    private function responseContract($response): array
    {
        $location = (string) $response->headers->get('Location', '');
        $path = parse_url($location, PHP_URL_PATH);
        $normalizedPath = is_string($path)
            ? (string) preg_replace('#/\d+(?=/|$)#', '/{id}', $path)
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
