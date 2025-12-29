@extends('layouts.client')

@section('title', 'Order Services')
@section('page-title', 'Order Services')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Order Services</h1>
            <p class="mt-1 text-sm text-slate-500">Choose a plan and generate an invoice instantly.</p>
        </div>
        <a href="{{ route('client.dashboard') }}" class="text-sm text-slate-500 hover:text-teal-600">Back to dashboard</a>
    </div>

    @if(! $customer)
        <div class="card p-6 text-sm text-slate-600">
            Your account is not linked to a customer profile yet. Please contact support.
        </div>
    @elseif($products->isEmpty())
        <div class="card p-6 text-sm text-slate-600">
            No active products are available right now. Please check back later.
        </div>
    @else
        <div class="space-y-6">
            @foreach($products as $product)
                <div class="card p-6">
                    <div class="section-label">Product</div>
                    <div class="mt-2 text-xl font-semibold text-slate-900">{{ $product->name }}</div>
                    @if($product->description)
                        <p class="mt-2 text-sm text-slate-500">{{ $product->description }}</p>
                    @endif

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        @foreach($product->plans as $plan)
                            <div class="card-muted p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="text-sm text-slate-500">{{ ucfirst($plan->interval) }} plan</div>
                                        <div class="mt-1 text-lg font-semibold text-slate-900">{{ $plan->name }}</div>
                                        <div class="mt-2 text-sm text-slate-600">{{ $currency }} {{ number_format((float) $plan->price, 2) }}</div>
                                    </div>
                                    <form method="POST" action="{{ route('client.orders.store') }}">
                                        @csrf
                                        <input type="hidden" name="plan_id" value="{{ $plan->id }}" />
                                        <button type="submit" class="rounded-full bg-teal-500 px-4 py-2 text-xs font-semibold text-white">Order now</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
