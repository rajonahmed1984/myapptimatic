@extends('layouts.client')

@section('title', 'Licenses')
@section('page-title', 'Licenses')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Licenses</h1>
            <p class="mt-1 text-sm text-slate-500">Track your licensed domains, plan level, and status.</p>
        </div>
        <a href="{{ route('client.dashboard') }}" class="text-sm text-slate-500 hover:text-teal-600">Back to dashboard</a>
    </div>

    @if($licenses->isEmpty())
        <div class="card p-6 text-sm text-slate-500">No licenses found.</div>
    @else
        <div class="card overflow-hidden">
            <table class="w-full min-w-[720px] text-left text-xs">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.2em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Site</th>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3">Plan</th>
                        <th class="px-4 py-3">Installed on</th>
                        <th class="px-4 py-3">Version</th>
                        <th class="px-4 py-3">License</th>
                        <th class="px-4 py-3">Is Premium</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($licenses as $license)
                        @php
                            $domain = $license->domains->firstWhere('status', 'active')?->domain
                                ?? $license->domains->first()?->domain;
                            $plan = $license->subscription?->plan;
                            $planName = strtolower($plan?->name ?? '');
                            $isPremium = $planName !== '' && (str_contains($planName, 'premium') || str_contains($planName, 'plus') || str_contains($planName, 'pro'));
                            $key = $license->license_key ?? '';
                            $maskedKey = $key !== '' && strlen($key) > 8
                                ? substr($key, 0, 4) . str_repeat('*', max(0, strlen($key) - 8)) . substr($key, -4)
                                : $key;
                        @endphp
                        <tr class="border-b border-slate-100 text-sm">
                            <td class="px-4 py-3 text-slate-500">{{ $license->id }}</td>
                            <td class="px-4 py-3">
                                @if($domain)
                                    <a href="https://{{ $domain }}" target="_blank" rel="noopener" class="text-slate-700 hover:text-teal-600">
                                        {{ $domain }}
                                    </a>
                                @else
                                    <span class="text-slate-400">--</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $license->product?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $plan?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $license->starts_at?->format($globalDateFormat) ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-400">-</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ $maskedKey ?: '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="{{ $isPremium ? 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700' : 'rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500' }}">
                                    {{ $isPremium ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="{{ $license->status === 'active' ? 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700' : 'rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-amber-700' }}">
                                    {{ ucfirst($license->status) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

