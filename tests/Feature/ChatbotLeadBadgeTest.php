<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ChatbotLead;
use App\Models\User;
use App\Services\HeaderStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatbotLeadBadgeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function header_stats_service_accurately_counts_unread_chatbot_leads(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        ChatbotLead::create([
            'name' => 'Lead One',
            'email' => 'lead1@example.com',
            'is_read' => false,
        ]);

        ChatbotLead::create([
            'name' => 'Lead Two',
            'email' => 'lead2@example.com',
            'is_read' => true,
            'read_at' => now(),
        ]);

        ChatbotLead::create([
            'name' => 'Lead Three',
            'email' => 'lead3@example.com',
            'is_read' => false,
        ]);

        $this->actingAs($admin);
        $stats = app(HeaderStatsService::class)->admin(request());

        $this->assertSame(2, $stats['unread_chatbot_leads']);
    }

    #[Test]
    public function chatbot_leads_index_renders_with_unread_count_and_allows_toggling_read_status(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $lead = ChatbotLead::create([
            'name' => 'Lead Unread',
            'email' => 'unread@example.com',
            'is_read' => false,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.chatbot-leads.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/ChatbotLeads/Index')
            ->has('leads', 1)
            ->where('leads.0.is_read', false)
            ->where('unreadCount', 1)
        );

        // Toggle to read
        $toggleResponse = $this->actingAs($admin)->post(route('admin.chatbot-leads.toggle-read', $lead->id));
        $toggleResponse->assertRedirect();

        $lead->refresh();
        $this->assertTrue($lead->is_read);
        $this->assertNotNull($lead->read_at);

        // Mark all as read endpoint
        $lead2 = ChatbotLead::create([
            'name' => 'Lead Another',
            'email' => 'another@example.com',
            'is_read' => false,
        ]);

        $this->actingAs($admin)->post(route('admin.chatbot-leads.mark-all-read'))->assertRedirect();
        $lead2->refresh();
        $this->assertTrue($lead2->is_read);
    }
}
