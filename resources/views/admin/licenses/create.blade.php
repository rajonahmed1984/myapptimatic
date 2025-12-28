@extends('layouts.admin')

@section('title', 'New License')
@section('page-title', 'New License')

@section('content')
    <div class="card p-6">
        <h1 class="text-2xl font-semibold text-slate-900">Create License</h1>

        <form method="POST" action="{{ route('admin.licenses.store') }}" class="mt-6 space-y-6">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm text-slate-600">Subscription</label>
                    <select name="subscription_id" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        @foreach($subscriptions as $subscription)
                            <option value="{{ $subscription->id }}">#{{ $subscription->id }} - {{ $subscription->customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Product</label>
                    <select name="product_id" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">License key (optional)</label>
                    <input name="license_key" value="{{ old('license_key') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Status</label>
                    <select name="status" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="revoked">Revoked</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Start date</label>
                    <input name="starts_at" type="date" value="{{ old('starts_at', now()->format('Y-m-d')) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Expires at</label>
                    <input name="expires_at" type="date" value="{{ old('expires_at') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Max domains</label>
                    <input name="max_domains" type="number" value="{{ old('max_domains', 1) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-slate-600">Allowed domains (one per line)</label>
                    <textarea name="allowed_domains" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('allowed_domains') }}</textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-slate-600">Notes</label>
                    <textarea name="notes" rows="2" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('notes') }}</textarea>
                </div>
            </div>

            <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Save license</button>
        </form>
    </div>
@endsection
