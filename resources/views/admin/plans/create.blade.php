@extends('layouts.admin')

@section('title', 'New Plan')
@section('page-title', 'New Plan')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Plans</div>
            <h1 class="text-2xl font-semibold text-slate-900">Create Plan</h1>
        </div>
        <a href="{{ route('admin.plans.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back to plans</a>
    </div>

    <div class="card p-6">

        <form method="POST" action="{{ route('admin.plans.store') }}" class="mt-6 space-y-6">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm text-slate-600">Product</label>
                    <select name="product_id" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Name</label>
                    <input name="name" value="{{ old('name') }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Slug</label>
                    <input name="slug" value="{{ old('slug') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    <p class="mt-2 text-xs text-slate-500">Leave blank to auto-generate.</p>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Interval</label>
                    <select name="interval" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Price</label>
                    <input name="price" type="number" step="0.01" value="{{ old('price') }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Currency</label>
                    <div class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-600">
                        {{ $defaultCurrency }}
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Currency is set globally in Settings.</p>
                </div>
                <div class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="hidden" name="is_active" value="0" />
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-teal-500" />
                    Active plan
                </div>
            </div>

            <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Save plan</button>
        </form>
    </div>
@endsection
