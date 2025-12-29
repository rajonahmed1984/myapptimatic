@extends('layouts.client')

@section('title', 'Domains')
@section('page-title', 'Domains')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Domains</h1>
            <p class="mt-1 text-sm text-slate-500">Manage licensed domains and submit domain change requests.</p>
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
                            <td class="px-4 py-3 text-slate-500">{{ $domain->verified_at?->format('d-m-Y') ?? '--' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $domain->last_seen_at?->format('d-m-Y') ?? '--' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3 text-xs">
                                    <a href="{{ route('client.domains.show', $domain) }}" class="text-teal-600 hover:text-teal-500">View</a>
                                    <details class="relative">
                                        <summary class="cursor-pointer text-slate-500 hover:text-teal-600">Request</summary>
                                        <form method="POST" action="{{ route('client.requests.store') }}" class="absolute right-0 z-10 mt-2 w-64 rounded-xl border border-slate-200 bg-white p-3 text-left shadow-lg">
                                            @csrf
                                            <input type="hidden" name="license_domain_id" value="{{ $domain->id }}">
                                            <label class="text-xs font-semibold text-slate-500">Type</label>
                                            <select name="type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600">
                                                <option value="domain_edit">Request edit</option>
                                                <option value="domain_delete">Request delete</option>
                                            </select>
                                            <label class="mt-3 block text-xs font-semibold text-slate-500">Message (optional)</label>
                                            <textarea name="message" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600" placeholder="Add any details..."></textarea>
                                            <button type="submit" class="mt-3 w-full rounded-full bg-teal-500 px-4 py-2 text-xs font-semibold text-white">Submit request</button>
                                        </form>
                                    </details>
                                </div>
                                @if($domain->clientRequests->where('status', 'pending')->isNotEmpty())
                                    <div class="mt-2 text-xs text-amber-600">Request pending</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
