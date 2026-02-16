@extends('layouts.admin')

@section('title', 'Plans')
@section('page-title', 'Plans')

@section('content')
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-slate-900">Plans</h1>
        <a
            href="{{ route('admin.plans.create') }}"
            data-ajax-modal="true"
            data-modal-title="New Plan"
            data-url="{{ route('admin.plans.create') }}"
            class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white"
        >
            New Plan
        </a>
    </div>

    @include('admin.plans.partials.table', ['plans' => $plans, 'defaultCurrency' => $defaultCurrency])
@endsection
