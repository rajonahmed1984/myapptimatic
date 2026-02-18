<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Notifications\ProjectChatSummaryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProjectChatSummaryNotificationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_summary_email_sends_only_when_new_messages_exist(): void
    {
        config(['project-chat-notifications.enabled' => true]);

        $customer = Customer::create([
            'name' => 'Chat Client',
            'email' => 'client@example.com',
        ]);

        $clientUser = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
            'email' => 'client@example.com',
        ]);

        $project = Project::create([
            'name' => 'Chat Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $employee = Employee::create([
            'name' => 'Chat Employee',
            'email' => 'employee@example.com',
            'status' => 'active',
        ]);
        $project->employees()->attach($employee->id);

        $salesUser = User::factory()->create([
            'role' => Role::SALES,
            'email' => 'sales@example.com',
        ]);
        $salesRep = SalesRepresentative::create([
            'user_id' => $salesUser->id,
            'name' => 'Sales Rep',
            'email' => 'sales@example.com',
            'status' => 'active',
        ]);
        $project->salesRepresentatives()->attach($salesRep->id, ['amount' => 100]);

        ProjectMessage::create([
            'project_id' => $project->id,
            'author_type' => 'user',
            'author_id' => $clientUser->id,
            'message' => 'First chat message',
        ]);

        Notification::fake();

        $this->artisan('projects:chat-summary-notify')->assertExitCode(0);

        Notification::assertSentOnDemand(ProjectChatSummaryNotification::class, function ($notification, $channels, $notifiable): bool {
            return ($notifiable->routes['mail'] ?? null) === 'client@example.com';
        });
        Notification::assertSentOnDemand(ProjectChatSummaryNotification::class, function ($notification, $channels, $notifiable): bool {
            return ($notifiable->routes['mail'] ?? null) === 'employee@example.com';
        });
        Notification::assertSentOnDemand(ProjectChatSummaryNotification::class, function ($notification, $channels, $notifiable): bool {
            return ($notifiable->routes['mail'] ?? null) === 'sales@example.com';
        });
        Notification::assertSentOnDemandTimes(ProjectChatSummaryNotification::class, 3);

        Notification::fake();
        $this->artisan('projects:chat-summary-notify')->assertExitCode(0);
        Notification::assertNothingSent();

        ProjectMessage::create([
            'project_id' => $project->id,
            'author_type' => 'employee',
            'author_id' => $employee->id,
            'message' => 'Second chat message',
        ]);

        Notification::fake();
        $this->artisan('projects:chat-summary-notify')->assertExitCode(0);
        Notification::assertSentOnDemandTimes(ProjectChatSummaryNotification::class, 3);
    }
}
