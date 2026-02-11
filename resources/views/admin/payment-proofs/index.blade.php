@extends('layouts.admin')

@section('title', 'Manual Payments')
@section('page-title', 'Manual Payments')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex-1">
            <form id="paymentProofsSearchForm" method="GET" action="{{ route('admin.payment-proofs.index') }}" class="flex items-center gap-3">
                <input type="hidden" name="status" value="{{ $status }}">
                <div class="relative w-full max-w-sm">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search ?? request('search') }}"
                        placeholder="Search payment proofs..."
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                        hx-get="{{ route('admin.payment-proofs.index') }}"
                        hx-trigger="keyup changed delay:300ms"
                        hx-target="#paymentProofsTable"
                        hx-swap="outerHTML"
                        hx-push-url="true"
                        hx-include="#paymentProofsSearchForm"
                    />
                </div>
            </form>
        </div>
        <div class="flex items-center gap-2 text-xs">
            <a href="{{ route('admin.payment-proofs.index', ['status' => 'all']) }}" class="{{ $status === 'all' ? 'rounded-full bg-slate-900 px-3 py-1 text-white' : 'rounded-full border border-slate-300 px-3 py-1 text-slate-600 hover:border-teal-300 hover:text-teal-600' }}">All</a>
            <a href="{{ route('admin.payment-proofs.index', ['status' => 'pending']) }}" class="{{ $status === 'pending' ? 'rounded-full bg-slate-900 px-3 py-1 text-white' : 'rounded-full border border-slate-300 px-3 py-1 text-slate-600 hover:border-teal-300 hover:text-teal-600' }}">Pending</a>
            <a href="{{ route('admin.payment-proofs.index', ['status' => 'approved']) }}" class="{{ $status === 'approved' ? 'rounded-full bg-slate-900 px-3 py-1 text-white' : 'rounded-full border border-slate-300 px-3 py-1 text-slate-600 hover:border-teal-300 hover:text-teal-600' }}">Approved</a>
            <a href="{{ route('admin.payment-proofs.index', ['status' => 'rejected']) }}" class="{{ $status === 'rejected' ? 'rounded-full bg-slate-900 px-3 py-1 text-white' : 'rounded-full border border-slate-300 px-3 py-1 text-slate-600 hover:border-teal-300 hover:text-teal-600' }}">Rejected</a>
        </div>
    </div>

    @include('admin.payment-proofs.partials.table', ['paymentProofs' => $paymentProofs])
@endsection
