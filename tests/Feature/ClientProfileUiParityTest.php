<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientProfileUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function client_profile_edit_renders_inertia_page(): void
    {
        $customer = Customer::create(['name' => 'Client Profile']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($client)
            ->get(route('client.profile.edit'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Client\\/Profile\\/Edit', false);
    }
}
