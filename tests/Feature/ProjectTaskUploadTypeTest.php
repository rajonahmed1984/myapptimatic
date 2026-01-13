<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectTaskUploadTypeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function upload_task_requires_attachment(): void
    {
        $admin = User::factory()->create(['role' => 'master_admin']);

        $customer = Customer::create([
            'name' => 'Upload Client',
        ]);

        $project = Project::create([
            'name' => 'Upload Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $employee = Employee::create([
            'user_id' => $admin->id,
            'name' => 'Task Assignee',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.projects.tasks.store', $project), [
                'title' => 'Upload Task',
                'task_type' => 'upload',
                'priority' => 'medium',
                'start_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'assignee' => 'employee:' . $employee->id,
            ]);

        $response->assertSessionHasErrors('attachment');
    }
}
