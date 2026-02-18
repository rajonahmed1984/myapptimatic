@extends('layouts.admin')

@section('title', 'Edit Affiliate')
@section('page-title', 'Edit Affiliate')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Affiliates</div>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $affiliate->customer->name }}</h1>
        </div>
        <a href="{{ route('admin.affiliates.show', $affiliate) }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back to affiliate</a>
    </div>

    <div class="card p-8">
        <div class="section-label">Edit Affiliate</div>

        <form method="POST" action="{{ route('admin.affiliates.update', $affiliate) }}" class="mt-8 space-y-6">
            @csrf
            @method('PUT')

            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <label class="text-sm text-slate-600">Customer *</label>
                    <select name="customer_id" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id', $affiliate->customer_id) == $customer->id)>
                                {{ $customer->name }} ({{ $customer->email }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm text-slate-600">Status *</label>
                    <select name="status" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                        <option value="active" @selected(old('status', $affiliate->status) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $affiliate->status) === 'inactive')>Inactive</option>
                        <option value="suspended" @selected(old('status', $affiliate->status) === 'suspended')>Suspended</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm text-slate-600">Commission Type *</label>
                    <select name="commission_type" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                        <option value="percentage" @selected(old('commission_type', $affiliate->commission_type) === 'percentage')>Percentage</option>
                        <option value="fixed" @selected(old('commission_type', $affiliate->commission_type) === 'fixed')>Fixed Amount</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm text-slate-600">Commission Rate (%) *</label>
                    <input type="number" step="0.01" name="commission_rate" value="{{ old('commission_rate', $affiliate->commission_rate) }}" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                </div>

                <div>
                    <label class="text-sm text-slate-600">Fixed Commission Amount</label>
                    <input type="number" step="0.01" name="fixed_commission_amount" value="{{ old('fixed_commission_amount', $affiliate->fixed_commission_amount) }}" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                    <p class="mt-1 text-xs text-slate-500">Only used if commission type is "Fixed Amount"</p>
                </div>
            </div>

            <div>
                <label class="text-sm text-slate-600">Payment Details</label>
                <textarea name="payment_details" rows="3" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">{{ old('payment_details', $affiliate->payment_details) }}</textarea>
                <p class="mt-1 text-xs text-slate-500">Bank account, PayPal email, or other payment information</p>
            </div>

            <div>
                <label class="text-sm text-slate-600">Notes</label>
                <textarea name="notes" rows="3" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">{{ old('notes', $affiliate->notes) }}</textarea>
            </div>

            <div class="flex justify-end gap-4 pt-6">
                <a href="{{ route('admin.affiliates.show', $affiliate) }}" class="rounded-full border border-slate-300 px-6 py-2 text-sm font-semibold text-slate-600">
                    Cancel
                </a>
                <button type="submit" class="rounded-full bg-teal-500 px-6 py-2 text-sm font-semibold text-white">
                    Update affiliate
                </button>
            </div>
        </form>
    </div>
@endsection
