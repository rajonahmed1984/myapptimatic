@extends('layouts.client')

@section('title', 'Pay Invoice')
@section('page-title', 'Pay Invoice')

@section('content')
    <div class="card p-6">
        <div class="section-label">Invoice payment</div>
        <div class="mt-3 text-2xl font-semibold text-slate-900">Invoice {{ $invoice->number }}</div>
        <div class="mt-1 text-sm text-slate-500">Due {{ $invoice->due_date->format('Y-m-d') }}</div>

        <div class="mt-6 space-y-3">
            @foreach($invoice->items as $item)
                <div class="flex items-center justify-between border-b border-slate-200 pb-2 text-sm">
                    <span class="text-slate-600">{{ $item->description }}</span>
                    <span class="text-slate-700">{{ $invoice->currency }} {{ $item->line_total }}</span>
                </div>
            @endforeach
        </div>

        <div class="mt-4 text-right text-lg font-semibold text-teal-600">
            Total: {{ $invoice->currency }} {{ $invoice->total }}
        </div>

        <div class="mt-6 card-muted p-4 text-sm text-slate-600">
            {!! nl2br(e($paymentInstructions ?: 'Contact support to arrange payment.')) !!}
        </div>
    </div>
@endsection
