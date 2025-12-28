@extends('layouts.admin')

@section('title', 'Invoice Details')
@section('page-title', 'Invoice Details')

@section('content')
    <div class="card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="section-label">Invoice</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $invoice->number }}</div>
                <div class="mt-1 text-sm text-slate-500">Customer: {{ $invoice->customer->name }}</div>
            </div>
            <div class="text-sm text-slate-600">
                <div>Status: {{ ucfirst($invoice->status) }}</div>
                <div>Due: {{ $invoice->due_date->format('Y-m-d') }}</div>
            </div>
        </div>

        <div class="mt-6 space-y-3">
            @foreach($invoice->items as $item)
                <div class="flex items-center justify-between border-b border-slate-200 pb-2 text-sm">
                    <span>{{ $item->description }}</span>
                    <span>{{ $invoice->currency }} {{ $item->line_total }}</span>
                </div>
            @endforeach
        </div>

        <div class="mt-4 text-right text-lg font-semibold text-teal-600">
            Total: {{ $invoice->currency }} {{ $invoice->total }}
        </div>

        @if($invoice->status !== 'paid')
            <form method="POST" action="{{ route('admin.invoices.mark-paid', $invoice) }}" class="mt-6">
                @csrf
                <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Mark as paid</button>
            </form>
        @endif
    </div>
@endsection
