<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurringExpenseActionContractParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function store_validation_contract_is_identical_when_react_flags_toggle(): void
    {
        $admin = $this->createMasterAdmin();
        $contracts = [];

        foreach ([false, true] as $enabled) {
            $this->setRecurringUiFlags($enabled);

            $response = $this->actingAs($admin)
                ->from(route('admin.expenses.recurring.create'))
                ->post(route('admin.expenses.recurring.store'), []);

            $response->assertRedirect(route('admin.expenses.recurring.create'));
            $response->assertSessionHasErrors(['category_id', 'title', 'amount', 'recurrence_type', 'recurrence_interval', 'start_date']);
            $this->assertAuthenticatedAs($admin, 'web');

            $contracts[$this->flagKey($enabled)] = $this->responseContract($response);
            $contracts[$this->flagKey($enabled)]['errors'] = $this->sessionErrorKeys();
        }

        $this->assertSame($contracts['off'], $contracts['on']);
    }

    #[Test]
    public function store_success_redirect_contract_is_identical_when_react_flags_toggle(): void
    {
        $admin = $this->createMasterAdmin();
        $contracts = [];

        foreach ([false, true] as $enabled) {
            $this->setRecurringUiFlags($enabled);
            $category = $this->createCategory();

            $payload = [
                'category_id' => $category->id,
                'title' => 'Recurring Contract '.uniqid(),
                'amount' => 120.50,
                'recurrence_type' => 'monthly',
                'recurrence_interval' => 1,
                'start_date' => '2026-05-01',
                'end_date' => null,
                'notes' => 'Contract parity test',
            ];

            $response = $this->actingAs($admin)
                ->post(route('admin.expenses.recurring.store'), $payload);

            $response->assertRedirect(route('admin.expenses.recurring.index'));
            $response->assertSessionHas('status', 'Recurring expense created.');
            $this->assertAuthenticatedAs($admin, 'web');

            $contracts[$this->flagKey($enabled)] = $this->responseContract($response);
        }

        $this->assertSame($contracts['off'], $contracts['on']);
    }

    #[Test]
    public function update_validation_contract_is_identical_when_react_flags_toggle(): void
    {
        $admin = $this->createMasterAdmin();
        $contracts = [];

        foreach ([false, true] as $enabled) {
            $this->setRecurringUiFlags($enabled);
            $recurring = $this->createRecurringExpense($admin);

            $response = $this->actingAs($admin)
                ->from(route('admin.expenses.recurring.edit', $recurring))
                ->put(route('admin.expenses.recurring.update', $recurring), []);

            $response->assertRedirect(route('admin.expenses.recurring.edit', $recurring));
            $response->assertSessionHasErrors(['category_id', 'title', 'amount', 'recurrence_type', 'recurrence_interval', 'start_date']);
            $this->assertAuthenticatedAs($admin, 'web');

            $contracts[$this->flagKey($enabled)] = $this->responseContract($response);
            $contracts[$this->flagKey($enabled)]['errors'] = $this->sessionErrorKeys();
        }

        $this->assertSame($contracts['off'], $contracts['on']);
    }

    #[Test]
    public function update_success_redirect_contract_is_identical_when_react_flags_toggle(): void
    {
        $admin = $this->createMasterAdmin();
        $contracts = [];

        foreach ([false, true] as $enabled) {
            $this->setRecurringUiFlags($enabled);
            $recurring = $this->createRecurringExpense($admin);
            $category = $this->createCategory();

            $payload = [
                'category_id' => $category->id,
                'title' => 'Recurring Updated '.uniqid(),
                'amount' => 220.75,
                'recurrence_type' => 'yearly',
                'recurrence_interval' => 1,
                'start_date' => '2026-06-01',
                'end_date' => '2026-12-01',
                'notes' => 'Updated by parity test',
            ];

            $response = $this->actingAs($admin)
                ->put(route('admin.expenses.recurring.update', $recurring), $payload);

            $response->assertRedirect(route('admin.expenses.recurring.index'));
            $response->assertSessionHas('status', 'Recurring expense updated.');
            $this->assertAuthenticatedAs($admin, 'web');

            $recurring->refresh();
            $this->assertSame($payload['title'], $recurring->title);

            $contracts[$this->flagKey($enabled)] = $this->responseContract($response);
        }

        $this->assertSame($contracts['off'], $contracts['on']);
    }

    #[Test]
    public function store_advance_validation_contract_is_identical_when_react_flags_toggle(): void
    {
        $admin = $this->createMasterAdmin();
        $contracts = [];

        foreach ([false, true] as $enabled) {
            $this->setRecurringUiFlags($enabled);
            $recurring = $this->createRecurringExpense($admin);

            $response = $this->actingAs($admin)
                ->from(route('admin.expenses.recurring.show', $recurring))
                ->post(route('admin.expenses.recurring.advance.store', $recurring), []);

            $response->assertRedirect(route('admin.expenses.recurring.show', $recurring));
            $response->assertSessionHasErrors(['payment_method', 'amount', 'paid_at']);
            $this->assertAuthenticatedAs($admin, 'web');

            $contracts[$this->flagKey($enabled)] = $this->responseContract($response);
            $contracts[$this->flagKey($enabled)]['errors'] = $this->sessionErrorKeys();
        }

        $this->assertSame($contracts['off'], $contracts['on']);
    }

    #[Test]
    public function store_advance_success_redirect_contract_is_identical_when_react_flags_toggle(): void
    {
        $admin = $this->createMasterAdmin();
        $contracts = [];

        foreach ([false, true] as $enabled) {
            $this->setRecurringUiFlags($enabled);
            $recurring = $this->createRecurringExpense($admin);

            $response = $this->actingAs($admin)
                ->from(route('admin.expenses.recurring.show', $recurring))
                ->post(route('admin.expenses.recurring.advance.store', $recurring), [
                    'payment_method' => 'bank',
                    'amount' => 30.25,
                    'paid_at' => '2026-06-03',
                    'payment_reference' => 'ADV-123',
                    'note' => 'Advance contract parity',
                ]);

            $response->assertRedirect(route('admin.expenses.recurring.show', $recurring));
            $response->assertSessionHas('status', 'Advance payment added.');
            $this->assertAuthenticatedAs($admin, 'web');

            $this->assertDatabaseHas('recurring_expense_advances', [
                'recurring_expense_id' => $recurring->id,
                'payment_method' => 'bank',
                'amount' => 30.25,
            ]);

            $contracts[$this->flagKey($enabled)] = $this->responseContract($response);
        }

        $this->assertSame($contracts['off'], $contracts['on']);
    }

    #[Test]
    public function resume_and_stop_redirect_contracts_are_identical_when_react_flags_toggle(): void
    {
        $admin = $this->createMasterAdmin();
        $contracts = [];

        foreach ([false, true] as $enabled) {
            $this->setRecurringUiFlags($enabled);

            $paused = $this->createRecurringExpense($admin, ['status' => 'paused']);
            $stopped = $this->createRecurringExpense($admin, ['status' => 'stopped']);
            $active = $this->createRecurringExpense($admin, ['status' => 'active']);

            $resumeSuccess = $this->actingAs($admin)
                ->from(route('admin.expenses.recurring.show', $paused))
                ->post(route('admin.expenses.recurring.resume', $paused));

            $resumeSuccess->assertRedirect(route('admin.expenses.recurring.show', $paused));
            $resumeSuccess->assertSessionHas('status', 'Recurring expense resumed.');
            $paused->refresh();
            $this->assertSame('active', $paused->status);

            $resumeError = $this->actingAs($admin)
                ->from(route('admin.expenses.recurring.show', $stopped))
                ->post(route('admin.expenses.recurring.resume', $stopped));

            $resumeError->assertRedirect(route('admin.expenses.recurring.show', $stopped));
            $resumeError->assertSessionHasErrors(['recurring']);
            $resumeErrorKeys = $this->sessionErrorKeys();

            $stopSuccess = $this->actingAs($admin)
                ->from(route('admin.expenses.recurring.show', $active))
                ->post(route('admin.expenses.recurring.stop', $active));

            $stopSuccess->assertRedirect(route('admin.expenses.recurring.show', $active));
            $stopSuccess->assertSessionHas('status', 'Recurring expense stopped.');
            $active->refresh();
            $this->assertSame('stopped', $active->status);
            $this->assertAuthenticatedAs($admin, 'web');

            $contracts[$this->flagKey($enabled)] = [
                'resume_success' => $this->responseContract($resumeSuccess),
                'resume_error' => array_merge($this->responseContract($resumeError), ['errors' => $resumeErrorKeys]),
                'stop_success' => $this->responseContract($stopSuccess),
            ];
        }

        $this->assertSame($contracts['off'], $contracts['on']);
    }

    private function createMasterAdmin(): User
    {
        return User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);
    }

    private function createCategory(): ExpenseCategory
    {
        return ExpenseCategory::query()->create([
            'name' => 'Recurring Category '.uniqid(),
            'description' => null,
            'status' => 'active',
        ]);
    }

    private function createRecurringExpense(User $admin, array $overrides = []): RecurringExpense
    {
        $category = $this->createCategory();

        $defaults = [
            'category_id' => $category->id,
            'title' => 'Recurring '.uniqid(),
            'amount' => 150.00,
            'recurrence_type' => 'monthly',
            'recurrence_interval' => 1,
            'start_date' => '2026-05-01',
            'end_date' => null,
            'next_run_date' => '2026-05-01',
            'status' => 'active',
            'created_by' => $admin->id,
        ];

        return RecurringExpense::query()->create(array_merge($defaults, $overrides));
    }

    private function setRecurringUiFlags(bool $enabled): void
    {
        config()->set('features.admin_expenses_recurring_index', $enabled);
        config()->set('features.admin_expenses_recurring_show', $enabled);
        config()->set('features.admin_expenses_recurring_create', $enabled);
        config()->set('features.admin_expenses_recurring_edit', $enabled);
    }

    /**
     * @return array{status:int,location_path:string}
     */
    private function responseContract($response): array
    {
        $location = (string) $response->headers->get('Location', '');
        $path = parse_url($location, PHP_URL_PATH);
        $normalizedPath = is_string($path)
            ? (string) preg_replace('#/\d+(?=/|$)#', '/{id}', $path)
            : '';

        return [
            'status' => $response->getStatusCode(),
            'location_path' => $normalizedPath,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function sessionErrorKeys(): array
    {
        $errors = session('errors');
        if (! $errors) {
            return [];
        }

        $keys = array_keys($errors->getBag('default')->messages());
        sort($keys);

        return $keys;
    }

    private function flagKey(bool $enabled): string
    {
        return $enabled ? 'on' : 'off';
    }
}
