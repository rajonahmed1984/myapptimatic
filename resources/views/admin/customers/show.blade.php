@extends('layouts.admin')

@section('title', 'Customer Details')
@section('page-title', 'Customer Details')

@section('content')
    <div class="card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="section-label">Customer</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $customer->name }}</div>
                <div class="mt-1 text-sm text-slate-500">Client ID: {{ $customer->id }}</div>
            </div>
            <div class="text-sm text-slate-600">
                <div>Status: {{ ucfirst($customer->status) }}</div>
                <div>Created: {{ $customer->created_at?->format('Y-m-d') ?? '--' }}</div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2 text-sm text-slate-600">
            <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Profile</div>
                <div class="mt-2">Company: {{ $customer->company_name ?: '--' }}</div>
                <div class="mt-1">Email: {{ $customer->email ?: '--' }}</div>
                <div class="mt-1">Phone: {{ $customer->phone ?: '--' }}</div>
                <div class="mt-1">Address: {{ $customer->address ?: '--' }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Services</div>
                <div class="mt-2">Total: {{ $customer->subscriptions->count() }}</div>
                <div class="mt-1">
                    Active: {{ $customer->subscriptions->where('status', 'active')->count() }}
                </div>
                <div class="mt-1">
                    Pending: {{ $customer->subscriptions->where('status', 'pending')->count() }}
                </div>
                <div class="mt-1">
                    Suspended: {{ $customer->subscriptions->where('status', 'suspended')->count() }}
                </div>
            </div>
        </div>

        @if($customer->invoices->isNotEmpty())
            <div class="mt-6">
                <div class="section-label">Recent Invoices</div>
                <div class="mt-3 space-y-2 text-sm">
                    @foreach($customer->invoices->take(5) as $invoice)
                        <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                            <div>
                                <div class="font-semibold text-slate-900">
                                    Invoice #{{ is_numeric($invoice->number) ? $invoice->number : $invoice->id }}
                                </div>
                                <div class="text-xs text-slate-500">{{ $invoice->issue_date->format('Y-m-d') }}</div>
                            </div>
                            <div class="text-sm text-slate-600">{{ ucfirst($invoice->status) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('admin.customers.edit', $customer) }}" class="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white">Edit Customer</a>
            <a href="{{ route('admin.customers.index') }}" class="rounded-full border border-slate-200 px-5 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Back to Customers</a>
        </div>
    </div>
@endsection
