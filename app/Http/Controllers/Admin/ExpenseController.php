<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Models\Setting;
use App\Services\ExpenseEntryService;
use App\Support\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ExpenseController extends Controller
{
    public function index(Request $request, ExpenseEntryService $entryService): View
    {
        $sourceFilters = $request->input('sources', []);
        if (! is_array($sourceFilters) || empty($sourceFilters)) {
            $sourceFilters = ['manual', 'salary', 'contract_payout', 'sales_payout'];
        }

        [$personType, $personId] = $entryService->parsePersonFilter($request->query('person'));

        $filters = [
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'category_id' => $request->query('category_id'),
            'type' => $request->query('type'),
            'recurring_expense_id' => $request->query('recurring_expense_id'),
            'sources' => $sourceFilters,
            'person_type' => $personType,
            'person_id' => $personId,
            'person' => $request->query('person'),
        ];

        $entries = $entryService->entries($filters)
            ->sortByDesc(fn ($entry) => $entry['expense_date'] ?? now());

        $expenses = $this->paginateEntries($entries, 20, $request);

        $totalAmount = (float) $entries->sum('amount');

        $categoryTotals = $entries
            ->groupBy('category_id')
            ->map(function ($items) {
                $first = $items->first();
                return [
                    'category_id' => $first['category_id'],
                    'name' => $first['category_name'],
                    'total' => (float) collect($items)->sum('amount'),
                ];
            })
            ->values()
            ->sortByDesc('total');

        $now = now();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $yearStart = $now->copy()->startOfYear()->toDateString();

        $monthlyTotal = (float) $entryService->entries([
            'start_date' => $monthStart,
            'end_date' => $now->toDateString(),
            'sources' => ['manual', 'salary', 'contract_payout', 'sales_payout'],
        ])->sum('amount');

        $yearlyTotal = (float) $entryService->entries([
            'start_date' => $yearStart,
            'end_date' => $now->toDateString(),
            'sources' => ['manual', 'salary', 'contract_payout', 'sales_payout'],
        ])->sum('amount');

        $topCategories = $entryService->entries([
            'start_date' => $monthStart,
            'end_date' => $now->toDateString(),
            'sources' => ['manual', 'salary', 'contract_payout', 'sales_payout'],
        ])->groupBy('category_id')
            ->map(function ($items) {
                $first = $items->first();
                return [
                    'category_id' => $first['category_id'],
                    'name' => $first['category_name'],
                    'total' => (float) collect($items)->sum('amount'),
                ];
            })
            ->values()
            ->sortByDesc('total')
            ->take(5);

        $categories = ExpenseCategory::query()->orderBy('name')->get();
        $recurringTemplates = RecurringExpense::query()->orderBy('title')->get();
        $peopleOptions = $entryService->peopleOptions();

        $currencyCode = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($currencyCode)) {
            $currencyCode = Currency::DEFAULT;
        }
        $currencySymbol = Currency::symbol($currencyCode);

        return view('admin.expenses.index', [
            'expenses' => $expenses,
            'categories' => $categories,
            'recurringTemplates' => $recurringTemplates,
            'peopleOptions' => $peopleOptions,
            'filters' => $filters,
            'totalAmount' => $totalAmount,
            'categoryTotals' => $categoryTotals,
            'monthlyTotal' => $monthlyTotal,
            'yearlyTotal' => $yearlyTotal,
            'topCategories' => $topCategories,
            'currencySymbol' => $currencySymbol,
            'currencyCode' => $currencyCode,
        ]);
    }

    public function create(): View
    {
        $categories = ExpenseCategory::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('admin.expenses.create', compact('categories'));
    }

    public function store(Request $request, \App\Services\ExpenseInvoiceService $invoiceService): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => [
                'required',
                Rule::exists('expense_categories', 'id')->where('status', 'active'),
            ],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'generate_invoice' => ['nullable', 'boolean'],
        ]);

        $expense = Expense::create([
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
            'notes' => $data['notes'] ?? null,
            'type' => 'one_time',
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('expenses/receipts', 'public');
            $expense->update(['attachment_path' => $path]);
        }

        if ($request->boolean('generate_invoice')) {
            $invoiceService->createForExpense($expense, $request->user()->id);
        }

        return redirect()->route('admin.expenses.index')
            ->with('status', 'Expense recorded.');
    }

    public function attachment(Expense $expense)
    {
        if (! $expense->attachment_path) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($expense->attachment_path)) {
            abort(404);
        }

        return Storage::disk('public')->response($expense->attachment_path);
    }

    private function paginateEntries(Collection $entries, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $entries = $entries->values();
        $slice = $entries->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $entries->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }
}
