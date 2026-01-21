<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Setting;
use App\Services\IncomeEntryService;
use App\Support\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncomeController extends Controller
{
    public function index(Request $request, IncomeEntryService $entryService): View
    {
        $sourceFilters = $request->input('sources', []);
        if (! is_array($sourceFilters) || empty($sourceFilters)) {
            $sourceFilters = ['manual', 'system'];
        }

        $filters = [
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'category_id' => $request->query('category_id'),
            'sources' => $sourceFilters,
        ];

        $entries = $entryService->entries($filters)
            ->sortByDesc(fn ($entry) => $entry['income_date'] ?? now());

        $incomes = $this->paginateEntries($entries, 20, $request);

        $totalAmount = (float) $entries->sum('amount');

        $categoryTotals = $entries
            ->groupBy(fn ($entry) => $entry['category_id'] ?? 'system')
            ->map(function ($items) {
                $first = $items->first();
                return [
                    'category_id' => $first['category_id'],
                    'name' => $first['category_name'] ?? 'System',
                    'total' => (float) collect($items)->sum('amount'),
                ];
            })
            ->values()
            ->sortByDesc('total');

        $categories = IncomeCategory::query()->orderBy('name')->get();

        $currencyCode = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($currencyCode)) {
            $currencyCode = Currency::DEFAULT;
        }
        $currencySymbol = Currency::symbol($currencyCode);

        return view('admin.income.index', [
            'incomes' => $incomes,
            'categories' => $categories,
            'filters' => $filters,
            'totalAmount' => $totalAmount,
            'categoryTotals' => $categoryTotals,
            'currencySymbol' => $currencySymbol,
            'currencyCode' => $currencyCode,
        ]);
    }

    public function create(): View
    {
        $categories = IncomeCategory::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('admin.income.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'income_category_id' => [
                'required',
                Rule::exists('income_categories', 'id')->where('status', 'active'),
            ],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'income_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $income = Income::create([
            'income_category_id' => $data['income_category_id'],
            'title' => $data['title'],
            'amount' => $data['amount'],
            'income_date' => $data['income_date'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('income/receipts', 'public');
            $income->update(['attachment_path' => $path]);
        }

        return redirect()->route('admin.income.index')
            ->with('status', 'Income recorded.');
    }

    public function attachment(Income $income)
    {
        if (! $income->attachment_path) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($income->attachment_path)) {
            abort(404);
        }

        return Storage::disk('public')->response($income->attachment_path);
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
