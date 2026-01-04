@extends('layouts.admin')

@section('title', 'Edit Customer')
@section('page-title', 'Edit Customer')

@section('content')
    <div class="card p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
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
                <div>
                    <label class="text-sm text-slate-600">Notes</label>
                    <textarea name="notes" rows="2" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('notes', $customer->notes) }}</textarea>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Override access until</label>
                    <input name="access_override_until" type="date" value="{{ old('access_override_until', optional($customer->access_override_until)->format('Y-m-d')) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Status</label>
                    <select name="status" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        <option value="active" @selected($customer->status === 'active')>Active</option>
                        <option value="inactive" @selected($customer->status === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Default sales rep</label>
                    <select name="default_sales_rep_id" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        <option value="">None</option>
                        @foreach($salesReps as $rep)
                            <option value="{{ $rep->id }}" @selected(old('default_sales_rep_id', $customer->default_sales_rep_id) == $rep->id)>
                                {{ $rep->name }} @if($rep->status !== 'active') ({{ ucfirst($rep->status) }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Update customer</button>
        </form>
    </div>
@endsection
