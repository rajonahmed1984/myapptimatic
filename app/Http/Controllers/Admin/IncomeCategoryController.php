<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Income;
use App\Models\IncomeCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncomeCategoryController extends Controller
{
    public function index(): View
    {
        $categories = IncomeCategory::query()->orderBy('name')->get();

        return view('admin.income.categories.index', compact('categories'));
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
        return view('admin.income.categories.edit', compact('category'));
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
}
