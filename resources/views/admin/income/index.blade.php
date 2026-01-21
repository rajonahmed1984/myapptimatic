@extends('layouts.admin')

@section('title', 'Income')
@section('page-title', 'Income')

@section('content')
    @php
        $formatCurrency = function ($amount) use ($currencySymbol, $currencyCode) {
            $formatted = number_format((float) ($amount ?? 0), 2);
            return "{$currencySymbol}{$formatted}{$currencyCode}";
        };
        $formatDate = function ($date) use ($globalDateFormat) {
            if (! $date) {
                return '--';
            }
            if ($date instanceof \Illuminate\Support\Carbon) {
                return $date->format($globalDateFormat);
            }
            return \Illuminate\Support\Carbon::parse($date)->format($globalDateFormat);
        };
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Finance</div>
            <div class="text-2xl font-semibold text-slate-900">Income</div>
            <div class="mt-1 text-sm text-slate-500">Track manual income alongside system receipts.</div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('admin.income.categories.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Categories</a>
            <a href="{{ route('admin.income.create') }}" class="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Add Income</a>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card p-6">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Filtered Total</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatCurrency($totalAmount) }}</div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div class="card p-6">
            <div class="section-label">Filters</div>
            <form method="GET" action="{{ route('admin.income.index') }}" class="mt-4 grid gap-3 text-sm md:grid-cols-4">
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
                <div class="md:col-span-4">
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
                            <input type="checkbox" name="sources[]" value="system" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('system', $sourceSelections, true))>
                            System
                        </label>
                    </div>
                </div>
                <div class="md:col-span-4 flex flex-wrap items-center gap-3">
                    <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Apply</button>
                    <a href="{{ route('admin.income.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Reset</a>
                </div>
            </form>
        </div>

        <div class="card p-6">
            <div class="section-label">Category totals</div>
            <div class="mt-4 space-y-3 text-sm text-slate-600">
                @forelse($categoryTotals as $summary)
                    <div class="flex items-center justify-between">
                        <div>{{ $summary['name'] }}</div>
                        <div class="font-semibold text-slate-900">{{ $formatCurrency($summary['total']) }}</div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">No income entries yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-6 card p-6">
        <div class="section-label">Income list</div>
        <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white/80">
            <table class="min-w-full text-left text-sm text-slate-700">
                <thead>
                    <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Title</th>
                        <th class="px-3 py-2">Category</th>
                        <th class="px-3 py-2">Source</th>
                        <th class="px-3 py-2">Customer</th>
                        <th class="px-3 py-2">Project</th>
                        <th class="px-3 py-2">Amount</th>
                        <th class="px-3 py-2">Attachment</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($incomes as $income)
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2">{{ $formatDate($income['income_date'] ?? null) }}</td>
                            <td class="px-3 py-2">
                                <div class="font-semibold text-slate-900">{{ $income['title'] }}</div>
                                @if(!empty($income['notes']))
                                    <div class="text-xs text-slate-500">{{ $income['notes'] }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $income['category_name'] ?? '--' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-slate-600">
                                    {{ $income['source_label'] ?? ucfirst($income['source_type'] ?? 'manual') }}
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ $income['customer_name'] ?? '--' }}</td>
                            <td class="px-3 py-2">{{ $income['project_name'] ?? '--' }}</td>
                            <td class="px-3 py-2 font-semibold text-slate-900">{{ $formatCurrency($income['amount'] ?? 0) }}</td>
                            <td class="px-3 py-2">
                                @if(!empty($income['attachment_path']) && ($income['source_type'] ?? 'manual') === 'manual')
                                    <a href="{{ route('admin.income.attachments.show', $income['source_id']) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">View</a>
                                @else
                                    <span class="text-xs text-slate-400">--</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-4 text-center text-slate-500">No income found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $incomes->links() }}</div>
    </div>
@endsection
