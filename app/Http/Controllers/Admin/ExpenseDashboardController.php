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
        $startDateInput = $request->query('start_date');
        $endDateInput = $request->query('end_date');

        $sourceFilters = $request->input('sources', []);
        if (! is_array($sourceFilters) || empty($sourceFilters)) {
            $sourceFilters = ['manual', 'salary', 'contract_payout', 'sales_payout'];
        }

        [$personType, $personId] = $entryService->parsePersonFilter($request->query('person'));

        $filters = [
            'start_date' => $startDateInput,
            'end_date' => $endDateInput,
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
        $yearStart = $now->copy()->startOfYear()->toDateString();
        $baseFilters = collect($filters)->except(['start_date', 'end_date'])->toArray();
        $allTimeTotal = (float) $entryService->entries($baseFilters)->sum('amount');

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

        $periodSeries = $this->buildPeriodSeries($entries);
        $expenseStatus = $this->buildExpenseStatus($entries, $allTimeTotal);

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
            $startDateInput,
            $endDateInput,
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
            'filters' => [
                'start_date' => (string) ($filters['start_date'] ?? ''),
                'end_date' => (string) ($filters['end_date'] ?? ''),
                'category_id' => (string) ($filters['category_id'] ?? ''),
                'person' => (string) ($filters['person'] ?? ''),
                'sources' => array_values($filters['sources'] ?? []),
            ],
            'expenseTotal' => $expenseTotal,
            'expenseBySource' => $expenseBySource,
            'expenseStatus' => $expenseStatus,
            'categoryTotals' => $categoryTotals->values(),
            'employeeTotals' => $employeeTotals->values(),
            'salesRepTotals' => $salesRepTotals->values(),
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

    private function buildPeriodSeries(Collection $entries): array
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

        $now = now();

        return [
            'day' => $this->buildSeriesForPeriod(
                $normalized,
                $now->copy()->startOfDay()->subDays(29),
                30,
                'day'
            ),
            'week' => $this->buildSeriesForPeriod(
                $normalized,
                $now->copy()->startOfWeek()->subWeeks(11),
                12,
                'week'
            ),
            'month' => $this->buildSeriesForPeriod(
                $normalized,
                $now->copy()->startOfMonth()->subMonths(11),
                12,
                'month'
            ),
        ];
    }

    private function buildSeriesForPeriod(Collection $entries, Carbon $start, int $slots, string $unit): array
    {
        $labels = [];
        $total = array_fill(0, $slots, 0);
        $manual = array_fill(0, $slots, 0);
        $salary = array_fill(0, $slots, 0);
        $contract = array_fill(0, $slots, 0);
        $sales = array_fill(0, $slots, 0);

        for ($index = 0; $index < $slots; $index++) {
            $bucket = $start->copy();

            if ($unit === 'month') {
                $bucket->addMonths($index);
                $labels[] = $bucket->format('M Y');
            } elseif ($unit === 'week') {
                $bucket->addWeeks($index);
                $labels[] = $bucket->format('d M');
            } else {
                $bucket->addDays($index);
                $labels[] = $bucket->format('d M');
            }
        }

        foreach ($entries as $entry) {
            $date = $entry['_date'] ?? null;
            if (! $date instanceof Carbon) {
                continue;
            }

            $amount = (float) ($entry['amount'] ?? 0);
            $bucketDate = match ($unit) {
                'month' => $date->copy()->startOfMonth(),
                'week' => $date->copy()->startOfWeek(),
                default => $date->copy()->startOfDay(),
            };

            if ($bucketDate->lt($start)) {
                continue;
            }

            $index = match ($unit) {
                'month' => (($bucketDate->year - $start->year) * 12) + ($bucketDate->month - $start->month),
                'week' => (int) floor($start->diffInDays($bucketDate) / 7),
                default => $start->diffInDays($bucketDate),
            };

            if ($index < 0 || $index >= $slots) {
                continue;
            }

            $total[$index] += $amount;

            if (($entry['source_type'] ?? null) === 'expense') {
                $manual[$index] += $amount;
            }
            if (($entry['expense_type'] ?? null) === 'salary') {
                $salary[$index] += $amount;
            }
            if (($entry['expense_type'] ?? null) === 'contract_payout') {
                $contract[$index] += $amount;
            }
            if (($entry['expense_type'] ?? null) === 'sales_payout') {
                $sales[$index] += $amount;
            }
        }

        return [
            'labels' => $labels,
            'total' => array_map(fn ($value) => round((float) $value, 2), $total),
            'manual' => array_map(fn ($value) => round((float) $value, 2), $manual),
            'salary' => array_map(fn ($value) => round((float) $value, 2), $salary),
            'contract' => array_map(fn ($value) => round((float) $value, 2), $contract),
            'sales' => array_map(fn ($value) => round((float) $value, 2), $sales),
        ];
    }

    private function buildExpenseStatus(Collection $entries, float $allTimeTotal): array
    {
        $entriesWithDate = $entries->map(function ($entry) {
            $entry['parsed_expense_date'] = ! empty($entry['expense_date']) ? Carbon::parse($entry['expense_date']) : null;

            return $entry;
        })->values();

        $sumForRange = function (Carbon $start, Carbon $end) use ($entriesWithDate): float {
            return (float) $entriesWithDate
                ->filter(function ($entry) use ($start, $end) {
                    $date = $entry['parsed_expense_date'] ?? null;

                    return $date instanceof Carbon && $date->between($start, $end);
                })
                ->sum(fn ($entry) => (float) ($entry['amount'] ?? 0));
        };

        $now = now();

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
                'amount' => $allTimeTotal,
                'change_percent' => null,
                'comparison_label' => 'all time',
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
        ?string $startDate,
        ?string $endDate,
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
            'start' => (string) ($startDate ?: ''),
            'end' => (string) ($endDate ?: ''),
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
                $periodStart = $startDate ?: 'all time';
                $periodEnd = $endDate ?: 'today';

                $prompt = <<<PROMPT
You are a finance analyst. Summarize the expense dashboard in Bengali.

Period: {$periodStart} to {$periodEnd}
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
