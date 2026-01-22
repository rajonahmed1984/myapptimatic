@extends('layouts.admin')

@section('title', 'Orders')
@section('page-title', 'Orders')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Orders</h1>
            <p class="mt-1 text-sm text-slate-500">Review pending orders and approve or cancel them.</p>
        </div>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[980px] text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Order number</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Service</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3">Amount</th>
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
                        $invoice = $order->invoice;
                        $invoiceNumber = $invoice ? (is_numeric($invoice->number) ? $invoice->number : $invoice->id) : '--';
                    @endphp
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 font-medium text-slate-900">
                            <a href="{{ route('admin.orders.show', $order) }}" class="text-teal-500">#{{ $order->order_number ?? $order->id }}</a>                            
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $order->customer?->name ?? '--' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $service }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$order->status" />
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            @if($invoice)
                                <a href="{{ route('admin.invoices.show', $invoice) }}" class="hover:text-teal-600">
                                    {{ $invoiceNumber }}
                                </a>
                            @else
                                --
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            @if($invoice)
                                {{ $invoice->currency ?? '' }} {{ number_format((float) ($invoice->total ?? 0), 2) }}
                            @else
                                --
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $order->created_at?->format($globalDateFormat) ?? '--' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.orders.show', $order) }}" class="text-teal-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                                        <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                                        <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                                    </svg>
                                </a>
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

    <div class="mt-4">
        {{ $orders->links() }}
    </div>
@endsection
