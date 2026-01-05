@extends('layouts.admin')

@section('title', 'Subscriptions')
@section('page-title', 'Subscriptions')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Subscriptions</h1>
        </div>
        <a href="{{ route('admin.subscriptions.create') }}" class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">New Subscription</a>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[900px] text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">SL</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3">Plan</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Next invoice</th>
                    <th class="px-4 py-3">Period end</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($subscriptions as $subscription)
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 text-slate-500">{{ $subscriptions->firstItem() ? $subscriptions->firstItem() + $loop->index : $subscription->id }}</td>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900">{{ $subscription->customer?->name ?? '--' }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $subscription->plan?->product?->name ?? '--' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $subscription->plan?->name ?? '--' }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$subscription->status" />
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $subscription->next_invoice_at?->format($globalDateFormat) ?? '--' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $subscription->current_period_end?->format($globalDateFormat) ?? '--' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.subscriptions.edit', $subscription) }}" class="text-teal-600 hover:text-teal-500">Manage</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">No subscriptions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $subscriptions->links() }}
    </div>
@endsection
