@extends('layouts.admin')

@section('title', 'Edit Subscription')
@section('page-title', 'Edit Subscription')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Subscriptions</div>
            <h1 class="text-2xl font-semibold text-slate-900">Edit Subscription</h1>
        </div>
        <a href="{{ route('admin.subscriptions.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back to subscriptions</a>
    </div>

    <div class="card p-6">
        @include('admin.subscriptions.partials.form', [
            'subscription' => $subscription,
            'customers' => $customers,
            'plans' => $plans,
            'salesReps' => $salesReps,
            'ajaxForm' => false,
        ])
    </div>
@endsection
