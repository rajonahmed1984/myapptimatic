<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientProfileUpdateContractTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function profile_update_success_contract_remains_redirect_with_flash(): void
    {
        $customer = Customer::create([
            'name' => 'Old Name',
            'email' => 'old@example.test',
        ]);

        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
            'name' => 'Old Name',
            'email' => 'old@example.test',
        ]);

        $response = $this->actingAs($client)->put(route('client.profile.update'), [
            'name' => 'Updated Name',
            'email' => 'updated@example.test',
        ]);

        $response->assertRedirect(route('client.profile.edit'));
        $response->assertSessionHas('status', 'Profile updated.');

        $client->refresh();
        $customer->refresh();

        $this->assertSame('Updated Name', $client->name);
        $this->assertSame('updated@example.test', $client->email);
        $this->assertSame('Updated Name', $customer->name);
        $this->assertSame('updated@example.test', $customer->email);
    }

    #[Test]
    public function profile_update_password_requires_current_password_when_new_password_present(): void
    {
        $customer = Customer::create(['name' => 'Client']);

        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $response = $this->actingAs($client)->from(route('client.profile.edit'))->put(route('client.profile.update'), [
            'name' => $client->name,
            'email' => $client->email,
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ]);

        $response->assertRedirect(route('client.profile.edit'));
        $response->assertSessionHasErrors('current_password');
    }
}
