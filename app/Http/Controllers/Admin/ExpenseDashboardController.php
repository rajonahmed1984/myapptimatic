<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Services\ExpenseEntryService;
use App\Services\GeminiService;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $expenseBySource = [
            'manual' => (float) $entries->where('source_type', 'expense')->sum('amount'),
            'salary' => (float) $entries->where('expense_type', 'salary')->sum('amount'),
            'contract_payout' => (float) $entries->where('expense_type', 'contract_payout')->sum('amount'),
            'sales_payout' => (float) $entries->where('expense_type', 'sales_payout')->sum('amount'),
        ];

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
        $baseFilters = collect($filters)->except(['start_date', 'end_date'])->toArray();

        $monthlyTotal = (float) $entryService->entries(array_merge($baseFilters, [
            'start_date' => $monthStart,
            'end_date' => $now->toDateString(),
        ]))->sum('amount');

        $yearlyTotal = (float) $entryService->entries(array_merge($baseFilters, [
            'start_date' => $yearStart,
            'end_date' => $now->toDateString(),
        ]))->sum('amount');

        $topCategories = $entries->groupBy('category_id')
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

        $periodSeries = $this->buildPeriodSeries($entries, $startDate, $endDate);
        $expenseStatus = $this->buildExpenseStatus($entryService, $baseFilters, $expenseTotal, $now);

        $categories = ExpenseCategory::query()->orderBy('name')->get();
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
            $expenseBySource,
            $categoryTotals,
            $employeeTotals,
            $filters,
            $forceAiRefresh
        );

        return Inertia::render('Admin/Expenses/Dashboard', [
            'pageTitle' => 'Expense Dashboard',
            'filters' => $filters,
            'expenseTotal' => $expenseTotal,
            'expenseBySource' => $expenseBySource,
            'expenseStatus' => $expenseStatus,
            'categoryTotals' => $categoryTotals->values(),
            'employeeTotals' => $employeeTotals->values(),
            'salesRepTotals' => $salesRepTotals->values(),
            'monthlyTotal' => $monthlyTotal,
            'yearlyTotal' => $yearlyTotal,
            'topCategories' => $topCategories->values(),
            'periodSeries' => $periodSeries,
            'categories' => $categories->map(fn (ExpenseCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
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

    private function buildPeriodSeries(Collection $entries, Carbon $startDate, Carbon $endDate): array
    {
        $normalized = $entries
            ->map(function ($entry) {
                $date = ! empty($entry['expense_date']) ? Carbon::parse($entry['expense_date']) : null;
                if (! $date) {
                    return null;
                }

                $entry['_date'] = $date;

                return $entry;
            })
            ->filter()
            ->values();

        return [
            'day' => $this->buildSeriesForGranularity($normalized, $startDate, $endDate, 'day'),
            'week' => $this->buildSeriesForGranularity($normalized, $startDate, $endDate, 'week'),
            'month' => $this->buildSeriesForGranularity($normalized, $startDate, $endDate, 'month'),
        ];
    }

    private function buildSeriesForGranularity(Collection $entries, Carbon $startDate, Carbon $endDate, string $granularity): array
    {
        $seriesMap = [];
        foreach ($entries as $entry) {
            /** @var Carbon $date */
            $date = $entry['_date'];
            if ($granularity === 'day') {
                $key = $date->format('Y-m-d');
            } elseif ($granularity === 'week') {
                $key = $date->format('o-\WW');
            } else {
                $key = $date->format('Y-m');
            }

            if (! isset($seriesMap[$key])) {
                $seriesMap[$key] = [
                    'total' => 0.0,
                    'manual' => 0.0,
                    'salary' => 0.0,
                    'contract' => 0.0,
                    'sales' => 0.0,
                ];
            }

            $amount = (float) ($entry['amount'] ?? 0);
            $seriesMap[$key]['total'] += $amount;

            if (($entry['source_type'] ?? null) === 'expense') {
                $seriesMap[$key]['manual'] += $amount;
            }
            if (($entry['expense_type'] ?? null) === 'salary') {
                $seriesMap[$key]['salary'] += $amount;
            }
            if (($entry['expense_type'] ?? null) === 'contract_payout') {
                $seriesMap[$key]['contract'] += $amount;
            }
            if (($entry['expense_type'] ?? null) === 'sales_payout') {
                $seriesMap[$key]['sales'] += $amount;
            }
        }

        $labels = [];
        $total = [];
        $manual = [];
        $salary = [];
        $contract = [];
        $sales = [];

        $cursor = $startDate->copy();
        if ($granularity === 'week') {
            $cursor->startOfWeek(Carbon::MONDAY);
        } elseif ($granularity === 'month') {
            $cursor->startOfMonth();
        } else {
            $cursor->startOfDay();
        }

        $endCursor = $endDate->copy();
        if ($granularity === 'week') {
            $endCursor->endOfWeek(Carbon::SUNDAY);
        } elseif ($granularity === 'month') {
            $endCursor->endOfMonth();
        } else {
            $endCursor->endOfDay();
        }

        while ($cursor->lessThanOrEqualTo($endCursor)) {
            if ($granularity === 'day') {
                $key = $cursor->format('Y-m-d');
                $labels[] = $cursor->format('d M');
                $cursor->addDay();
            } elseif ($granularity === 'week') {
                $key = $cursor->format('o-\WW');
                $labels[] = 'W'.$cursor->format('W').' '.$cursor->format('d M');
                $cursor->addWeek();
            } else {
                $key = $cursor->format('Y-m');
                $labels[] = $cursor->format('M Y');
                $cursor->addMonth();
            }

            $bucket = $seriesMap[$key] ?? [
                'total' => 0.0,
                'manual' => 0.0,
                'salary' => 0.0,
                'contract' => 0.0,
                'sales' => 0.0,
            ];

            $total[] = (float) $bucket['total'];
            $manual[] = (float) $bucket['manual'];
            $salary[] = (float) $bucket['salary'];
            $contract[] = (float) $bucket['contract'];
            $sales[] = (float) $bucket['sales'];
        }

        return [
            'labels' => $labels,
            'total' => $total,
            'manual' => $manual,
            'salary' => $salary,
            'contract' => $contract,
            'sales' => $sales,
        ];
    }

    private function buildExpenseStatus(ExpenseEntryService $entryService, array $baseFilters, float $filteredTotal, Carbon $now): array
    {
        $sumForRange = function (Carbon $start, Carbon $end) use ($entryService, $baseFilters): float {
            return (float) $entryService->entries(array_merge($baseFilters, [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]))->sum('amount');
        };

        $today = $sumForRange($now->copy()->startOfDay(), $now->copy()->endOfDay());
        $yesterday = $sumForRange($now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay());

        $thisWeek = $sumForRange($now->copy()->startOfWeek(Carbon::MONDAY), $now->copy()->endOfWeek(Carbon::SUNDAY));
        $lastWeek = $sumForRange(
            $now->copy()->subWeek()->startOfWeek(Carbon::MONDAY),
            $now->copy()->subWeek()->endOfWeek(Carbon::SUNDAY)
        );

        $thisMonth = $sumForRange($now->copy()->startOfMonth(), $now->copy()->endOfMonth());
        $lastMonth = $sumForRange(
            $now->copy()->subMonthNoOverflow()->startOfMonth(),
            $now->copy()->subMonthNoOverflow()->endOfMonth()
        );

        return [
            'today' => [
                'label' => 'Today',
                'amount' => $today,
                'change_percent' => $this->percentChange($today, $yesterday),
                'comparison_label' => 'vs yesterday',
            ],
            'week' => [
                'label' => 'This Week',
                'amount' => $thisWeek,
                'change_percent' => $this->percentChange($thisWeek, $lastWeek),
                'comparison_label' => 'vs last week',
            ],
            'month' => [
                'label' => 'This Month',
                'amount' => $thisMonth,
                'change_percent' => $this->percentChange($thisMonth, $lastMonth),
                'comparison_label' => 'vs last month',
            ],
            'filtered' => [
                'label' => 'Filtered Total',
                'amount' => $filteredTotal,
                'change_percent' => null,
                'comparison_label' => 'selected filters',
            ],
        ];
    }

    private function percentChange(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return (($current - $previous) / $previous) * 100;
    }

    private function buildAiSummary(
        GeminiService $geminiService,
        Carbon $startDate,
        Carbon $endDate,
        string $currencyCode,
        float $expenseTotal,
        array $expenseBySource,
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
                $expenseBySource,
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

                $manualTotal = number_format((float) ($expenseBySource['manual'] ?? 0), 2);
                $salaryTotal = number_format((float) ($expenseBySource['salary'] ?? 0), 2);
                $contractTotal = number_format((float) ($expenseBySource['contract_payout'] ?? 0), 2);
                $salesTotal = number_format((float) ($expenseBySource['sales_payout'] ?? 0), 2);

                $prompt = <<<PROMPT
You are a finance analyst. Summarize the expense dashboard in Bengali.

Period: {$startDate->toDateString()} to {$endDate->toDateString()}
Totals:
- Total expenses: {$currencyCode} {$expenseTotal}
- Manual expenses: {$currencyCode} {$manualTotal}
- Salary expenses: {$currencyCode} {$salaryTotal}
- Contract payouts: {$currencyCode} {$contractTotal}
- Sales rep payouts: {$currencyCode} {$salesTotal}

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
