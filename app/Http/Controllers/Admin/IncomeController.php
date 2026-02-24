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
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class IncomeController extends Controller
{
    private const WHMCS_DEFAULT_START = '2026-01-01';

    private const WHMCS_PAGE_SIZE = 100;

    private const WHMCS_MAX_PAGES = 50;

    public function dashboard(Request $request, IncomeEntryService $entryService, GeminiService $geminiService, WhmcsClient $whmcsClient): View
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

        $totalAmount = (float) $entries->sum('amount');
        $manualTotal = (float) $entries->where('source_type', 'manual')->sum('amount');
        $systemTotal = (float) $entries->where('source_type', 'system')->sum('amount');
        $creditSettlementTotal = (float) $entries->where('source_type', 'credit_settlement')->sum('amount');

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

        $topCustomers = $entries
            ->filter(fn ($entry) => ! empty($entry['customer_name']))
            ->groupBy(fn ($entry) => $entry['customer_name'])
            ->map(fn ($items) => [
                'name' => $items->first()['customer_name'],
                'total' => (float) collect($items)->sum('amount'),
            ])
            ->values()
            ->sortByDesc('total')
            ->take(5);

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

        $trendStart = $filters['start_date']
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : now()->startOfMonth();
        $trendEnd = $filters['end_date']
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : now()->endOfDay();

        [$trendLabels, $trendTotals] = $this->buildTrends($entries, $trendStart, $trendEnd);

        return view('admin.income.dashboard', [
            'categories' => $categories,
            'filters' => $filters,
            'totalAmount' => $totalAmount,
            'manualTotal' => $manualTotal,
            'systemTotal' => $systemTotal,
            'creditSettlementTotal' => $creditSettlementTotal,
            'categoryTotals' => $categoryTotals,
            'topCustomers' => $topCustomers,
            'currencySymbol' => $currencySymbol,
            'currencyCode' => $currencyCode,
            'aiSummary' => $aiSummary,
            'aiError' => $aiError,
            'trendLabels' => $trendLabels,
            'trendTotals' => $trendTotals,
            'whmcsErrors' => $whmcsErrors,
        ]);
    }

    public function index(
        Request $request,
        IncomeEntryService $entryService
    ): View|InertiaResponse {
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

        $payload = [
            'incomes' => $incomes,
            'categories' => $categories,
            'filters' => $filters,
            'totalAmount' => $totalAmount,
            'categoryTotals' => $categoryTotals,
            'currencySymbol' => $currencySymbol,
            'currencyCode' => $currencyCode,
            'search' => $search,
        ];

        if ($request->header('HX-Request')) {
            return view('admin.income.partials.table', $payload);
        }

        return Inertia::render(
            'Admin/Income/Index',
            $this->indexInertiaProps($incomes, $search, $currencySymbol, $currencyCode)
        );
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
                    'income_date_display' => $this->formatEntryDate(data_get($entry, 'income_date'), $globalDateFormat),
                    'title' => (string) data_get($entry, 'title', '--'),
                    'notes' => (string) (data_get($entry, 'notes') ?: ''),
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

    private function carrotHostEntries(
        WhmcsClient $client,
        ?string $startDate,
        ?string $endDate,
        array &$errors
    ): Collection {
        $start = $startDate ? Carbon::parse($startDate)->toDateString() : self::WHMCS_DEFAULT_START;
        $end = $endDate ? Carbon::parse($endDate)->toDateString() : now()->toDateString();

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
}
