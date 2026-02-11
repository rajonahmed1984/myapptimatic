@extends('layouts.admin')

@section('title', $title ?? 'Invoices')
@section('page-title', $title ?? 'Invoices')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex-1">
            <form id="invoicesSearchForm" method="GET" action="{{ url()->current() }}" class="flex items-center gap-3">
                <div class="relative w-full max-w-sm">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search ?? request('search') }}"
                        placeholder="Search invoices..."
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                        hx-get="{{ url()->current() }}"
                        hx-trigger="keyup changed delay:300ms"
                        hx-target="#invoicesTable"
                        hx-swap="outerHTML"
                        hx-push-url="true"
                        hx-include="#invoicesSearchForm"
                    />
                </div>
            </form>
        </div>
        <a href="{{ route('admin.invoices.create') }}" class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">Create Invoice</a>
    </div>

    @include('admin.invoices.partials.table', ['invoices' => $invoices])
@endsection
