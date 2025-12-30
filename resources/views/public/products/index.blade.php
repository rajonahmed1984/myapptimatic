@extends('layouts.public')

@section('title', 'Products')

@section('content')
    <div class="mb-6">
        <div class="section-label">Products</div>
        <h1 class="mt-2 text-3xl font-semibold text-slate-900">Choose a service plan</h1>
        <p class="mt-2 text-sm text-slate-600">Browse available products and start your order.</p>
    </div>

    @if($products->isEmpty())
        <div class="card p-6 text-sm text-slate-600">No active products are available right now.</div>
    @else
        <div class="grid gap-6 md:grid-cols-2">
            @foreach($products as $product)
                @php($minPrice = $product->plans->min('price'))
                <div class="card p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-xl font-semibold text-slate-900">{{ $product->name }}</div>
                            @if($product->description)
                                <p class="mt-2 text-sm text-slate-500">{{ $product->description }}</p>
                            @endif
                            <div class="mt-3 text-sm text-slate-600">
                                {{ $product->plans->count() }} plan(s)
                                @if($minPrice !== null)
                                    <span class="mx-2 text-slate-300">|</span>
                                    Starts at {{ $currency }} {{ number_format((float) $minPrice, 2) }}
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('products.public.show', $product) }}" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">View plans</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
