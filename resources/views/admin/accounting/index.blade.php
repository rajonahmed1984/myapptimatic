@extends('layouts.admin')

@section('title', $pageTitle)
@section('page-title', $pageTitle)

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex-1">
            <form id="accountingSearchForm" method="GET" action="{{ url()->current() }}" class="flex items-center gap-3" data-ajax-form="true">
                <div class="relative w-full max-w-sm">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search ?? request('search') }}"
                        placeholder="Search entries..."
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                    />
                </div>
            </form>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('admin.accounting.create', ['type' => 'payment', 'scope' => $scope, 'search' => $search]) }}"
                data-ajax-modal="true"
                data-url="{{ route('admin.accounting.create', ['type' => 'payment', 'scope' => $scope, 'search' => $search]) }}"
                data-modal-title="New Payment"
                class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white"
            >New Payment</a>
            <a
                href="{{ route('admin.accounting.create', ['type' => 'refund', 'scope' => $scope, 'search' => $search]) }}"
                data-ajax-modal="true"
                data-url="{{ route('admin.accounting.create', ['type' => 'refund', 'scope' => $scope, 'search' => $search]) }}"
                data-modal-title="New Refund"
                class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
            >New Refund</a>
            <a
                href="{{ route('admin.accounting.create', ['type' => 'credit', 'scope' => $scope, 'search' => $search]) }}"
                data-ajax-modal="true"
                data-url="{{ route('admin.accounting.create', ['type' => 'credit', 'scope' => $scope, 'search' => $search]) }}"
                data-modal-title="New Credit"
                class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
            >New Credit</a>
            <a
                href="{{ route('admin.accounting.create', ['type' => 'expense', 'scope' => $scope, 'search' => $search]) }}"
                data-ajax-modal="true"
                data-url="{{ route('admin.accounting.create', ['type' => 'expense', 'scope' => $scope, 'search' => $search]) }}"
                data-modal-title="New Expense"
                class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
            >New Expense</a>
        </div>
    </div>
    @include('admin.accounting.partials.table', ['entries' => $entries, 'scope' => $scope, 'search' => $search])
@endsection
