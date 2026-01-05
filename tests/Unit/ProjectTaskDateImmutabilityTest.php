<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Models\Customer;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectTaskDateImmutabilityTest extends TestCase
{
    protected Project $project;
    protected ProjectTask $task;
    protected User $user;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a customer
        $this->customer = Customer::factory()->create();

        // Create a project
        $this->project = Project::factory()->create([
            'customer_id' => $this->customer->id,
        ]);

        // Create a task
        $this->task = ProjectTask::factory()->create([
            'project_id' => $this->project->id,
            'start_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(7),
        ]);

        // Create a user
        $this->user = User::factory()->create(['role' => 'admin']);
    }

    #[Test]
    public function start_date_cannot_be_changed_after_creation()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Start date is locked and cannot be modified after task creation.');

        $this->task->update([
            'start_date' => Carbon::now()->addDays(1),
        ]);
    }

    #[Test]
    public function due_date_cannot_be_changed_after_creation()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Due date is locked and cannot be modified after task creation.');

        $this->task->update([
            'due_date' => Carbon::now()->addDays(14),
        ]);
    }

    #[Test]
    public function both_dates_cannot_be_changed_together()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Start date is locked and cannot be modified after task creation.');

        $this->task->update([
            'start_date' => Carbon::now()->addDays(1),
            'due_date' => Carbon::now()->addDays(14),
        ]);
    }

    #[Test]
    public function other_fields_can_be_updated_without_affecting_dates()
    {
        $this->task->update([
            'title' => 'Updated Task Title',
            'status' => 'in_progress',
            'progress' => 50,
            'notes' => 'Some notes',
        ]);

        $this->task->refresh();

        $this->assertEquals('Updated Task Title', $this->task->title);
        $this->assertEquals('in_progress', $this->task->status);
        $this->assertEquals(50, $this->task->progress);
        $this->assertEquals('Some notes', $this->task->notes);
    }

    #[Test]
    public function status_can_change_to_completed_and_sets_completed_at()
    {
        $this->task->update([
            'status' => 'completed',
        ]);

        $this->task->refresh();

        $this->assertEquals('completed', $this->task->status);
        $this->assertNotNull($this->task->completed_at);
        $this->assertInstanceOf(Carbon::class, $this->task->completed_at);
    }

    #[Test]
    public function description_can_be_updated()
    {
        $newDescription = "Updated description with\nmultiple lines";

        $this->task->update([
            'description' => $newDescription,
        ]);

        $this->task->refresh();

        $this->assertEquals($newDescription, $this->task->description);
    }

    #[Test]
    public function customer_visible_can_be_toggled()
    {
        $this->assertFalse($this->task->customer_visible);

        $this->task->update([
            'customer_visible' => true,
        ]);

        $this->task->refresh();

        $this->assertTrue($this->task->customer_visible);
    }
}
