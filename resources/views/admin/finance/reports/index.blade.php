@extends('layouts.admin')

@section('title', 'Finance Reports')
@section('page-title', 'Finance Reports')

@section('content')
    @php
        $formatCurrency = function ($amount) use ($currencySymbol, $currencyCode) {
            $formatted = number_format((float) ($amount ?? 0), 2);
            return "{$currencySymbol}{$formatted}{$currencyCode}";
        };
        $incomeSourceSelections = $filters['income_sources'] ?? [];
        $expenseSourceSelections = $filters['expense_sources'] ?? [];
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Finance</div>
            <div class="text-2xl font-semibold text-slate-900">Finance reports</div>
            <div class="mt-1 text-sm text-slate-500">Review income, expenses, payouts, and tax totals in one view.</div>
        </div>
        <a href="{{ route('admin.finance.tax.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Tax Settings</a>
    </div>

    <div class="card p-6">
        <div class="section-label">Filters</div>
        <form method="GET" action="{{ route('admin.finance.reports.index') }}" class="mt-4 grid gap-3 text-sm md:grid-cols-6">
            <div>
                <label class="text-xs text-slate-500">Start date</label>
                <input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">End date</label>
                <input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">Income basis</label>
                <select name="income_basis" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    <option value="received" @selected($filters['income_basis'] === 'received')>Received</option>
                    <option value="invoiced" @selected($filters['income_basis'] === 'invoiced')>Invoiced</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">Income category</label>
                <select name="income_category_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach($incomeCategories as $category)
                        <option value="{{ $category->id }}" @selected((string) $filters['income_category_id'] === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">Expense category</label>
                <select name="expense_category_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach($expenseCategories as $category)
                        <option value="{{ $category->id }}" @selected((string) $filters['expense_category_id'] === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-6">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Income sources</div>
                <div class="mt-2 flex flex-wrap gap-4 text-xs text-slate-600">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="income_sources[]" value="manual" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('manual', $incomeSourceSelections, true))>
                        Manual
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="income_sources[]" value="system" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('system', $incomeSourceSelections, true))>
                        System
                    </label>
                </div>
            </div>
            <div class="md:col-span-6">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Expense sources</div>
                <div class="mt-2 flex flex-wrap gap-4 text-xs text-slate-600">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="expense_sources[]" value="manual" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('manual', $expenseSourceSelections, true))>
                        Manual
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="expense_sources[]" value="salary" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('salary', $expenseSourceSelections, true))>
                        Salaries
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="expense_sources[]" value="contract_payout" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('contract_payout', $expenseSourceSelections, true))>
                        Contract Payouts
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="expense_sources[]" value="sales_payout" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('sales_payout', $expenseSourceSelections, true))>
                        Sales Rep Payouts
                    </label>
                </div>
            </div>
            <div class="md:col-span-6 flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Apply</button>
                <a href="{{ route('admin.finance.reports.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Reset</a>
            </div>
        </form>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Total income</div>
            <div class="mt-2 text-2xl font-semibold text-emerald-600">{{ $formatCurrency($totalIncome) }}</div>
        </div>
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Total expense</div>
            <div class="mt-2 text-2xl font-semibold text-rose-600">{{ $formatCurrency($totalExpense) }}</div>
        </div>
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Net profit</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatCurrency($netProfit) }}</div>
        </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Received income</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatCurrency($receivedIncome) }}</div>
        </div>
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Payout expense</div>
            <div class="mt-2 text-2xl font-semibold text-amber-600">{{ $formatCurrency($payoutExpense) }}</div>
        </div>
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Net cashflow</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatCurrency($netCashflow) }}</div>
        </div>
    </div>

    <div class="mt-6 card p-6">
        <div class="section-label">Tax summary</div>
        <div class="mt-4 grid gap-4 md:grid-cols-5">
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Taxable base</div>
                <div class="mt-2 text-lg font-semibold text-slate-900">{{ $formatCurrency($taxableBase) }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Tax amount</div>
                <div class="mt-2 text-lg font-semibold text-slate-900">{{ $formatCurrency($taxAmount) }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Gross total</div>
                <div class="mt-2 text-lg font-semibold text-slate-900">{{ $formatCurrency($taxGross) }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Exclusive tax</div>
                <div class="mt-2 text-lg font-semibold text-slate-900">{{ $formatCurrency($taxExclusive) }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Inclusive tax</div>
                <div class="mt-2 text-lg font-semibold text-slate-900">{{ $formatCurrency($taxInclusive) }}</div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card p-6">
            <div class="section-label">Income by category</div>
            <div class="mt-4 space-y-3 text-sm text-slate-600">
                @forelse($incomeCategoryTotals as $summary)
                    <div class="flex items-center justify-between">
                        <div>{{ $summary['name'] }}</div>
                        <div class="font-semibold text-slate-900">{{ $formatCurrency($summary['total']) }}</div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">No income entries.</div>
                @endforelse
            </div>
        </div>
        <div class="card p-6">
            <div class="section-label">Expense by category</div>
            <div class="mt-4 space-y-3 text-sm text-slate-600">
                @forelse($expenseCategoryTotals as $summary)
                    <div class="flex items-center justify-between">
                        <div>{{ $summary['name'] }}</div>
                        <div class="font-semibold text-slate-900">{{ $formatCurrency($summary['total']) }}</div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">No expense entries.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-6 card p-6">
        <div class="section-label">Employee payout breakdown</div>
        <div class="mt-4 space-y-3 text-sm text-slate-600">
            @forelse($employeeTotals as $summary)
                <div class="flex items-center justify-between">
                    <div>{{ $summary['label'] }}</div>
                    <div class="font-semibold text-slate-900">{{ $formatCurrency($summary['total']) }}</div>
                </div>
            @empty
                <div class="text-sm text-slate-500">No payout entries in this range.</div>
            @endforelse
        </div>
    </div>

    @php
        $trendIncomeValues = collect($trendIncome ?? [])->map(fn ($value) => (float) $value)->values()->all();
        $trendExpenseValues = collect($trendExpense ?? [])->map(fn ($value) => (float) $value)->values()->all();
        $trendWidth = 480;
        $trendHeight = 200;
        $trendPadding = 20;
        $trendMax = max(1, ...$trendIncomeValues, ...$trendExpenseValues);
        $buildTrendPoints = function (array $values) use ($trendWidth, $trendHeight, $trendPadding, $trendMax): string {
            $count = count($values);
            if ($count === 0) {
                return '';
            }

            $points = [];
            foreach ($values as $index => $value) {
                $x = $count > 1
                    ? $trendPadding + ($index / ($count - 1)) * ($trendWidth - $trendPadding * 2)
                    : $trendWidth / 2;
                $y = $trendHeight - $trendPadding - ((float) $value / $trendMax) * ($trendHeight - $trendPadding * 2);
                $points[] = number_format($x, 2, '.', '').','.number_format($y, 2, '.', '');
            }

            return implode(' ', $points);
        };
        $trendIncomePoints = $buildTrendPoints($trendIncomeValues);
        $trendExpensePoints = $buildTrendPoints($trendExpenseValues);
    @endphp

    <div class="mt-6 card p-6">
        <div class="section-label">Income vs expense trend</div>
        <div class="mt-4" id="finance-trend" data-income='@json($trendIncome)' data-expense='@json($trendExpense)'>
            <div class="flex items-center gap-4 text-xs text-slate-500">
                <div class="flex items-center gap-2">
                    <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                    Income
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-block h-2 w-2 rounded-full bg-rose-500"></span>
                    Expense
                </div>
            </div>
            <svg viewBox="0 0 480 200" class="mt-4 h-48 w-full">
                <polyline id="finance-trend-income" fill="none" stroke="#10b981" stroke-width="2" points="{{ $trendIncomePoints }}"></polyline>
                <polyline id="finance-trend-expense" fill="none" stroke="#f43f5e" stroke-width="2" points="{{ $trendExpensePoints }}"></polyline>
            </svg>
        </div>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white/80">
            <table class="min-w-full text-left text-sm text-slate-700">
                <thead>
                    <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                        <th class="px-3 py-2">Period</th>
                        <th class="px-3 py-2">Income</th>
                        <th class="px-3 py-2">Expense</th>
                        <th class="px-3 py-2">Net</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($trendLabels as $index => $label)
                        @php
                            $income = $trendIncome[$index] ?? 0;
                            $expense = $trendExpense[$index] ?? 0;
                        @endphp
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2">{{ $label }}</td>
                            <td class="px-3 py-2">{{ $formatCurrency($income) }}</td>
                            <td class="px-3 py-2">{{ $formatCurrency($expense) }}</td>
                            <td class="px-3 py-2">{{ $formatCurrency($income - $expense) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-4 text-center text-slate-500">No trend data.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 card p-6">
        <div class="section-label">Monthly summary</div>
        <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white/80">
            <table class="min-w-full text-left text-sm text-slate-700">
                <thead>
                    <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                        <th class="px-3 py-2">Month</th>
                        <th class="px-3 py-2">Income</th>
                        <th class="px-3 py-2">Expense</th>
                        <th class="px-3 py-2">Net</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($monthTotals as $row)
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2">{{ $row['label'] }}</td>
                            <td class="px-3 py-2">{{ $formatCurrency($row['income']) }}</td>
                            <td class="px-3 py-2">{{ $formatCurrency($row['expense']) }}</td>
                            <td class="px-3 py-2">{{ $formatCurrency($row['income'] - $row['expense']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-4 text-center text-slate-500">No monthly totals.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('finance-trend');
            if (!container) {
                return;
            }

            const parseSeries = (raw) => {
                if (!raw) {
                    return [];
                }
                try {
                    const parsed = JSON.parse(raw);
                    if (Array.isArray(parsed)) {
                        return parsed;
                    }
                    if (parsed && typeof parsed === 'object') {
                        return Object.values(parsed);
                    }
                } catch (e) {
                    return [];
                }
                return [];
            };

            const income = parseSeries(container.dataset.income);
            const expense = parseSeries(container.dataset.expense);
            const incomeLine = document.getElementById('finance-trend-income');
            const expenseLine = document.getElementById('finance-trend-expense');
            const width = 480;
            const height = 200;
            const padding = 20;
            const maxValue = Math.max(1, ...income, ...expense);

            const toPoints = (values) => {
                if (!values.length) {
                    return null;
                }
                return values.map((value, index) => {
                    const x = values.length > 1
                        ? padding + (index / (values.length - 1)) * (width - padding * 2)
                        : width / 2;
                    const y = height - padding - (Number(value || 0) / maxValue) * (height - padding * 2);
                    return `${x.toFixed(2)},${y.toFixed(2)}`;
                }).join(' ');
            };

            if (incomeLine) {
                const points = toPoints(income);
                if (points) {
                    incomeLine.setAttribute('points', points);
                }
            }
            if (expenseLine) {
                const points = toPoints(expense);
                if (points) {
                    expenseLine.setAttribute('points', points);
                }
            }
        });
    </script>
@endpush
