<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingEntry;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\ExpenseEntryService;
use App\Services\IncomeEntryService;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceReportController extends Controller
{
    public function index(Request $request, IncomeEntryService $incomeService, ExpenseEntryService $expenseService): View
    {
        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))->startOfDay()
            : now()->startOfMonth();
        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))->endOfDay()
            : now()->endOfDay();

        $incomeSources = $request->input('income_sources', []);
        if (! is_array($incomeSources) || empty($incomeSources)) {
            $incomeSources = ['manual', 'system'];
        }

        $expenseSources = $request->input('expense_sources', []);
        if (! is_array($expenseSources) || empty($expenseSources)) {
            $expenseSources = ['manual', 'salary', 'contract_payout', 'sales_payout'];
        }

        $incomeBasis = $request->query('income_basis', 'received');
        if (! in_array($incomeBasis, ['received', 'invoiced'], true)) {
            $incomeBasis = 'received';
        }

        $incomeCategoryId = $request->query('income_category_id');
        $expenseCategoryId = $request->query('expense_category_id');

        $manualIncomeEntries = collect();
        if (in_array('manual', $incomeSources, true)) {
            $manualIncomeEntries = $incomeService->entries([
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'category_id' => $incomeCategoryId,
                'sources' => ['manual'],
            ]);
        }

        $systemIncomeEntries = collect();
        if (in_array('system', $incomeSources, true)) {
            if ($incomeBasis === 'invoiced') {
                $systemIncomeEntries = Invoice::query()
                    ->whereDate('issue_date', '>=', $startDate->toDateString())
                    ->whereDate('issue_date', '<=', $endDate->toDateString())
                    ->get()
                    ->map(fn ($invoice) => [
                        'key' => 'invoice:'.$invoice->id,
                        'source_type' => 'system',
                        'source_label' => 'Invoice',
                        'amount' => (float) $invoice->total,
                        'income_date' => $invoice->issue_date,
                        'category_id' => null,
                        'category_name' => 'System',
                    ]);
            } else {
                $systemIncomeEntries = AccountingEntry::query()
                    ->where('type', 'payment')
                    ->whereDate('entry_date', '>=', $startDate->toDateString())
                    ->whereDate('entry_date', '<=', $endDate->toDateString())
                    ->get()
                    ->map(fn ($entry) => [
                        'key' => 'payment:'.$entry->id,
                        'source_type' => 'system',
                        'source_label' => 'Payment',
                        'amount' => (float) $entry->amount,
                        'income_date' => $entry->entry_date,
                        'category_id' => null,
                        'category_name' => 'System',
                    ]);
            }
        }

        $incomeEntries = $manualIncomeEntries->merge($systemIncomeEntries);
        $totalIncome = (float) $incomeEntries->sum('amount');

        $expenseEntries = $expenseService->entries([
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'category_id' => $expenseCategoryId,
            'sources' => $expenseSources,
        ]);
        $totalExpense = (float) $expenseEntries->sum('amount');

        $netProfit = $totalIncome - $totalExpense;

        $receivedIncome = (float) AccountingEntry::query()
            ->where('type', 'payment')
            ->whereDate('entry_date', '>=', $startDate->toDateString())
            ->whereDate('entry_date', '<=', $endDate->toDateString())
            ->sum('amount');

        $payoutExpense = (float) $expenseEntries
            ->whereIn('expense_type', ['salary', 'contract_payout', 'sales_payout'])
            ->sum('amount');

        $netCashflow = $receivedIncome - $payoutExpense;

        $incomeCategoryTotals = $incomeEntries
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

        $expenseCategoryTotals = $expenseEntries
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

        $employeeTotals = $expenseEntries
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

        [$trendLabels, $trendIncome, $trendExpense] = $this->buildTrends($incomeEntries, $expenseEntries, $startDate, $endDate);

        $taxRows = Invoice::query()
            ->whereNotNull('tax_amount')
            ->whereDate('issue_date', '>=', $startDate->toDateString())
            ->whereDate('issue_date', '<=', $endDate->toDateString())
            ->get(['id', 'subtotal', 'tax_amount', 'total', 'tax_mode']);

        $taxableBase = (float) $taxRows->sum('subtotal');
        $taxAmount = (float) $taxRows->sum('tax_amount');
        $taxGross = (float) $taxRows->sum('total');
        $taxExclusive = (float) $taxRows->where('tax_mode', 'exclusive')->sum('tax_amount');
        $taxInclusive = (float) $taxRows->where('tax_mode', 'inclusive')->sum('tax_amount');

        $monthTotals = $this->monthTotals($incomeEntries, $expenseEntries, $startDate, $endDate);

        $incomeCategories = IncomeCategory::query()->orderBy('name')->get();
        $expenseCategories = ExpenseCategory::query()->orderBy('name')->get();

        $currencyCode = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($currencyCode)) {
            $currencyCode = Currency::DEFAULT;
        }
        $currencySymbol = Currency::symbol($currencyCode);

        return view('admin.finance.reports.index', [
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'income_sources' => $incomeSources,
                'expense_sources' => $expenseSources,
                'income_category_id' => $incomeCategoryId,
                'expense_category_id' => $expenseCategoryId,
                'income_basis' => $incomeBasis,
            ],
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'netProfit' => $netProfit,
            'receivedIncome' => $receivedIncome,
            'payoutExpense' => $payoutExpense,
            'netCashflow' => $netCashflow,
            'incomeCategoryTotals' => $incomeCategoryTotals,
            'expenseCategoryTotals' => $expenseCategoryTotals,
            'employeeTotals' => $employeeTotals,
            'trendLabels' => $trendLabels,
            'trendIncome' => $trendIncome,
            'trendExpense' => $trendExpense,
            'taxableBase' => $taxableBase,
            'taxAmount' => $taxAmount,
            'taxGross' => $taxGross,
            'taxExclusive' => $taxExclusive,
            'taxInclusive' => $taxInclusive,
            'monthTotals' => $monthTotals,
            'incomeCategories' => $incomeCategories,
            'expenseCategories' => $expenseCategories,
            'currencyCode' => $currencyCode,
            'currencySymbol' => $currencySymbol,
        ]);
    }

    private function buildTrends($incomeEntries, $expenseEntries, Carbon $startDate, Carbon $endDate): array
    {
        $days = $startDate->diffInDays($endDate);
        $format = $days > 62 ? 'Y-m' : 'Y-m-d';

        $incomeGroups = $incomeEntries->groupBy(function ($entry) use ($format) {
            $date = $entry['income_date'] ? Carbon::parse($entry['income_date']) : now();
            return $date->format($format);
        });

        $expenseGroups = $expenseEntries->groupBy(function ($entry) use ($format) {
            $date = $entry['expense_date'] ? Carbon::parse($entry['expense_date']) : now();
            return $date->format($format);
        });

        $labels = [];
        $incomeSeries = [];
        $expenseSeries = [];

        $cursor = $startDate->copy();
        while ($cursor->lessThanOrEqualTo($endDate)) {
            $label = $cursor->format($format);
            $labels[] = $label;
            $incomeSeries[] = (float) collect($incomeGroups->get($label, []))->sum('amount');
            $expenseSeries[] = (float) collect($expenseGroups->get($label, []))->sum('amount');

            if ($days > 62) {
                $cursor->addMonth();
            } else {
                $cursor->addDay();
            }
        }

        return [$labels, $incomeSeries, $expenseSeries];
    }

    private function monthTotals($incomeEntries, $expenseEntries, Carbon $startDate, Carbon $endDate): array
    {
        $format = 'Y-m';
        $incomeGroups = $incomeEntries->groupBy(function ($entry) use ($format) {
            $date = $entry['income_date'] ? Carbon::parse($entry['income_date']) : now();
            return $date->format($format);
        });
        $expenseGroups = $expenseEntries->groupBy(function ($entry) use ($format) {
            $date = $entry['expense_date'] ? Carbon::parse($entry['expense_date']) : now();
            return $date->format($format);
        });

        $rows = [];
        $cursor = $startDate->copy()->startOfMonth();
        $endCursor = $endDate->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($endCursor)) {
            $label = $cursor->format($format);
            $rows[] = [
                'label' => $label,
                'income' => (float) collect($incomeGroups->get($label, []))->sum('amount'),
                'expense' => (float) collect($expenseGroups->get($label, []))->sum('amount'),
            ];
            $cursor->addMonth();
        }

        return $rows;
    }
}
