<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Setting;
use App\Services\GeminiService;
use App\Services\IncomeEntryService;
use App\Services\WhmcsClient;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class IncomeController extends Controller
{
    private const WHMCS_DEFAULT_START = '2026-01-01';

    private const WHMCS_PAGE_SIZE = 100;

    private const WHMCS_MAX_PAGES = 50;

    public function dashboard(Request $request, IncomeEntryService $entryService, GeminiService $geminiService, WhmcsClient $whmcsClient): InertiaResponse
    {
        $sourceFilters = $request->input('sources', []);
        if (! is_array($sourceFilters) || empty($sourceFilters)) {
            $sourceFilters = ['manual', 'system', 'credit_settlement', 'carrothost'];
        }

        $filters = [
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'category_id' => $request->query('category_id'),
            'sources' => $sourceFilters,
        ];

        $entries = $entryService->entries($filters)
            ->sortByDesc(fn ($entry) => $entry['income_date'] ?? now());

        $whmcsErrors = [];
        if (in_array('carrothost', $sourceFilters, true)) {
            $carrotHostEntries = $this->carrotHostEntries(
                $whmcsClient,
                $filters['start_date'],
                $filters['end_date'],
                $whmcsErrors
            );
            if ($carrotHostEntries->isNotEmpty()) {
                $entries = $entries->merge($carrotHostEntries);
            }
        }

        $entries = $entries->sortByDesc(fn ($entry) => $entry['income_date'] ?? now());

        $entriesWithDate = $entries->map(function ($entry) {
            $entry['parsed_income_date'] = $this->parseEntryDate($entry['income_date'] ?? null);

            return $entry;
        })->values();

        $totalAmount = (float) $entriesWithDate->sum('amount');
        $manualTotal = (float) $entriesWithDate->where('source_type', 'manual')->sum('amount');
        $systemTotal = (float) $entriesWithDate->where('source_type', 'system')->sum('amount');
        $creditSettlementTotal = (float) $entriesWithDate->where('source_type', 'credit_settlement')->sum('amount');

        $categoryTotals = $entriesWithDate
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
            ->sortByDesc('total')
            ->values()
            ->all();

        $categories = IncomeCategory::query()->orderBy('name')->get();

        $currencyCode = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($currencyCode)) {
            $currencyCode = Currency::DEFAULT;
        }
        $currencySymbol = Currency::symbol($currencyCode);

        $topCustomers = $entriesWithDate
            ->filter(fn ($entry) => ! empty($entry['customer_name']))
            ->groupBy(fn ($entry) => $entry['customer_name'])
            ->map(fn ($items) => [
                'name' => $items->first()['customer_name'],
                'total' => (float) collect($items)->sum('amount'),
            ])
            ->values()
            ->sortByDesc('total')
            ->take(5)
            ->values()
            ->all();

        $forceAiRefresh = $request->query('ai') === 'refresh';
        [$aiSummary, $aiError] = $this->buildAiSummary(
            $geminiService,
            $filters,
            $entries->count(),
            $currencyCode,
            $totalAmount,
            $manualTotal,
            $systemTotal,
            $creditSettlementTotal,
            $categoryTotals,
            $topCustomers,
            $forceAiRefresh
        );

        $incomeStatus = $this->buildIncomeStatusCards($entriesWithDate);
        $periodSeries = $this->buildPeriodSeries($entriesWithDate);
        $trendStart = $this->parseDateOrDefault($filters['start_date'] ?? null, now()->startOfMonth(), false);
        $trendEnd = $this->parseDateOrDefault($filters['end_date'] ?? null, now()->endOfDay(), true);

        [$trendLabels, $trendTotals] = $this->buildTrends($entriesWithDate, $trendStart, $trendEnd);
        $globalDateFormat = (string) config('app.date_format', 'd-m-Y');
        $recentEntries = $entriesWithDate
            ->take(15)
            ->values()
            ->map(function (array $entry) use ($globalDateFormat, $currencySymbol, $currencyCode) {
                return [
                    'key' => (string) ($entry['key'] ?? uniqid('income:', true)),
                    'income_date_display' => $this->formatEntryDate($entry['income_date'] ?? null, $globalDateFormat),
                    'title' => (string) ($entry['title'] ?? '--'),
                    'source_label' => (string) ($entry['source_label'] ?? '--'),
                    'category_name' => (string) ($entry['category_name'] ?? '--'),
                    'customer_name' => (string) (($entry['customer_name'] ?? '') ?: '--'),
                    'project_name' => (string) (($entry['project_name'] ?? '') ?: '--'),
                    'amount_display' => $currencySymbol.number_format((float) ($entry['amount'] ?? 0), 2).$currencyCode,
                ];
            })
            ->all();

        return Inertia::render('Admin/Income/Dashboard', [
            'pageTitle' => 'Income Dashboard',
            'categories' => $categories->map(fn (IncomeCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])->values(),
            'filters' => [
                'start_date' => (string) ($filters['start_date'] ?? ''),
                'end_date' => (string) ($filters['end_date'] ?? ''),
                'category_id' => (string) ($filters['category_id'] ?? ''),
                'sources' => array_values($filters['sources'] ?? []),
            ],
            'totals' => [
                'total_amount' => (float) $totalAmount,
                'manual_total' => (float) $manualTotal,
                'system_total' => (float) $systemTotal,
                'credit_settlement_total' => (float) $creditSettlementTotal,
            ],
            'income_status' => $incomeStatus,
            'period_series' => $periodSeries,
            'entries_count' => $entriesWithDate->count(),
            'recent_entries' => $recentEntries,
            'category_totals' => $categoryTotals,
            'top_customers' => $topCustomers,
            'currency' => [
                'symbol' => $currencySymbol,
                'code' => $currencyCode,
            ],
            'ai' => [
                'summary' => $aiSummary,
                'error' => $aiError,
            ],
            'trend' => [
                'labels' => $trendLabels,
                'totals' => $trendTotals,
            ],
            'whmcs_errors' => array_values($whmcsErrors),
            'routes' => [
                'dashboard' => route('admin.income.dashboard'),
                'ai_refresh' => route('admin.income.dashboard', array_merge($request->query(), ['ai' => 'refresh'])),
            ],
        ]);
    }

    public function index(
        Request $request,
        IncomeEntryService $entryService
    ): InertiaResponse {
        $search = trim((string) $request->input('search', ''));
        $sourceFilters = $request->input('sources', []);
        if (! is_array($sourceFilters) || empty($sourceFilters)) {
            $sourceFilters = ['manual', 'system', 'credit_settlement'];
        }

        $filters = [
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'category_id' => $request->query('category_id'),
            'sources' => $sourceFilters,
        ];

        $entries = $entryService->entries($filters)
            ->sortByDesc(fn ($entry) => $entry['income_date'] ?? now());

        if ($search !== '') {
            $entries = $entries->filter(function ($entry) use ($search) {
                $haystacks = [
                    $entry['title'] ?? '',
                    $entry['notes'] ?? '',
                    $entry['category_name'] ?? '',
                    $entry['customer_name'] ?? '',
                    $entry['project_name'] ?? '',
                    $entry['source_label'] ?? '',
                    $entry['source_type'] ?? '',
                    (string) ($entry['income_date'] ?? ''),
                    (string) ($entry['amount'] ?? ''),
                    (string) ($entry['source_id'] ?? ''),
                ];

                foreach ($haystacks as $value) {
                    if ($value !== '' && stripos((string) $value, $search) !== false) {
                        return true;
                    }
                }

                return false;
            });
        }

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

        return Inertia::render(
            'Admin/Income/Index',
            $this->indexInertiaProps($incomes, $search, $currencySymbol, $currencyCode)
        );
    }

    public function create(): InertiaResponse
    {
        $categories = IncomeCategory::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Income/Create', [
            'pageTitle' => 'Add Income',
            'routes' => [
                'index' => route('admin.income.index'),
                'store' => route('admin.income.store'),
            ],
            'categories' => $categories->map(function (IncomeCategory $category) {
                return [
                    'id' => $category->id,
                    'name' => (string) $category->name,
                ];
            })->values()->all(),
            'form' => [
                'fields' => [
                    'income_category_id' => (string) old('income_category_id', ''),
                    'title' => (string) old('title', ''),
                    'amount' => (string) old('amount', ''),
                    'income_date' => (string) old('income_date', now()->toDateString()),
                    'notes' => (string) old('notes', ''),
                ],
            ],
        ]);
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

    private function indexInertiaProps(
        LengthAwarePaginator $incomes,
        string $search,
        string $currencySymbol,
        string $currencyCode
    ): array {
        $globalDateFormat = (string) config('app.date_format', 'd-m-Y');

        $incomeRows = $incomes->getCollection()
            ->values()
            ->map(function ($entry) use ($currencySymbol, $currencyCode, $globalDateFormat) {
                $sourceType = (string) data_get($entry, 'source_type', 'manual');
                $sourceId = data_get($entry, 'source_id');
                $attachmentPath = (string) data_get($entry, 'attachment_path', '');

                $canViewAttachment = $sourceType === 'manual'
                    && $attachmentPath !== ''
                    && is_numeric($sourceId);

                return [
                    'key' => (string) data_get($entry, 'key', ''),
                    'id_display' => $sourceId !== null && $sourceId !== ''
                        ? (string) $sourceId
                        : '--',
                    'income_date_display' => $this->formatEntryDate(data_get($entry, 'income_date'), $globalDateFormat),
                    'title' => (string) data_get($entry, 'title', '--'),
                    'notes' => (string) (data_get($entry, 'notes') ?: ''),
                    'invoice_number' => (string) (data_get($entry, 'invoice_number') ?: ''),
                    'category_name' => (string) (data_get($entry, 'category_name') ?: '--'),
                    'source_label' => (string) (data_get($entry, 'source_label') ?: ucfirst($sourceType)),
                    'customer_name' => (string) (data_get($entry, 'customer_name') ?: '--'),
                    'project_name' => (string) (data_get($entry, 'project_name') ?: '--'),
                    'amount_display' => $currencySymbol.number_format((float) data_get($entry, 'amount', 0), 2).$currencyCode,
                    'attachment_url' => $canViewAttachment
                        ? route('admin.income.attachments.show', (int) $sourceId)
                        : null,
                ];
            })
            ->all();

        $paginationLinks = $incomes->linkCollection()
            ->map(function ($link) {
                return [
                    'url' => $link['url'],
                    'label' => (string) $link['label'],
                    'active' => (bool) $link['active'],
                ];
            })
            ->all();

        return [
            'pageTitle' => 'Income list',
            'search' => $search,
            'routes' => [
                'index' => route('admin.income.index'),
                'categories' => route('admin.income.categories.index'),
                'create' => route('admin.income.create'),
            ],
            'incomes' => $incomeRows,
            'pagination_links' => $paginationLinks,
        ];
    }

    private function formatEntryDate(mixed $value, string $format): string
    {
        if (empty($value)) {
            return '--';
        }

        try {
            return Carbon::parse((string) $value)->format($format);
        } catch (\Throwable) {
            return '--';
        }
    }

    private function buildAiSummary(
        GeminiService $geminiService,
        array $filters,
        int $count,
        string $currencyCode,
        float $totalAmount,
        float $manualTotal,
        float $systemTotal,
        float $creditSettlementTotal,
        $categoryTotals,
        $topCustomers,
        bool $forceRefresh = false
    ): array {
        if (! config('google_ai.enabled')) {
            return [null, 'Google AI is disabled.'];
        }

        $cacheKey = 'ai:income-dashboard:'.md5(json_encode($filters));

        try {
            $builder = function () use (
                $geminiService,
                $filters,
                $count,
                $currencyCode,
                $totalAmount,
                $manualTotal,
                $systemTotal,
                $creditSettlementTotal,
                $categoryTotals,
                $topCustomers
            ) {
                $startDate = $filters['start_date'] ?: 'all time';
                $endDate = $filters['end_date'] ?: 'today';

                $topCategories = collect($categoryTotals)->take(3)->map(function ($item) use ($currencyCode) {
                    $amount = number_format((float) ($item['total'] ?? 0), 2);

                    return "{$item['name']}: {$currencyCode} {$amount}";
                })->implode(', ');

                $topCustomerText = collect($topCustomers)->take(3)->map(function ($item) use ($currencyCode) {
                    $amount = number_format((float) ($item['total'] ?? 0), 2);

                    return "{$item['name']}: {$currencyCode} {$amount}";
                })->implode(', ');

                $prompt = <<<PROMPT
You are a finance analyst. Summarize the income dashboard in Bengali.

Period: {$startDate} to {$endDate}
Totals:
- Total income: {$currencyCode} {$totalAmount}
- Manual income: {$currencyCode} {$manualTotal}
- System income: {$currencyCode} {$systemTotal}
- Credit settlement: {$currencyCode} {$creditSettlementTotal}
- Entries: {$count}

Top categories: {$topCategories}
Top customers: {$topCustomerText}

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

    private function buildTrends($entries, Carbon $startDate, Carbon $endDate): array
    {
        $days = $startDate->diffInDays($endDate);
        $format = $days > 62 ? 'Y-m' : 'Y-m-d';

        $groups = $entries->groupBy(function ($entry) use ($format) {
            $date = $entry['income_date'] ? Carbon::parse($entry['income_date']) : now();

            return $date->format($format);
        });

        $labels = [];
        $totals = [];
        $cursor = $startDate->copy();

        while ($cursor->lessThanOrEqualTo($endDate)) {
            $label = $cursor->format($format);
            $labels[] = $label;
            $totals[] = (float) collect($groups->get($label, []))->sum('amount');

            if ($days > 62) {
                $cursor->addMonth();
            } else {
                $cursor->addDay();
            }
        }

        return [$labels, $totals];
    }

    private function buildIncomeStatusCards(Collection $entries): array
    {
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $yesterdayStart = $todayStart->copy()->subDay()->startOfDay();
        $yesterdayEnd = $todayStart->copy()->subDay()->endOfDay();

        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();
        $prevWeekStart = $weekStart->copy()->subWeek()->startOfWeek();
        $prevWeekEnd = $weekStart->copy()->subWeek()->endOfWeek();

        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $prevMonthStart = $monthStart->copy()->subMonth()->startOfMonth();
        $prevMonthEnd = $monthStart->copy()->subMonth()->endOfMonth();

        $todayAmount = $this->sumForRange($entries, $todayStart, $todayEnd);
        $yesterdayAmount = $this->sumForRange($entries, $yesterdayStart, $yesterdayEnd);
        $weekAmount = $this->sumForRange($entries, $weekStart, $weekEnd);
        $prevWeekAmount = $this->sumForRange($entries, $prevWeekStart, $prevWeekEnd);
        $monthAmount = $this->sumForRange($entries, $monthStart, $monthEnd);
        $prevMonthAmount = $this->sumForRange($entries, $prevMonthStart, $prevMonthEnd);

        return [
            'today' => [
                'label' => 'Today',
                'amount' => $todayAmount,
                'change_percent' => $this->percentChange($todayAmount, $yesterdayAmount),
                'comparison_label' => 'vs yesterday',
            ],
            'week' => [
                'label' => 'This Week',
                'amount' => $weekAmount,
                'change_percent' => $this->percentChange($weekAmount, $prevWeekAmount),
                'comparison_label' => 'vs last week',
            ],
            'month' => [
                'label' => 'This Month',
                'amount' => $monthAmount,
                'change_percent' => $this->percentChange($monthAmount, $prevMonthAmount),
                'comparison_label' => 'vs last month',
            ],
            'overall' => [
                'label' => 'Filtered Total',
                'amount' => (float) $entries->sum(fn ($entry) => (float) ($entry['amount'] ?? 0)),
                'change_percent' => null,
                'comparison_label' => 'selected filters',
            ],
        ];
    }

    private function buildPeriodSeries(Collection $entries): array
    {
        $now = now();

        return [
            'day' => $this->buildSeriesForPeriod(
                $entries,
                $now->copy()->startOfDay()->subDays(29),
                30,
                'day'
            ),
            'week' => $this->buildSeriesForPeriod(
                $entries,
                $now->copy()->startOfWeek()->subWeeks(11),
                12,
                'week'
            ),
            'month' => $this->buildSeriesForPeriod(
                $entries,
                $now->copy()->startOfMonth()->subMonths(11),
                12,
                'month'
            ),
        ];
    }

    private function buildSeriesForPeriod(
        Collection $entries,
        Carbon $start,
        int $slots,
        string $unit
    ): array {
        $labels = [];
        $total = array_fill(0, $slots, 0);
        $manual = array_fill(0, $slots, 0);
        $system = array_fill(0, $slots, 0);

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
            $date = $entry['parsed_income_date'] ?? null;
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
            if (($entry['source_type'] ?? '') === 'manual') {
                $manual[$index] += $amount;
            } else {
                $system[$index] += $amount;
            }
        }

        return [
            'labels' => $labels,
            'total' => array_map(fn ($value) => round((float) $value, 2), $total),
            'manual' => array_map(fn ($value) => round((float) $value, 2), $manual),
            'system' => array_map(fn ($value) => round((float) $value, 2), $system),
        ];
    }

    private function sumForRange(Collection $entries, Carbon $start, Carbon $end): float
    {
        return (float) $entries
            ->filter(function ($entry) use ($start, $end) {
                $date = $entry['parsed_income_date'] ?? null;

                return $date instanceof Carbon && $date->between($start, $end);
            })
            ->sum(fn ($entry) => (float) ($entry['amount'] ?? 0));
    }

    private function percentChange(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return $current == 0.0 ? 0.0 : null;
        }

        return (($current - $previous) / $previous) * 100;
    }

    private function parseEntryDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function carrotHostEntries(
        WhmcsClient $client,
        ?string $startDate,
        ?string $endDate,
        array &$errors
    ): Collection {
        $start = $this->parseDateOrDefault($startDate, Carbon::parse(self::WHMCS_DEFAULT_START), false)->toDateString();
        $end = $this->parseDateOrDefault($endDate, now(), true)->toDateString();

        $cacheKey = 'whmcs:carrothost:transactions:'.$start.':'.$end;

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($client, $start, $end) {
            $whmcsErrors = [];
            $transactions = $this->fetchAllWhmcs(
                $client,
                'GetTransactions',
                [
                    'startdate' => $start,
                    'enddate' => $end,
                    'orderby' => 'date',
                    'order' => 'desc',
                ],
                'transactions',
                'transaction',
                $whmcsErrors
            );

            $transactions = $this->filterWhmcsByDate($transactions, 'date', $start, $end);

            return [
                'transactions' => $transactions,
                'errors' => $whmcsErrors,
            ];
        });

        $errors = array_merge($errors, $payload['errors'] ?? []);

        $transactions = $payload['transactions'] ?? [];
        if (empty($transactions)) {
            return collect();
        }

        return collect($transactions)->map(function ($row) {
            $amount = (float) ($row['amountin'] ?? 0);
            $invoiceId = $row['invoiceid'] ?? null;
            $transId = $row['transid'] ?? ($row['id'] ?? null);
            $clientName = $row['clientname'] ?? ($row['userid'] ?? null);
            $gateway = $row['gateway'] ?? null;
            $title = $invoiceId ? "WHMCS payment (Invoice #{$invoiceId})" : 'WHMCS payment';

            return [
                'key' => 'carrothost:transaction:'.($transId ?: uniqid()),
                'source_type' => 'carrothost',
                'source_label' => 'CarrotHost',
                'source_id' => $transId,
                'title' => $title,
                'amount' => $amount,
                'income_date' => $row['date'] ?? null,
                'category_id' => 'carrothost',
                'category_name' => 'CarrotHost',
                'notes' => $gateway ? "Gateway: {$gateway}" : null,
                'attachment_path' => null,
                'customer_id' => null,
                'customer_name' => $clientName,
                'project_id' => null,
                'project_name' => null,
            ];
        });
    }

    private function fetchAllWhmcs(
        WhmcsClient $client,
        string $action,
        array $params,
        string $rootKey,
        ?string $itemKey,
        array &$errors
    ): array {
        $items = [];
        $offset = 0;

        for ($page = 0; $page < self::WHMCS_MAX_PAGES; $page++) {
            $result = $client->call($action, array_merge($params, [
                'limitstart' => $offset,
                'limitnum' => self::WHMCS_PAGE_SIZE,
            ]));

            if (! $result['ok']) {
                $errors[] = $action.': '.$result['error'];
                break;
            }

            $data = $result['data'] ?? [];
            $container = $data[$rootKey] ?? [];
            $batch = $this->normalizeWhmcsList($container, $itemKey);

            if (empty($batch)) {
                break;
            }

            $items = array_merge($items, $batch);

            $total = (int) ($data['totalresults'] ?? 0);
            $offset += self::WHMCS_PAGE_SIZE;

            if ($total > 0 && count($items) >= $total) {
                break;
            }

            if (count($batch) < self::WHMCS_PAGE_SIZE) {
                break;
            }
        }

        return $items;
    }

    private function normalizeWhmcsList($container, ?string $itemKey): array
    {
        if (! is_array($container)) {
            return [];
        }

        $items = $container;
        if ($itemKey && array_key_exists($itemKey, $container)) {
            $items = $container[$itemKey];
        }

        if ($items === null || $items === '') {
            return [];
        }

        if (is_array($items) && array_is_list($items)) {
            return $items;
        }

        return is_array($items) ? [$items] : [];
    }

    private function filterWhmcsByDate(array $items, string $dateKey, string $start, string $end): array
    {
        return array_values(array_filter($items, function ($item) use ($dateKey, $start, $end) {
            $value = $item[$dateKey] ?? null;
            if (! $value) {
                return true;
            }

            try {
                $date = Carbon::parse($value)->toDateString();
            } catch (\Throwable $e) {
                return true;
            }

            return $date >= $start && $date <= $end;
        }));
    }

    private function parseDateOrDefault(mixed $value, Carbon $default, bool $endOfDay): Carbon
    {
        if ($value === null || $value === '') {
            return $default->copy();
        }

        try {
            $date = Carbon::parse((string) $value);
        } catch (\Throwable) {
            return $default->copy();
        }

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }
}
