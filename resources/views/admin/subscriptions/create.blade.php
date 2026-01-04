@extends('layouts.admin')

@section('title', 'New Subscription')
@section('page-title', 'New Subscription')

@section('content')
    <div class="card p-6">
        <h1 class="text-2xl font-semibold text-slate-900">Create Subscription</h1>

        <form method="POST" action="{{ route('admin.subscriptions.store') }}" class="mt-6 space-y-6">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm text-slate-600">Customer</label>
                    <select name="customer_id" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Plan</label>
                    <select name="plan_id" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->product->name }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Sales rep</label>
                    <select name="sales_rep_id" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        <option value="">None</option>
                        @foreach($salesReps as $rep)
                            <option value="{{ $rep->id }}" @selected(old('sales_rep_id') == $rep->id)>
                                {{ $rep->name }} @if($rep->status !== 'active') ({{ ucfirst($rep->status) }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Start date</label>
                    <input name="start_date" type="date" value="{{ old('start_date', now()->format('Y-m-d')) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Status</label>
                    <select name="status" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="hidden" name="auto_renew" value="0" />
                    <input type="checkbox" name="auto_renew" value="1" checked class="rounded border-slate-300 text-teal-500" />
                    Auto renew
                </div>
                <div class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="hidden" name="cancel_at_period_end" value="0" />
                    <input type="checkbox" name="cancel_at_period_end" value="1" class="rounded border-slate-300 text-teal-500" />
                    Cancel at period end
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-slate-600">Notes</label>
                    <textarea name="notes" rows="2" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('notes') }}</textarea>
                </div>
            </div>

            <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Save subscription</button>
        </form>
    </div>
@endsection
