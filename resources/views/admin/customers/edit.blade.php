@extends('layouts.admin')

@section('title', 'Edit Customer')
@section('page-title', 'Edit Customer')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Customer</div>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $customer->name }}</h1>
            <div class="mt-1 text-sm text-slate-500">Client ID: {{ $customer->id }}</div>
        </div>
        <div class="text-sm text-slate-600">
            <div>Status: {{ ucfirst($customer->status) }}</div>
            <div>Created: {{ $customer->created_at?->format($globalDateFormat) ?? '--' }}</div>
        </div>
    </div>

    <div class="card p-6">
        @include('admin.customers.partials.tabs', ['customer' => $customer, 'activeTab' => 'profile'])

        <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="mt-6 space-y-6">
            @csrf
            @method('PUT')

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm text-slate-600">Name</label>
                    <input name="name" value="{{ old('name', $customer->name) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Company Name</label>
                    <input name="company_name" value="{{ old('company_name', $customer->company_name) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Email</label>
                    <input name="email" type="email" value="{{ old('email', $customer->email) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Phone</label>
                    <input name="phone" value="{{ old('phone', $customer->phone) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Address</label>
                    <textarea name="address" rows="2" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('address', $customer->address) }}</textarea>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Update customer</button>
                <a href="{{ route('admin.customers.index') }}" class="text-sm text-slate-600 hover:text-teal-600">Cancel</a>
            </div>
        </form>
    </div>
@endsection
