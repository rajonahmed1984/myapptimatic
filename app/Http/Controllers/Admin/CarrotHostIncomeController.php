<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WhmcsClient;
use Carbon\Carbon;
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
        $monthStart = $selectedMonth->copy()->startOfMonth();
        $monthEnd = $selectedMonth->copy()->endOfMonth();
        $today = now()->startOfDay();
        if ($monthEnd->greaterThan($today)) {
            $monthEnd = $today;
        }

        $startDate = $monthStart->toDateString();
        $endDate = $monthEnd->toDateString();

        $earliestMonth = Carbon::parse(self::DEFAULT_START)->startOfMonth();
        $prevMonth = $selectedMonth->copy()->subMonth();
        $nextMonth = $selectedMonth->copy()->addMonth();
        $hasPrev = $prevMonth->greaterThanOrEqualTo($earliestMonth);
        $hasNext = $selectedMonth->lt(now()->startOfMonth());

        $cacheKey = 'whmcs:carrothost:' . $startDate . ':' . $endDate;

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($client, $startDate, $endDate) {
            $whmcsErrors = [];

            $transactions = $this->fetchAll($client, 'GetTransactions', [
                'startdate' => $startDate,
                'enddate' => $endDate,
                'orderby' => 'date',
                'order' => 'desc',
            ], 'transactions', 'transaction', $whmcsErrors);

            $transactions = $this->filterByDate($transactions, 'date', $startDate, $endDate);
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
        });

        if (array_key_exists('errors', $payload) && ! array_key_exists('whmcsErrors', $payload)) {
            $payload['whmcsErrors'] = $payload['errors'];
        }
        unset($payload['errors']);

        $payload['month'] = $selectedMonth->format('Y-m');
        $payload['monthLabel'] = $selectedMonth->format('F Y');
        $payload['prevMonth'] = $hasPrev ? $prevMonth->format('Y-m') : null;
        $payload['prevMonthLabel'] = $hasPrev ? $prevMonth->format('F Y') : null;
        $payload['nextMonth'] = $hasNext ? $nextMonth->format('Y-m') : null;
        $payload['nextMonthLabel'] = $hasNext ? $nextMonth->format('F Y') : null;

        return view('admin.income.carrothost', $payload);
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
}
