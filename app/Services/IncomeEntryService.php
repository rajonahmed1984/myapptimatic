<?php

namespace App\Services;

use App\Models\AccountingEntry;
use App\Models\Income;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class IncomeEntryService
{
    public function entries(array $filters = []): Collection
    {
        $sources = $filters['sources'] ?? ['manual', 'system'];
        $categoryIdFilter = $filters['category_id'] ?? null;

        $startDate = $filters['start_date'] ? Carbon::parse($filters['start_date'])->startOfDay() : null;
        $endDate = $filters['end_date'] ? Carbon::parse($filters['end_date'])->endOfDay() : null;

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
                    'customer_id' => $entry->customer_id,
                    'customer_name' => $entry->customer?->name,
                    'project_id' => $project?->id,
                    'project_name' => $project?->name,
                ]);
            }
        }

        return $entries;
    }
}
