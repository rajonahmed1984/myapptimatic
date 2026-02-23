<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Support\HybridUiResponder;
use App\Support\UiFeature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;

class ExpenseCategoryController extends Controller
{
    public function index(
        Request $request,
        HybridUiResponder $hybridUiResponder
    ): View|InertiaResponse {
        $categories = ExpenseCategory::query()
            ->orderBy('name')
            ->get();

        return $hybridUiResponder->render(
            $request,
            UiFeature::ADMIN_EXPENSES_CATEGORIES_INDEX,
            'admin.expenses.categories.index',
            compact('categories'),
            'Admin/Expenses/Categories/Index',
            $this->indexInertiaProps($request, $categories)
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:expense_categories,name'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        ExpenseCategory::create($data);

        return back()->with('status', 'Expense category created.');
    }

    public function edit(ExpenseCategory $category): View
    {
        return redirect()->route('admin.expenses.categories.index', ['edit' => $category->id]);
    }

    public function update(Request $request, ExpenseCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('expense_categories', 'name')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $category->update($data);

        return redirect()->route('admin.expenses.categories.index')
            ->with('status', 'Expense category updated.');
    }

    public function destroy(ExpenseCategory $category): RedirectResponse
    {
        $hasExpenses = Expense::query()->where('category_id', $category->id)->exists();
        $hasRecurring = RecurringExpense::query()->where('category_id', $category->id)->exists();

        if ($hasExpenses || $hasRecurring) {
            return back()->withErrors(['category' => 'Category has expenses and cannot be deleted.']);
        }

        $category->delete();

        return back()->with('status', 'Expense category deleted.');
    }

    private function indexInertiaProps(Request $request, Collection $categories): array
    {
        $editId = $request->old('edit_id', $request->query('edit'));
        $editCategoryId = is_numeric($editId) ? (int) $editId : null;
        $editCategory = $editCategoryId
            ? $categories->firstWhere('id', $editCategoryId)
            : null;
        $isEditing = $editCategory instanceof ExpenseCategory;
        $defaultStatus = $isEditing ? (string) $editCategory->status : 'active';

        return [
            'pageTitle' => 'Expense Categories',
            'heading' => 'Expense categories',
            'routes' => [
                'back' => route('admin.expenses.index'),
                'index' => route('admin.expenses.categories.index'),
            ],
            'form' => [
                'editing' => $isEditing,
                'title' => $isEditing ? 'Edit category' : 'Add category',
                'button_label' => $isEditing ? 'Update Category' : 'Add Category',
                'action' => $isEditing
                    ? route('admin.expenses.categories.update', $editCategory)
                    : route('admin.expenses.categories.store'),
                'method' => $isEditing ? 'PUT' : 'POST',
                'cancel_href' => $isEditing ? route('admin.expenses.categories.index') : null,
                'editing_name' => $isEditing ? (string) $editCategory->name : null,
                'fields' => [
                    'edit_id' => $isEditing ? (string) $editCategory->id : '',
                    'name' => (string) old('name', (string) ($editCategory?->name ?? '')),
                    'status' => (string) old('status', $defaultStatus),
                    'description' => (string) old('description', (string) ($editCategory?->description ?? '')),
                ],
            ],
            'categories' => $categories->values()->map(function (ExpenseCategory $category) {
                return [
                    'id' => $category->id,
                    'name' => (string) $category->name,
                    'status' => (string) $category->status,
                    'status_label' => ucfirst((string) $category->status),
                    'description' => (string) ($category->description ?? '--'),
                    'routes' => [
                        'edit' => route('admin.expenses.categories.index', ['edit' => $category->id]),
                        'destroy' => route('admin.expenses.categories.destroy', $category),
                    ],
                ];
            })->all(),
        ];
    }
}
