@extends('layouts.admin')

@section('title', 'Project Maintenance')
@section('page-title', 'Project Maintenance')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex-1">
            <form id="maintenanceSearchForm" method="GET" action="{{ route('admin.project-maintenances.index') }}" class="flex items-center gap-3" data-live-filter="true">
                <div class="relative w-full max-w-sm">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search ?? request('search') }}"
                        placeholder="Search maintenance..."
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                    />
                </div>
            </form>
        </div>
        <a href="{{ route('admin.project-maintenances.create') }}" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Add maintenance</a>
    </div>

    @include('admin.project-maintenances.partials.table', ['maintenances' => $maintenances])
@endsection
