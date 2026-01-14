<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_creation_forces_client_role(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $response = $this->actingAs($admin)->post(route('admin.customers.store'), [
            'name' => 'Acme Ltd',
            'status' => 'active',
            'email' => 'client@example.com',
            'user_password' => 'secret1234',
            'send_account_message' => false,
            'role' => 'employee',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'client@example.com',
            'role' => Role::CLIENT,
        ]);
    }

    public function test_employee_creation_links_user_with_employee_role(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $user = User::factory()->create(['role' => Role::SALES]);

        $response = $this->actingAs($admin)->post(route('admin.hr.employees.store'), [
            'user_id' => $user->id,
            'name' => 'Jane Employee',
            'email' => 'employee@example.com',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
            'join_date' => now()->toDateString(),
            'status' => 'active',
            'salary_type' => 'monthly',
            'currency' => 'BDT',
            'basic_pay' => 50000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => Role::EMPLOYEE,
        ]);
        $this->assertDatabaseHas('employees', [
            'user_id' => $user->id,
            'email' => 'employee@example.com',
        ]);
    }

    public function test_sales_user_creation_forces_sales_role(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $response = $this->actingAs($admin)->post(route('admin.users.store', Role::SALES), [
            'name' => 'Sales User',
            'email' => 'sales@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'support',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'sales@example.com',
            'role' => Role::SALES,
        ]);
    }

    public function test_support_user_creation_forces_support_role(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $response = $this->actingAs($admin)->post(route('admin.users.store', Role::SUPPORT), [
            'name' => 'Support User',
            'email' => 'support@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'hacker',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'support@example.com',
            'role' => Role::SUPPORT,
        ]);
    }

    public function test_sales_rep_creation_forces_sales_role(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $user = User::factory()->create(['role' => Role::SUPPORT]);

        $response = $this->actingAs($admin)->post(route('admin.sales-reps.store'), [
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => Role::SALES,
        ]);
    }

    public function test_master_admin_creation_flow_still_works(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $response = $this->actingAs($admin)->post(route('admin.users.store', Role::MASTER_ADMIN), [
            'name' => 'New Master',
            'email' => 'master@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'master@example.com',
            'role' => Role::MASTER_ADMIN,
        ]);
    }
}
