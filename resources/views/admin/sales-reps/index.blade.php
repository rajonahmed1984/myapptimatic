@extends('layouts.admin')

@section('title', 'Sales Representatives')
@section('page-title', 'Sales Representatives')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex-1">
            <form id="salesRepsSearchForm" method="GET" action="{{ route('admin.sales-reps.index') }}" class="flex items-center gap-3" data-live-filter="true">
                <div class="relative">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search ?? request('search') }}"
                        placeholder="Search sales reps..."
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                    />
                </div>
            </form>
        </div>
        <a href="{{ route('admin.sales-reps.create') }}" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Add sales rep</a>
    </div>

    @include('admin.sales-reps.partials.table', ['reps' => $reps, 'totals' => $totals, 'loginStatuses' => $loginStatuses])
@endsection
