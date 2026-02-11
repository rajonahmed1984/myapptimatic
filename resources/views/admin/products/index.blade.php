@extends('layouts.admin')

@section('title', 'Products')
@section('page-title', 'Products')

@section('content')
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-slate-900">Products</h1>
        <a href="{{ route('admin.products.create') }}" class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">New Product</a>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[800px] text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">SL</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Slug</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 text-slate-500">{{ $loop->iteration }}</td>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $product->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $product->slug }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$product->status" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.products.edit', $product) }}" class="text-teal-600 hover:text-teal-500">Edit</a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.products.destroy', $product) }}"
                                    data-delete-confirm
                                    data-confirm-name="{{ $product->name }}"
                                    data-confirm-title="Delete {{ $product->name }}?"
                                    data-confirm-description="Deleting this product will also remove related plans and subscriptions."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:text-rose-500">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">No products yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
