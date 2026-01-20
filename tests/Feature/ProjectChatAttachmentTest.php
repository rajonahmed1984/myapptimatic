<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectChatAttachmentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function client_can_upload_and_view_project_chat_attachment(): void
    {
        Storage::fake('public');

        $customer = Customer::create([
            'name' => 'Chat Client',
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

        $client = User::factory()->create([
            'role' => 'client',
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($client)
            ->post(route('client.projects.chat.store', $project), [
                'attachment' => UploadedFile::fake()->image('chat.png'),
            ])
            ->assertRedirect();

        $message = ProjectMessage::query()->latest('id')->firstOrFail();
        $this->assertNotEmpty($message->attachment_path);
        Storage::disk('public')->assertExists($message->attachment_path);

        $this->actingAs($client)
            ->get(route('client.projects.chat.messages.attachment', [$project, $message]))
            ->assertOk();
    }
}
