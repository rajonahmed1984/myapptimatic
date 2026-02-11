<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingEntry;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Services\ExpenseEntryService;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseDashboardController extends Controller
{
    public function index(Request $request, ExpenseEntryService $entryService): View
    {
        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))->startOfDay()
            : now()->startOfMonth();
        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))->endOfDay()
            : now()->endOfDay();

        $sourceFilters = $request->input('sources', []);
        if (! is_array($sourceFilters) || empty($sourceFilters)) {
            $sourceFilters = ['manual', 'salary', 'contract_payout', 'sales_payout'];
        }

        [$personType, $personId] = $entryService->parsePersonFilter($request->query('person'));

        $filters = [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'category_id' => $request->query('category_id'),
            'type' => $request->query('type'),
            'recurring_expense_id' => $request->query('recurring_expense_id'),
            'sources' => $sourceFilters,
            'person_type' => $personType,
            'person_id' => $personId,
            'person' => $request->query('person'),
        ];

        $entries = $entryService->entries($filters);

        $expenseTotal = (float) $entries->sum('amount');
        $totalAmount = $expenseTotal;

        $incomeReceived = (float) AccountingEntry::query()
            ->where('type', 'payment')
            ->whereDate('entry_date', '>=', $startDate->toDateString())
            ->whereDate('entry_date', '<=', $endDate->toDateString())
            ->sum('amount');

        $payoutExpenseTotal = (float) $entries
            ->whereIn('expense_type', ['salary', 'contract_payout', 'sales_payout'])
            ->sum('amount');

        $netIncome = $incomeReceived - $expenseTotal;
        $netCashflow = $incomeReceived - $payoutExpenseTotal;

        $categoryTotals = $entries
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

        $employeeTotals = $entries
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

        $now = now();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $yearStart = $now->copy()->startOfYear()->toDateString();

        $monthlyTotal = (float) $entryService->entries([
            'start_date' => $monthStart,
            'end_date' => $now->toDateString(),
            'sources' => ['manual', 'salary', 'contract_payout', 'sales_payout'],
        ])->sum('amount');

        $yearlyTotal = (float) $entryService->entries([
            'start_date' => $yearStart,
            'end_date' => $now->toDateString(),
            'sources' => ['manual', 'salary', 'contract_payout', 'sales_payout'],
        ])->sum('amount');

        $topCategories = $entryService->entries([
            'start_date' => $monthStart,
            'end_date' => $now->toDateString(),
            'sources' => ['manual', 'salary', 'contract_payout', 'sales_payout'],
        ])->groupBy('category_id')
            ->map(function ($items) {
                $first = $items->first();
                return [
                    'category_id' => $first['category_id'],
                    'name' => $first['category_name'],
                    'total' => (float) collect($items)->sum('amount'),
                ];
            })
            ->values()
            ->sortByDesc('total')
            ->take(5);

        [$labels, $expenseSeries, $incomeSeries] = $this->buildTrends($entries, $startDate, $endDate);

        $categories = ExpenseCategory::query()->orderBy('name')->get();
        $recurringTemplates = RecurringExpense::query()->orderBy('title')->get();
        $peopleOptions = $entryService->peopleOptions();
        $currencyCode = strtoupper((string) \App\Models\Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($currencyCode)) {
            $currencyCode = Currency::DEFAULT;
        }
        $currencySymbol = Currency::symbol($currencyCode);

        return view('admin.expenses.dashboard', [
            'filters' => $filters,
            'expenseTotal' => $expenseTotal,
            'incomeReceived' => $incomeReceived,
            'payoutExpenseTotal' => $payoutExpenseTotal,
            'netIncome' => $netIncome,
            'netCashflow' => $netCashflow,
            'categoryTotals' => $categoryTotals,
            'employeeTotals' => $employeeTotals,
            'totalAmount' => $totalAmount,
            'monthlyTotal' => $monthlyTotal,
            'yearlyTotal' => $yearlyTotal,
            'topCategories' => $topCategories,
            'trendLabels' => $labels,
            'trendExpenses' => $expenseSeries,
            'trendIncome' => $incomeSeries,
            'categories' => $categories,
            'recurringTemplates' => $recurringTemplates,
            'peopleOptions' => $peopleOptions,
            'currencyCode' => $currencyCode,
            'currencySymbol' => $currencySymbol,
        ]);
    }

    private function buildTrends($entries, Carbon $startDate, Carbon $endDate): array
    {
        $days = $startDate->diffInDays($endDate);
        $format = $days > 62 ? 'Y-m' : 'Y-m-d';

        $expenseGroups = $entries->groupBy(function ($entry) use ($format) {
            $date = $entry['expense_date'] ? Carbon::parse($entry['expense_date']) : now();
            return $date->format($format);
        });

        $incomeGroups = AccountingEntry::query()
            ->where('type', 'payment')
            ->whereDate('entry_date', '>=', $startDate->toDateString())
            ->whereDate('entry_date', '<=', $endDate->toDateString())
            ->get(['entry_date', 'amount'])
            ->groupBy(fn ($entry) => Carbon::parse($entry->entry_date)->format($format));

        $labels = [];
        $expenseSeries = [];
        $incomeSeries = [];

        $cursor = $startDate->copy();
        while ($cursor->lessThanOrEqualTo($endDate)) {
            $label = $cursor->format($format);
            $labels[] = $label;
            $expenseSeries[] = (float) collect($expenseGroups->get($label, []))->sum('amount');
            $incomeSeries[] = (float) collect($incomeGroups->get($label, []))->sum('amount');

            if ($days > 62) {
                $cursor->addMonth();
            } else {
                $cursor->addDay();
            }
        }

        return [$labels, $expenseSeries, $incomeSeries];
    }
}
