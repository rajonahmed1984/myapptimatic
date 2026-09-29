<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Services\AuthFresh\LoginOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'recaptcha.enabled' => false,
            'recaptcha.site_key' => null,
            'recaptcha.secret_key' => null,
            'recaptcha.project_id' => null,
            'recaptcha.api_key' => null,
        ]);
    }

    public function test_admin_login_always_requires_otp(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
            'password' => 'secret-password',
            'otp_enabled' => false, // even if false, admin MUST require OTP
        ]);

        $response = $this->post(route('admin.login.attempt'), [
            'email' => $admin->email,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('login.otp.show', ['portal' => 'admin']));
        $this->assertGuest('web');

        $admin->refresh();
        $this->assertNotNull($admin->login_otp_code);
        $this->assertNotNull($admin->login_otp_expires_at);
    }

    public function test_admin_can_verify_otp_and_login_successfully(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
            'password' => 'secret-password',
        ]);

        // Attempt login
        $this->post(route('admin.login.attempt'), [
            'email' => $admin->email,
            'password' => 'secret-password',
        ]);

        // Manually set a known OTP for test
        $knownCode = '654321';
        $admin->update([
            'login_otp_code' => hash('sha256', $knownCode),
            'login_otp_expires_at' => now()->addMinutes(10),
        ]);

        $verifyResponse = $this->post(route('login.otp.verify'), [
            'otp' => $knownCode,
        ]);

        $verifyResponse->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertNull(session('login.pending_otp'));
    }

    public function test_admin_can_use_local_temporary_otp_in_local_environment(): void
    {
        config(['app.env' => 'local']);

        $admin = User::factory()->create([
            'role' => Role::ADMIN,
            'password' => 'secret-password',
        ]);

        $this->post(route('admin.login.attempt'), [
            'email' => $admin->email,
            'password' => 'secret-password',
        ]);

        // Submit temporary local dev OTP: 123456
        $verifyResponse = $this->post(route('login.otp.verify'), [
            'otp' => LoginOtpService::LOCAL_TEMP_OTP,
        ]);

        $verifyResponse->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_client_without_otp_enabled_logs_in_directly(): void
    {
        $customer = Customer::create([
            'name' => 'Acme Corp',
            'email' => 'client@example.com',
        ]);

        $user = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
            'password' => 'secret-password',
            'otp_enabled' => false,
        ]);

        $response = $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('client.dashboard'));
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_client_with_otp_enabled_requires_otp(): void
    {
        $customer = Customer::create([
            'name' => 'Acme Corp 2',
            'email' => 'client2@example.com',
        ]);

        $user = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
            'password' => 'secret-password',
            'otp_enabled' => true,
        ]);

        $response = $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('login.otp.show', ['portal' => 'web']));
        $this->assertGuest('web');

        // Submitting invalid OTP fails
        $failResponse = $this->post(route('login.otp.verify'), [
            'otp' => '000000',
        ]);
        $failResponse->assertSessionHasErrors('otp');
        $this->assertGuest('web');
    }

    public function test_sales_rep_and_employee_can_toggle_otp_enabled_in_settings(): void
    {
        $repUser = User::factory()->create([
            'role' => Role::SALES,
            'otp_enabled' => false,
        ]);
        SalesRepresentative::create([
            'user_id' => $repUser->id,
            'name' => 'Rep John',
            'email' => $repUser->email,
            'status' => 'active',
        ]);

        $this->actingAs($repUser, 'sales')
            ->put(route('rep.profile.update'), [
                'name' => 'Rep John',
                'email' => $repUser->email,
                'otp_enabled' => true,
            ]);

        $repUser->refresh();
        $this->assertTrue((bool) $repUser->otp_enabled);

        // Employee
        $empUser = User::factory()->create([
            'role' => Role::EMPLOYEE,
            'otp_enabled' => false,
        ]);
        Employee::create([
            'user_id' => $empUser->id,
            'name' => 'Emp Jane',
            'email' => $empUser->email,
            'status' => 'active',
        ]);

        $this->actingAs($empUser, 'employee')
            ->put(route('employee.profile.update'), [
                'name' => 'Emp Jane',
                'email' => $empUser->email,
                'otp_enabled' => true,
            ]);

        $empUser->refresh();
        $this->assertTrue((bool) $empUser->otp_enabled);
    }
}
