<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientSupportTicketUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function client_support_ticket_index_and_create_render_inertia_pages(): void
    {
        $customer = Customer::create(['name' => 'Support Customer']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($client)
            ->get(route('client.support-tickets.index'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Client\\/SupportTickets\\/Index', false);

        $this->actingAs($client)
            ->get(route('client.support-tickets.create'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Client\\/SupportTickets\\/Create', false);
    }

    #[Test]
    public function client_support_ticket_show_renders_inertia_for_owner(): void
    {
        $customer = Customer::create(['name' => 'Support Customer']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'user_id' => $client->id,
            'subject' => 'Need help',
            'priority' => 'medium',
            'status' => 'open',
            'last_reply_at' => now(),
            'last_reply_by' => 'client',
        ]);

        $this->actingAs($client)
            ->get(route('client.support-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Client\\/SupportTickets\\/Show', false);
    }

    #[Test]
    public function client_support_ticket_show_returns_not_found_for_other_customer(): void
    {
        $owner = Customer::create(['name' => 'Owner']);
        $other = Customer::create(['name' => 'Other']);

        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $other->id,
        ]);

        $ownerUser = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $owner->id,
        ]);

        $ticket = SupportTicket::create([
            'customer_id' => $owner->id,
            'user_id' => $ownerUser->id,
            'subject' => 'Private ticket',
            'priority' => 'low',
            'status' => 'open',
            'last_reply_at' => now(),
            'last_reply_by' => 'client',
        ]);

        $this->actingAs($client)
            ->get(route('client.support-tickets.show', $ticket))
            ->assertNotFound();
    }
}
