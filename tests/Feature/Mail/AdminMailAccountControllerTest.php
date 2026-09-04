<?php

namespace Tests\Feature\Mail;

use App\Models\MailAccount;
use App\Models\MailAccountAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminMailAccountControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_create_update_and_delete_mail_account(): void
    {
        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);
        \assert($admin instanceof User);

        $storeResponse = $this->actingAs($admin, 'web')
            ->postJson(route('admin.apptimatic-email.accounts.store'), [
                'email' => 'Inbox.Admin@example.com',
                'display_name' => 'Admin Inbox',
                'imap_host' => 'imap.example.test',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'imap_validate_cert' => true,
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Mailbox created.');

        $mailAccountId = (int) $storeResponse->json('data.id');
        $this->assertGreaterThan(0, $mailAccountId);

        $this->assertDatabaseHas('mail_accounts', [
            'id' => $mailAccountId,
            'email' => 'inbox.admin@example.com',
            'display_name' => 'Admin Inbox',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'web')
            ->putJson(route('admin.apptimatic-email.accounts.update', ['mailAccount' => $mailAccountId]), [
                'email' => 'inbox.admin@example.com',
                'display_name' => 'Admin Inbox Updated',
                'imap_host' => 'imap2.example.test',
                'imap_port' => 993,
                'imap_encryption' => 'tls',
                'imap_validate_cert' => false,
                'status' => 'auth_failed',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Mailbox updated.');

        $this->assertDatabaseHas('mail_accounts', [
            'id' => $mailAccountId,
            'display_name' => 'Admin Inbox Updated',
            'imap_host' => 'imap2.example.test',
            'imap_encryption' => 'tls',
            'imap_validate_cert' => 0,
            'status' => 'auth_failed',
        ]);

        $this->actingAs($admin, 'web')
            ->getJson(route('admin.apptimatic-email.accounts.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin, 'web')
            ->deleteJson(route('admin.apptimatic-email.accounts.destroy', ['mailAccount' => $mailAccountId]))
            ->assertOk()
            ->assertJsonPath('message', 'Mailbox deleted.');

        $this->assertDatabaseMissing('mail_accounts', [
            'id' => $mailAccountId,
        ]);
    }

    #[Test]
    public function admin_can_manage_mail_account_assignments(): void
    {
        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);
        \assert($admin instanceof User);

        $supportUser = User::factory()->create([
            'role' => 'support',
        ]);
        \assert($supportUser instanceof User);

        $mailAccount = MailAccount::query()->create([
            'email' => 'support.mail@example.com',
            'display_name' => 'Support Mail',
            'imap_host' => 'imap.example.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_validate_cert' => true,
            'status' => 'active',
        ]);

        $createAssignmentResponse = $this->actingAs($admin, 'web')
            ->postJson(route('admin.apptimatic-email.assignments.store', ['mailAccount' => $mailAccount->id]), [
                'assignee_type' => 'support',
                'assignee_id' => $supportUser->id,
                'can_read' => true,
                'can_manage' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Mailbox assignment saved.');

        $assignmentId = (int) $createAssignmentResponse->json('data.id');
        $this->assertGreaterThan(0, $assignmentId);

        $this->assertDatabaseHas('mail_account_assignments', [
            'id' => $assignmentId,
            'mail_account_id' => $mailAccount->id,
            'assignee_type' => 'support',
            'assignee_id' => $supportUser->id,
            'can_read' => 1,
            'can_manage' => 0,
        ]);

        $this->actingAs($admin, 'web')
            ->putJson(route('admin.apptimatic-email.assignments.update', [
                'mailAccount' => $mailAccount->id,
                'assignment' => $assignmentId,
            ]), [
                'can_read' => true,
                'can_manage' => true,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Mailbox assignment updated.');

        $this->assertDatabaseHas('mail_account_assignments', [
            'id' => $assignmentId,
            'can_read' => 1,
            'can_manage' => 1,
        ]);

        $this->actingAs($admin, 'web')
            ->deleteJson(route('admin.apptimatic-email.assignments.destroy', [
                'mailAccount' => $mailAccount->id,
                'assignment' => $assignmentId,
            ]))
            ->assertOk()
            ->assertJsonPath('message', 'Mailbox assignment deleted.');

        $this->assertDatabaseMissing('mail_account_assignments', [
            'id' => $assignmentId,
        ]);

        $this->assertSame(0, MailAccountAssignment::query()->count());
    }

    #[Test]
    public function admin_can_update_and_load_mail_server_settings(): void
    {
        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);
        \assert($admin instanceof User);

        $response = $this->actingAs($admin, 'web')
            ->putJson(route('admin.apptimatic-email.settings.update'), [
                'smtp_host' => 'mail.example.com',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'imap_host' => 'mail.example.com',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'domain' => 'example.com',
                'auto_provision' => true,
                'validate_cert' => true,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Mail server & SMTP settings saved successfully.')
            ->assertJsonPath('settings.smtp_host', 'mail.example.com')
            ->assertJsonPath('settings.domain', 'example.com');

        $this->assertSame('mail.example.com', \App\Models\Setting::getValue('mail_server_smtp_host'));
        $this->assertSame('example.com', \App\Models\Setting::getValue('mail_server_domain'));
    }

    #[Test]
    public function auto_provision_creates_mailbox_and_assignment_when_mail_server_configured(): void
    {
        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);
        \assert($admin instanceof User);

        \App\Models\Setting::setValue('mail_server_smtp_host', 'mail.example.com');
        \App\Models\Setting::setValue('mail_server_imap_host', 'mail.example.com');
        \App\Models\Setting::setValue('mail_server_domain', 'example.com');
        \App\Models\Setting::setValue('mail_server_auto_provision', '1');

        $mockImap = $this->createMock(\App\Services\Mail\ImapAuthService::class);
        $mockImap->method('verifyCredentials')->willReturn(true);
        $this->app->instance(\App\Services\Mail\ImapAuthService::class, $mockImap);

        $this->actingAs($admin, 'web')
            ->post(route('admin.apptimatic-email.login.store'), [
                'email' => 'newuser@example.com',
                'password' => 'secret123',
                'remember' => false,
            ])
            ->assertRedirect(route('admin.apptimatic-email.inbox'));

        $this->assertDatabaseHas('mail_accounts', [
            'email' => 'newuser@example.com',
            'imap_host' => 'mail.example.com',
        ]);

        $createdAccount = MailAccount::query()->where('email', 'newuser@example.com')->first();
        $this->assertNotNull($createdAccount);

        $this->assertDatabaseHas('mail_account_assignments', [
            'mail_account_id' => $createdAccount->id,
            'assignee_type' => 'user',
            'assignee_id' => $admin->id,
            'can_read' => 1,
            'can_manage' => 1,
        ]);
    }
}
