@extends('layouts.client')

@section('title', 'Services')
@section('page-title', 'Services')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Services</h1>
            <p class="mt-1 text-sm text-slate-500">Review active services and submit change requests.</p>
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
                                    <details class="relative">
                                        <summary class="cursor-pointer text-slate-500 hover:text-teal-600">Request</summary>
                                        <form method="POST" action="{{ route('client.requests.store') }}" class="absolute right-0 z-10 mt-2 w-64 rounded-xl border border-slate-200 bg-white p-3 text-left shadow-lg">
                                            @csrf
                                            <input type="hidden" name="subscription_id" value="{{ $subscription->id }}">
                                            <label class="text-xs font-semibold text-slate-500">Type</label>
                                            <select name="type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600">
                                                <option value="subscription_edit">Request edit</option>
                                                <option value="subscription_cancel">Request cancellation</option>
                                                <option value="subscription_delete">Request delete</option>
                                            </select>
                                            <label class="mt-3 block text-xs font-semibold text-slate-500">Message (optional)</label>
                                            <textarea name="message" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600" placeholder="Add any details..."></textarea>
                                            <button type="submit" class="mt-3 w-full rounded-full bg-teal-500 px-4 py-2 text-xs font-semibold text-white">Submit request</button>
                                        </form>
                                    </details>
                                </div>
                                @if($subscription->clientRequests->where('status', 'pending')->isNotEmpty())
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

