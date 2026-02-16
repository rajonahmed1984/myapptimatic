@extends('layouts.admin')

@section('title', 'Edit Plan')
@section('page-title', 'Edit Plan')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Plans</div>
            <h1 class="text-2xl font-semibold text-slate-900">Edit Plan</h1>
        </div>
        <a href="{{ route('admin.plans.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back to plans</a>
    </div>

    <div class="card p-6">
        @include('admin.plans.partials.form', ['plan' => $plan, 'products' => $products, 'defaultCurrency' => $defaultCurrency, 'ajaxForm' => false])
    </div>
@endsection
