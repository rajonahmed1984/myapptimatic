<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Setting;
use App\Services\IncomeEntryService;
use App\Services\GeminiService;
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

class IncomeController extends Controller
{
    public function dashboard(Request $request, IncomeEntryService $entryService, GeminiService $geminiService): View
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

        $totalAmount = (float) $entries->sum('amount');
        $manualTotal = (float) $entries->where('source_type', 'manual')->sum('amount');
        $systemTotal = (float) $entries->where('source_type', 'system')->sum('amount');

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
            'categoryTotals' => $categoryTotals,
            'topCustomers' => $topCustomers,
            'currencySymbol' => $currencySymbol,
            'currencyCode' => $currencyCode,
            'aiSummary' => $aiSummary,
            'aiError' => $aiError,
            'trendLabels' => $trendLabels,
            'trendTotals' => $trendTotals,
        ]);
    }

    public function index(Request $request, IncomeEntryService $entryService): View
    {
        $search = trim((string) $request->input('search', ''));
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

        return view('admin.income.index', $payload);
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

    private function buildAiSummary(
        GeminiService $geminiService,
        array $filters,
        int $count,
        string $currencyCode,
        float $totalAmount,
        float $manualTotal,
        float $systemTotal,
        $categoryTotals,
        $topCustomers,
        bool $forceRefresh = false
    ): array {
        if (! config('google_ai.enabled')) {
            return [null, 'Google AI is disabled.'];
        }

        $cacheKey = 'ai:income-dashboard:' . md5(json_encode($filters));

        try {
            $builder = function () use (
                $geminiService,
                $filters,
                $count,
                $currencyCode,
                $totalAmount,
                $manualTotal,
                $systemTotal,
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
}
