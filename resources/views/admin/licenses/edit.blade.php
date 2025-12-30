@extends('layouts.admin')

@section('title', 'Edit License')
@section('page-title', 'Edit License')

@section('content')
    @php($primaryDomain = $license->domains->first()?->domain)

    <div class="card p-6">
        <h1 class="text-2xl font-semibold text-slate-900">Edit License</h1>

        <form method="POST" action="{{ route('admin.licenses.update', $license) }}" class="mt-6 space-y-6">
            @csrf
            @method('PUT')
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm text-slate-600">Subscription</label>
                    <select name="subscription_id" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        @foreach($subscriptions as $subscription)
                            <option value="{{ $subscription->id }}" @selected($license->subscription_id === $subscription->id)>
                                #{{ $subscription->id }} - {{ $subscription->customer->name }} - {{ $subscription->plan?->product?->name ?? '--' }} / {{ $subscription->plan?->name ?? '--' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Product</label>
                    <select name="product_id" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" @selected($license->product_id === $product->id)>{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">License key</label>
                    <input name="license_key" value="{{ old('license_key', $license->license_key) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-mono" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Status</label>
                    <select name="status" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        <option value="active" @selected($license->status === 'active')>Active</option>
                        <option value="suspended" @selected($license->status === 'suspended')>Suspended</option>
                        <option value="revoked" @selected($license->status === 'revoked')>Revoked</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Start date</label>
                    <input name="starts_at" type="date" value="{{ old('starts_at', $license->starts_at->format('Y-m-d')) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Expires at</label>
                    <input name="expires_at" type="date" value="{{ old('expires_at', optional($license->expires_at)->format('Y-m-d')) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Max domains</label>
                    <div class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-600">1 (fixed)</div>
                    <p class="mt-2 text-xs text-slate-500">Only one domain is allowed per license.</p>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Domain URL</label>
                    <input
                        name="domain_url"
                        value="{{ old('domain_url', $primaryDomain) }}"
                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                        placeholder="https://example.com"
                    />
                    <p class="mt-2 text-xs text-slate-500">Optional. Use a full URL or domain (single).</p>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-slate-600">Notes</label>
                    <textarea name="notes" rows="2" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('notes', $license->notes) }}</textarea>
                </div>
            </div>

            <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Update license</button>
        </form>
    </div>
@endsection
