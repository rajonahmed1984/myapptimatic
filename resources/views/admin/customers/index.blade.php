@extends('layouts.admin')

@section('title', 'Customers')
@section('page-title', 'Customers')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex-1">
            <form id="customersSearchForm" method="GET" action="{{ route('admin.customers.index') }}" class="flex items-center gap-3" data-live-filter="true">
                <div class="relative">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search ?? request('search') }}"
                        placeholder="Search customers..."
                        class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                    />
                </div>
            </form>
        </div>
        <a href="{{ route('admin.customers.create') }}" class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">New Customer</a>
    </div>

    @include('admin.customers.partials.table', ['customers' => $customers, 'loginStatuses' => $loginStatuses])
@endsection
