<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Support\HybridUiResponder;
use App\Support\UiFeature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;

class IncomeCategoryController extends Controller
{
    public function index(
        Request $request,
        HybridUiResponder $hybridUiResponder
    ): View|InertiaResponse {
        $categories = IncomeCategory::query()->orderBy('name')->get();

        return $hybridUiResponder->render(
            $request,
            UiFeature::ADMIN_INCOME_CATEGORIES_INDEX,
            'admin.income.categories.index',
            compact('categories'),
            'Admin/Income/Categories/Index',
            $this->indexInertiaProps($request, $categories)
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:income_categories,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        IncomeCategory::create($data);

        return back()->with('status', 'Income category created.');
    }

    public function edit(IncomeCategory $category): View
    {
        return redirect()->route('admin.income.categories.index', ['edit' => $category->id]);
    }

    public function update(Request $request, IncomeCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('income_categories', 'name')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $category->update($data);

        return redirect()->route('admin.income.categories.index')
            ->with('status', 'Income category updated.');
    }

    public function destroy(IncomeCategory $category): RedirectResponse
    {
        $hasIncome = Income::query()->where('income_category_id', $category->id)->exists();
        if ($hasIncome) {
            return back()->withErrors(['category' => 'Category has income entries and cannot be deleted.']);
        }

        $category->delete();

        return back()->with('status', 'Income category deleted.');
    }

    private function indexInertiaProps(Request $request, Collection $categories): array
    {
        $editId = $request->old('edit_id', $request->query('edit'));
        $editCategoryId = is_numeric($editId) ? (int) $editId : null;
        $editCategory = $editCategoryId
            ? $categories->firstWhere('id', $editCategoryId)
            : null;
        $isEditing = $editCategory instanceof IncomeCategory;
        $defaultStatus = $isEditing ? (string) $editCategory->status : 'active';

        return [
            'pageTitle' => 'Income Categories',
            'heading' => 'Income categories',
            'routes' => [
                'back' => route('admin.income.index'),
                'index' => route('admin.income.categories.index'),
            ],
            'form' => [
                'editing' => $isEditing,
                'title' => $isEditing ? 'Edit category' : 'Add category',
                'button_label' => $isEditing ? 'Update Category' : 'Add Category',
                'action' => $isEditing
                    ? route('admin.income.categories.update', $editCategory)
                    : route('admin.income.categories.store'),
                'method' => $isEditing ? 'PUT' : 'POST',
                'cancel_href' => $isEditing ? route('admin.income.categories.index') : null,
                'editing_name' => $isEditing ? (string) $editCategory->name : null,
                'fields' => [
                    'edit_id' => $isEditing ? (string) $editCategory->id : '',
                    'name' => (string) old('name', (string) ($editCategory?->name ?? '')),
                    'status' => (string) old('status', $defaultStatus),
                    'description' => (string) old('description', (string) ($editCategory?->description ?? '')),
                ],
            ],
            'categories' => $categories->values()->map(function (IncomeCategory $category) {
                return [
                    'id' => $category->id,
                    'name' => (string) $category->name,
                    'status' => (string) $category->status,
                    'status_label' => ucfirst((string) $category->status),
                    'description' => (string) ($category->description ?? '--'),
                    'routes' => [
                        'edit' => route('admin.income.categories.index', ['edit' => $category->id]),
                        'destroy' => route('admin.income.categories.destroy', $category),
                    ],
                ];
            })->all(),
        ];
    }
}
