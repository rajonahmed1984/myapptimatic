@extends('layouts.admin')

@section('title', 'Subscriptions')
@section('page-title', 'Subscriptions')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Subscriptions</h1>
        <a href="{{ route('admin.subscriptions.create') }}" class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">New Subscription</a>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">SL</th>
                    <th class="px-4 py-3">Order date</th>
                    <th class="px-4 py-3">Order number</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Products</th>
                    <th class="px-4 py-3">Plan</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Next invoice</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($subscriptions as $subscription)
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 text-slate-500">{{ $loop->iteration }}</td>                       
                        <td class="px-4 py-3 text-slate-500">
                            {{ $subscription->latestOrder?->created_at?->format($globalDateFormat) ?? '--' }}
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            {{ $subscription->latestOrder?->order_number ?? '--' }}
                        </td>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $subscription->customer->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $subscription->plan->product->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $subscription->plan->name }}</td>
                        @php
                            $customerId = $subscription->customer?->id;
                            $isBlocked = $customerId ? ($accessBlockedCustomers[$customerId] ?? false) : false;
                        @endphp
                        <td class="px-4 py-3 text-slate-700">
                            @if($subscription->status === 'suspended')
                                Suspended
                            @elseif($subscription->status === 'active' && $isBlocked)
                                Access blocked
                            @else
                                {{ ucfirst($subscription->status) }}
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $subscription->next_invoice_at->format($globalDateFormat) }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.subscriptions.edit', $subscription) }}" class="text-teal-600 hover:text-teal-500">Edit</a>
                                <form method="POST" action="{{ route('admin.subscriptions.destroy', $subscription) }}" onsubmit="return confirm('Delete this subscription? Licenses will be revoked and invoices remain.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:text-rose-500">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-6 text-center text-slate-500">No subscriptions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

