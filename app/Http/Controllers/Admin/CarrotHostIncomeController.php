<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WhmcsClient;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CarrotHostIncomeController extends Controller
{
    private const DEFAULT_START = '2026-01-01';
    private const PAGE_SIZE = 100;
    private const MAX_PAGES = 50;

    public function index(Request $request, WhmcsClient $client): View
    {
        $selectedMonth = $this->resolveMonth($request->query('month'));
        [$startDate, $endDate] = $this->monthRange($selectedMonth);

        $earliestMonth = Carbon::parse(self::DEFAULT_START)->startOfMonth();
        $prevMonth = $selectedMonth->copy()->subMonth();
        $nextMonth = $selectedMonth->copy()->addMonth();
        $hasPrev = $prevMonth->greaterThanOrEqualTo($earliestMonth);
        $hasNext = $selectedMonth->lt(now()->startOfMonth());

        $payload = $this->loadPayload($client, $startDate, $endDate);
        $payload['transactions'] = collect($this->sortTransactionsNewestFirst((array) ($payload['transactions'] ?? []), 'date'))
            ->map(fn (array $row) => $this->enrichClientName($client, $row))
            ->all();

        $payload['month'] = $selectedMonth->format('Y-m');
        $payload['monthLabel'] = $selectedMonth->format('F Y');
        $payload['prevMonth'] = $hasPrev ? $prevMonth->format('Y-m') : null;
        $payload['prevMonthLabel'] = $hasPrev ? $prevMonth->format('F Y') : null;
        $payload['nextMonth'] = $hasNext ? $nextMonth->format('Y-m') : null;
        $payload['nextMonthLabel'] = $hasNext ? $nextMonth->format('F Y') : null;

        return view('admin.income.carrothost', $payload);
    }

    public function sync(Request $request, WhmcsClient $client): JsonResponse|RedirectResponse
    {
        $selectedMonth = $this->resolveMonth((string) $request->input('month', $request->query('month', '')));
        [$startDate, $endDate] = $this->monthRange($selectedMonth);

        $payload = $this->loadPayload($client, $startDate, $endDate, true);
        $warningCount = count((array) ($payload['whmcsErrors'] ?? []));

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => 'CarrotHost data synced.',
                'warnings' => $warningCount,
                'month' => $selectedMonth->format('Y-m'),
                'range' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
            ]);
        }

        return redirect()
            ->route('admin.income.carrothost', ['month' => $selectedMonth->format('Y-m')])
            ->with('status', 'CarrotHost data synced.');
    }

    private function fetchAll(
        WhmcsClient $client,
        string $action,
        array $params,
        string $rootKey,
        ?string $itemKey,
        array &$errors
    ): array {
        $items = [];
        $offset = 0;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $result = $client->call($action, array_merge($params, [
                'limitstart' => $offset,
                'limitnum' => self::PAGE_SIZE,
            ]));

            if (! $result['ok']) {
                $errors[] = $action . ': ' . $result['error'];
                break;
            }

            $data = $result['data'] ?? [];
            $container = $data[$rootKey] ?? [];
            $batch = $this->normalizeList($container, $itemKey);

            if (empty($batch)) {
                break;
            }

            $items = array_merge($items, $batch);

            $total = (int) ($data['totalresults'] ?? 0);
            $offset += self::PAGE_SIZE;

            if ($total > 0 && count($items) >= $total) {
                break;
            }

            if (count($batch) < self::PAGE_SIZE) {
                break;
            }
        }

        return $items;
    }

    private function normalizeList($container, ?string $itemKey): array
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

    private function filterByDate(array $items, string $dateKey, string $start, string $end): array
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

    private function sortTransactionsNewestFirst(array $items, string $dateKey): array
    {
        usort($items, function ($a, $b) use ($dateKey) {
            $aTime = $this->timestampFromValue($a[$dateKey] ?? null);
            $bTime = $this->timestampFromValue($b[$dateKey] ?? null);

            if ($aTime === $bTime) {
                return strcmp((string) ($b['transid'] ?? ''), (string) ($a['transid'] ?? ''));
            }

            return $bTime <=> $aTime;
        });

        return $items;
    }

    private function timestampFromValue($value): int
    {
        if (! $value) {
            return 0;
        }

        try {
            return Carbon::parse((string) $value)->timestamp;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function resolveMonth(?string $month): Carbon
    {
        if (! is_string($month) || trim($month) === '') {
            return now()->startOfMonth();
        }

        try {
            return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            return now()->startOfMonth();
        }
    }

    private function monthRange(Carbon $selectedMonth): array
    {
        $monthStart = $selectedMonth->copy()->startOfMonth();
        $monthEnd = $selectedMonth->copy()->endOfMonth();
        $today = now()->startOfDay();

        if ($monthEnd->greaterThan($today)) {
            $monthEnd = $today;
        }

        return [$monthStart->toDateString(), $monthEnd->toDateString()];
    }

    private function cacheKey(string $startDate, string $endDate): string
    {
        return 'whmcs:carrothost:' . $startDate . ':' . $endDate;
    }

    private function loadPayload(WhmcsClient $client, string $startDate, string $endDate, bool $forceRefresh = false): array
    {
        $cacheKey = $this->cacheKey($startDate, $endDate);

        if ($forceRefresh) {
            Cache::forget($cacheKey);
            $payload = $this->buildPayload($client, $startDate, $endDate);
            Cache::put($cacheKey, $payload, now()->addMinutes(10));

            return $payload;
        }

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($client, $startDate, $endDate) {
            return $this->buildPayload($client, $startDate, $endDate);
        });
    }

    private function buildPayload(WhmcsClient $client, string $startDate, string $endDate): array
    {
        $whmcsErrors = [];

        $transactions = $this->fetchAll($client, 'GetTransactions', [
            'startdate' => $startDate,
            'enddate' => $endDate,
            'orderby' => 'date',
            'order' => 'desc',
        ], 'transactions', 'transaction', $whmcsErrors);

        $transactions = $this->filterByDate($transactions, 'date', $startDate, $endDate);
        $transactions = $this->sortTransactionsNewestFirst($transactions, 'date');
        $amountInSubtotal = $this->sumField($transactions, 'amountin');
        $feesSubtotal = $this->sumField($transactions, 'fees');

        return [
            'transactions' => $transactions,
            'amountInSubtotal' => $amountInSubtotal,
            'feesSubtotal' => $feesSubtotal,
            'amountInSubtotalDisplay' => $this->formatMoney($amountInSubtotal),
            'feesSubtotalDisplay' => $this->formatMoney($feesSubtotal),
            'whmcsErrors' => $whmcsErrors,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
    }

    private function sumField(array $rows, string $key): float
    {
        $total = 0.0;

        foreach ($rows as $row) {
            $total += $this->normalizeMoney($row[$key] ?? null);
        }

        return $total;
    }

    private function normalizeMoney($value): float
    {
        if ($value === null) {
            return 0.0;
        }

        $clean = preg_replace('/[^\d\.\-]/', '', (string) $value);
        if ($clean === '' || $clean === '-' || $clean === '.') {
            return 0.0;
        }

        return (float) $clean;
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 2);
    }

    private function enrichClientName(WhmcsClient $client, array $row): array
    {
        $displayName = trim((string) ($row['clientname'] ?? ''));

        if ($displayName === '') {
            $firstName = trim((string) ($row['firstname'] ?? ''));
            $lastName = trim((string) ($row['lastname'] ?? ''));
            $displayName = trim($firstName . ' ' . $lastName);
        }

        if ($displayName === '') {
            $clientId = (int) ($row['userid'] ?? $row['clientid'] ?? 0);
            if ($clientId > 0) {
                $displayName = $this->fetchClientNameById($client, $clientId);
            }
        }

        if ($displayName !== '') {
            $row['clientname'] = $displayName;
        }

        return $row;
    }

    private function fetchClientNameById(WhmcsClient $client, int $clientId): string
    {
        $cacheKey = 'whmcs:carrothost:client-name:' . $clientId;

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($client, $clientId) {
            $result = $client->call('GetClientsDetails', [
                'clientid' => $clientId,
            ]);

            if (! ($result['ok'] ?? false)) {
                return '';
            }

            $data = $result['data'] ?? [];
            $firstName = trim((string) ($data['firstname'] ?? ''));
            $lastName = trim((string) ($data['lastname'] ?? ''));
            $fullName = trim($firstName . ' ' . $lastName);

            if ($fullName !== '') {
                return $fullName;
            }

            return trim((string) ($data['companyname'] ?? ''));
        });
    }
}
