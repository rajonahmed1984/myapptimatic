@extends('layouts.client')

@section('title', 'Domains')
@section('page-title', 'Domains')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Domains</h1>
            <p class="mt-1 text-sm text-slate-500">Manage licensed domains and monitor verification status.</p>
        </div>
        <a href="{{ route('client.dashboard') }}" class="text-sm text-slate-500 hover:text-teal-600">Back to dashboard</a>
    </div>

    @if(! $customer)
        <div class="card p-6 text-sm text-slate-600">
            Your account is not linked to a customer profile yet. Please contact support.
        </div>
    @elseif($domains->isEmpty())
        <div class="card p-6 text-sm text-slate-500">No domains found.</div>
    @else
        <div class="card overflow-hidden">
            <table class="w-full min-w-[860px] text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Domain</th>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3">Plan</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Verified</th>
                        <th class="px-4 py-3">Last Seen</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($domains as $domain)
                        @php
                            $plan = $domain->license?->subscription?->plan;
                            $product = $domain->license?->product;
                            $key = $domain->license?->license_key ?? '';
                            $maskedKey = $key !== '' && strlen($key) > 8
                                ? substr($key, 0, 4) . str_repeat('*', max(0, strlen($key) - 8)) . substr($key, -4)
                                : $key;
                        @endphp
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $domain->domain }}</div>
                                <div class="text-xs text-slate-400">{{ $maskedKey ?: '--' }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $product?->name ?? '--' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $plan?->name ?? '--' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ ucfirst($domain->status) }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $domain->verified_at?->format($globalDateFormat) ?? '--' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $domain->last_seen_at?->format($globalDateFormat) ?? '--' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3 text-xs">
                                    <a href="{{ route('client.domains.show', $domain) }}" class="text-teal-600 hover:text-teal-500">View</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

