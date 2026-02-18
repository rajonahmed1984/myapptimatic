@extends('layouts.admin')

@section('title', 'New Subscription')
@section('page-title', 'New Subscription')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Subscriptions</div>
            <h1 class="text-2xl font-semibold text-slate-900">Create Subscription</h1>
        </div>
        <a href="{{ route('admin.subscriptions.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back to subscriptions</a>
    </div>

    <div class="card p-6">
        @include('admin.subscriptions.partials.form', ['customers' => $customers, 'plans' => $plans, 'salesReps' => $salesReps, 'ajaxForm' => false])
    </div>
@endsection
