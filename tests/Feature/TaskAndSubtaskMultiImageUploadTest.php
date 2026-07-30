<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\ProjectTaskSubtaskComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskAndSubtaskMultiImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Project $project;
    private ProjectTask $task;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $customerUser = User::factory()->create(['role' => 'client']);
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->project = Project::create([
            'customer_id' => $customer->id,
            'name' => 'Test Multi Upload Project',
            'status' => 'active',
        ]);

        $this->task = ProjectTask::create([
            'project_id' => $this->project->id,
            'title' => 'Main Task for Testing Multi Image',
            'task_type' => 'feature',
            'status' => 'pending',
            'priority' => 'medium',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_can_upload_multiple_images_when_creating_subtask(): void
    {
        $file1 = UploadedFile::fake()->image('subtask_img1.jpg');
        $file2 = UploadedFile::fake()->image('subtask_img2.png');

        $response = $this->actingAs($this->admin)
            ->post(route('admin.projects.tasks.subtasks.store', [$this->project, $this->task]), [
                'title' => 'Subtask with 2 Images',
                'images' => [$file1, $file2],
            ]);

        $response->assertRedirect();

        $subtask = ProjectTaskSubtask::where('project_task_id', $this->task->id)->first();
        $this->assertNotNull($subtask);
        $this->assertCount(2, $subtask->allAttachmentUrls());

        foreach ($subtask->allAttachmentUrls() as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_can_upload_images_when_updating_subtask(): void
    {
        $subtask = ProjectTaskSubtask::create([
            'project_task_id' => $this->task->id,
            'title' => 'Original Subtask Title',
            'created_by' => $this->admin->id,
        ]);

        $file1 = UploadedFile::fake()->image('edit_subtask_1.jpg');
        $file2 = UploadedFile::fake()->image('edit_subtask_2.png');

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.projects.tasks.subtasks.update', [$this->project, $this->task, $subtask]), [
                'title' => 'Updated Subtask Title',
                'images' => [$file1, $file2],
            ]);

        $response->assertRedirect();

        $subtask->refresh();
        $this->assertEquals('Updated Subtask Title', $subtask->title);
        $this->assertCount(2, $subtask->allAttachmentUrls());

        foreach ($subtask->allAttachmentUrls() as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_can_upload_multiple_images_to_task(): void
    {
        $file1 = UploadedFile::fake()->image('task_img1.jpg');
        $file2 = UploadedFile::fake()->image('task_img2.png');

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.projects.tasks.update', [$this->project, $this->task]), [
                'title' => $this->task->title,
                'images' => [$file1, $file2],
            ]);

        $response->assertRedirect();

        $this->task->refresh();
        $this->assertCount(2, $this->task->allAttachmentUrls());

        foreach ($this->task->allAttachmentUrls() as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_can_upload_images_with_subtask_comment(): void
    {
        $subtask = ProjectTaskSubtask::create([
            'project_task_id' => $this->task->id,
            'title' => 'Subtask for Comment Test',
            'created_by' => $this->admin->id,
        ]);

        $file1 = UploadedFile::fake()->image('comment_img1.jpg');

        $response = $this->actingAs($this->admin)
            ->post(route('admin.projects.tasks.subtasks.comments.store', [$this->project, $this->task, $subtask]), [
                'message' => 'Here is a subtask comment with an image',
                'images' => [$file1],
            ]);

        $response->assertRedirect();

        $comment = ProjectTaskSubtaskComment::where('project_task_subtask_id', $subtask->id)->first();
        $this->assertNotNull($comment);
        $this->assertCount(1, $comment->allAttachmentUrls());

        foreach ($comment->allAttachmentUrls() as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_can_upload_images_with_subtask_comment_reply(): void
    {
        $subtask = ProjectTaskSubtask::create([
            'project_task_id' => $this->task->id,
            'title' => 'Subtask for Comment Reply Test',
            'created_by' => $this->admin->id,
        ]);

        $parentComment = ProjectTaskSubtaskComment::create([
            'project_task_id' => $this->task->id,
            'project_task_subtask_id' => $subtask->id,
            'actor_type' => 'admin',
            'actor_id' => $this->admin->id,
            'message' => 'Parent comment text',
        ]);

        $file1 = UploadedFile::fake()->image('reply_img1.jpg');

        $response = $this->actingAs($this->admin)
            ->post(route('admin.projects.tasks.subtasks.comments.store', [$this->project, $this->task, $subtask]), [
                'message' => 'Reply text with attached image',
                'parent_id' => $parentComment->id,
                'images' => [$file1],
            ]);

        $response->assertRedirect();

        $reply = ProjectTaskSubtaskComment::where('parent_id', $parentComment->id)->first();
        $this->assertNotNull($reply);
        $this->assertCount(1, $reply->allAttachmentUrls());

        foreach ($reply->allAttachmentUrls() as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_can_delete_individual_subtask_image(): void
    {
        $file1 = UploadedFile::fake()->image('subtask_del1.jpg');
        $file2 = UploadedFile::fake()->image('subtask_del2.png');

        $this->actingAs($this->admin)
            ->post(route('admin.projects.tasks.subtasks.store', [$this->project, $this->task]), [
                'title' => 'Subtask for Image Deletion',
                'images' => [$file1, $file2],
            ]);

        $subtask = ProjectTaskSubtask::where('project_task_id', $this->task->id)->first();
        $paths = $subtask->allAttachmentUrls();
        $this->assertCount(2, $paths);

        $pathToDelete = $paths[0];

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.projects.tasks.subtasks.attachments.destroy', [$this->project, $this->task, $subtask]), [
                'path' => $pathToDelete,
            ]);

        $response->assertRedirect();

        $subtask->refresh();
        $this->assertCount(1, $subtask->allAttachmentUrls());
        Storage::disk('public')->assertMissing($pathToDelete);
    }

    public function test_can_delete_individual_task_image(): void
    {
        $file1 = UploadedFile::fake()->image('task_del1.jpg');
        $file2 = UploadedFile::fake()->image('task_del2.png');

        $this->actingAs($this->admin)
            ->patch(route('admin.projects.tasks.update', [$this->project, $this->task]), [
                'title' => $this->task->title,
                'images' => [$file1, $file2],
            ]);

        $this->task->refresh();
        $paths = $this->task->allAttachmentUrls();
        $this->assertCount(2, $paths);

        $pathToDelete = $paths[0];

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.projects.tasks.attachments.destroy', [$this->project, $this->task]), [
                'path' => $pathToDelete,
            ]);

        $response->assertRedirect();

        $this->task->refresh();
        $this->assertCount(1, $this->task->allAttachmentUrls());
        Storage::disk('public')->assertMissing($pathToDelete);
    }
}
