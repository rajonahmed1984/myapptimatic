<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemoveDuplicateCustomersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_removes_newer_duplicate_and_moves_its_login_to_the_kept_customer(): void
    {
        $original = Customer::create(['name' => 'Original', 'email' => 'dup@example.com', 'status' => 'active']);
        $duplicate = Customer::create(['name' => 'Google Duplicate', 'email' => 'DUP@example.com', 'status' => 'active']);
        $user = $this->clientUser($duplicate, 'dup@example.com');

        $this->artisan('customers:remove-duplicates')->assertSuccessful();

        $this->assertDatabaseMissing('customers', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('customers', ['id' => $original->id]);
        $this->assertSame($original->id, $user->fresh()->customer_id);
    }

    public function test_deletes_duplicate_login_when_kept_customer_already_has_one(): void
    {
        $original = Customer::create(['name' => 'Original', 'email' => 'dup@example.com', 'status' => 'active']);
        $originalUser = $this->clientUser($original, 'original-login@example.com');
        $duplicate = Customer::create(['name' => 'Google Duplicate', 'email' => 'dup@example.com', 'status' => 'active']);
        $duplicateUser = $this->clientUser($duplicate, 'dup@example.com');

        $this->artisan('customers:remove-duplicates')->assertSuccessful();

        $this->assertDatabaseMissing('customers', ['id' => $duplicate->id]);
        $this->assertDatabaseMissing('users', ['id' => $duplicateUser->id]);
        $this->assertSame($original->id, $originalUser->fresh()->customer_id);
    }

    public function test_keeps_customer_with_history_even_when_newer(): void
    {
        $blank = Customer::create(['name' => 'Blank', 'email' => 'dup@example.com', 'status' => 'active']);
        $withHistory = Customer::create(['name' => 'With Invoice', 'email' => 'dup@example.com', 'status' => 'active']);
        $this->invoiceFor($withHistory);

        $this->artisan('customers:remove-duplicates')->assertSuccessful();

        $this->assertDatabaseMissing('customers', ['id' => $blank->id]);
        $this->assertDatabaseHas('customers', ['id' => $withHistory->id]);
    }

    public function test_skips_duplicates_that_both_have_history(): void
    {
        $first = Customer::create(['name' => 'First', 'email' => 'dup@example.com', 'status' => 'active']);
        $second = Customer::create(['name' => 'Second', 'email' => 'dup@example.com', 'status' => 'active']);
        $this->invoiceFor($first, 'INV-DUP-1');
        $this->invoiceFor($second, 'INV-DUP-2');

        $this->artisan('customers:remove-duplicates')->assertSuccessful();

        $this->assertSame(2, Customer::count());
    }

    public function test_dry_run_changes_nothing(): void
    {
        Customer::create(['name' => 'Original', 'email' => 'dup@example.com', 'status' => 'active']);
        Customer::create(['name' => 'Duplicate', 'email' => 'dup@example.com', 'status' => 'active']);

        $this->artisan('customers:remove-duplicates', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(2, Customer::count());
    }

    private function clientUser(Customer $customer, string $email): User
    {
        return User::create([
            'name' => $customer->name,
            'email' => $email,
            'password' => 'secret123',
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
            'currency' => Currency::DEFAULT,
            'status' => 'active',
        ]);
    }

    private function invoiceFor(Customer $customer, string $number = 'INV-DUP-1'): Invoice
    {
        return Invoice::create([
            'customer_id' => $customer->id,
            'number' => $number,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
        ]);
    }
}
