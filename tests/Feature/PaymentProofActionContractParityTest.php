<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Models\PaymentGateway;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentProofActionContractParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function approve_action_contract_is_identical_when_react_flag_toggles(): void
    {
        $admin = $this->createMasterAdmin();
        $contracts = [];

        foreach ([false, true] as $enabled) {
            $this->setUiFlag($enabled);

            [$attempt, $proof] = $this->createPaymentProofFixture();

            $response = $this->actingAs($admin)
                ->from(route('admin.payment-proofs.index'))
                ->post(route('admin.payment-proofs.approve', $proof));

            $response->assertRedirect(route('admin.payment-proofs.index'));
            $response->assertSessionHas('status', 'Manual payment approved.');

            $attempt->refresh();
            $proof->refresh();

            $this->assertSame('paid', $attempt->status);
            $this->assertSame('approved', $proof->status);
            $this->assertSame($admin->id, $proof->reviewed_by);

            $contracts[$this->flagKey($enabled)] = $this->responseContract($response);
        }

        $this->assertSame($contracts['off'], $contracts['on']);
    }

    #[Test]
    public function reject_action_contract_is_identical_when_react_flag_toggles(): void
    {
        $admin = $this->createMasterAdmin();
        $contracts = [];

        foreach ([false, true] as $enabled) {
            $this->setUiFlag($enabled);

            [$attempt, $proof] = $this->createPaymentProofFixture();

            $response = $this->actingAs($admin)
                ->from(route('admin.payment-proofs.index'))
                ->post(route('admin.payment-proofs.reject', $proof));

            $response->assertRedirect(route('admin.payment-proofs.index'));
            $response->assertSessionHas('status', 'Manual payment rejected.');

            $attempt->refresh();
            $proof->refresh();

            $this->assertSame('failed', $attempt->status);
            $this->assertSame('rejected', $proof->status);
            $this->assertSame($admin->id, $proof->reviewed_by);

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

    private function createPaymentProofFixture(): array
    {
        $customer = Customer::query()->create([
            'name' => 'Proof Customer',
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'number' => 'INV-PROOF-'.uniqid(),
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 210,
            'late_fee' => 0,
            'total' => 210,
            'currency' => 'USD',
            'type' => 'project_initial_payment',
        ]);

        $gateway = PaymentGateway::query()
            ->where('driver', 'manual')
            ->orderBy('id')
            ->firstOrFail();

        $attempt = PaymentAttempt::query()->create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'payment_gateway_id' => $gateway->id,
            'status' => 'pending',
            'amount' => 210,
            'currency' => 'USD',
            'gateway_reference' => 'PROOF-ATTEMPT-'.uniqid(),
        ]);

        $proof = PaymentProof::query()->create([
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'payment_gateway_id' => $gateway->id,
            'reference' => 'PROOF-REF-'.uniqid(),
            'amount' => 210,
            'paid_at' => now()->toDateString(),
            'notes' => 'Parity fixture',
            'status' => 'pending',
        ]);

        return [$attempt, $proof];
    }

    private function setUiFlag(bool $enabled): void
    {
        config()->set('features.admin_payment_proofs_index', $enabled);
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

    private function flagKey(bool $enabled): string
    {
        return $enabled ? 'on' : 'off';
    }
}
