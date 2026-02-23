<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExpenseCategoryActionContractParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function store_validation_and_success_contracts_are_identical_when_react_flag_toggles(): void
    {
        $admin = $this->createMasterAdmin();
        $contracts = [];

        foreach ([false, true] as $enabled) {
            $this->setUiFlag($enabled);

            $validation = $this->actingAs($admin)
                ->from(route('admin.expenses.categories.index'))
                ->post(route('admin.expenses.categories.store'), []);

            $validation->assertRedirect(route('admin.expenses.categories.index'));
            $validation->assertSessionHasErrors(['name', 'status']);
            $validationErrorKeys = $this->sessionErrorKeys();

            $name = 'Expense Category '.uniqid();
            $success = $this->actingAs($admin)
                ->from(route('admin.expenses.categories.index'))
                ->post(route('admin.expenses.categories.store'), [
                    'name' => $name,
                    'status' => 'active',
                    'description' => 'Contract parity check',
                ]);

            $success->assertRedirect(route('admin.expenses.categories.index'));
            $success->assertSessionHas('status', 'Expense category created.');
            $this->assertDatabaseHas('expense_categories', [
                'name' => $name,
                'status' => 'active',
            ]);

            $contracts[$this->flagKey($enabled)] = [
                'validation' => array_merge(
                    $this->responseContract($validation),
                    ['errors' => $validationErrorKeys]
                ),
                'success' => $this->responseContract($success),
            ];
        }

        $this->assertSame($contracts['off'], $contracts['on']);
    }

    #[Test]
    public function update_validation_and_success_contracts_are_identical_when_react_flag_toggles(): void
    {
        $admin = $this->createMasterAdmin();
        $contracts = [];

        foreach ([false, true] as $enabled) {
            $this->setUiFlag($enabled);
            $category = $this->createCategory();

            $validation = $this->actingAs($admin)
                ->from(route('admin.expenses.categories.index', ['edit' => $category->id]))
                ->put(route('admin.expenses.categories.update', $category), []);

            $validation->assertRedirect(route('admin.expenses.categories.index', ['edit' => $category->id]));
            $validation->assertSessionHasErrors(['name', 'status']);
            $validationErrorKeys = $this->sessionErrorKeys();

            $updatedName = 'Updated Expense Category '.uniqid();
            $success = $this->actingAs($admin)
                ->from(route('admin.expenses.categories.index', ['edit' => $category->id]))
                ->put(route('admin.expenses.categories.update', $category), [
                    'name' => $updatedName,
                    'status' => 'inactive',
                    'description' => 'Updated by parity test',
                ]);

            $success->assertRedirect(route('admin.expenses.categories.index'));
            $success->assertSessionHas('status', 'Expense category updated.');
            $category->refresh();
            $this->assertSame($updatedName, $category->name);
            $this->assertSame('inactive', $category->status);

            $contracts[$this->flagKey($enabled)] = [
                'validation' => array_merge(
                    $this->responseContract($validation),
                    ['errors' => $validationErrorKeys]
                ),
                'success' => $this->responseContract($success),
            ];
        }

        $this->assertSame($contracts['off'], $contracts['on']);
    }

    #[Test]
    public function destroy_success_and_blocked_contracts_are_identical_when_react_flag_toggles(): void
    {
        $admin = $this->createMasterAdmin();
        $contracts = [];

        foreach ([false, true] as $enabled) {
            $this->setUiFlag($enabled);

            $deletable = $this->createCategory();
            $deleteSuccess = $this->actingAs($admin)
                ->from(route('admin.expenses.categories.index'))
                ->delete(route('admin.expenses.categories.destroy', $deletable));

            $deleteSuccess->assertRedirect(route('admin.expenses.categories.index'));
            $deleteSuccess->assertSessionHas('status', 'Expense category deleted.');
            $this->assertDatabaseMissing('expense_categories', ['id' => $deletable->id]);

            $blocked = $this->createCategory();
            $this->attachExpense($blocked);
            $deleteBlocked = $this->actingAs($admin)
                ->from(route('admin.expenses.categories.index'))
                ->delete(route('admin.expenses.categories.destroy', $blocked));

            $deleteBlocked->assertRedirect(route('admin.expenses.categories.index'));
            $deleteBlocked->assertSessionHasErrors(['category']);
            $blockedErrorKeys = $this->sessionErrorKeys();

            $contracts[$this->flagKey($enabled)] = [
                'success' => $this->responseContract($deleteSuccess),
                'blocked' => array_merge(
                    $this->responseContract($deleteBlocked),
                    ['errors' => $blockedErrorKeys]
                ),
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

    private function createCategory(array $overrides = []): ExpenseCategory
    {
        return ExpenseCategory::query()->create(array_merge([
            'name' => 'Expense Category '.uniqid(),
            'status' => 'active',
            'description' => null,
        ], $overrides));
    }

    private function attachExpense(ExpenseCategory $category): void
    {
        Expense::query()->create([
            'category_id' => $category->id,
            'title' => 'Used expense '.uniqid(),
            'amount' => 10,
            'expense_date' => '2026-01-10',
            'notes' => null,
            'type' => 'manual',
            'created_by' => null,
        ]);
    }

    private function setUiFlag(bool $enabled): void
    {
        config()->set('features.admin_expenses_categories_index', $enabled);
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
