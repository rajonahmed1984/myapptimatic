@extends('layouts.admin')

@section('title', 'Order Details')
@section('page-title', 'Order Details')

@section('content')
    @php
        $plan = $order->plan;
        $product = $plan?->product;
        $service = $product ? $product->name . ' - ' . ($plan?->name ?? '') : ($plan?->name ?? '--');
        $invoiceNumber = $order->invoice ? (is_numeric($order->invoice->number) ? $order->invoice->number : $order->invoice->id) : '--';
    @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="section-label">Order</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">#{{ $order->order_number ?? $order->id }}</div>
            <div class="mt-1 text-sm text-slate-500">Customer: {{ $order->customer?->name ?? '--' }}</div>
        </div>
        <div class="text-sm text-slate-600">
            <div>Status: {{ ucfirst($order->status) }}</div>
            <div>Created: {{ $order->created_at?->format($globalDateFormat) ?? '--' }}</div>
        </div>
    </div>

    <div class="card p-6">

        <div class="mt-6 grid gap-4 md:grid-cols-2 text-sm text-slate-600">
            <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Service</div>
                <div class="mt-2 font-semibold text-slate-900">{{ $service }}</div>
                <div class="mt-2 text-xs text-slate-500">Subscription ID: {{ $order->subscription_id ?? '--' }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Invoice</div>
                <div class="mt-2 font-semibold text-slate-900">#{{ $invoiceNumber }}</div>
                <div class="mt-2 text-xs text-slate-500">Invoice ID: {{ $order->invoice_id ?? '--' }}</div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2 text-sm text-slate-600">
            <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">License</div>
                @if($order->subscription && $order->subscription->licenses->isNotEmpty())
                    @foreach($order->subscription->licenses as $license)
                        <div class="mt-2">
                            <div class="font-semibold text-slate-900">{{ $license->license_key }}</div>
                            <div class="text-xs text-slate-500">Status: {{ ucfirst($license->status) }}</div>
                        </div>
                    @endforeach
                @else
                    <div class="mt-2 text-xs text-slate-500">No license created yet.</div>
                @endif
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Interval</div>
                <div class="mt-2 font-semibold text-slate-900">{{ ucfirst($order->plan?->interval ?? 'n/a') }}</div>
                @if(in_array($order->status, ['pending', 'accepted'], true))
                    @if($intervalOptions->isEmpty())
                        <div class="mt-3 text-xs text-slate-500">No interval options available.</div>
                    @else
                        <form method="POST" action="{{ route('admin.orders.plan', $order) }}" class="mt-3">
                            @csrf
                            @method('PATCH')
                            <label class="text-xs text-slate-500">Change interval</label>
                            <select name="interval" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                @foreach($intervalOptions as $intervalOption)
                                    <option value="{{ $intervalOption }}" @selected($order->plan?->interval === $intervalOption)>
                                        {{ ucfirst($intervalOption) }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="mt-3 rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white">Update Interval</button>
                        </form>
                    @endif
                @endif
            </div>
        </div>

        @php
            $primaryLicense = $order->subscription?->licenses->first();
            $primaryDomain = $primaryLicense?->domains->first()?->domain;
        @endphp

        <div class="mt-6 flex flex-wrap items-start gap-3">
            @if($order->status === 'pending')
                <form method="POST" action="{{ route('admin.orders.approve', $order) }}" class="w-full max-w-xl space-y-3 rounded-2xl border border-slate-200 bg-white/70 p-4">
                    @csrf
                    <div>
                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">License number</label>
                        <input
                            name="license_key"
                            value="{{ old('license_key', $primaryLicense?->license_key) }}"
                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                            placeholder="Enter license key"
                            required
                        />
                    </div>
                    <div>
                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">License URL</label>
                        <input
                            name="license_url"
                            value="{{ old('license_url', $primaryDomain) }}"
                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                            placeholder="https://example.com"
                            required
                        />
                    </div>
                    <button type="submit" class="rounded-full bg-emerald-500 px-5 py-2 text-sm font-semibold text-white">Accept Order</button>
                </form>
                <form method="POST" action="{{ route('admin.orders.cancel', $order) }}">
                    @csrf
                    <button type="submit" class="rounded-full border border-rose-200 px-5 py-2 text-sm font-semibold text-rose-600 hover:border-rose-300">Cancel Order</button>
                </form>
            @endif
            <form method="POST" action="{{ route('admin.orders.destroy', $order) }}" onsubmit="return confirm('Delete this order?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-full border border-slate-200 px-5 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">Delete Order</button>
            </form>
            <a href="{{ route('admin.orders.index') }}" class="rounded-full border border-slate-200 px-5 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Back to Orders</a>
        </div>
    </div>
@endsection

