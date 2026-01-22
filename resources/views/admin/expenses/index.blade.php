@extends('layouts.admin')

@section('title', 'Expenses')
@section('page-title', 'Expenses')

@section('content')
    @php
        $formatCurrency = function ($amount) use ($currencyCode) {
            $formatted = number_format((float) ($amount ?? 0), 2);
            return "{$currencyCode} {$formatted}";
        };
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Finance</div>
            <div class="text-2xl font-semibold text-slate-900">Expenses</div>
            <div class="mt-1 text-sm text-slate-500">Track manual expenses alongside salary and payout costs.</div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('admin.expenses.recurring.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Recurring</a>
            <a href="{{ route('admin.expenses.categories.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Categories</a>
            <a href="{{ route('admin.expenses.create') }}" class="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Add Expense</a>
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
                    <input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">End date</label>
                    <input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Category</label>
                    <select name="category_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Type</label>
                    <select name="type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        <option value="one_time" @selected($filters['type'] === 'one_time')>One-time</option>
                        <option value="recurring" @selected($filters['type'] === 'recurring')>Recurring</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Template</label>
                    <select name="recurring_expense_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        @foreach($recurringTemplates as $template)
                            <option value="{{ $template->id }}" @selected((string) $filters['recurring_expense_id'] === (string) $template->id)>{{ $template->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Employee</label>
                    <select name="person" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
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
        <div class="section-label">Expense list</div>
        <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white/80">
            <table class="min-w-full text-left text-sm text-slate-700">
                <thead>
                    <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Title</th>
                        <th class="px-3 py-2">Category</th>
                        <th class="px-3 py-2">Source</th>
                        <th class="px-3 py-2">Person</th>
                        <th class="px-3 py-2">Amount</th>
                        <th class="px-3 py-2">Invoice</th>
                        <th class="px-3 py-2">Attachment</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $expense)
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2">{{ $expense['expense_date']?->format($globalDateFormat) ?? '--' }}</td>
                            <td class="px-3 py-2">
                                <div class="font-semibold text-slate-900">{{ $expense['title'] }}</div>
                                @if($expense['notes'])
                                    <div class="text-xs text-slate-500">{{ $expense['notes'] }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $expense['category_name'] ?? '--' }}</td>
                            <td class="px-3 py-2">
                                @php
                                    $typeClasses = [
                                        'one_time' => 'border-slate-200 text-slate-600 bg-slate-50',
                                        'recurring' => 'border-amber-200 text-amber-700 bg-amber-50',
                                        'salary' => 'border-blue-200 text-blue-700 bg-blue-50',
                                        'contract_payout' => 'border-emerald-200 text-emerald-700 bg-emerald-50',
                                        'sales_payout' => 'border-indigo-200 text-indigo-700 bg-indigo-50',
                                    ];
                                    $typeKey = $expense['expense_type'] ?? 'one_time';
                                @endphp
                                <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $typeClasses[$typeKey] ?? 'border-slate-200 text-slate-600 bg-slate-50' }}">
                                    {{ $expense['source_label'] ?? ucfirst(str_replace('_', ' ', $typeKey)) }}
                                </span>
                                @if(!empty($expense['source_detail']))
                                    <div class="text-[11px] text-slate-500">{{ $expense['source_detail'] }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <div class="text-sm text-slate-700">{{ $expense['person_name'] ?? '--' }}</div>
                            </td>
                            <td class="px-3 py-2 font-semibold text-slate-900">{{ $formatCurrency($expense['amount']) }}</td>
                            <td class="px-3 py-2">
                                @if($expense['invoice_no'])
                                    <div class="text-xs font-semibold text-slate-700">{{ $expense['invoice_no'] }}</div>
                                    <div class="text-[11px] text-slate-500">{{ ucfirst($expense['invoice_status'] ?? 'issued') }}</div>
                                @else
                                    <form method="POST" action="{{ route('admin.expenses.invoices.store') }}">
                                        @csrf
                                        <input type="hidden" name="source_type" value="{{ $expense['source_type'] }}">
                                        <input type="hidden" name="source_id" value="{{ $expense['source_id'] }}">
                                        <button type="submit" class="text-xs font-semibold text-teal-600 hover:text-teal-500">Generate</button>
                                    </form>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                @if($expense['attachment_path'] && $expense['source_type'] === 'expense')
                                    <a href="{{ route('admin.expenses.attachments.show', $expense['source_id']) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">View</a>
                                @else
                                    <span class="text-xs text-slate-400">--</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-4 text-center text-slate-500">No expenses found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $expenses->links() }}</div>
    </div>

    <div class="mt-6 card p-6">
        <div class="section-label">Category totals (filtered)</div>
        <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white/80">
            <table class="min-w-full text-left text-sm text-slate-700">
                <thead>
                    <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                        <th class="px-3 py-2">Category</th>
                        <th class="px-3 py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categoryTotals as $summary)
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2">{{ $summary['name'] }}</td>
                            <td class="px-3 py-2 text-right font-semibold text-slate-900">{{ $formatCurrency($summary['total']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-3 py-4 text-center text-slate-500">No totals available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
