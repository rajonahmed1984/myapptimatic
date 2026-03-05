<?php

namespace Tests\Feature\Mail;

use App\Models\MailAccount;
use App\Models\MailAccountAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailInertiaLoginResponseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_mail_login_failure_returns_redirect_for_inertia_requests_not_plain_json(): void
    {
        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);
        \assert($admin instanceof User);

        $mailAccount = MailAccount::query()->create([
            'email' => 'support@gmail.com',
            'display_name' => 'Support Inbox',
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_validate_cert' => true,
            'status' => 'active',
        ]);

        MailAccountAssignment::query()->create([
            'mail_account_id' => $mailAccount->id,
            'assignee_type' => 'user',
            'assignee_id' => $admin->id,
            'can_read' => true,
            'can_manage' => true,
        ]);

        $response = $this->actingAs($admin, 'web')
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'text/html, application/xhtml+xml',
            ])
            ->from(route('admin.apptimatic-email.login'))
            ->post(route('admin.apptimatic-email.login.store'), [
                'email' => $mailAccount->email,
                'password' => 'wrong-password',
                'remember' => false,
            ]);

        $response->assertRedirect(route('admin.apptimatic-email.login'));
        $response->assertSessionHasErrors(['email']);

        $contentType = (string) $response->headers->get('Content-Type', '');
        $this->assertFalse(str_contains(strtolower($contentType), 'application/json'));
    }
}
