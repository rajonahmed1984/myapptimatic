<?php

namespace Tests\Feature\Mail;

use App\Models\MailAccount;
use App\Models\MailAccountAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailBootstrapCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function command_warns_when_bootstrap_config_is_empty(): void
    {
        Config::set('apptimatic_email.bootstrap.mailboxes', []);
        Config::set('apptimatic_email.bootstrap.assignments', []);

        $this->artisan('mail:bootstrap --dry-run')
            ->expectsOutput('No mail bootstrap definitions found in config/apptimatic_email.php.')
            ->expectsOutput('Add bootstrap.mailboxes and bootstrap.assignments, then run this command again.')
            ->assertExitCode(0);
    }

    #[Test]
    public function command_creates_mailbox_and_assignment_from_config(): void
    {
        $user = User::factory()->create([
            'role' => 'support',
        ]);

        Config::set('apptimatic_email.bootstrap.mailboxes', [
            [
                'email' => 'support-mail@example.test',
                'display_name' => 'Support Shared Inbox',
                'imap_host' => 'imap.example.test',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'imap_validate_cert' => true,
                'status' => 'active',
            ],
        ]);

        Config::set('apptimatic_email.bootstrap.assignments', [
            [
                'mailbox_email' => 'support-mail@example.test',
                'actor' => [
                    'type' => 'support',
                    'id' => $user->id,
                ],
                'can_read' => true,
                'can_manage' => false,
            ],
        ]);

        $this->artisan('mail:bootstrap')
            ->expectsOutput('Mail bootstrap summary:')
            ->expectsOutput('Mailboxes created: 1')
            ->expectsOutput('Assignments created: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('mail_accounts', [
            'email' => 'support-mail@example.test',
            'display_name' => 'Support Shared Inbox',
            'imap_host' => 'imap.example.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_validate_cert' => 1,
            'status' => 'active',
        ]);

        $mailbox = MailAccount::query()->where('email', 'support-mail@example.test')->first();
        $this->assertNotNull($mailbox);

        $this->assertDatabaseHas('mail_account_assignments', [
            'mail_account_id' => $mailbox->id,
            'assignee_type' => 'support',
            'assignee_id' => $user->id,
            'can_read' => 1,
            'can_manage' => 0,
        ]);

        $this->assertSame(1, MailAccountAssignment::query()->count());
    }
}
