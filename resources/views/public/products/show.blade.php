@extends('layouts.public')

@section('title', $product->name)

@section('content')
    <div class="card p-6">
        <div class="section-label">Product</div>
        <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $product->name }}</div>
        @if($product->description)
            <p class="mt-2 text-sm text-slate-500">{{ $product->description }}</p>
        @endif

        @php
            $visiblePlans = $product->plans;
            if ($selectedPlanId) {
                $visiblePlans = $product->plans->where('id', (int) $selectedPlanId);
            }
        @endphp

        @if($visiblePlans->isEmpty())
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white/70 p-4 text-sm text-slate-600">
                No active plans are available right now. Please check back later.
            </div>
        @else
            <div class="mt-6 grid gap-4 md:grid-cols-2">
                @foreach($visiblePlans as $plan)
                    @php($isSelected = (string) $selectedPlanId === (string) $plan->id)
                    <div class="card-muted p-4 {{ $isSelected ? 'ring-2 ring-teal-300' : '' }}">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-sm text-slate-500">{{ ucfirst($plan->interval) }} plan</div>
                                <div class="mt-1 text-lg font-semibold text-slate-900">{{ $plan->name }}</div>
                                <div class="mt-2 text-sm text-slate-600">{{ $currency }} {{ number_format((float) $plan->price, 2) }}</div>
                            </div>
                            @auth
                                @if(auth()->user()->isClient())
                                    <form method="GET" action="{{ route('client.orders.review') }}">
                                        <input type="hidden" name="plan_id" value="{{ $plan->id }}" />
                                        <button type="submit" class="rounded-full bg-teal-500 px-4 py-2 text-xs font-semibold text-white">Review & checkout</button>
                                    </form>
                                @else
                                    <div class="text-xs text-slate-500">Login as client to order</div>
                                @endif
                            @else
                                @php($redirectUrl = request()->fullUrlWithQuery(['plan' => $plan->id]))
                                <div class="flex flex-col gap-2 text-xs">
                                    <a href="{{ route('login', ['redirect' => $redirectUrl]) }}" class="rounded-full border border-slate-200 px-3 py-2 text-center text-slate-600 hover:border-teal-300 hover:text-teal-600">Sign in to order</a>
                                    <a href="{{ route('register', ['redirect' => $redirectUrl]) }}" class="rounded-full bg-teal-500 px-3 py-2 text-center font-semibold text-white">Create account</a>
                                    <a href="{{ route('products.public.plan', ['product' => $product, 'plan' => $plan]) }}" class="text-center text-xs font-semibold text-teal-600 hover:text-teal-500">View plan</a>
                                </div>
                            @endauth
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @auth
            @if(auth()->user()->isClient() && !auth()->user()->customer)
                <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                    Your account is not linked to a customer profile yet. Please contact support.
                </div>
            @endif
        @endauth
    </div>
@endsection
