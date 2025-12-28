@extends('layouts.admin')

@section('title', 'Edit Plan')
@section('page-title', 'Edit Plan')

@section('content')
    <div class="card p-6">
        <h1 class="text-2xl font-semibold text-slate-900">Edit Plan</h1>

        <form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="mt-6 space-y-6">
            @csrf
            @method('PUT')
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm text-slate-600">Product</label>
                    <select name="product_id" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" @selected($plan->product_id === $product->id)>{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Name</label>
                    <input name="name" value="{{ old('name', $plan->name) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Interval</label>
                    <select name="interval" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        <option value="monthly" @selected($plan->interval === 'monthly')>Monthly</option>
                        <option value="yearly" @selected($plan->interval === 'yearly')>Yearly</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Price</label>
                    <input name="price" type="number" step="0.01" value="{{ old('price', $plan->price) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Currency</label>
                    <input name="currency" value="{{ old('currency', $plan->currency) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Invoice due days</label>
                    <input name="invoice_due_days" type="number" value="{{ old('invoice_due_days', $plan->invoice_due_days) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="hidden" name="is_active" value="0" />
                    <input type="checkbox" name="is_active" value="1" @checked($plan->is_active) class="rounded border-slate-300 text-teal-500" />
                    Active plan
                </div>
            </div>

            <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Update plan</button>
        </form>
    </div>
@endsection
