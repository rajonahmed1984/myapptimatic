<?php

namespace App\Services;

use App\Models\AccountingEntry;
use App\Models\Income;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class IncomeEntryService
{
    private const WHMCS_DEFAULT_START = '2026-01-01';
    private const WHMCS_PAGE_SIZE = 100;
    private const WHMCS_MAX_PAGES = 50;

    public function __construct(
        private readonly WhmcsClient $whmcsClient
    ) {
    }

    public function entries(array $filters = []): Collection
    {
        $sources = $filters['sources'] ?? ['manual', 'system'];
        $categoryIdFilter = $filters['category_id'] ?? null;

        $startDate = $this->parseFilterDate($filters['start_date'] ?? null, false);
        $endDate = $this->parseFilterDate($filters['end_date'] ?? null, true);

        $entries = collect();

        if (in_array('manual', $sources, true)) {
            $query = Income::query()->with('category');

            if ($startDate) {
                $query->whereDate('income_date', '>=', $startDate->toDateString());
            }
            if ($endDate) {
                $query->whereDate('income_date', '<=', $endDate->toDateString());
            }
            if ($categoryIdFilter) {
                $query->where('income_category_id', $categoryIdFilter);
            }

            $manualIncomes = $query->get();

            foreach ($manualIncomes as $income) {
                $entries->push([
                    'key' => 'income:'.$income->id,
                    'source_type' => 'manual',
                    'source_label' => 'Manual',
                    'source_id' => $income->id,
                    'title' => $income->title,
                    'amount' => (float) $income->amount,
                    'income_date' => $income->income_date,
                    'category_id' => $income->income_category_id,
                    'category_name' => $income->category?->name ?? '--',
                    'notes' => $income->notes,
                    'attachment_path' => $income->attachment_path,
                    'invoice_number' => null,
                    'customer_id' => null,
                    'customer_name' => null,
                    'project_id' => null,
                    'project_name' => null,
                ]);
            }
        }

        if (in_array('system', $sources, true)) {
            $query = AccountingEntry::query()
                ->with(['customer', 'invoice.project'])
                ->where('type', 'payment');

            if ($startDate) {
                $query->whereDate('entry_date', '>=', $startDate->toDateString());
            }
            if ($endDate) {
                $query->whereDate('entry_date', '<=', $endDate->toDateString());
            }

            $systemEntries = $query->get();

            foreach ($systemEntries as $entry) {
                $invoice = $entry->invoice;
                $project = $invoice?->project;
                $invoiceNumber = $invoice?->number ?: ($invoice?->id ?: $entry->invoice_id);

                $entries->push([
                    'key' => 'payment:'.$entry->id,
                    'source_type' => 'system',
                    'source_label' => 'System',
                    'source_id' => $entry->id,
                    'title' => $entry->description ?: 'Invoice payment',
                    'amount' => (float) $entry->amount,
                    'income_date' => $entry->entry_date,
                    'category_id' => null,
                    'category_name' => 'System',
                    'notes' => $entry->reference,
                    'attachment_path' => null,
                    'invoice_number' => $invoiceNumber,
                    'customer_id' => $entry->customer_id,
                    'customer_name' => $entry->customer?->name,
                    'project_id' => $project?->id,
                    'project_name' => $project?->name,
                ]);
            }
        }

        if (in_array('credit_settlement', $sources, true)) {
            $query = AccountingEntry::query()
                ->with(['customer', 'invoice.project'])
                ->where('type', 'credit')
                ->whereNotNull('invoice_id')
                ->latest('entry_date')
                ->latest('id');

            if ($startDate) {
                $query->whereDate('entry_date', '>=', $startDate->toDateString());
            }
            if ($endDate) {
                $query->whereDate('entry_date', '<=', $endDate->toDateString());
            }

            $creditSettlements = $query->get()
                ->unique(function (AccountingEntry $entry) {
                    $amount = number_format((float) $entry->amount, 2, '.', '');
                    return $entry->invoice_id . ':' . strtoupper((string) $entry->currency) . ':' . $amount;
                })
                ->values();

            foreach ($creditSettlements as $entry) {
                $invoice = $entry->invoice;
                $project = $invoice?->project;
                $invoiceNumber = $invoice?->number ?: $invoice?->id;
                $title = $invoiceNumber
                    ? "Credit settlement (Invoice #{$invoiceNumber})"
                    : 'Credit settlement';

                $entries->push([
                    'key' => 'credit_settlement:'.$entry->id,
                    'source_type' => 'credit_settlement',
                    'source_label' => 'Credit Settlement',
                    'source_id' => $entry->id,
                    'title' => $title,
                    'amount' => (float) $entry->amount,
                    'income_date' => $entry->entry_date,
                    'category_id' => null,
                    'category_name' => 'Credit Settlement',
                    'notes' => $entry->reference,
                    'attachment_path' => null,
                    'invoice_number' => $invoiceNumber,
                    'customer_id' => $entry->customer_id,
                    'customer_name' => $entry->customer?->name,
                    'project_id' => $project?->id,
                    'project_name' => $project?->name,
                ]);
            }
        }

        if (in_array('carrothost', $sources, true)) {
            $entries = $entries->merge($this->carrotHostEntries($startDate, $endDate));
        }

        return $entries;
    }

    private function carrotHostEntries(?Carbon $startDate, ?Carbon $endDate): Collection
    {
        if (! $this->whmcsClient->isConfigured()) {
            return collect();
        }

        $start = ($startDate?->copy() ?? Carbon::parse(self::WHMCS_DEFAULT_START)->startOfDay())->toDateString();
        $end = ($endDate?->copy() ?? now()->endOfDay())->toDateString();

        $cacheKey = 'income-service:carrothost:transactions:'.$start.':'.$end;
        $transactions = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($start, $end) {
            $errors = [];
            $rows = $this->fetchAllWhmcs(
                'GetTransactions',
                [
                    'startdate' => $start,
                    'enddate' => $end,
                    'orderby' => 'date',
                    'order' => 'desc',
                ],
                'transactions',
                'transaction',
                $errors
            );

            return $this->filterWhmcsByDate($rows, 'date', $start, $end);
        });

        return collect($transactions)->map(function ($row) {
            $amountIn = $this->normalizeMoney($row['amountin'] ?? 0);
            $fees = $this->normalizeMoney($row['fees'] ?? 0);
            $netIncome = round($amountIn - $fees, 2);
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
                'amount' => $netIncome,
                'income_date' => $row['date'] ?? null,
                'category_id' => 'carrothost',
                'category_name' => 'CarrotHost',
                'notes' => $gateway ? "Gateway: {$gateway}" : null,
                'attachment_path' => null,
                'invoice_number' => $invoiceId ?: null,
                'customer_id' => null,
                'customer_name' => $clientName,
                'project_id' => null,
                'project_name' => null,
            ];
        });
    }

    private function parseFilterDate(mixed $value, bool $endOfDay): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }

    private function fetchAllWhmcs(
        string $action,
        array $params,
        string $rootKey,
        ?string $itemKey,
        array &$errors
    ): array {
        $items = [];
        $offset = 0;

        for ($page = 0; $page < self::WHMCS_MAX_PAGES; $page++) {
            $result = $this->whmcsClient->call($action, array_merge($params, [
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
            } catch (\Throwable) {
                return true;
            }

            return $date >= $start && $date <= $end;
        }));
    }

    private function normalizeMoney(mixed $value): float
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
}
