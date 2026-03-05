<?php

namespace Tests\Feature\Mail;

use App\Models\MailAccount;
use App\Models\MailAccountAssignment;
use App\Models\User;
use App\Services\Mail\ImapAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    #[Test]
    public function unassigned_mailbox_attempt_returns_validation_error_not_403(): void
    {
        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);
        \assert($admin instanceof User);

        $mailAccount = MailAccount::query()->create([
            'email' => 'billing@example.com',
            'display_name' => 'Billing Inbox',
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_validate_cert' => true,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin, 'web')
            ->from(route('admin.apptimatic-email.login'))
            ->post(route('admin.apptimatic-email.login.store'), [
                'email' => $mailAccount->email,
                'password' => 'any-password',
                'remember' => false,
            ]);

        $response->assertRedirect(route('admin.apptimatic-email.login'));
        $response->assertSessionHasErrors(['email']);
        $response->assertStatus(302);
    }

    #[Test]
    public function support_role_can_use_support_assignment_from_admin_mail_login(): void
    {
        config()->set('admin.panel_roles', ['master_admin', 'sub_admin', 'admin', 'support']);

        $supportUser = User::factory()->create([
            'role' => 'support',
        ]);
        \assert($supportUser instanceof User);

        $mailAccount = MailAccount::query()->create([
            'email' => 'support@example.com',
            'display_name' => 'Support Inbox',
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_validate_cert' => true,
            'status' => 'active',
        ]);

        MailAccountAssignment::query()->create([
            'mail_account_id' => $mailAccount->id,
            'assignee_type' => 'support',
            'assignee_id' => $supportUser->id,
            'can_read' => true,
            'can_manage' => false,
        ]);

        $this->mock(ImapAuthService::class, function ($mock): void {
            $mock->shouldReceive('verifyCredentials')
                ->once()
                ->andReturn(true);
        });

        $response = $this->actingAs($supportUser, 'web')
            ->post(route('admin.apptimatic-email.login.store'), [
                'email' => $mailAccount->email,
                'password' => 'correct-password',
                'remember' => false,
            ]);

        $response->assertRedirect(route('admin.apptimatic-email.inbox'));
    }

    #[Test]
    public function server_connectivity_failures_show_unavailable_message(): void
    {
        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);
        \assert($admin instanceof User);

        $mailAccount = MailAccount::query()->create([
            'email' => 'ops@example.com',
            'display_name' => 'Ops Inbox',
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

        $this->mock(ImapAuthService::class, function ($mock): void {
            $mock->shouldReceive('verifyCredentials')
                ->once()
                ->andReturn(false);
            $mock->shouldReceive('lastFailureType')
                ->once()
                ->andReturn('server_unavailable');
            $mock->shouldReceive('lastFailureDetail')
                ->once()
                ->andReturn('connection refused');
        });

        $response = $this->actingAs($admin, 'web')
            ->from(route('admin.apptimatic-email.login'))
            ->post(route('admin.apptimatic-email.login.store'), [
                'email' => $mailAccount->email,
                'password' => 'correct-password',
                'remember' => false,
            ]);

        $response->assertRedirect(route('admin.apptimatic-email.login'));
        $response->assertSessionHasErrors([
            'email' => 'Email server unavailable. Please contact admin.',
        ]);
    }

    #[Test]
    public function mail_login_throttle_redirects_with_friendly_error_message(): void
    {
        Cache::fake();

        config()->set('apptimatic_email.login_rate_limit_attempts', 1);
        config()->set('apptimatic_email.login_rate_limit_decay_minutes', 10);

        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);
        \assert($admin instanceof User);

        $mailAccount = MailAccount::query()->create([
            'email' => 'throttle@example.com',
            'display_name' => 'Throttle Inbox',
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

        $this->mock(ImapAuthService::class, function ($mock): void {
            $mock->shouldReceive('verifyCredentials')
                ->once()
                ->andReturn(false);
            $mock->shouldReceive('lastFailureType')
                ->once()
                ->andReturn('invalid_credentials');
            $mock->shouldReceive('lastFailureDetail')
                ->once()
                ->andReturn('auth failed');
        });

        $this->actingAs($admin, 'web')
            ->from(route('admin.apptimatic-email.login'))
            ->post(route('admin.apptimatic-email.login.store'), [
                'email' => $mailAccount->email,
                'password' => 'wrong-password',
                'remember' => false,
            ])
            ->assertRedirect(route('admin.apptimatic-email.login'))
            ->assertSessionHasErrors(['email' => 'Invalid email or password']);

        $response = $this->actingAs($admin, 'web')
            ->from(route('admin.apptimatic-email.login'))
            ->post(route('admin.apptimatic-email.login.store'), [
                'email' => $mailAccount->email,
                'password' => 'wrong-password',
                'remember' => false,
            ]);

        $response->assertRedirect(route('admin.apptimatic-email.login'));
        $response->assertSessionHasErrors([
            'email' => 'Too many email login attempts. Please try again in 600 seconds.',
        ]);
    }
}
