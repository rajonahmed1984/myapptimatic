<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function index(): View
    {
        $categories = ExpenseCategory::query()
            ->orderBy('name')
            ->get();

        return view('admin.expenses.categories.index', compact('categories'));
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
        return view('admin.expenses.categories.edit', compact('category'));
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
}
