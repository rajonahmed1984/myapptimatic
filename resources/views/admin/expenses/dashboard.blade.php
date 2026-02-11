@extends('layouts.admin')

@section('title', 'Expense Dashboard')
@section('page-title', 'Expense Dashboard')

@section('content')
    @php
        $formatCurrency = function ($amount) use ($currencySymbol, $currencyCode) {
            $formatted = number_format((float) ($amount ?? 0), 2);
            return "{$currencySymbol}{$formatted}{$currencyCode}";
        };
    @endphp

    <div class="card p-6">
        <div class="section-label">Filters</div>
        <form method="GET" action="{{ route('admin.expenses.dashboard') }}" class="mt-4 grid gap-3 text-sm md:grid-cols-5">
            <div>
                <label class="text-xs text-slate-500">Start date</label>
                <input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">End date</label>
                <input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">Category</label>
                <select name="category_id" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">Employee</label>
                <select name="person" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach($peopleOptions as $option)
                        <option value="{{ $option['key'] }}" @selected((string) $filters['person'] === (string) $option['key'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-5">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Sources</div>
                <div class="mt-2 flex flex-wrap gap-4 text-xs text-slate-600">
                    @php
                        $sourceSelections = $filters['sources'] ?? [];
                    @endphp
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="sources[]" value="manual" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('manual', $sourceSelections, true))>
                        Manual
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="sources[]" value="salary" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('salary', $sourceSelections, true))>
                        Salaries
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="sources[]" value="contract_payout" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('contract_payout', $sourceSelections, true))>
                        Contract Payouts
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="sources[]" value="sales_payout" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('sales_payout', $sourceSelections, true))>
                        Sales Rep Payouts
                    </label>
                </div>
            </div>
            <div class="md:col-span-5 flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Apply</button>
                <a href="{{ route('admin.expenses.dashboard') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Reset</a>
            </div>
        </form>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-5">
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Income (Received)</div>
            <div class="mt-2 text-2xl font-semibold text-emerald-600">{{ $formatCurrency($incomeReceived) }}</div>
        </div>
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Total Expenses</div>
            <div class="mt-2 text-2xl font-semibold text-rose-600">{{ $formatCurrency($expenseTotal) }}</div>
        </div>
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Payout Expenses</div>
            <div class="mt-2 text-2xl font-semibold text-amber-600">{{ $formatCurrency($payoutExpenseTotal) }}</div>
        </div>
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Net (Income - Expense)</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatCurrency($netIncome) }}</div>
        </div>
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Cashflow (Received - Payout)</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatCurrency($netCashflow) }}</div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card p-6">
            <div class="section-label">Expense by category</div>
            <div class="mt-4 space-y-3 text-sm text-slate-600">
                @forelse($categoryTotals as $summary)
                    <div class="flex items-center justify-between">
                        <div>{{ $summary['name'] }}</div>
                        <div class="font-semibold text-slate-900">{{ $formatCurrency($summary['total']) }}</div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">No expenses found.</div>
                @endforelse
            </div>
        </div>
        <div class="card p-6">
            <div class="section-label">Expense by employee</div>
            <div class="mt-4 space-y-3 text-sm text-slate-600">
                @forelse($employeeTotals as $summary)
                    <div class="flex items-center justify-between">
                        <div>{{ $summary['label'] }}</div>
                        <div class="font-semibold text-slate-900">{{ $formatCurrency($summary['total']) }}</div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">No employee payouts in this range.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card p-6">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">This Month</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatCurrency($monthlyTotal) }}</div>
        </div>
        <div class="card p-6">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">This Year</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatCurrency($yearlyTotal) }}</div>
        </div>
        <div class="card p-6">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Filtered Total</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatCurrency($totalAmount) }}</div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div class="card p-6">
            <div class="section-label">Filters</div>
            <form method="GET" action="{{ route('admin.expenses.index') }}" class="mt-4 grid gap-3 text-sm md:grid-cols-6">
                <div>
                    <label class="text-xs text-slate-500">Start date</label>
                    <input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">End date</label>
                    <input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Category</label>
                    <select name="category_id" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Type</label>
                    <select name="type" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        <option value="one_time" @selected($filters['type'] === 'one_time')>One-time</option>
                        <option value="recurring" @selected($filters['type'] === 'recurring')>Recurring</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Template</label>
                    <select name="recurring_expense_id" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        @foreach($recurringTemplates as $template)
                            <option value="{{ $template->id }}" @selected((string) $filters['recurring_expense_id'] === (string) $template->id)>{{ $template->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Employee</label>
                    <select name="person" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        @foreach($peopleOptions as $option)
                            <option value="{{ $option['key'] }}" @selected((string) $filters['person'] === (string) $option['key'])>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-6">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Sources</div>
                    <div class="mt-2 flex flex-wrap gap-4 text-xs text-slate-600">
                        @php
                            $sourceSelections = $filters['sources'] ?? [];
                        @endphp
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="sources[]" value="manual" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('manual', $sourceSelections, true))>
                            Manual
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="sources[]" value="salary" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('salary', $sourceSelections, true))>
                            Salaries
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="sources[]" value="contract_payout" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('contract_payout', $sourceSelections, true))>
                            Contract Payouts
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="sources[]" value="sales_payout" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('sales_payout', $sourceSelections, true))>
                            Sales Rep Payouts
                        </label>
                    </div>
                </div>
                <div class="md:col-span-6 flex flex-wrap items-center gap-3">
                    <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Apply</button>
                    <a href="{{ route('admin.expenses.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Reset</a>
                </div>
            </form>
        </div>

        <div class="card p-6">
            <div class="flex items-center justify-between">
                <div class="section-label">Top Categories (Month)</div>
            </div>
            <div class="mt-4 space-y-3 text-sm text-slate-600">
                @forelse($topCategories as $summary)
                    <div class="flex items-center justify-between">
                        <div>{{ $summary['name'] }}</div>
                        <div class="font-semibold text-slate-900">{{ $formatCurrency($summary['total']) }}</div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">No expenses yet.</div>
                @endforelse
            </div>
        </div>
    </div>


    <div class="mt-6 card p-6">
        <div class="section-label">Trend overview</div>
        <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-300 bg-white/80">
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
                            $expense = $trendExpenses[$index] ?? 0;
                        @endphp
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2">{{ $label }}</td>
                            <td class="px-3 py-2">{{ $formatCurrency($income) }}</td>
                            <td class="px-3 py-2">{{ $formatCurrency($expense) }}</td>
                            <td class="px-3 py-2">{{ $formatCurrency($income - $expense) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-4 text-center text-slate-500">No data to display.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
