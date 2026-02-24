<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingEntry;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Services\ExpenseEntryService;
use App\Services\GeminiService;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ExpenseDashboardController extends Controller
{
    public function index(Request $request, ExpenseEntryService $entryService, GeminiService $geminiService): InertiaResponse
    {
        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))->startOfDay()
            : now()->startOfMonth();
        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))->endOfDay()
            : now()->endOfDay();

        $sourceFilters = $request->input('sources', []);
        if (! is_array($sourceFilters) || empty($sourceFilters)) {
            $sourceFilters = ['manual', 'salary', 'contract_payout', 'sales_payout'];
        }

        [$personType, $personId] = $entryService->parsePersonFilter($request->query('person'));

        $filters = [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'category_id' => $request->query('category_id'),
            'type' => $request->query('type'),
            'recurring_expense_id' => $request->query('recurring_expense_id'),
            'sources' => $sourceFilters,
            'person_type' => $personType,
            'person_id' => $personId,
            'person' => $request->query('person'),
        ];

        $entries = $entryService->entries($filters);

        $expenseTotal = (float) $entries->sum('amount');
        $totalAmount = $expenseTotal;

        $incomeReceived = (float) AccountingEntry::query()
            ->where('type', 'payment')
            ->whereDate('entry_date', '>=', $startDate->toDateString())
            ->whereDate('entry_date', '<=', $endDate->toDateString())
            ->sum('amount');

        $payoutExpenseTotal = (float) $entries
            ->whereIn('expense_type', ['salary', 'contract_payout', 'sales_payout'])
            ->sum('amount');

        $netIncome = $incomeReceived - $expenseTotal;
        $netCashflow = $incomeReceived - $payoutExpenseTotal;

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

        $employeeTotals = $entries
            ->filter(fn ($entry) => ! empty($entry['person_name']))
            ->groupBy(fn ($entry) => ($entry['person_type'] ?? 'person').':'.($entry['person_id'] ?? ''))
            ->map(function ($items) {
                $first = $items->first();

                return [
                    'label' => $first['person_name'],
                    'total' => (float) collect($items)->sum('amount'),
                ];
            })
            ->values()
            ->sortByDesc('total');

        $salesRepTotals = $entries
            ->filter(fn ($entry) => ($entry['person_type'] ?? null) === 'sales_rep' && ! empty($entry['person_name']))
            ->groupBy(fn ($entry) => (string) ($entry['person_id'] ?? ''))
            ->map(function ($items) {
                $first = $items->first();

                return [
                    'label' => $first['person_name'],
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

        [$labels, $expenseSeries, $incomeSeries] = $this->buildTrends($entries, $startDate, $endDate);

        $categories = ExpenseCategory::query()->orderBy('name')->get();
        $recurringTemplates = RecurringExpense::query()->orderBy('title')->get();
        $peopleOptions = $entryService->peopleOptions();
        $currencyCode = strtoupper((string) \App\Models\Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($currencyCode)) {
            $currencyCode = Currency::DEFAULT;
        }
        $currencySymbol = Currency::symbol($currencyCode);

        $forceAiRefresh = $request->query('ai') === 'refresh';
        [$aiSummary, $aiError] = $this->buildAiSummary(
            $geminiService,
            $startDate,
            $endDate,
            $currencyCode,
            $expenseTotal,
            $incomeReceived,
            $payoutExpenseTotal,
            $netIncome,
            $netCashflow,
            $categoryTotals,
            $employeeTotals,
            $filters,
            $forceAiRefresh
        );

        return Inertia::render('Admin/Expenses/Dashboard', [
            'pageTitle' => 'Expense Dashboard',
            'filters' => $filters,
            'expenseTotal' => $expenseTotal,
            'incomeReceived' => $incomeReceived,
            'payoutExpenseTotal' => $payoutExpenseTotal,
            'netIncome' => $netIncome,
            'netCashflow' => $netCashflow,
            'categoryTotals' => $categoryTotals->values(),
            'employeeTotals' => $employeeTotals->values(),
            'salesRepTotals' => $salesRepTotals->values(),
            'totalAmount' => $totalAmount,
            'monthlyTotal' => $monthlyTotal,
            'yearlyTotal' => $yearlyTotal,
            'topCategories' => $topCategories->values(),
            'trendLabels' => $labels,
            'trendExpenses' => $expenseSeries,
            'trendIncome' => $incomeSeries,
            'categories' => $categories->map(fn (ExpenseCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])->values(),
            'recurringTemplates' => $recurringTemplates->map(fn (RecurringExpense $template) => [
                'id' => $template->id,
                'title' => $template->title,
            ])->values(),
            'peopleOptions' => collect($peopleOptions)->values(),
            'currencyCode' => $currencyCode,
            'currencySymbol' => $currencySymbol,
            'aiSummary' => $aiSummary,
            'aiError' => $aiError,
            'routes' => [
                'index' => route('admin.expenses.dashboard'),
                'refresh_ai' => route('admin.expenses.dashboard', array_merge($request->query(), ['ai' => 'refresh'])),
            ],
        ]);
    }

    private function buildTrends($entries, Carbon $startDate, Carbon $endDate): array
    {
        $days = $startDate->diffInDays($endDate);
        $format = $days > 62 ? 'Y-m' : 'Y-m-d';

        $expenseGroups = $entries->groupBy(function ($entry) use ($format) {
            $date = $entry['expense_date'] ? Carbon::parse($entry['expense_date']) : now();

            return $date->format($format);
        });

        $incomeGroups = AccountingEntry::query()
            ->where('type', 'payment')
            ->whereDate('entry_date', '>=', $startDate->toDateString())
            ->whereDate('entry_date', '<=', $endDate->toDateString())
            ->get(['entry_date', 'amount'])
            ->groupBy(fn ($entry) => Carbon::parse($entry->entry_date)->format($format));

        $labels = [];
        $expenseSeries = [];
        $incomeSeries = [];

        $cursor = $startDate->copy();
        while ($cursor->lessThanOrEqualTo($endDate)) {
            $label = $cursor->format($format);
            $labels[] = $label;
            $expenseSeries[] = (float) collect($expenseGroups->get($label, []))->sum('amount');
            $incomeSeries[] = (float) collect($incomeGroups->get($label, []))->sum('amount');

            if ($days > 62) {
                $cursor->addMonth();
            } else {
                $cursor->addDay();
            }
        }

        return [$labels, $expenseSeries, $incomeSeries];
    }

    private function buildAiSummary(
        GeminiService $geminiService,
        Carbon $startDate,
        Carbon $endDate,
        string $currencyCode,
        float $expenseTotal,
        float $incomeReceived,
        float $payoutExpenseTotal,
        float $netIncome,
        float $netCashflow,
        $categoryTotals,
        $employeeTotals,
        array $filters,
        bool $forceRefresh = false
    ): array {
        if (! config('google_ai.enabled')) {
            return [null, 'Google AI is disabled.'];
        }

        $cacheKey = 'ai:expense-dashboard:'.md5(json_encode([
            'start' => $startDate->toDateString(),
            'end' => $endDate->toDateString(),
            'filters' => $filters,
        ]));

        try {
            $builder = function () use (
                $geminiService,
                $startDate,
                $endDate,
                $currencyCode,
                $expenseTotal,
                $incomeReceived,
                $payoutExpenseTotal,
                $netIncome,
                $netCashflow,
                $categoryTotals,
                $employeeTotals
            ) {
                $topCategories = collect($categoryTotals)->take(3)->map(function ($item) use ($currencyCode) {
                    $amount = number_format((float) ($item['total'] ?? 0), 2);

                    return "{$item['name']}: {$currencyCode} {$amount}";
                })->implode(', ');

                $topEmployees = collect($employeeTotals)->take(3)->map(function ($item) use ($currencyCode) {
                    $amount = number_format((float) ($item['total'] ?? 0), 2);

                    return "{$item['label']}: {$currencyCode} {$amount}";
                })->implode(', ');

                $prompt = <<<PROMPT
You are a finance analyst. Summarize the expense dashboard in Bengali.

Period: {$startDate->toDateString()} to {$endDate->toDateString()}
Totals:
- Total expenses: {$currencyCode} {$expenseTotal}
- Income received: {$currencyCode} {$incomeReceived}
- Payout expenses: {$currencyCode} {$payoutExpenseTotal}
- Net (Income - Expense): {$currencyCode} {$netIncome}
- Cashflow (Received - Payout): {$currencyCode} {$netCashflow}

Top categories: {$topCategories}
Top employees: {$topEmployees}

Return 4-6 bullet points, short and clear.
PROMPT;

                return $geminiService->generateText($prompt);
            };

            if ($forceRefresh) {
                $summary = $builder();
                Cache::put($cacheKey, $summary, now()->addMinutes(10));
            } else {
                $summary = Cache::remember($cacheKey, now()->addMinutes(10), $builder);
            }

            return [$summary, null];
        } catch (\Throwable $e) {
            return [null, $e->getMessage()];
        }
    }
}
