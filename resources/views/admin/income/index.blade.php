@extends('layouts.admin')

@section('title', 'Income list')
@section('page-title', 'Income list')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex-1">
            <form id="incomeSearchForm" method="GET" action="{{ route('admin.income.index') }}" class="flex items-center gap-3">
                <div class="relative w-full max-w-sm">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search ?? request('search') }}"
                        placeholder="Search income..."
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                        hx-get="{{ route('admin.income.index') }}"
                        hx-trigger="keyup changed delay:300ms"
                        hx-target="#incomeTable"
                        hx-swap="outerHTML"
                        hx-push-url="true"
                        hx-include="#incomeSearchForm"
                    />
                </div>
            </form>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('admin.income.categories.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Categories</a>
            <a href="{{ route('admin.income.create') }}" class="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Add Income</a>
        </div>
    </div>

    @include('admin.income.partials.table', ['incomes' => $incomes])
@endsection
