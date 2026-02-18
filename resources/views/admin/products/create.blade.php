@extends('layouts.admin')

@section('title', 'New Product')
@section('page-title', 'New Product')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Products</div>
            <h1 class="text-2xl font-semibold text-slate-900">Create Product</h1>
        </div>
        <a href="{{ route('admin.products.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back to products</a>
    </div>

    <div class="card p-6">
        @include('admin.products.partials.form', ['ajaxForm' => false])
    </div>
@endsection
