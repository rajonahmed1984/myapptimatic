<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IncomeCategoryActionContractParityTest extends TestCase
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
                ->from(route('admin.income.categories.index'))
                ->post(route('admin.income.categories.store'), []);

            $validation->assertRedirect(route('admin.income.categories.index'));
            $validation->assertSessionHasErrors(['name', 'status']);
            $validationErrorKeys = $this->sessionErrorKeys();

            $name = 'Income Category '.uniqid();
            $success = $this->actingAs($admin)
                ->from(route('admin.income.categories.index'))
                ->post(route('admin.income.categories.store'), [
                    'name' => $name,
                    'status' => 'active',
                    'description' => 'Contract parity check',
                ]);

            $success->assertRedirect(route('admin.income.categories.index'));
            $success->assertSessionHas('status', 'Income category created.');
            $this->assertDatabaseHas('income_categories', [
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
                ->from(route('admin.income.categories.index', ['edit' => $category->id]))
                ->put(route('admin.income.categories.update', $category), []);

            $validation->assertRedirect(route('admin.income.categories.index', ['edit' => $category->id]));
            $validation->assertSessionHasErrors(['name', 'status']);
            $validationErrorKeys = $this->sessionErrorKeys();

            $updatedName = 'Updated Income Category '.uniqid();
            $success = $this->actingAs($admin)
                ->from(route('admin.income.categories.index', ['edit' => $category->id]))
                ->put(route('admin.income.categories.update', $category), [
                    'name' => $updatedName,
                    'status' => 'inactive',
                    'description' => 'Updated by parity test',
                ]);

            $success->assertRedirect(route('admin.income.categories.index'));
            $success->assertSessionHas('status', 'Income category updated.');
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
                ->from(route('admin.income.categories.index'))
                ->delete(route('admin.income.categories.destroy', $deletable));

            $deleteSuccess->assertRedirect(route('admin.income.categories.index'));
            $deleteSuccess->assertSessionHas('status', 'Income category deleted.');
            $this->assertDatabaseMissing('income_categories', ['id' => $deletable->id]);

            $blocked = $this->createCategory();
            $this->attachIncome($blocked);
            $deleteBlocked = $this->actingAs($admin)
                ->from(route('admin.income.categories.index'))
                ->delete(route('admin.income.categories.destroy', $blocked));

            $deleteBlocked->assertRedirect(route('admin.income.categories.index'));
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

    private function createCategory(array $overrides = []): IncomeCategory
    {
        return IncomeCategory::query()->create(array_merge([
            'name' => 'Income Category '.uniqid(),
            'status' => 'active',
            'description' => null,
        ], $overrides));
    }

    private function attachIncome(IncomeCategory $category): void
    {
        Income::query()->create([
            'income_category_id' => $category->id,
            'title' => 'Used income '.uniqid(),
            'amount' => 10,
            'income_date' => '2026-01-10',
            'notes' => null,
            'created_by' => null,
        ]);
    }

    private function setUiFlag(bool $enabled): void
    {
        config()->set('features.admin_income_categories_index', $enabled);
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
