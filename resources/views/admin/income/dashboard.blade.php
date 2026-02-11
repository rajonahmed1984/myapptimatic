@extends('layouts.admin')

@section('title', 'Income dashboard')
@section('page-title', 'Income Dashboard')

@section('content')
    @php
        $formatCurrency = function ($amount) use ($currencySymbol, $currencyCode) {
            $formatted = number_format((float) ($amount ?? 0), 2);
            return "{$currencySymbol}{$formatted}{$currencyCode}";
        };
    @endphp

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card p-6">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Filtered Total</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatCurrency($totalAmount) }}</div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div class="card p-6">
            <div class="section-label">Filters</div>
            <form method="GET" action="{{ route('admin.income.dashboard') }}" class="mt-4 grid gap-3 text-sm md:grid-cols-4">
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
                    <a href="{{ route('admin.income.dashboard') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Reset</a>
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
@endsection
