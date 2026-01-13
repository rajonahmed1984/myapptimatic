<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectCurrencyValidationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function invalid_currency_is_rejected_on_project_create(): void
    {
        $admin = User::factory()->create(['role' => 'master_admin']);

        $customer = Customer::create([
            'name' => 'Currency Client',
        ]);

        $employee = Employee::create([
            'name' => 'Task Assignee',
            'status' => 'active',
        ]);

        $payload = [
            'name' => 'Currency Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'start_date' => now()->toDateString(),
            'expected_end_date' => now()->addDays(10)->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'total_budget' => 1000,
            'initial_payment_amount' => 200,
            'currency' => 'ZZZ',
            'tasks' => [
                [
                    'title' => 'Initial Task',
                    'start_date' => now()->toDateString(),
                    'due_date' => now()->addDays(5)->toDateString(),
                    'assignee' => 'employee:' . $employee->id,
                    'customer_visible' => true,
                ],
            ],
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.projects.store'), $payload);

        $response->assertSessionHasErrors(['currency']);
    }
}
