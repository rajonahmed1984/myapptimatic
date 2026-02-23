<?php

namespace Tests\Feature\Smoke;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NoBreakSmokeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_and_logout_smoke_for_each_portal_guard(): void
    {
        config()->set('recaptcha.enabled', false);

        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'password' => 'secret-pass',
        ]);
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
            'password' => 'secret-pass',
        ]);
        $employee = $this->createEmployeeUser('secret-pass');
        $sales = $this->createSalesUser('secret-pass');
        $support = User::factory()->create([
            'role' => Role::SUPPORT,
            'password' => 'secret-pass',
        ]);

        $cases = [
            [$client, 'login.attempt', 'client.dashboard', 'web', 'login'],
            [$admin, 'admin.login.attempt', 'admin.dashboard', 'web', 'admin.login'],
            [$employee, 'employee.login.attempt', 'employee.dashboard', 'employee', 'employee.login'],
            [$sales, 'sales.login.attempt', 'rep.dashboard', 'sales', 'sales.login'],
            [$support, 'support.login.attempt', 'support.dashboard', 'support', 'support.login'],
        ];

        foreach ($cases as [$user, $attemptRoute, $targetRoute, $guard, $loginRoute]) {
            $loginResponse = $this->post(route($attemptRoute), [
                'email' => $user->email,
                'password' => 'secret-pass',
            ]);

            $loginResponse->assertRedirect(route($targetRoute, [], false));
            $this->assertAuthenticatedAs($user, $guard);

            $logoutResponse = $this->post(route('logout'));
            $logoutResponse->assertRedirect(route($loginRoute));
            $this->assertGuest($guard);
        }
    }

    #[Test]
    public function unauthorized_user_gets_403_for_admin_route(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        $this->actingAs($client, 'web')
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    #[Test]
    public function master_admin_income_category_crud_smoke(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $storeResponse = $this->actingAs($admin)->post(route('admin.income.categories.store'), [
            'name' => 'Smoke Category',
            'description' => 'Smoke test category',
            'status' => 'active',
        ]);

        $storeResponse->assertRedirect();
        $storeResponse->assertSessionHasNoErrors();

        $category = IncomeCategory::query()->firstOrFail();

        $updateResponse = $this->actingAs($admin)->put(route('admin.income.categories.update', $category), [
            'name' => 'Smoke Category Updated',
            'description' => 'Updated description',
            'status' => 'inactive',
        ]);
        $updateResponse->assertRedirect(route('admin.income.categories.index'));
        $updateResponse->assertSessionHasNoErrors();

        $deleteResponse = $this->actingAs($admin)->delete(route('admin.income.categories.destroy', $category));
        $deleteResponse->assertRedirect();
        $deleteResponse->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('income_categories', ['id' => $category->id]);
    }

    #[Test]
    public function file_upload_endpoint_smoke_for_income_attachment(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $category = IncomeCategory::query()->create([
            'name' => 'Upload Category',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.income.store'), [
            'income_category_id' => $category->id,
            'title' => 'Upload smoke income',
            'amount' => 150.25,
            'income_date' => now()->toDateString(),
            'notes' => 'Upload smoke',
            'attachment' => UploadedFile::fake()->create('receipt.pdf', 120, 'application/pdf'),
        ]);

        $response->assertRedirect(route('admin.income.index'));
        $response->assertSessionHasNoErrors();

        $income = Income::query()->latest('id')->firstOrFail();
        $this->assertNotNull($income->attachment_path);
        Storage::disk('public')->assertExists($income->attachment_path);
    }

    #[Test]
    public function pdf_download_endpoint_smoke_for_client_invoice(): void
    {
        $customer = Customer::query()->create([
            'name' => 'PDF Client',
        ]);

        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'number' => 'INV-SMOKE-1001',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
            'type' => 'project_initial_payment',
        ]);

        $response = $this->actingAs($client)->get(route('client.invoices.download', $invoice));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
    }

    #[Test]
    public function sse_stream_endpoint_smoke_for_client_project_chat(): void
    {
        $customer = Customer::query()->create([
            'name' => 'SSE Client',
        ]);

        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $project = Project::query()->create([
            'name' => 'SSE Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1200,
            'initial_payment_amount' => 200,
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($client)->get(route('client.projects.chat.stream', $project));

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('cache-control'));
    }

    private function createEmployeeUser(string $password = 'password'): User
    {
        $user = User::factory()->create([
            'role' => Role::EMPLOYEE,
            'password' => $password,
        ]);

        Employee::query()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => 'active',
        ]);

        return $user;
    }

    private function createSalesUser(string $password = 'password'): User
    {
        $user = User::factory()->create([
            'role' => Role::SALES,
            'password' => $password,
        ]);

        SalesRepresentative::query()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => 'active',
        ]);

        return $user;
    }
}
