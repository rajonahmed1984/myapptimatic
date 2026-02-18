@extends('layouts.client')

@section('title', 'Services')
@section('page-title', 'Services')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Services</h1>
            <p class="mt-1 text-sm text-slate-500">Review active services and billing cycle details.</p>
        </div>
        <a href="{{ route('client.dashboard') }}" class="text-sm text-slate-500 hover:text-teal-600">Back to dashboard</a>
    </div>

    @if(! $customer)
        <div class="card p-6 text-sm text-slate-600">
            Your account is not linked to a customer profile yet. Please contact support.
        </div>
    @elseif($subscriptions->isEmpty())
        <div class="card p-6 text-sm text-slate-500">No active services found.</div>
    @else
        <div class="card overflow-hidden">
            <table class="w-full min-w-[820px] text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">SL</th>
                        <th class="px-4 py-3">Service</th>
                        <th class="px-4 py-3">Plan</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Cycle</th>
                        <th class="px-4 py-3">Next Due</th>
                        <th class="px-4 py-3">Auto Renew</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subscriptions as $subscription)
                        @php
                            $plan = $subscription->plan;
                            $product = $plan?->product;
                            $serviceName = $product ? $product->name : 'Service';
                            $planName = $plan?->name ?? '--';
                            $cycle = $plan?->interval ? ucfirst($plan->interval) : '--';
                            $nextDue = $subscription->next_invoice_at?->format($globalDateFormat) ?? '--';
                        @endphp
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-3 text-slate-600">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $serviceName }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $planName }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ ucfirst($subscription->status) }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $cycle }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $nextDue }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $subscription->auto_renew ? 'Yes' : 'No' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3 text-xs">
                                    <a href="{{ route('client.services.show', $subscription) }}" class="text-teal-600 hover:text-teal-500">View</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

