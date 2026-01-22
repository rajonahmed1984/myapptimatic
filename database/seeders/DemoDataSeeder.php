<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskActivity;
use App\Models\ProjectTaskAssignment;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('Password123!');

        $customers = $this->seedCustomers($password);
        $employees = $this->seedEmployees($password);
        $salesReps = $this->seedSalesReps($password);
        $projects = $this->seedProjects($customers, $employees, $salesReps);

        $this->seedTasks($projects, $employees, $salesReps);
    }

    private function seedCustomers(string $password): Collection
    {
        $names = [
            'Acme Labs',
            'Brightline LLC',
            'Cedar Works',
            'Delta Studio',
        ];

        return collect($names)->map(function (string $name, int $index) use ($password) {
            $email = 'customer' . ($index + 1) . '@example.com';

            $customer = Customer::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'company_name' => $name,
                    'phone' => '555-010' . $index,
                    'address' => '123 Main St, Suite ' . ($index + 1),
                    'status' => 'active',
                    'notes' => 'Seeded customer account.',
                ]
            );

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                    'role' => 'client',
                    'customer_id' => $customer->id,
                ]
            );

            return $customer;
        });
    }

    private function seedEmployees(string $password): Collection
    {
        $names = [
            'Adnan Rahman',
            'Sara Ali',
            'Nina Roy',
            'Kamal Hossain',
        ];

        return collect($names)->map(function (string $name, int $index) use ($password) {
            $email = 'employee' . ($index + 1) . '@example.com';

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                    'role' => 'support',
                ]
            );

            return Employee::updateOrCreate(
                ['email' => $email],
                [
                    'user_id' => $user->id,
                    'name' => $name,
                    'phone' => '555-020' . $index,
                    'designation' => 'Engineer',
                    'department' => 'Delivery',
                    'employment_type' => 'full_time',
                    'work_mode' => 'remote',
                    'join_date' => now()->subDays(30 * ($index + 1)),
                    'status' => 'active',
                ]
            );
        });
    }

    private function seedSalesReps(string $password): Collection
    {
        $names = [
            'Ibrahim Saleh',
            'Maya Khan',
        ];

        return collect($names)->map(function (string $name, int $index) use ($password) {
            $email = 'sales' . ($index + 1) . '@example.com';

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                    'role' => 'sales',
                ]
            );

            return SalesRepresentative::updateOrCreate(
                ['email' => $email],
                [
                    'user_id' => $user->id,
                    'name' => $name,
                    'phone' => '555-030' . $index,
                    'status' => 'active',
                    'payout_method_default' => 'bank',
                    'metadata' => ['region' => 'local'],
                ]
            );
        });
    }

    private function seedProjects(Collection $customers, Collection $employees, Collection $salesReps): Collection
    {
        $templates = [
            ['name' => 'Website Revamp', 'type' => 'website', 'status' => 'ongoing'],
            ['name' => 'CRM Upgrade', 'type' => 'software', 'status' => 'hold'],
            ['name' => 'Mobile App Sprint', 'type' => 'software', 'status' => 'ongoing'],
            ['name' => 'Marketing Portal', 'type' => 'website', 'status' => 'complete'],
            ['name' => 'Automation Setup', 'type' => 'software', 'status' => 'ongoing'],
            ['name' => 'Analytics Refresh', 'type' => 'software', 'status' => 'cancel'],
        ];

        $projects = collect();

        foreach ($templates as $index => $template) {
            $customer = $customers[$index % $customers->count()];
            $startDate = now()->subDays(14 * ($index + 1));
            $expectedEnd = $startDate->copy()->addDays(45);
            $dueDate = $startDate->copy()->addDays(60);

            $totalBudget = 8000 + ($index * 1500);
            $initialPayment = round($totalBudget * 0.2, 2);

            $project = Project::updateOrCreate(
                ['name' => $template['name'], 'customer_id' => $customer->id],
                [
                    'type' => $template['type'],
                    'status' => $template['status'],
                    'start_date' => $startDate,
                    'expected_end_date' => $expectedEnd,
                    'due_date' => $dueDate,
                    'notes' => 'Seeded project for demos.',
                    'total_budget' => $totalBudget,
                    'initial_payment_amount' => $initialPayment,
                    'currency' => 'USD',
                    'budget_amount' => $totalBudget,
                ]
            );

            $employeeIds = $employees->pluck('id')->shuffle()->take(min(2, $employees->count()))->values()->all();
            $project->employees()->sync($employeeIds);

            $repIds = $salesReps->pluck('id')->shuffle()->take(min(1, $salesReps->count()))->values()->all();
            $repSync = [];
            foreach ($repIds as $repId) {
                $repSync[$repId] = ['amount' => round($totalBudget * 0.1, 2)];
            }
            if (! empty($repSync)) {
                $project->salesRepresentatives()->sync($repSync);
            }

            $project->sales_rep_ids = $repIds;
            $project->save();

            $projects->push($project);
        }

        return $projects;
    }

    private function seedTasks(Collection $projects, Collection $employees, Collection $salesReps): void
    {
        $adminId = User::query()
            ->whereIn('role', ['admin', 'master_admin', 'sub_admin'])
            ->value('id');

        $taskTypes = ['bug', 'feature', 'support', 'design', 'custom'];
        $statuses = ['pending', 'in_progress', 'blocked', 'completed', 'done'];
        $priorities = ['low', 'medium', 'high'];

        foreach ($projects as $projectIndex => $project) {
            for ($i = 1; $i <= 3; $i++) {
                $useSalesRep = $salesReps->isNotEmpty() && ($i % 2 === 0);
                $assigneeType = $useSalesRep ? 'sales_rep' : 'employee';
                $assigneeId = $useSalesRep
                    ? $salesReps->random()->id
                    : $employees->random()->id;

                $status = $statuses[($projectIndex + $i) % count($statuses)];
                $progress = match ($status) {
                    'completed', 'done' => 100,
                    'in_progress' => 50,
                    'blocked' => 20,
                    default => 0,
                };

                $startDate = now()->subDays(5 * $i);
                $dueDate = now()->addDays(10 * $i);

                $task = ProjectTask::updateOrCreate(
                    ['project_id' => $project->id, 'title' => $project->name . ' Task ' . $i],
                    [
                        'description' => 'Seeded task for ' . $project->name . '.',
                        'task_type' => $taskTypes[($projectIndex + $i) % count($taskTypes)],
                        'status' => $status,
                        'priority' => $priorities[($i - 1) % count($priorities)],
                        'start_date' => $startDate,
                        'due_date' => $dueDate,
                        'assigned_type' => $assigneeType,
                        'assigned_id' => $assigneeId,
                        'customer_visible' => $i % 2 === 1,
                        'progress' => $progress,
                        'created_by' => $adminId,
                        'time_estimate_minutes' => 120,
                        'tags' => ['seeded', 'demo'],
                        'relationship_ids' => [],
                    ]
                );

                ProjectTaskAssignment::updateOrCreate(
                    [
                        'project_task_id' => $task->id,
                        'assignee_type' => $assigneeType,
                        'assignee_id' => $assigneeId,
                    ],
                    []
                );

                ProjectTaskActivity::updateOrCreate(
                    [
                        'project_task_id' => $task->id,
                        'type' => 'system',
                        'message' => 'Task created.',
                    ],
                    [
                        'actor_type' => 'admin',
                        'actor_id' => $adminId ?? 0,
                    ]
                );

                ProjectTaskActivity::updateOrCreate(
                    [
                        'project_task_id' => $task->id,
                        'type' => 'comment',
                        'message' => 'Initial update from the team.',
                    ],
                    [
                        'actor_type' => 'admin',
                        'actor_id' => $adminId ?? 0,
                    ]
                );
            }
        }
    }
}
