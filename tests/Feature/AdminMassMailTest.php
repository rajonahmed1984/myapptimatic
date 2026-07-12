<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MassMail;
use App\Models\User;
use App\Jobs\SendMassMailJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminMassMailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function client_role_cannot_access_mass_mail_portal(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($client)
            ->get(route('admin.mass-mail.index'));

        $response->assertStatus(403);
    }

    #[Test]
    public function admin_can_access_mass_mail_portal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->get(route('admin.mass-mail.index'));

        $response->assertOk()
            ->assertSee('Admin\\/MassMail\\/Index', false);
    }

    #[Test]
    public function admin_can_dispatch_mass_mail_campaign(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->post(route('admin.mass-mail.store'), [
                'subject' => 'Promotional Newsletter',
                'body' => '<p>Check out our new features!</p>',
                'target_status' => 'active',
            ]);

        $response->assertRedirect(route('admin.mass-mail.index'));
        $response->assertSessionHas('status', 'Mass mail campaign dispatched successfully.');

        $this->assertDatabaseHas('mass_mails', [
            'subject' => 'Promotional Newsletter',
            'target_status' => 'active',
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        $massMail = MassMail::first();
        Queue::assertPushed(SendMassMailJob::class, function ($job) use ($massMail) {
            return $job->massMailId === $massMail->id;
        });
    }

    #[Test]
    public function mass_mail_job_sends_emails_to_filtered_recipients(): void
    {
        // Seed customer data
        Customer::create([
            'name' => 'Active Customer',
            'email' => 'active@example.com',
            'status' => 'active',
        ]);
        Customer::create([
            'name' => 'Suspended Customer',
            'email' => 'suspended@example.com',
            'status' => 'suspended',
        ]);

        $massMail = MassMail::create([
            'subject' => 'Test Campaign',
            'body' => 'Campaign body content',
            'target_status' => 'active',
            'status' => 'pending',
        ]);

        // Mock MailSender
        $mailSenderMock = $this->mock(\App\Services\Mail\MailSender::class);
        $mailSenderMock->shouldReceive('sendView')
            ->once()
            ->with(
                \App\Enums\MailCategory::SYSTEM,
                'active@example.com',
                'emails.generic',
                \Mockery::on(function ($data) {
                    return $data['subject'] === 'Test Campaign' && $data['bodyHtml'] === 'Campaign body content';
                }),
                'Test Campaign'
            );

        // Run the job synchronously
        $job = new SendMassMailJob($massMail->id);
        app()->call([$job, 'handle']);

        $massMail->refresh();
        $this->assertSame('completed', $massMail->status);
        $this->assertEquals(1, $massMail->total_recipients);
        $this->assertEquals(1, $massMail->sent_count);
    }
}
