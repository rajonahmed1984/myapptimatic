<?php

namespace App\Services;

use App\Models\CommissionPayout;
use App\Models\Employee;
use App\Models\EmployeePayout;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseInvoice;
use App\Models\PayrollItem;
use App\Models\SalesRepresentative;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;

class ExpenseEntryService
{
    private array $systemCategoryIds = [];

    public function entries(array $filters = []): Collection
    {
        $sources = $this->normalizeSources($filters);
        $categoryIdFilter = Arr::get($filters, 'category_id');
        $personType = Arr::get($filters, 'person_type');
        $personId = $this->toNullableInt(Arr::get($filters, 'person_id'));

        $startDate = $this->parseFilterDate(Arr::get($filters, 'start_date'));
        $endDate = $this->parseFilterDate(Arr::get($filters, 'end_date'), true);

        $entries = collect();

        if (in_array('manual', $sources, true) && ! $personType) {
            $query = Expense::query()->with(['category', 'invoice']);

            if ($startDate) {
                $query->whereDate('expense_date', '>=', $startDate->toDateString());
            }
            if ($endDate) {
                $query->whereDate('expense_date', '<=', $endDate->toDateString());
            }
            if ($categoryIdFilter) {
                $query->where('category_id', $categoryIdFilter);
            }
            if (! empty(Arr::get($filters, 'type'))) {
                $query->where('type', Arr::get($filters, 'type'));
            }
            if (! empty(Arr::get($filters, 'recurring_expense_id'))) {
                $query->where('recurring_expense_id', Arr::get($filters, 'recurring_expense_id'));
            }

            $manualExpenses = $query->get();

            foreach ($manualExpenses as $expense) {
                $manualType = $expense->type ?? 'one_time';
                $entries->push([
                    'key' => 'expense:'.$expense->id,
                    'source_type' => 'expense',
                    'source_id' => $expense->id,
                    'expense_type' => $manualType,
                    'source_label' => 'Manual',
                    'source_detail' => $manualType === 'recurring' ? 'Recurring' : 'One-time',
                    'title' => $expense->title,
                    'amount' => (float) $expense->amount,
                    'expense_date' => $expense->expense_date,
                    'category_id' => $expense->category_id,
                    'category_name' => $expense->category?->name ?? '--',
                    'person_type' => null,
                    'person_id' => null,
                    'person_name' => null,
                    'project_id' => null,
                    'notes' => $expense->notes,
                    'attachment_path' => $expense->attachment_path,
                    'invoice_no' => $expense->invoice?->invoice_no,
                    'invoice_status' => $expense->invoice?->status,
                ]);
            }
        }

        $systemCategories = $this->resolveSystemCategories();

        if (in_array('salary', $sources, true)) {
            $categoryId = $systemCategories['salary']['id'] ?? null;
            if (! $categoryIdFilter || (string) $categoryIdFilter === (string) $categoryId) {
                $salaryEntries = $this->salaryEntries($startDate, $endDate, $personType, $personId);
                $entries = $entries->merge($this->mapSystemEntries($salaryEntries, 'salary', $systemCategories['salary'] ?? []));
            }
        }

        if (in_array('contract_payout', $sources, true)) {
            $categoryId = $systemCategories['contract_payout']['id'] ?? null;
            if (! $categoryIdFilter || (string) $categoryIdFilter === (string) $categoryId) {
                $contractEntries = $this->contractPayoutEntries($startDate, $endDate, $personType, $personId);
                $entries = $entries->merge($this->mapSystemEntries($contractEntries, 'contract_payout', $systemCategories['contract_payout'] ?? []));
            }
        }

        if (in_array('sales_payout', $sources, true)) {
            $categoryId = $systemCategories['sales_payout']['id'] ?? null;
            if (! $categoryIdFilter || (string) $categoryIdFilter === (string) $categoryId) {
                $salesEntries = $this->salesPayoutEntries($startDate, $endDate, $personType, $personId);
                $entries = $entries->merge($this->mapSystemEntries($salesEntries, 'sales_payout', $systemCategories['sales_payout'] ?? []));
            }
        }

        return $entries;
    }

    private function parseFilterDate(mixed $value, bool $endOfDay = false): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $parsed = Carbon::parse($value);

            return $endOfDay ? $parsed->endOfDay() : $parsed->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeSources(array $filters): array
    {
        $defaults = ['manual', 'salary', 'contract_payout', 'sales_payout'];
        $sources = Arr::get($filters, 'sources');

        if (! is_array($sources) || $sources === []) {
            return $defaults;
        }

        $allowed = array_fill_keys($defaults, true);
        $normalized = collect($sources)
            ->filter(fn ($source) => is_string($source) && isset($allowed[$source]))
            ->values()
            ->all();

        return $normalized === [] ? $defaults : $normalized;
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function resolveSystemCategories(): array
    {
        if (! empty($this->systemCategoryIds)) {
            return $this->systemCategoryIds;
        }

        $defaults = [
            'salary' => 'Salaries',
            'contract_payout' => 'Contractual Payouts',
            'sales_payout' => 'Sales Rep Payouts',
        ];

        $categories = ExpenseCategory::query()
            ->whereIn('name', array_values($defaults))
            ->get()
            ->keyBy('name');

        foreach ($defaults as $key => $name) {
            $category = $categories->get($name);
            if (! $category) {
                $category = ExpenseCategory::create([
                    'name' => $name,
                    'description' => $name,
                    'status' => 'active',
                ]);
            }
            $this->systemCategoryIds[$key] = [
                'id' => $category->id,
                'name' => $category->name,
            ];
        }

        return $this->systemCategoryIds;
    }

    private function salaryEntries(?Carbon $startDate, ?Carbon $endDate, ?string $personType, ?int $personId): Collection
    {
        $query = PayrollItem::query()
            ->with(['employee', 'period'])
            ->where('status', 'paid')
            ->whereNotNull('paid_at');

        if ($startDate) {
            $query->whereDate('paid_at', '>=', $startDate->toDateString());
        }
        if ($endDate) {
            $query->whereDate('paid_at', '<=', $endDate->toDateString());
        }
        if ($personType === 'employee' && $personId) {
            $query->where('employee_id', $personId);
        } elseif ($personType) {
            return collect();
        }

        return $query->get();
    }

    private function contractPayoutEntries(?Carbon $startDate, ?Carbon $endDate, ?string $personType, ?int $personId): Collection
    {
        $query = EmployeePayout::query()
            ->with('employee')
            ->whereNotNull('paid_at');

        if ($startDate) {
            $query->whereDate('paid_at', '>=', $startDate->toDateString());
        }
        if ($endDate) {
            $query->whereDate('paid_at', '<=', $endDate->toDateString());
        }
        if ($personType === 'employee' && $personId) {
            $query->where('employee_id', $personId);
        } elseif ($personType) {
            return collect();
        }

        return $query->get();
    }

    private function salesPayoutEntries(?Carbon $startDate, ?Carbon $endDate, ?string $personType, ?int $personId): Collection
    {
        $query = CommissionPayout::query()
            ->with('salesRep')
            ->where('status', 'paid')
            ->whereNotNull('paid_at');

        if ($startDate) {
            $query->whereDate('paid_at', '>=', $startDate->toDateString());
        }
        if ($endDate) {
            $query->whereDate('paid_at', '<=', $endDate->toDateString());
        }
        if ($personType === 'sales_rep' && $personId) {
            $query->where('sales_representative_id', $personId);
        } elseif ($personType) {
            return collect();
        }

        return $query->get();
    }

    private function mapSystemEntries(Collection $items, string $type, array $category): Collection
    {
        if ($items->isEmpty()) {
            return collect();
        }

        $sourceType = match ($type) {
            'salary' => 'payroll_item',
            'contract_payout' => 'employee_payout',
            'sales_payout' => 'commission_payout',
            default => $type,
        };

        $invoiceMap = ExpenseInvoice::query()
            ->where('source_type', $sourceType)
            ->whereIn('source_id', $items->pluck('id')->all())
            ->get()
            ->keyBy(fn ($invoice) => $invoice->source_type.':'.$invoice->source_id);

        return $items->map(function ($item) use ($type, $category, $sourceType, $invoiceMap) {
            $amount = 0.0;
            $date = null;
            $title = '';
            $notes = null;
            $personType = null;
            $personId = null;
            $personName = null;

            if ($type === 'salary') {
                $amount = (float) ($item->net_pay ?? $item->gross_pay ?? 0);
                $date = $item->paid_at;
                $personType = 'employee';
                $personId = $item->employee_id;
                $personName = $item->employee?->name;
                $title = $personName ? "Salary payout - {$personName}" : 'Salary payout';
                if ($item->period) {
                    $notes = sprintf(
                        'Period: %s to %s',
                        $item->period->start_date?->format('Y-m-d'),
                        $item->period->end_date?->format('Y-m-d')
                    );
                }
            } elseif ($type === 'contract_payout') {
                $amount = (float) $item->amount;
                $date = $item->paid_at;
                $personType = 'employee';
                $personId = $item->employee_id;
                $personName = $item->employee?->name;
                $title = $personName ? "Contract payout - {$personName}" : 'Contract payout';
                $projectIds = (array) ($item->metadata['project_ids'] ?? []);
                if (! empty($projectIds)) {
                    $notes = 'Projects: '.implode(', ', $projectIds);
                }
            } elseif ($type === 'sales_payout') {
                $amount = (float) $item->total_amount;
                $date = $item->paid_at;
                $personType = 'sales_rep';
                $personId = $item->sales_representative_id;
                $personName = $item->salesRep?->name;
                $title = $personName ? "Sales rep payout - {$personName}" : 'Sales rep payout';
                if ($item->reference) {
                    $notes = 'Reference: '.$item->reference;
                }
            }

            $invoice = $invoiceMap->get($sourceType.':'.$item->id);

            return [
                'key' => $sourceType.':'.$item->id,
                'source_type' => $sourceType,
                'source_id' => $item->id,
                'expense_type' => $type,
                'source_label' => match ($type) {
                    'salary' => 'Salary',
                    'contract_payout' => 'Contract Payout',
                    'sales_payout' => 'Sales Rep Payout',
                    default => 'System',
                },
                'source_detail' => null,
                'title' => $title,
                'amount' => $amount,
                'expense_date' => $date,
                'category_id' => $category['id'] ?? null,
                'category_name' => $category['name'] ?? '--',
                'person_type' => $personType,
                'person_id' => $personId,
                'person_name' => $personName,
                'project_id' => null,
                'notes' => $notes,
                'attachment_path' => null,
                'invoice_no' => $invoice?->invoice_no,
                'invoice_status' => $invoice?->status,
            ];
        });
    }

    public function peopleOptions(): array
    {
        $employees = Employee::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $salesReps = SalesRepresentative::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $options = [];
        foreach ($employees as $employee) {
            $options[] = [
                'key' => 'employee:'.$employee->id,
                'label' => $employee->name,
            ];
        }
        foreach ($salesReps as $rep) {
            $options[] = [
                'key' => 'sales_rep:'.$rep->id,
                'label' => $rep->name,
            ];
        }

        return $options;
    }

    public function parsePersonFilter(?string $input): array
    {
        if (! $input || ! str_contains($input, ':')) {
            return [null, null];
        }

        [$type, $id] = explode(':', $input, 2);
        $id = (int) $id;

        if (! in_array($type, ['employee', 'sales_rep'], true) || $id <= 0) {
            return [null, null];
        }

        return [$type, $id];
    }
}
