@extends('layouts.client')

@section('title', 'Domain Details')
@section('page-title', 'Domain Details')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Domain Details</h1>
            <p class="mt-1 text-sm text-slate-500">Review domain status and submit change requests.</p>
        </div>
        <a href="{{ route('client.domains.index') }}" class="text-sm text-slate-500 hover:text-teal-600">Back to domains</a>
    </div>

    @php
        $plan = $domain->license?->subscription?->plan;
        $product = $domain->license?->product;
        $key = $domain->license?->license_key ?? '';
        $maskedKey = $key !== '' && strlen($key) > 8
            ? substr($key, 0, 4) . str_repeat('*', max(0, strlen($key) - 8)) . substr($key, -4)
            : $key;
    @endphp

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card p-6">
            <div class="section-label">Domain</div>
            <h2 class="mt-2 text-xl font-semibold text-slate-900">{{ $domain->domain }}</h2>
            <div class="mt-4 space-y-3 text-sm text-slate-600">
                <div class="flex items-center justify-between">
                    <span>Product</span>
                    <span class="font-semibold text-slate-900">{{ $product?->name ?? '--' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Plan</span>
                    <span class="font-semibold text-slate-900">{{ $plan?->name ?? '--' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Status</span>
                    <span class="font-semibold text-slate-900">{{ ucfirst($domain->status) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Verified</span>
                    <span class="font-semibold text-slate-900">{{ $domain->verified_at?->format('d-m-Y') ?? '--' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Last seen</span>
                    <span class="font-semibold text-slate-900">{{ $domain->last_seen_at?->format('d-m-Y') ?? '--' }}</span>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <div class="section-label">License</div>
            <h3 class="mt-2 text-lg font-semibold text-slate-900">License key</h3>
            <div class="mt-3 rounded-2xl border border-slate-200 bg-white/70 p-4 font-mono text-sm text-slate-700">
                {{ $maskedKey ?: '--' }}
            </div>
        </div>
    </div>

    <div class="card mt-6 p-6">
        <div class="section-label">Request Change</div>
        <h3 class="mt-2 text-lg font-semibold text-slate-900">Submit a request</h3>
        <form method="POST" action="{{ route('client.requests.store') }}" class="mt-4 grid gap-4 md:grid-cols-[1fr_2fr_auto]">
            @csrf
            <input type="hidden" name="license_domain_id" value="{{ $domain->id }}">
            <div>
                <label class="text-xs font-semibold text-slate-500">Type</label>
                <select name="type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600">
                    <option value="domain_edit">Request edit</option>
                    <option value="domain_delete">Request delete</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-500">Message (optional)</label>
                <input type="text" name="message" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600" placeholder="Add any notes or new domain info">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full rounded-full bg-teal-500 px-6 py-2 text-xs font-semibold text-white">Submit request</button>
            </div>
        </form>
    </div>
@endsection
