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
        <div class="flex-1">
            <form id="expensesSearchForm" method="GET" action="{{ url()->current() }}" class="flex items-center gap-3">
                <div class="relative w-full max-w-sm">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search ?? request('search') }}"
                        placeholder="Search expenses..."
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                        hx-get="{{ url()->current() }}"
                        hx-trigger="keyup changed delay:300ms"
                        hx-target="#expensesTable"
                        hx-swap="outerHTML"
                        hx-push-url="true"
                        hx-include="#expensesSearchForm"
                    />
                </div>
            </form>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('admin.expenses.recurring.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Recurring</a>
            <a href="{{ route('admin.expenses.categories.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Categories</a>
            <a href="{{ route('admin.expenses.create') }}" class="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Add Expense</a>
        </div>
    </div>

    @include('admin.expenses.partials.table', ['expenses' => $expenses])

    <div class="mt-6 card p-6">
        <div class="section-label">Category totals (filtered)</div>
        <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-300 bg-white/80">
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
