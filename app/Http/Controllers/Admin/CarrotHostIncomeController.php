<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WhmcsClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class CarrotHostIncomeController extends Controller
{
    private const DEFAULT_START = '2026-01-01';
    private const PAGE_SIZE = 100;
    private const MAX_PAGES = 50;

    public function index(WhmcsClient $client): View
    {
        $startDate = Carbon::parse(self::DEFAULT_START)->toDateString();
        $endDate = now()->toDateString();

        $cacheKey = 'whmcs:carrothost:' . $startDate . ':' . $endDate;

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($client, $startDate, $endDate) {
            $whmcsErrors = [];

            $transactions = $this->fetchAll($client, 'GetTransactions', [
                'startdate' => $startDate,
                'enddate' => $endDate,
                'orderby' => 'date',
                'order' => 'desc',
            ], 'transactions', 'transaction', $whmcsErrors);

            $invoices = $this->fetchAll($client, 'GetInvoices', [
                'status' => 'Paid',
                'orderby' => 'datepaid',
                'order' => 'desc',
            ], 'invoices', 'invoice', $whmcsErrors);

            $invoicePayments = $this->fetchAll($client, 'GetInvoicePayments', [
                'orderby' => 'date',
                'order' => 'desc',
            ], 'payments', 'payment', $whmcsErrors);

            $transactions = $this->filterByDate($transactions, 'date', $startDate, $endDate);
            $invoices = $this->filterByDate($invoices, 'datepaid', $startDate, $endDate);
            $invoicePayments = $this->filterByDate($invoicePayments, 'date', $startDate, $endDate);

            return [
                'transactions' => $transactions,
                'invoices' => $invoices,
                'invoicePayments' => $invoicePayments,
                'whmcsErrors' => $whmcsErrors,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ];
        });

        if (array_key_exists('errors', $payload) && ! array_key_exists('whmcsErrors', $payload)) {
            $payload['whmcsErrors'] = $payload['errors'];
        }
        unset($payload['errors']);

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
}
