@extends('layouts.client')

@section('title', 'Service Details')
@section('page-title', 'Service Details')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Service Details</h1>
            <p class="mt-1 text-sm text-slate-500">Review the service and submit requests.</p>
        </div>
        <a href="{{ route('client.services.index') }}" class="text-sm text-slate-500 hover:text-teal-600">Back to services</a>
    </div>

    @php
        $plan = $subscription->plan;
        $product = $plan?->product;
        $cycle = $plan?->interval ? ucfirst($plan->interval) : '--';
    @endphp

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card p-6">
            <div class="section-label">Service</div>
            <h2 class="mt-2 text-xl font-semibold text-slate-900">{{ $product?->name ?? 'Service' }}</h2>
            <div class="mt-4 space-y-3 text-sm text-slate-600">
                <div class="flex items-center justify-between">
                    <span>Plan</span>
                    <span class="font-semibold text-slate-900">{{ $plan?->name ?? '--' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Status</span>
                    <span class="font-semibold text-slate-900">{{ ucfirst($subscription->status) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Billing cycle</span>
                    <span class="font-semibold text-slate-900">{{ $cycle }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Start date</span>
                    <span class="font-semibold text-slate-900">{{ $subscription->start_date?->format($globalDateFormat) ?? '--' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Current period</span>
                    <span class="font-semibold text-slate-900">
                        {{ $subscription->current_period_start?->format($globalDateFormat) ?? '--' }}
                        -
                        {{ $subscription->current_period_end?->format($globalDateFormat) ?? '--' }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Auto renew</span>
                    <span class="font-semibold text-slate-900">{{ $subscription->auto_renew ? 'Enabled' : 'Disabled' }}</span>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <div class="section-label">License</div>
            <h3 class="mt-2 text-lg font-semibold text-slate-900">Licensed domains</h3>
            @if($subscription->licenses->isEmpty())
                <p class="mt-3 text-sm text-slate-500">No licenses associated with this service yet.</p>
            @else
                <div class="mt-4 space-y-4 text-sm text-slate-600">
                    @foreach($subscription->licenses as $license)
                        @php
                            $key = $license->license_key ?? '';
                            $maskedKey = $key !== '' && strlen($key) > 8
                                ? substr($key, 0, 4) . str_repeat('*', max(0, strlen($key) - 8)) . substr($key, -4)
                                : $key;
                            $domains = $license->domains->pluck('domain')->filter()->values();
                        @endphp
                        <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">License key</div>
                            <div class="mt-1 font-mono text-sm text-slate-700">{{ $maskedKey ?: '--' }}</div>
                            <div class="mt-3 text-xs uppercase tracking-[0.25em] text-slate-400">Domains</div>
                            @if($domains->isEmpty())
                                <div class="mt-1 text-sm text-slate-500">No domains registered.</div>
                            @else
                                <div class="mt-1 space-y-1 text-sm text-slate-600">
                                    @foreach($domains as $domain)
                                        <div>{{ $domain }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="card mt-6 p-6">
        <div class="section-label">Request Change</div>
        <h3 class="mt-2 text-lg font-semibold text-slate-900">Submit a request</h3>
        <form method="POST" action="{{ route('client.requests.store') }}" class="mt-4 grid gap-4 md:grid-cols-[1fr_2fr_auto]">
            @csrf
            <input type="hidden" name="subscription_id" value="{{ $subscription->id }}">
            <div>
                <label class="text-xs font-semibold text-slate-500">Type</label>
                <select name="type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600">
                    <option value="subscription_edit">Request edit</option>
                    <option value="subscription_cancel">Request cancellation</option>
                    <option value="subscription_delete">Request delete</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-500">Message (optional)</label>
                <input type="text" name="message" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600" placeholder="Add any notes or change details">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full rounded-full bg-teal-500 px-6 py-2 text-xs font-semibold text-white">Submit request</button>
            </div>
        </form>
    </div>
@endsection

