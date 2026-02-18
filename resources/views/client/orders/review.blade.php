@extends('layouts.client')

@section('title', 'Review & Checkout')
@section('page-title', 'Review & Checkout')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Review & Checkout</h1>
            <p class="mt-1 text-sm text-slate-500">Confirm your plan details before placing the order.</p>
        </div>
        <a href="{{ route('client.orders.index') }}" class="text-sm text-slate-500 hover:text-teal-600">Back to products</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card p-6 lg:col-span-2">
            <div class="section-label">Plan details</div>
            <div class="mt-4 space-y-3 text-sm text-slate-600">
                <div class="flex items-center justify-between">
                    <span>Product</span>
                    <span class="font-semibold text-slate-900">{{ $plan->product?->name ?? '--' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Plan</span>
                    <span class="font-semibold text-slate-900">{{ $plan->name }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Interval</span>
                    <span class="font-semibold text-slate-900">{{ ucfirst($plan->interval) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Billing period</span>
                    <span class="font-semibold text-slate-900">{{ $startDate->format($globalDateFormat) }} -> {{ $periodEnd->format($globalDateFormat) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Invoice due</span>
                    <span class="font-semibold text-slate-900">{{ $dueDays }} day(s) after issue</span>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <div class="section-label">Summary</div>
            <div class="mt-4 text-sm text-slate-600">
                <div class="flex items-center justify-between">
                    <span>Subtotal</span>
                    <span class="font-semibold text-slate-900">{{ $currency }} {{ number_format((float) $subtotal, 2) }}</span>
                </div>
                @if(!empty($showProration) && $showProration && !empty($cycleDays))
                    <div class="mt-1 text-xs text-slate-500">
                        Prorated for {{ $periodDays }}/{{ $cycleDays }} days
                    </div>
                @endif
                <div class="mt-2 flex items-center justify-between">
                    <span>Total</span>
                    <span class="text-lg font-semibold text-slate-900">{{ $currency }} {{ number_format((float) $subtotal, 2) }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('client.orders.store') }}" class="mt-6">
                @csrf
                <input type="hidden" name="plan_id" value="{{ $plan->id }}" />
                <button type="submit" class="w-full rounded-full bg-teal-500 px-4 py-3 text-sm font-semibold text-white">Place order</button>
            </form>
        </div>
    </div>
@endsection
