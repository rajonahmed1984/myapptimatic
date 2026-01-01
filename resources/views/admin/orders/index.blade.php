@extends('layouts.admin')

@section('title', 'Orders')
@section('page-title', 'Orders')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Orders</h1>
            <p class="mt-1 text-sm text-slate-500">Review pending orders and approve or cancel them.</p>
        </div>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full min-w-[900px] text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Order ID</th>
                        <th class="px-4 py-3">Order number</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Service</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3">Created</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    @php
                        $plan = $order->plan;
                        $product = $plan?->product;
                        $service = $product ? $product->name . ' - ' . ($plan?->name ?? '') : ($plan?->name ?? '--');
                        $invoiceNumber = $order->invoice ? (is_numeric($order->invoice->number) ? $order->invoice->number : $order->invoice->id) : '--';
                    @endphp
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 text-slate-500">{{ $loop->iteration }}</td>
                        <td class="px-4 py-3 font-medium text-slate-900">#{{ $order->order_number ?? $order->id }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $order->customer?->name ?? '--' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $service }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$order->status" />
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $invoiceNumber }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $order->created_at?->format($globalDateFormat) ?? '--' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.orders.show', $order) }}" class="text-slate-500 hover:text-teal-600">View</a>
                                @if($order->status === 'pending')
                                    <form method="POST" action="{{ route('admin.orders.approve', $order) }}">
                                        @csrf
                                        <button type="submit" class="rounded-full bg-emerald-500 px-4 py-2 text-xs font-semibold text-white">Accept</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.orders.cancel', $order) }}">
                                        @csrf
                                        <button type="submit" class="rounded-full border border-rose-200 px-4 py-2 text-xs font-semibold text-rose-600 hover:border-rose-300">Cancel</button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400">No actions</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">No orders yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

