@extends('layouts.admin')

@section('title', 'Products')
@section('page-title', 'Products')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Products</h1>
        <a href="{{ route('admin.products.create') }}" class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">New Product</a>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Slug</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $product->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $product->slug }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ ucfirst($product->status) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.products.edit', $product) }}" class="text-teal-600 hover:text-teal-500">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-slate-500">No products yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
