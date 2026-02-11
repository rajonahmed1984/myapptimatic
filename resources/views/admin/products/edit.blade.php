@extends('layouts.admin')

@section('title', 'Edit Product')
@section('page-title', 'Edit Product')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Products</div>
            <h1 class="text-2xl font-semibold text-slate-900">Edit Product</h1>
        </div>
        <a href="{{ route('admin.products.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back to products</a>
    </div>

    <div class="card p-6">

        <form method="POST" action="{{ route('admin.products.update', $product) }}" class="mt-6 space-y-6">
            @csrf
            @method('PUT')
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm text-slate-600">Name</label>
                    <input name="name" value="{{ old('name', $product->name) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Slug</label>
                    <input name="slug" value="{{ old('slug', $product->slug) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Status</label>
                    <select name="status" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        <option value="active" @selected($product->status === 'active')>Active</option>
                        <option value="inactive" @selected($product->status === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-slate-600">Description</label>
                    <textarea name="description" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('description', $product->description) }}</textarea>
                </div>
            </div>

            <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Update product</button>
        </form>
    </div>
@endsection
