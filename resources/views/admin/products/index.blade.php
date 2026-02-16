@extends('layouts.admin')

@section('title', 'Products')
@section('page-title', 'Products')

@section('content')
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-slate-900">Products</h1>
        <a
            href="{{ route('admin.products.create') }}"
            data-ajax-modal="true"
            data-modal-title="New Product"
            data-url="{{ route('admin.products.create') }}"
            class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white"
        >
            New Product
        </a>
    </div>

    @include('admin.products.partials.table', ['products' => $products])
@endsection
