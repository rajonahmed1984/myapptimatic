<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupportTicketAttachmentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function client_can_view_support_ticket_attachment(): void
    {
        Storage::fake('public');

        $customer = Customer::create([
            'name' => 'Support Client',
        ]);

        $client = User::factory()->create([
            'role' => 'client',
            'customer_id' => $customer->id,
        ]);

        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'user_id' => $client->id,
            'subject' => 'Help needed',
            'status' => 'open',
            'priority' => 'medium',
            'last_reply_at' => now(),
            'last_reply_by' => 'client',
        ]);

        $path = 'support-ticket-replies/test-attachment.png';
        Storage::disk('public')->put($path, 'dummy');

        $reply = SupportTicketReply::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Attachment included.',
            'attachment_path' => $path,
            'is_admin' => false,
        ]);

        $this->actingAs($client)
            ->get(route('support-ticket-replies.attachment', $reply))
            ->assertOk();
    }
}
