<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Setting;
use App\Support\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class IncomeCategoryController extends Controller
{
    public function index(
        Request $request,
    ): InertiaResponse {
        $categories = IncomeCategory::query()->orderBy('name')->get();

        return Inertia::render(
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
        $currencyCode = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($currencyCode)) {
            $currencyCode = Currency::DEFAULT;
        }

        // Only manual income entries carry a category; invoice payments do not.
        $incomeTotalsByCategory = Income::query()
            ->selectRaw('income_category_id, SUM(amount) as total_amount')
            ->whereNotNull('income_category_id')
            ->groupBy('income_category_id')
            ->pluck('total_amount', 'income_category_id');

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
            'categories' => $categories->values()->map(function (IncomeCategory $category) use ($incomeTotalsByCategory, $currencyCode) {
                $totalIncome = (float) ($incomeTotalsByCategory->get($category->id) ?? 0);

                return [
                    'id' => $category->id,
                    'name' => (string) $category->name,
                    'status' => (string) $category->status,
                    'status_label' => ucfirst((string) $category->status),
                    'description' => (string) ($category->description ?? '--'),
                    'total_income' => $totalIncome,
                    'total_income_display' => sprintf('%s %s', $currencyCode, number_format($totalIncome, 2)),
                    'routes' => [
                        'edit' => route('admin.income.categories.index', ['edit' => $category->id]),
                        'destroy' => route('admin.income.categories.destroy', $category),
                    ],
                ];
            })
                // Highest-earning categories first; ties keep alphabetical order.
                ->sortBy([['total_income', 'desc'], ['name', 'asc']])
                ->values()
                ->all(),
        ];
    }
}
