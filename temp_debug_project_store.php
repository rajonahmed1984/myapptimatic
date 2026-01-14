<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Admin\ProjectController;
use App\Services\BillingService;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\User;

$admin = User::factory()->create(['role' => 'master_admin']);
$customer = Customer::create(['name' => 'Currency Client']);
$employee = Employee::create(['name' => 'Task Assignee', 'status' => 'active']);

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

$request = Request::create('/admin/projects', 'POST', $payload);
$request->setUserResolver(fn () => $admin);

try {
    $controller = $app->make(ProjectController::class);
    $controller->store($request, $app->make(BillingService::class));
    echo "store succeeded\n";
} catch (Illuminate\Validation\ValidationException $e) {
    echo "validation errors:\n";
    print_r($e->errors());
}
?>
