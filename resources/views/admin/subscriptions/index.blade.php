@extends('layouts.admin')

@section('title', 'Subscriptions')
@section('page-title', 'Subscriptions')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex-1">
            <form id="subscriptionsSearchForm" method="GET" action="{{ route('admin.subscriptions.index') }}" data-ajax-form="true" class="flex items-center gap-3">
                <div class="relative w-full max-w-sm">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search ?? request('search') }}"
                        placeholder="Search subscriptions..."
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                        oninput="clearTimeout(this.__searchTimer); this.__searchTimer = setTimeout(() => this.form && this.form.requestSubmit(), 300);"
                    />
                </div>
            </form>
        </div>
        <a
            href="{{ route('admin.subscriptions.create') }}"
            data-ajax-modal="true"
            data-modal-title="New Subscription"
            data-url="{{ route('admin.subscriptions.create') }}"
            class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white"
        >
            New Subscription
        </a>
    </div>

    @include('admin.subscriptions.partials.table', ['subscriptions' => $subscriptions])
@endsection
